<?php
/**
 * copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Authorisation;

use Exception;
use Magento\Framework\Exception\LocalizedException;

class DirectService extends \Magento\Framework\DataObject
{

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;
    /**
     * @var \Sapient\AccessWorldpay\Model\Payment\UpdateAccessWorldpaymentFactory
     */
    protected $updateWorldPayPayment;

    /**
     * DirectService constructor
     *
     * @param \Sapient\AccessWorldpay\Model\Mapping\Service $mappingservice
     * @param \Sapient\AccessWorldpay\Model\Request\PaymentServiceRequest $paymentservicerequest
     * @param \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger
     * @param \Sapient\AccessWorldpay\Model\Response\DirectResponse $directResponse
     * @param \Sapient\AccessWorldpay\Model\Payment\UpdateAccessWorldpaymentFactory $updateWorldPayPayment
     * @param \Sapient\AccessWorldpay\Model\Payment\Service $paymentservice
     * @param \Sapient\AccessWorldpay\Helper\Registry $registryhelper
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Sapient\AccessWorldpay\Helper\Data $worldpayHelper
     * @param \Magento\Customer\Model\Session $customerSession
     */
    public function __construct(
        \Sapient\AccessWorldpay\Model\Mapping\Service $mappingservice,
        \Sapient\AccessWorldpay\Model\Request\PaymentServiceRequest $paymentservicerequest,
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger,
        \Sapient\AccessWorldpay\Model\Response\DirectResponse $directResponse,
        \Sapient\AccessWorldpay\Model\Payment\UpdateAccessWorldpaymentFactory $updateWorldPayPayment,
        \Sapient\AccessWorldpay\Model\Payment\Service $paymentservice,
        \Sapient\AccessWorldpay\Helper\Registry $registryhelper,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Sapient\AccessWorldpay\Helper\Data $worldpayHelper,
        \Magento\Customer\Model\Session $customerSession
    ) {
        $this->mappingservice = $mappingservice;
        $this->paymentservicerequest = $paymentservicerequest;
        $this->wplogger = $wplogger;
        $this->directResponse = $directResponse;
        $this->paymentservice = $paymentservice;
        $this->checkoutSession = $checkoutSession;
        $this->updateWorldPayPayment = $updateWorldPayPayment;
        $this->worldpayHelper = $worldpayHelper;
        $this->registryhelper = $registryhelper;
        $this->urlBuilders    = $urlBuilder;
        $this->customerSession = $customerSession;
    }

    /**
     * Handles provides authorization data for direct
     *
     * @param MageOrder $mageOrder
     * @param Quote $quote
     * @param string $orderCode
     * @param string $orderStoreId
     * @param array $paymentDetails
     * @param Payment $payment
     */
    public function authorizePayment(
        $mageOrder,
        $quote,
        $orderCode,
        $orderStoreId,
        $paymentDetails,
        $payment
    ) {
        $orderId = $quote->getReservedOrderId();
        //GraphQl flow
        if (isset($paymentDetails['additional_data']['is_graphql'])
            && (empty($paymentDetails['additional_data']['tokenId'])
                && empty($paymentDetails['token_url']))
                && $paymentDetails['additional_data']['cc_type']!='ACH_DIRECT_DEBIT-SSL') {
            $directSessionHref = $this->submitGraphQlRequest($paymentDetails);
            $paymentDetails['additional_data']['directSessionHref'] = $directSessionHref;
        }
        if ($paymentDetails['additional_data']['cc_type'] == 'ACH_DIRECT_DEBIT-SSL') {
            $directOrderParams = $this->mappingservice->collectACHOrderParameters(
                $orderCode,
                $quote,
                $orderStoreId,
                $paymentDetails
            );
                   $response = $this->paymentservicerequest->achOrder($directOrderParams);
                   $this->setResponseAndSaveData($response, $orderId, $orderCode, $payment);
        } else {
            $directOrderParams = $this->mappingservice->collectDirectOrderParameters(
                $orderCode,
                $quote,
                $orderStoreId,
                $paymentDetails
            );
            //3DS flow
            if ($this->worldpayHelper->is3DSecureEnabled()
             && !isset($paymentDetails['additional_data']['is_graphql'])) {
                $this->submit3DSRequest($directOrderParams, $mageOrder);
            } else {
                //non 3DS flow
                $response = $this->paymentservicerequest->order($directOrderParams);
                $this->setResponseAndSaveData($response, $orderId, $orderCode, $payment);
                //save Exemption data
                $this->_saveExemptionData();
            
                //Added Condition : If token available need not to save the card details
                $this->isAnExisitingCard($quote, $paymentDetails, $payment);
            }
        }
    }
  
    /**
     * Get 3ds2 params from the configuration and set to checkout session
     *
     * @return array
     */
    public function get3DS2ConfigValues()
    {
        $data = [];
        $data['challengeWindowType'] = $this->worldpayHelper->getChallengeWindowSize();
        return $data;
    }

    /**
     * Apply payment update
     *
     * @param \Sapient\AccessWorldpay\Model\Response\DirectResponse $directResponse
     * @param Payment $payment
     */
    private function _applyPaymentUpdate(
        \Sapient\AccessWorldpay\Model\Response\DirectResponse $directResponse,
        $payment
    ) {
        $paymentUpdate = $this->paymentservice->
                createPaymentUpdateFromWorldPayXml($directResponse->getXml());
        
        $paymentUpdate->apply($payment);
        $this->_abortIfPaymentError($paymentUpdate);
    }

    /**
     * Abort if payment error
     *
     * @param Object $paymentUpdate
     */
    private function _abortIfPaymentError($paymentUpdate)
    {
        if ($paymentUpdate instanceof \Sapient\AccessWorldpay\Model\Payment\Update\Refused) {
             throw new \Magento\Framework\Exception\LocalizedException(
                 sprintf('Payment REFUSED')
             );
        }

        if ($paymentUpdate instanceof \Sapient\AccessWorldpay\Model\Payment\Update\Cancelled) {
            throw new \Magento\Framework\Exception\LocalizedException(
                sprintf('Payment CANCELLED')
            );
        }

        if ($paymentUpdate instanceof \Sapient\AccessWorldpay\Model\Payment\Update\Error) {
            throw new \Magento\Framework\Exception\LocalizedException(
                sprintf('Payment ERROR')
            );
        }
    }

    /**
     * Capture the payment
     *
     * @param MageOrder $mageOrder
     * @param Quote $quote
     * @param mixed $response
     * @param Payment $payment
     */
    public function capturePayment(
        $mageOrder,
        $quote,
        $response,
        $payment
    ) {
        $directResponse = $this->directResponse->setResponse($response);
        $this->updateWorldPayPayment->create()->updatePaymentSettlement($response);
        $this->_applyPaymentUpdate($directResponse, $payment);
    }
    
    /**
     * Partial capture the payment
     *
     * @param MageOrder $mageOrder
     * @param Quote $quote
     * @param mixed $response
     * @param Payment $payment
     */
    public function partialCapturePayment(
        $mageOrder,
        $quote,
        $response,
        $payment
    ) {
        $directResponse = $this->directResponse->setResponse($response);
        $this->updateWorldPayPayment->create()->updatePaymentSettlement($response);
 
        // Normal order goes here.
        $this->_applyPaymentUpdate($directResponse, $payment);
    }
    
    /**
     * Refund the payment
     *
     * @param MageOrder $mageOrder
     * @param Quote $quote
     * @param mixed $response
     * @param Payment $payment
     */
    public function refundPayment(
        $mageOrder,
        $quote,
        $response,
        $payment
    ) {
        $directResponse = $this->directResponse->setResponse($response);
        $this->_applyPaymentUpdate($directResponse, $payment);
    }
    
    /**
     * Partial refund the payment
     *
     * @param MageOrder $mageOrder
     * @param Quote $quote
     * @param mixed $response
     * @param Payment $payment
     */
    public function partialRefundPayment(
        $mageOrder,
        $quote,
        $response,
        $payment
    ) {
        $directResponse = $this->directResponse->setResponse($response);
        $this->_applyPaymentUpdate($directResponse, $payment);
    }
    
    /**
     * Saved token data
     *
     * @param string $customerId
     * @param Payment $payment
     * @param array $paymentDetails
     */
    public function saveToken($customerId, $payment, $paymentDetails)
    {
        if (isset($paymentDetails['additional_data']['is_graphql'])) {
            //to save the card details for graphQl
            $this->saveCardForGraphQl($customerId, $payment, $paymentDetails);
        } elseif ($this->customerSession->getIsSavedCardRequested()) {
            //to save the card details for registered user
            $this->saveCardForDirect($payment);
        } elseif (!empty($this->customerSession->getVerifiedDetailedToken())
                  && $this->worldpayHelper->checkIfTokenExists(
                      $this->customerSession->getVerifiedDetailedToken()
                  )) {
            $this->wplogger->info(" User already has this card saved....");
            $this->customerSession->unsVerifiedDetailedToken();
        } elseif (empty($this->customerSession->getUsedSavedCard())) {
            //delete verified token for registered user when save_card=0
            $this->deleteSavedCardForDirect($customerId);
        } else {
            $this->customerSession->unsUsedSavedCard();
        }
    }
    
    /**
     * Saved GraphQL token data
     *
     * @param string $token_url
     * @param string $customerId
     * @param Payment $payment
     */
    public function saveTokenForGraphQl($token_url, $customerId, $payment)
    {
        $getTokenDetails = $this->paymentservicerequest->_getDetailedVerifiedToken(
            $token_url,
            $this->worldpayHelper->getXmlUsername(),
            $this->worldpayHelper->getXmlPassword()
        );

        $tokenDetailResponseToArray = json_decode($getTokenDetails, true);
        //make a call to getBrand Details,content-type is different
        $getTokenBrandDetails = $this->paymentservicerequest->getDetailedTokenForBrand(
            $token_url,
            $this->worldpayHelper->getXmlUsername(),
            $this->worldpayHelper->getXmlPassword()
        );
        $brandResponse = json_decode($getTokenBrandDetails, true);
        $tokenDetailResponseToArray['card_brand'] = $brandResponse['paymentInstrument']['brand'];
        $tokenDetailResponseToArray['customer_id'] = $customerId;
        $tokenDetailResponseToArray['disclaimer'] = 0;
        $this->updateWorldPayPayment->create()->
                saveVerifiedToken($tokenDetailResponseToArray, $payment);
    }
    
    /**
     * Submit GraphQL request
     *
     * @param array $paymentDetails
     */
    private function submitGraphQlRequest($paymentDetails)
    {
        $orderParams = [];
        $orderParams['identity'] = $this->worldpayHelper->getMerchantIdentity();
        $orderParams['cardExpiryDate'] = ["month" => $paymentDetails['additional_data']['cc_exp_month']
            , "year" => $paymentDetails['additional_data']['cc_exp_year']];
        $orderParams['cvc'] = $paymentDetails['additional_data']['cvc'];
        $orderParams['cardNumber'] = $paymentDetails['additional_data']['cc_number'];
        $hrefResponse = $this->paymentservicerequest->createSessionHrefForDirect($orderParams);
        return $hrefResponse['href'];
    }
    
    /**
     * Submit 3ds request
     *
     * @param array $directOrderParams
     * @param MageOrder $mageOrder
     */
    private function submit3DSRequest($directOrderParams, $mageOrder)
    {
        $this->checkoutSession->setauthenticatedOrderId($mageOrder->getIncrementId());
        if (!(isset($directOrderParams['paymentDetails']['tokenId']))) {
            $directOrderParams = $this->paymentservicerequest->
                    _createVerifiedTokenFor3Ds($directOrderParams);
        }
        if ($this->worldpayHelper->isExemptionEngineEnable()) {
            $exemptionData = $this->paymentservicerequest->sendExemptionAssesmentRequest($directOrderParams);
            $directOrderParams['paymentDetails']['exemptionResult'] = $exemptionData;
        }
        $this->checkoutSession->setDirectOrderParams($directOrderParams);
        $threeDSecureConfig = $this->get3DS2ConfigValues();
        $this->checkoutSession->set3DS2Config($threeDSecureConfig);
    }
   
    /**
     * Saved the exemption data
     */
    private function _saveExemptionData()
    {
        if (!empty($this->customerSession->getExemptionData())) {
            $this->wplogger->info(" Save Exemption data................");
            $exemptionData = $this->customerSession->getExemptionData();
            $this->customerSession->unsExemptionData();
            $this->updateWorldPayPayment->create()->saveExemptionData($exemptionData);
        }
    }
    
    /**
     * Check existing card?
     *
     * @param Quote $quote
     * @param array $paymentDetails
     * @param Payment $payment
     */
    private function isAnExisitingCard($quote, $paymentDetails, $payment)
    {
        $customerId = $quote->getCustomer()->getId();
        if ($customerId
            && !(isset($paymentDetails['additional_data']['token'])
            && !empty($paymentDetails['additional_data']['token']) )) {
            $this->saveToken($customerId, $payment, $paymentDetails);
        } elseif (!empty($this->customerSession->getVerifiedDetailedToken())
                  || (isset($paymentDetails['additional_data']['is_graphql'])
                      && !empty($paymentDetails['token_url']))) {
            //delete verified token for guest user
            $graphqlToken = isset($paymentDetails['additional_data']['is_graphql'])
                && !empty($paymentDetails['token_url']) ? $paymentDetails['token_url'] : '';
            $verifiedToken = $this->customerSession->getVerifiedDetailedToken();
            $this->customerSession->unsVerifiedDetailedToken();
            $this->wplogger->info(" Inititating Delete Token for Guest User....");
            $token = $verifiedToken ? $verifiedToken : $graphqlToken;
            $this->paymentservicerequest->getTokenDelete($token);
        }
    }
    
    /**
     * Saved GraphQL card data
     *
     * @param string $customerId
     * @param Payment $payment
     * @param array $paymentDetails
     */
    private function saveCardForGraphQl($customerId, $payment, $paymentDetails)
    {
        if ($paymentDetails['additional_data']['save_card'] !== '1'
            && !empty($paymentDetails['token_url'])) {
            if (!isset($paymentDetails['additional_data']['use_savedcard'])
                && $this->worldpayHelper->checkIfTokenExists($paymentDetails['token_url'])) {
                $this->wplogger->info(" User already has this card saved....");
            } else {
                $this->wplogger->info(
                    " Inititating Delete Token for Registered customer with customerID="
                        . $customerId . " ...."
                );
                $this->paymentservicerequest->getTokenDelete($paymentDetails['token_url']);
            }
        } elseif (($paymentDetails['additional_data']['save_card'] == '1'
                   && !empty($paymentDetails['token_url']))
                        || ($this->customerSession->getIsSavedCardRequested())) {
            $tokenFromcard = !empty($this->customerSession->getDetailedToken())
                             ? $this->customerSession->getDetailedToken() : '';
            $token = !empty($paymentDetails['token_url'])
                     ? $paymentDetails['token_url'] : $tokenFromcard['_links']['tokens:token']['href'];
            $this->customerSession->unsIsSavedCardRequested();
            $this->customerSession->unsDetailedToken();
            $this->saveTokenForGraphQl($token, $customerId, $payment);
        }
    }
    
    /**
     * Saved card for direct
     *
     * @param Payment $payment
     */
    private function saveCardForDirect($payment)
    {
        $tokenDetailResponseToArray = $this->customerSession->getDetailedToken();
        $this->updateWorldPayPayment->create()->
                saveVerifiedToken($tokenDetailResponseToArray, $payment);
        //unset the session variables
        $this->customerSession->unsIsSavedCardRequested();
        $this->customerSession->unsDetailedToken();
    }
    
    /**
     * Delete saved card for direct
     *
     * @param string $customerId
     */
    private function deleteSavedCardForDirect($customerId)
    {
        $verifiedToken = $this->customerSession->getVerifiedDetailedToken();
        $this->customerSession->unsVerifiedDetailedToken();
        $this->wplogger->info(
            " Inititating Delete Token for Registered customer with customerID="
                . $customerId . " ...."
        );
        $this->paymentservicerequest->getTokenDelete($verifiedToken);
    }
    
    /**
     * Set response and save data
     *
     * @param mixed $response
     * @param string $orderId
     * @param string $orderCode
     * @param Payment $payment
     */
    private function setResponseAndSaveData($response, $orderId, $orderCode, $payment)
    {
        $directResponse = $this->directResponse->setResponse($response);
        $this->updateWorldPayPayment->create()->updateAccessWorldpayPayment(
            $orderId,
            $orderCode,
            $directResponse,
            $payment
        );
        $this->_applyPaymentUpdate($directResponse, $payment);
    }
}
