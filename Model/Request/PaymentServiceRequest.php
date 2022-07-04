<?php
namespace Sapient\AccessWorldpay\Model\Request;

/**
 * @copyright 2020 Sapient
 */
use Exception;
use Sapient\AccessWorldpay\Model\SavedToken;

/**
 * Prepare the request and process them
 */
class PaymentServiceRequest extends \Magento\Framework\DataObject
{
    /**
     * @var \Sapient\AccessWorldpay\Model\Request $request
     */
    protected $_request;

    /**
     * @var array
     */
    public $threeDsValidResponse = ['AUTHENTICATED','BYPASSED','UNAVAILABLE','NOTENROLLED'];

    /**
     * Constructor
     *
     * @param \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger
     * @param \Sapient\AccessWorldpay\Model\Request $request
     * @param \Sapient\AccessWorldpay\Helper\Data $worldpayhelper
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Quote\Model\QuoteFactory $quoteFactory
     * @param \Sapient\AccessWorldpay\Model\ResourceModel\OmsData\CollectionFactory $omsCollectionFactory
     * @param \Sapient\AccessWorldpay\Model\Payment\UpdateAccessWorldpayment $updateAccessWorldpayment
     */
    public function __construct(
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger,
        \Sapient\AccessWorldpay\Model\Request $request,
        \Sapient\AccessWorldpay\Helper\Data $worldpayhelper,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Quote\Model\QuoteFactory $quoteFactory,
        \Sapient\AccessWorldpay\Model\ResourceModel\OmsData\CollectionFactory $omsCollectionFactory,
        \Sapient\AccessWorldpay\Model\Payment\UpdateAccessWorldpayment $updateAccessWorldpayment
    ) {
        $this->_wplogger = $wplogger;
        $this->_request = $request;
        $this->worldpayhelper = $worldpayhelper;
        $this->scopeConfig = $scopeConfig;
        $this->customerSession = $customerSession;
        $this->quoteFactory = $quoteFactory;
        $this->omsCollectionFactory = $omsCollectionFactory;
        $this->updateAccessWorldpayment = $updateAccessWorldpayment;
    }

    /**
     * Get URL of merchant site based on environment mode
     */
    private function _getUrl()
    {
        if ($this->worldpayhelper->getEnvironmentMode()=='Live Mode') {
            return $this->worldpayhelper->getLiveUrl();
        }
        return $this->worldpayhelper->getTestUrl();
    }

    /**
     * Send direct order XML to AccessWorldpay server
     *
     * @param array $directOrderParams
     * @return mixed
     */
    public function order($directOrderParams)
    {
        if (!isset($directOrderParams['threeDSecureConfig'])) {
            $directOrderParams['threeDSecureConfig'] = '';
        }
        $this->_wplogger->info('########## Submitting direct order request. OrderCode: '
                               . $directOrderParams['orderCode'] . ' ##########');
        $requestConfiguration = [
            'threeDSecureConfig' => $directOrderParams['threeDSecureConfig']
        ];

        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        $verifiedToken = $this->worldpayhelper->getTokenization();
        $directOrderParams['verifiedToken'] = '';

        if (isset($directOrderParams['paymentDetails']['tokenId'])
            && isset($directOrderParams['paymentDetails']['cvc'])) {
            //used saved card case
            return $this->_sendDirectSavedCardRequest(
                $directOrderParams,
                $requestConfiguration
            );
        } elseif ($verifiedToken
                && $directOrderParams['paymentDetails']['paymentType'] != 'TOKEN-SSL') {
            //build json request for verified token
            $directOrderParams = $this->createVerfiedTokenForNewCard($directOrderParams);
        }
        if (!$this->worldpayhelper->is3DSecureEnabled() && $this->worldpayhelper->isExemptionEngineEnable()) {
            $exemptionData = $this->sendExemptionAssesmentRequest($directOrderParams);
            $directOrderParams['paymentDetails']['exemptionResult'] = $exemptionData;
        }

        $this->xmldirectorder = new \Sapient\AccessWorldpay\Model\JsonBuilder\DirectOrder($requestConfiguration);
        $orderSimpleXml = $this->xmldirectorder->build(
            $directOrderParams['merchantCode'],
            $directOrderParams['orderCode'],
            $directOrderParams['orderDescription'],
            $directOrderParams['currencyCode'],
            $directOrderParams['amount'],
            $directOrderParams['paymentDetails'],
            $directOrderParams['cardAddress'],
            $directOrderParams['shopperEmail'],
            $directOrderParams['acceptHeader'],
            $directOrderParams['userAgentHeader'],
            $directOrderParams['shippingAddress'],
            $directOrderParams['billingAddress'],
            $directOrderParams['shopperId'],
            $directOrderParams['quoteId'],
            $directOrderParams['threeDSecureConfig']
        );
        $this->_wplogger->info("Sending direct order request as ....");
        $this->_wplogger->info($orderSimpleXml);
        return $this->_sendRequest(
            $directOrderParams['orderCode'],
            $this->worldpayhelper->getXmlUsername(),
            $this->worldpayhelper->getXmlPassword(),
            $this->_getUrl(),
            $orderSimpleXml
        );
    }

    /**
     * Get token detailed for brand
     *
     * @param string $verifiedToken
     * @param string $username
     * @param string $password
     * @return mixed
     */
    public function getDetailedTokenForBrand($verifiedToken, $username, $password)
    {
        return $this->_request->getDetailedTokenForBrand(
            $verifiedToken,
            $username,
            $password
        );
    }

    /**
     * Exemption Assesment Call
     *
     * @param array $directOrderParams
     * @return mixed
     */
    public function sendExemptionAssesmentRequest($directOrderParams)
    {
        $this->_wplogger->info(
            '########## Submitting exemption assesment request. OrderCode: '
            . $directOrderParams['orderCode'] . ' ##########'
        );
        $url = str_replace(
            '/payments/authorizations',
            '/exemptions/assessment',
            $this->_getUrl()
        );
        $this->xmldirectorder = new \Sapient\AccessWorldpay\Model\JsonBuilder\ExemptionRequest();
        $orderSimpleXml = $this->xmldirectorder->build(
            $directOrderParams['merchantCode'],
            $directOrderParams['orderCode'],
            $directOrderParams['orderDescription'],
            $directOrderParams['currencyCode'],
            $directOrderParams['amount'],
            $directOrderParams['paymentDetails'],
            $directOrderParams['cardAddress'],
            $directOrderParams['shopperEmail'],
            $directOrderParams['acceptHeader'],
            $directOrderParams['userAgentHeader'],
            $directOrderParams['shippingAddress'],
            $directOrderParams['billingAddress'],
            $directOrderParams['shopperId'],
            $directOrderParams['quoteId'],
            $directOrderParams['riskData']
        );
        $this->_wplogger->info("Sending exemption assesment request as ....");
        $this->_wplogger->info($orderSimpleXml);
        $exemptionresult = $this->_request->sendExemptionRequest(
            $directOrderParams['orderCode'],
            $this->worldpayhelper->getXmlUsername(),
            $this->worldpayhelper->getXmlPassword(),
            $url,
            $orderSimpleXml
        );
        //$directOrderParams["exemptionResult"] = $exemptionresult;
        $this->customerSession->setExemptionData($exemptionresult);
        return $exemptionresult;
    }

    /**
     * Submitting websdk token
     *
     * @param array $directOrderParams
     * @return mixed
     */
    public function sendWebsdkTokenOrder($directOrderParams)
    {
        if (!$this->worldpayhelper->is3DSecureEnabled() && $this->worldpayhelper->isExemptionEngineEnable()) {
            $exemptionData = $this->sendExemptionAssesmentRequest($directOrderParams);
            $directOrderParams['paymentDetails']['exemptionResult'] = $exemptionData;
        }

        $this->_wplogger->info(
            '########## Submitting websdk token only request. OrderCode: '
            . $directOrderParams['orderCode'] . ' ##########'
        );
        $this->xmldirectorder = new \Sapient\AccessWorldpay\Model\JsonBuilder\WebSdkOrder();
                $orderSimpleXml = $this->xmldirectorder->build(
                    $directOrderParams['merchantCode'],
                    $directOrderParams['orderCode'],
                    $directOrderParams['orderDescription'],
                    $directOrderParams['currencyCode'],
                    $directOrderParams['amount'],
                    $directOrderParams['paymentDetails'],
                    $directOrderParams['cardAddress'],
                    $directOrderParams['shopperEmail'],
                    $directOrderParams['acceptHeader'],
                    $directOrderParams['userAgentHeader'],
                    $directOrderParams['shippingAddress'],
                    $directOrderParams['billingAddress'],
                    $directOrderParams['shopperId'],
                    $directOrderParams['quoteId'],
                    $directOrderParams['threeDSecureConfig']
                );
            $this->_wplogger->info($orderSimpleXml);
            $tokenOnlyResponse= $this->_request->savedCardSendRequest(
                $directOrderParams['orderCode'],
                $this->worldpayhelper->getXmlUsername(),
                $this->worldpayhelper->getXmlPassword(),
                $this->_getUrl(),
                $orderSimpleXml
            );
        if (isset($tokenOnlyResponse['outcome'])
            && $tokenOnlyResponse['outcome'] === 'authorized') {
            $xml = $this->_request->_array2xml(
                $tokenOnlyResponse,
                false,
                $directOrderParams['orderCode']
            );
            //add check for Graphql
            if (!isset($directOrderParams['is_graphql'])) {
                $this->customerSession->setUsedSavedCard(true);
            }
            return $xml;
        } else {
            return $this->_handleFailureCases($tokenOnlyResponse);
        }
    }

    /**
     * Websdk order
     *
     * @param array $directOrderParams
     * @return mixed
     */
    public function websdkorder($directOrderParams)
    {
        $customerId = $this->customerSession->getCustomer()->getId();
        if (!isset($directOrderParams['threeDSecureConfig'])) {
            $directOrderParams['threeDSecureConfig'] = '';
        }
        $this->_wplogger->info('########## Submitting websdk order request. OrderCode: '
                               . $directOrderParams['orderCode'] . ' ##########');
        $requestConfiguration = [
            'threeDSecureConfig' => $directOrderParams['threeDSecureConfig']
        ];
       //checkGraphQl, !empty(token_url),!used_savedcard
        $tokenUrl = !empty($directOrderParams['paymentDetails']['token_url']) ? $directOrderParams['paymentDetails']['token_url'] : '';
        if (isset($directOrderParams['paymentDetails']['is_graphql'])
                && !empty($tokenUrl && !$this->worldpayhelper->is3DSecureEnabled())
                ) {
            $directOrderParams['paymentDetails']['verifiedToken'] = $tokenUrl;
            return $this->sendWebsdkTokenOrder($directOrderParams);
        } elseif (isset($directOrderParams['paymentDetails']['tokenId'])) {
            //saved card flow
            if (isset($directOrderParams['paymentDetails']['cvcHref'])
                && !empty($directOrderParams['paymentDetails']['cvcHref'])) {
                return $this->_sendWebSdkSavedCardRequest($directOrderParams, $customerId);
            } else {
                $directOrderParams['paymentDetails']['verifiedToken']=
                        $directOrderParams['paymentDetails']['tokenHref'];
                $this->customerSession->setUsedSavedCard(true);
                return $this->sendWebsdkTokenOrder($directOrderParams);
            }
        } else {
            //new card flow
            if ($this->worldpayhelper->is3DSecureEnabled()
                && (isset($directOrderParams['verifiedToken'])
                || $directOrderParams['paymentDetails']['paymentType'] === 'TOKEN-SSL')) {
                  return $this->place3dsOrderForWebsdk($directOrderParams);
            }

            $verifiedTokenResponse = $this->getVerifiedTokenResponseForWebsdk($directOrderParams);
            $responseToArray = json_decode($verifiedTokenResponse, true);

            if (isset($responseToArray['outcome'])
                && $responseToArray['outcome'] == 'verified') {

                return $this->placeNon3dsOrderForWebsdk($directOrderParams, $responseToArray, $customerId);
            } else {
                $message = $this->worldpayhelper->getCreditCardSpecificException('CCAM18');
                if (isset($responseToArray['message'])) {
                    $message = $responseToArray['message'];
                }
                throw new \Magento\Framework\Exception\LocalizedException(__($message));
            }
        }
    }

    /**
     * Place non 3ds order for websdk
     *
     * @param array $directOrderParams
     * @param array $responseToArray
     * @param int $customerId
     * @return mixed
     */
    public function placeNon3dsOrderForWebsdk($directOrderParams, $responseToArray, $customerId)
    {
        $directOrderParams['paymentDetails']['verifiedToken'] =
                        $responseToArray['_links']['tokens:token']['href'];
        if ($this->worldpayhelper->isExemptionEngineEnable()) {
            $directOrderParams['paymentDetails']['token_url'] =
                $directOrderParams['paymentDetails']['verifiedToken'];
        }
                $saveMyCard = $directOrderParams['paymentDetails']['saveMyCard'];
                /*Conflict Resolution*/
        if ($responseToArray['response_code']==409
                    && !empty($responseToArray['_links']['tokens:conflicts']['href'])) {
            $conflictResponse = $this->resolveConflict(
                $this->worldpayhelper->getXmlUsername(),
                $this->worldpayhelper->getXmlPassword(),
                $responseToArray['_links']['tokens:conflicts']['href']
            );
        }
        if ($saveMyCard == 1) {
        //get detailed token
            $conflictResponse = isset($conflictResponse)?$conflictResponse:null;
            $tokenDetailResponseToArray=$this->
                    savedCardOperationsForRegisteredCustomerWebsdk(
                        $directOrderParams,
                        $customerId,
                        $conflictResponse
                    );

                //save detailed token in session for later use
                $this->customerSession->setIsSavedCardRequested(true);
                $this->customerSession->setDetailedToken($tokenDetailResponseToArray);
        }
                //required to delete for guest user.
                $this->customerSession->setVerifiedDetailedToken(
                    $directOrderParams['paymentDetails']['verifiedToken']
                );

        if (!$this->worldpayhelper->is3DSecureEnabled() && $this->worldpayhelper->isExemptionEngineEnable()) {
            $exemptionData = $this->sendExemptionAssesmentRequest($directOrderParams);
            $directOrderParams['paymentDetails']['exemptionResult'] = $exemptionData;
        }
                $this->xmldirectorder = new \Sapient\AccessWorldpay\Model\JsonBuilder\WebSdkOrder();

                $orderSimpleXml = $this->xmldirectorder->build(
                    $directOrderParams['merchantCode'],
                    $directOrderParams['orderCode'],
                    $directOrderParams['orderDescription'],
                    $directOrderParams['currencyCode'],
                    $directOrderParams['amount'],
                    $directOrderParams['paymentDetails'],
                    $directOrderParams['cardAddress'],
                    $directOrderParams['shopperEmail'],
                    $directOrderParams['acceptHeader'],
                    $directOrderParams['userAgentHeader'],
                    $directOrderParams['shippingAddress'],
                    $directOrderParams['billingAddress'],
                    $directOrderParams['shopperId'],
                    $directOrderParams['quoteId'],
                    $directOrderParams['threeDSecureConfig']
                );
                $this->_wplogger->info($orderSimpleXml);
                return $this->_sendRequest(
                    $directOrderParams['orderCode'],
                    $this->worldpayhelper->getXmlUsername(),
                    $this->worldpayhelper->getXmlPassword(),
                    $this->_getUrl(),
                    $orderSimpleXml
                );
    }

    /**
     * Place 3ds order for websdk
     *
     * @param array $directOrderParams
     * @return mixed
     */
    public function place3dsOrderForWebsdk($directOrderParams)
    {
        $directOrderParams['paymentDetails']['verifiedToken'] =
                                        isset($directOrderParams['verifiedToken'])?
                                              $directOrderParams['verifiedToken']:
                                              $directOrderParams['paymentDetails']['token_url'];
        $this->xmldirectorder = new \Sapient\AccessWorldpay\Model\JsonBuilder\WebSdkOrder();

        $orderSimpleXml = $this->xmldirectorder->build(
            $directOrderParams['merchantCode'],
            $directOrderParams['orderCode'],
            $directOrderParams['orderDescription'],
            $directOrderParams['currencyCode'],
            $directOrderParams['amount'],
            $directOrderParams['paymentDetails'],
            $directOrderParams['cardAddress'],
            $directOrderParams['shopperEmail'],
            $directOrderParams['acceptHeader'],
            $directOrderParams['userAgentHeader'],
            $directOrderParams['shippingAddress'],
            $directOrderParams['billingAddress'],
            $directOrderParams['shopperId'],
            $directOrderParams['quoteId'],
            $directOrderParams['threeDSecureConfig']
        );
        $this->_wplogger->info($orderSimpleXml);
        return $this->_sendRequest(
            $directOrderParams['orderCode'],
            $this->worldpayhelper->getXmlUsername(),
            $this->worldpayhelper->getXmlPassword(),
            $this->_getUrl(),
            $orderSimpleXml
        );
    }

    /**
     * Saved card operation for websdk registered customer
     *
     * @param array $directOrderParams
     * @param int $customerId
     * @param mixed $conflictResponse
     * @return array
     */
    public function savedCardOperationsForRegisteredCustomerWebsdk($directOrderParams, $customerId, $conflictResponse)
    {
         $getTokenDetails = $this->_getDetailedVerifiedToken(
             $directOrderParams['paymentDetails']['verifiedToken'],
             $this->worldpayhelper->getXmlUsername(),
             $this->worldpayhelper->getXmlPassword()
         );
                     $tokenDetailResponseToArray = json_decode($getTokenDetails, true);

                    //make a call to getBrand Details,content-type is different
                    $getTokenBrandDetails = $this->getDetailedTokenForBrand(
                        $tokenDetailResponseToArray['_links']['tokens:token']['href'],
                        $this->worldpayhelper->getXmlUsername(),
                        $this->worldpayhelper->getXmlPassword()
                    );
                    $brandResponse = json_decode($getTokenBrandDetails, true);
                    $tokenDetailResponseToArray['card_brand'] = $brandResponse['paymentInstrument']['brand'];

                        $tokenDetailResponseToArray['customer_id'] = $customerId;
                        $tokenDetailResponseToArray['disclaimer'] = $directOrderParams['paymentDetails']['disclaimer'];
                        //Set Resolve Conflict Response Code In Customer Session
        if (isset($conflictResponse)) {
            $tokenDetailResponseToArray['conflictResponse'] = $conflictResponse;
        }
                    return $tokenDetailResponseToArray;
    }

    /**
     * Get verified token response for websdk
     *
     * @param array $directOrderParams
     * @return mixed
     */
    public function getVerifiedTokenResponseForWebsdk($directOrderParams)
    {
        $verifiedTokenRequest = $this->_createWebSdkVerifiedTokenReq($directOrderParams);
            $verifiedTokenResponse = $this->_getVerifiedToken(
                $verifiedTokenRequest,
                $this->worldpayhelper->getXmlUsername(),
                $this->worldpayhelper->getXmlPassword()
            );
        return $verifiedTokenResponse;
    }

    /**
     * Create websdk verified token request
     *
     * @param array $directOrderParams
     * @return string
     */
    protected function _createWebSdkVerifiedTokenReq($directOrderParams)
    {
        $instruction['paymentInstrument'] = $this->createPaymentDataForVerfiedToken(
            $directOrderParams,
            $directOrderParams['paymentDetails']['sessionHref']
        );

        $instruction['paymentInstrument']['billingAddress']=$this->createBillingDataForVerfiedToken($directOrderParams);
        if (isset($directOrderParams['billingAddress']['state'])
            && $directOrderParams['billingAddress']['state'] !== '') {
            $instruction['paymentInstrument']['billingAddress']['state'] =
                    $directOrderParams['billingAddress']['state'];
        }
            $instruction['paymentInstrument']['billingAddress']['countryCode'] =
                    $directOrderParams['billingAddress']['countryCode'];
            $instruction['merchant'] = ["entity" => $this->worldpayhelper->getMerchantEntityReference()];
            $instruction['verificationCurrency'] = ($directOrderParams['currencyCode']);

        if ($this->customerSession->isLoggedIn()) {
            $shoperId = $this->customerSession->getCustomer()->getId()
                    .'_'.date("m").date("Y");
            $instruction['namespace'] = $shoperId;
        } else {
            $instruction['namespace'] = strtotime("now");
        }
            return json_encode($instruction);
    }

    /**
     * Create verified token request
     *
     * @param array $directOrderParams
     * @return string
     */
    protected function _createVerifiedTokenReq($directOrderParams)
    {
        $instruction = [];
      //graphql order
        if ($directOrderParams['paymentDetails']['cardHolderName'] == '') {
            $quote = $this->quoteFactory->create()->load($directOrderParams['quoteId']);

            $addtionalData = $quote->getPayment()->getOrigData();
            $ccData = $addtionalData['additional_information'];
            $instruction['paymentInstrument'] = $this->createPaymentDataForVerfiedTokenForPlainCard($ccData);

            $instruction['paymentInstrument']['billingAddress'] =
                $this->createBillingDataForVerfiedToken($directOrderParams);
            if (isset($directOrderParams['billingAddress']['state'])
                && $directOrderParams['billingAddress']['state'] !== '') {
                $instruction['paymentInstrument']['billingAddress']['state'] =
                        $directOrderParams['billingAddress']['state'];
            }
            $instruction['paymentInstrument']['billingAddress']['countryCode'] =
                    $directOrderParams['billingAddress']['countryCode'];
            $instruction['merchant'] = ["entity" => $this->worldpayhelper->getMerchantEntityReference()];
            $instruction['verificationCurrency'] = ($directOrderParams['currencyCode']);
            /*Fixed namespace issue for graphQl*/
            if ($quote->getCustomer()->getId()) {
                $shoperId = $quote->getCustomer()->getId().'_'.date("m").date("Y");
                $instruction['namespace'] = $shoperId;
            } else {
                $instruction['namespace'] = strtotime("now");
            }

            return json_encode($instruction);

        } else {
            $instruction = $this->createRequestDataForVerifiedToken($directOrderParams);
            return json_encode($instruction);
        }
    }

    /**
     * Create request data for verified token
     *
     * @param array $directOrderParams
     * @return array
     */
    public function createRequestDataForVerifiedToken($directOrderParams)
    {
        if (isset($directOrderParams['paymentDetails']['directSessionHref'])
                && $directOrderParams['paymentDetails']['directSessionHref'] !== '') {
                $instruction['paymentInstrument'] = $this->createPaymentDataForVerfiedToken(
                    $directOrderParams,
                    $directOrderParams['paymentDetails']['directSessionHref']
                );
        }
        if (isset($directOrderParams['paymentDetails']['sessionHref'])
                && $directOrderParams['paymentDetails']['sessionHref'] !== '') {
            $instruction['paymentInstrument'] = $this->createPaymentDataForVerfiedToken(
                $directOrderParams,
                $directOrderParams['paymentDetails']['sessionHref']
            );
        }

            $instruction['paymentInstrument']['billingAddress'] =
                $this->createBillingDataForVerfiedToken($directOrderParams);
        if (isset($directOrderParams['billingAddress']['state'])
                && $directOrderParams['billingAddress']['state'] !== '') {
            $instruction['paymentInstrument']['billingAddress']['state'] =
                    $directOrderParams['billingAddress']['state'];
        }
            $instruction['paymentInstrument']['billingAddress']['countryCode'] =
                    $directOrderParams['billingAddress']['countryCode'];
            $instruction['merchant'] = ["entity" => $this->worldpayhelper->getMerchantEntityReference()];
            $instruction['verificationCurrency'] = ($directOrderParams['currencyCode']);

        if ($this->customerSession->isLoggedIn()) {
            $shoperId = $this->customerSession->getCustomer()->getId().'_'.date("m").date("Y");
            $instruction['namespace'] = $shoperId;
        } else {
            $instruction['namespace'] = strtotime("now");
        }
            return $instruction;
    }

    /**
     * Create payment data for plain card verified token
     *
     * @param array $ccData
     * @return array
     */
    public function createPaymentDataForVerfiedTokenForPlainCard($ccData)
    {
        $data = ["type" => "card/plain",
                "cardHolderName" => $ccData['cc_name'],//strtolower($ccData['cc_name']),
                //Using lowercase for cardholder name to minimize the conflict
                "cardNumber" => $ccData['cc_number'],
                "cardExpiryDate" => ["month" => (int) $ccData['cc_exp_month'],
                    "year" => (int) $ccData['cc_exp_year']],
                "cvc" => (int) $ccData['cvc']];
        return $data;
    }

    /**
     * Create payment data for verified token
     *
     * @param array $directOrderParams
     * @param string $sessionHref
     * @return array
     */
    public function createPaymentDataForVerfiedToken($directOrderParams, $sessionHref)
    {
        $data = ["type" => "card/checkout",
            "cardHolderName" => $directOrderParams['paymentDetails']['cardHolderName'],
            // strtolower(
//                            $directOrderParams['paymentDetails']['cardHolderName']
//                        ), //uncomment this after Exemption engine
            "sessionHref" =>$sessionHref
            ];
        return $data;
    }

    /**
     * Create billing data for verified token
     *
     * @param array $directOrderParams
     * @return array
     */
    public function createBillingDataForVerfiedToken($directOrderParams)
    {
        $address =["address1" => $directOrderParams['billingAddress']['firstName'],
                "address2" => $directOrderParams['billingAddress']['lastName'],
                "address3" => $directOrderParams['billingAddress']['street'],
                "postalCode" => $directOrderParams['billingAddress']['postalCode'],
                "city" => $directOrderParams['billingAddress']['city']];
        return $address;
    }

    /**
     * Get verified token response
     *
     * @param array $directOrderParams
     * @return mixed
     */
    public function getVerfiedTokenResponse($directOrderParams)
    {
        $verifiedTokenRequest = $this->_createVerifiedTokenReq($directOrderParams);

            //send verified token request to Access Worldpay
            $verifiedTokenResponse = $this->_getVerifiedToken(
                $verifiedTokenRequest,
                $this->worldpayhelper->getXmlUsername(),
                $this->worldpayhelper->getXmlPassword()
            );
        return $verifiedTokenResponse;
    }

    /**
     * Create verified token for new card
     *
     * @param array $directOrderParams
     * @return mixed
     */
    public function createVerfiedTokenForNewCard($directOrderParams)
    {
        $verifiedTokenResponse= $this->getVerfiedTokenResponse($directOrderParams);
        $responseToArray = json_decode($verifiedTokenResponse, true);

        if (isset($responseToArray['outcome']) && $responseToArray['outcome'] == 'verified') {

            $directOrderParams['verifiedToken'] = $responseToArray['_links']['tokens:token']['href'];
            /*Conflict Resolution*/
            if ($responseToArray['response_code']==409
                && !empty($responseToArray['_links']['tokens:conflicts']['href'])) {
                $conflictResponse = $this->resolveConflict(
                    $this->worldpayhelper->getXmlUsername(),
                    $this->worldpayhelper->getXmlPassword(),
                    $responseToArray['_links']['tokens:conflicts']['href']
                );
            }
            $directOrderParams['paymentDetails']['paymentType'] = 'TOKEN-SSL';
            $directOrderParams['paymentDetails']['token_url'] = $directOrderParams['verifiedToken'];
            $this->_wplogger->info('check customer is logged in paymentDetails token_url ...................');

            $customerId = $this->customerSession->getCustomer()->getId();

            //check save card for graphql order
            $quote = $this->quoteFactory->create()->load($directOrderParams['quoteId']);

            $customerId = $quote->getCustomer()->getId();

            //check save card is checked by user
            if ($customerId) {
                $this->_wplogger->info('customer is logged in  ...............................');
                //check save card request for normal order
                $saveMyCard = $directOrderParams['paymentDetails']['saveMyCard'];

                //check save card for graphql order
                $addtionalData = $quote->getPayment()->getOrigData();
                $saveMyCardGraphQl = '';
                if (isset($addtionalData['additional_information']['save_card'])) {
                    $saveMyCardGraphQl = $addtionalData['additional_information']['save_card'];
                }

                if ($saveMyCard == 1 || $saveMyCardGraphQl == 1) {
                    $this->_wplogger->info('sending detailed tokem req  ..........................');
                    //get detailed token
                    $conflictResponse = isset($conflictResponse)?$conflictResponse:null;
                    $tokenDetailResponseToArray = $this->savedCardOperationsForRegisteredCustomer(
                        $directOrderParams,
                        $customerId,
                        $conflictResponse
                    );
                //save detailed token in session for later use
                    $this->customerSession->setIsSavedCardRequested(true);
                    $this->customerSession->setDetailedToken($tokenDetailResponseToArray);
                }
            }
            //required to delete for guest user.
            $this->customerSession->setVerifiedDetailedToken($directOrderParams['verifiedToken']);
            return $directOrderParams;
        } else {
            $this->handleVerfiedTokenFailureCases($responseToArray);
        }
    }

    /**
     * Saved card operation for registered customer
     *
     * @param array $directOrderParams
     * @param int $customerId
     * @param mixed $conflictResponse
     * @return array
     */
    public function savedCardOperationsForRegisteredCustomer($directOrderParams, $customerId, $conflictResponse)
    {
        $getTokenDetails = $this->_getDetailedVerifiedToken(
            $directOrderParams['verifiedToken'],
            $this->worldpayhelper->getXmlUsername(),
            $this->worldpayhelper->getXmlPassword()
        );

        $tokenDetailResponseToArray = json_decode($getTokenDetails, true);
        //make a call to getBrand Details,content-type is different
        $getTokenBrandDetails = $this->getDetailedTokenForBrand(
            $tokenDetailResponseToArray['_links']['tokens:token']['href'],
            $this->worldpayhelper->getXmlUsername(),
            $this->worldpayhelper->getXmlPassword()
        );
        $brandResponse = json_decode($getTokenBrandDetails, true);
        $tokenDetailResponseToArray['card_brand'] = $brandResponse['paymentInstrument']['brand'];
        $tokenDetailResponseToArray['customer_id'] = $customerId;
        // Set disclaimer flag in customer token session
        $tokenDetailResponseToArray['disclaimer'] = $directOrderParams['paymentDetails']['disclaimer'];
        if (isset($conflictResponse)) {
            $tokenDetailResponseToArray['conflictResponse'] = $conflictResponse;
        }
        return $tokenDetailResponseToArray;
    }

    /**
     * Send Apple Pay order XML to Worldpay server
     *
     * @param array $applePayOrderParams
     * @return mixed
     */
    public function applePayOrder($applePayOrderParams)
    {
        try {
            $this->_wplogger->info(
                '########## Submitting Apple Pay order request. OrderCode: '
                . $applePayOrderParams['orderCode'] . ' ##########'
            );
            $customerId = $this->customerSession->getCustomer()->getId();
            $this->xmldirectorder = new \Sapient\AccessWorldpay\Model\JsonBuilder\ApplePayOrder();

            //Entity Ref value
            //$applePayOrderParams['merchantCode']['entityRef'] = $this->worldpayhelper->getMerchantEntityReference();
            $appleSimpleXml = $this->xmldirectorder->build(
                $applePayOrderParams['merchantCode'],
                $applePayOrderParams['orderCode'],
                $applePayOrderParams['orderDescription'],
                $applePayOrderParams['currencyCode'],
                $applePayOrderParams['amount'],
                $applePayOrderParams['shopperEmail'],
                $applePayOrderParams['protocolVersion'],
                $applePayOrderParams['signature'],
                $applePayOrderParams['data'],
                $applePayOrderParams['ephemeralPublicKey'],
                $applePayOrderParams['publicKeyHash'],
                $applePayOrderParams['transactionId']
            );
            
            return $this->_sendApplePayRequest(
                $applePayOrderParams['orderCode'],
                $this->worldpayhelper->getXmlUsername(),
                $this->worldpayhelper->getXmlPassword(),
                $this->_getUrl(),
                $appleSimpleXml
            );
        } catch (Exception $ex) {
            throw new \Magento\Framework\Exception\LocalizedException(__($ex));
        }
    }

    /**
     * Process the request
     *
     * @param SimpleXmlElement $verifiedTokenRequest
     * @param string $username
     * @param string $password
     * @return mixed
     */
    public function _getVerifiedToken($verifiedTokenRequest, $username, $password)
    {
        $response = $this->_request->getVerifiedToken($verifiedTokenRequest, $username, $password);
        return $response;
    }

    /**
     * Get detailed verified token
     *
     * @param mixed $verifiedToken
     * @param string $username
     * @param string $password
     * @return mixed
     */
    public function _getDetailedVerifiedToken($verifiedToken, $username, $password)
    {
        $response = $this->_request->getDetailedVerifiedToken($verifiedToken, $username, $password);
        return $response;
    }

    /**
     * Process the request
     *
     * @param string $orderCode
     * @param string $username
     * @param string $password
     * @param string $url
     * @param SimpleXmlElement $xml
     * @return mixed
     */
    protected function _sendApplePayRequest($orderCode, $username, $password, $url, $xml)
    {
        $response = $this->_request->sendApplePayRequest($orderCode, $username, $password, $url, $xml);
        return $response;
    }

    /**
     * Send request
     *
     * @param string $orderCode
     * @param string $username
     * @param string $password
     * @param string $url
     * @param SimpleXmlElement $xml
     * @return mixed
     */
    protected function _sendRequest($orderCode, $username, $password, $url, $xml)
    {
        $response = $this->_request->sendRequest($orderCode, $username, $password, $url, $xml);
        return $response;
    }

    /**
     * Check error
     *
     * @param SimpleXmlElement $response
     * @throw Exception
     */
    protected function _checkForError($response)
    {
        $paymentService = new \SimpleXmlElement($response);
        $lastEvent = $paymentService->xpath('//lastEvent');
        if ($lastEvent && $lastEvent[0] =='REFUSED') {
            return;
        }
        $error = $paymentService->xpath('//error');

        if ($error) {
            $this->_wplogger->error('An error occurred while sending the request');
            $this->_wplogger->error('Error (code ' . $error[0]['code'] . '): ' . $error[0]);
            throw new \Magento\Framework\Exception\LocalizedException($error[0]);
        }
    }

    /**
     * Payment options by country
     *
     * @param array $paymentOptionsParams
     * @return mixed
     */
    public function paymentOptionsByCountry($paymentOptionsParams)
    {
         $this->_wplogger->info('########## Submitting payment otions request ##########');
         $this->xmlpaymentoptions = new \Sapient\AccessWorldpay\Model\JsonBuilder\PaymentOptions();
        $paymentOptionsXml = $this->xmlpaymentoptions->build(
            $paymentOptionsParams['merchantCode'],
            $paymentOptionsParams['countryCode']
        );

        return $this->_sendRequest(
            dom_import_simplexml($paymentOptionsXml)->ownerDocument,
            $this->worldpayhelper->getXmlUsername($paymentOptionsParams['paymentType']),
            $this->worldpayhelper->getXmlPassword($paymentOptionsParams['paymentType'])
        );
    }

    /**
     * Send capture XML to Worldpay server
     *
     * @param \Magento\Sales\Model\Order $order
     * @param \Magento\Framework\DataObject $wp
     * @param string $paymentMethodCode
     * @return mixed
     */
    public function capture(\Magento\Sales\Model\Order $order, $wp, $paymentMethodCode)
    {
        if ($this->worldpayhelper->isWorldPayEnable()) { // Capture request only when service is enabled
            $collectionData = $this->omsCollectionFactory->create()
                    ->addFieldToSelect(['awp_settle_param','awp_partial_settle_param'])
                    ->addFieldToFilter('awp_order_code', ['eq' => $wp->getWorldpayOrderId()]);
                $collectionData = $collectionData->getData();
            if ($collectionData) {
                $captureUrl = $collectionData[0]['awp_settle_param'];
                $partialCaptureUrl = $collectionData[0]['awp_partial_settle_param'];
            }
            $requestType = 'capture';
            //print_r($collectionData);
            //exit;
            $orderCode = $wp->getWorldpayOrderId();
            $this->_wplogger->info(
                '########## Submitting capture request. Order: '
                . $orderCode . ' Amount:' . $order->getGrandTotal() . ' ##########'
            );
            $this->xmlcapture = new \Sapient\AccessWorldpay\Model\JsonBuilder\Capture();

            $captureSimpleXml = $this->xmlcapture->build(
                $this->worldpayhelper->getMerchantCode($wp->getPaymentType()),
                $orderCode,
                $order->getOrderCurrencyCode(),
                $order->getGrandTotal(),
                $requestType
            );
            //print_r($captureSimpleXml); exit;

            return $this->_sendRequest(
                $orderCode,
                $this->worldpayhelper->getXmlUsername(),
                $this->worldpayhelper->getXmlPassword(),
                $captureUrl,
                null
            );
        }
        throw new \Magento\Framework\Exception\LocalizedException(
            __('Access Worldpay Service Not Available')
        );
    }

    /**
     * Send Partial capture XML to Worldpay server
     *
     * @param \Magento\Sales\Model\Order $order
     * @param \Magento\Framework\DataObject $wp
     * @param float $grandTotal
     * @return mixed
     */
    public function partialCapture(\Magento\Sales\Model\Order $order, $wp, $grandTotal)
    {
        if ($this->worldpayhelper->isWorldPayEnable()) { // Capture request only when service is enabled
            $collectionData = $this->omsCollectionFactory->create()
                    ->addFieldToSelect(['awp_settle_param','awp_partial_settle_param'])
                    ->addFieldToFilter('awp_order_code', ['eq' => $wp->getWorldpayOrderId()]);
            $collectionData = $collectionData->getData();
            if ($collectionData) {
                $captureUrl = $collectionData[0]['awp_settle_param'];
                $partialCaptureUrl = $collectionData[0]['awp_partial_settle_param'];
            }
            $requestType = 'partial_capture';
            $orderCode = $wp->getWorldpayOrderId();
            $this->_wplogger->info(
                '########## Submitting Partial capture request. Order: '
                . $orderCode . ' Amount:' . $grandTotal . ' ##########'
            );
            $this->xmlcapture = new \Sapient\AccessWorldpay\Model\JsonBuilder\Capture();

            $captureSimpleXml = $this->xmlcapture->build(
                $this->worldpayhelper->getMerchantCode($wp->getPaymentType()),
                $orderCode,
                $order->getOrderCurrencyCode(),
                $grandTotal,
                $requestType
            );
            $this->_wplogger->info($captureSimpleXml);
            return $this->_sendRequest(
                $orderCode,
                $this->worldpayhelper->getXmlUsername(),
                $this->worldpayhelper->getXmlPassword(),
                $partialCaptureUrl,
                $captureSimpleXml
            );
        }
        throw new \Magento\Framework\Exception\LocalizedException(
            __('Access Worldpay Service Not Available')
        );
    }

    /**
     * Send refund Json to Worldpay server
     *
     * @param \Magento\Sales\Model\Order $order
     * @param \Magento\Framework\DataObject $wp
     * @param string $paymentMethodCode
     * @param float $amount
     * @param string|array $reference
     * @return mixed
     */
    public function refund(\Magento\Sales\Model\Order $order, $wp, $paymentMethodCode, $amount, $reference)
    {
        $collectionData = $this->omsCollectionFactory->create()
                ->addFieldToSelect(['awp_refund_param','awp_partial_refund_param'])
                ->addFieldToFilter('awp_order_code', ['eq' => $wp->getWorldpayOrderId()]);
            $collectionData = $collectionData->getData();
        if ($collectionData) {
            $refundUrl = $collectionData[0]['awp_refund_param'];
        }
        $requestType = 'refund';
        $orderCode = $wp->getWorldpayOrderId();
        $this->_wplogger->info('########## Submitting refund request. OrderCode: ' . $orderCode . ' ##########');
        $this->xmlrefund = new \Sapient\AccessWorldpay\Model\JsonBuilder\Refund();
        $refundSimpleXml = $this->xmlrefund->build(
            $this->worldpayhelper->getMerchantCode($wp->getPaymentType()),
            $orderCode,
            $order->getOrderCurrencyCode(),
            $amount,
            $requestType,
            $reference
        );

        return $this->_sendRequest(
            $orderCode,
            $this->worldpayhelper->getXmlUsername(),
            $this->worldpayhelper->getXmlPassword(),
            $refundUrl,
            null
        );
    }

    /**
     * Send partial refund Json to Worldpay server
     *
     * @param \Magento\Sales\Model\Order $order
     * @param \Magento\Framework\DataObject $wp
     * @param string $paymentMethodCode
     * @param float $amount
     * @param string|array $reference
     * @return mixed
     */
    public function partialRefund(\Magento\Sales\Model\Order $order, $wp, $paymentMethodCode, $amount, $reference)
    {
        $collectionData = $this->omsCollectionFactory->create()
                ->addFieldToSelect(['awp_refund_param','awp_partial_refund_param'])
                ->addFieldToFilter('awp_order_code', ['eq' => $wp->getWorldpayOrderId()]);
            $collectionData = $collectionData->getData();
        if ($collectionData) {
            $partialRefundUrl = $collectionData[0]['awp_partial_refund_param'];
        }
        $requestType = 'partial_refund';
        $orderCode = $wp->getWorldpayOrderId();
        $this->_wplogger->info('########## Submitting Partial refund request. OrderCode: '
                               . $orderCode . ' ##########');
        $this->xmlrefund = new \Sapient\AccessWorldpay\Model\JsonBuilder\Refund();
        $refundSimpleXml = $this->xmlrefund->build(
            $this->worldpayhelper->getMerchantCode($wp->getPaymentType()),
            $orderCode,
            $order->getOrderCurrencyCode(),
            $amount,
            $reference,
            $requestType
        );

        return $this->_sendRequest(
            $orderCode,
            $this->worldpayhelper->getXmlUsername(),
            $this->worldpayhelper->getXmlPassword(),
            $partialRefundUrl,
            $refundSimpleXml
        );
    }

    /**
     * Create device data collection
     *
     * @param array $directOrderParams
     * @return mixed
     */
    public function _createDeviceDataCollection($directOrderParams)
    {
        $url = str_replace(
            '/payments/authorizations',
            '/verifications/customers/3ds/deviceDataInitialization',
            $this->_getUrl()
        );
        //$url = 'https://try.access.worldpay.com/verifications/customers/3ds/deviceDataInitialization';
        if ($this->worldpayhelper->is3DSecureEnabled()) {
            $this->_wplogger->info(
                '########## Submitting get DDC order request. OrderCode: '
                . $directOrderParams['orderCode'] . ' ##########'
            );
            $this->xmldirectorder = new \Sapient\AccessWorldpay\Model\JsonBuilder\DeviceDataCollection();
            $orderSimpleXml= $this->xmldirectorder->build(
                $directOrderParams['orderCode'],
                $directOrderParams['paymentDetails']
            );
            $response = $this->_request->sendDdcRequest(
                $directOrderParams['orderCode'],
                $this->worldpayhelper->getXmlUsername(),
                $this->worldpayhelper->getXmlPassword(),
                $url,
                $orderSimpleXml
            );
            return $response;
        }
    }

    /**
     * Authenticate 3D data
     *
     * @param string $authenticationurl
     * @param array $directOrderParams
     * @return mixed
     */
    public function authenticate3Ddata($authenticationurl, $directOrderParams)
    {
        $this->_wplogger->info(
            '########## Submitting get 3Ds authentication request. OrderCode: '
            . $directOrderParams['orderCode'] . ' ##########'
        );
        $this->xmldirectorder = new \Sapient\AccessWorldpay\Model\JsonBuilder\ThreeDsAuthentication();

        $orderSimpleXml= $this->xmldirectorder->build(
            $directOrderParams['orderCode'],
            $directOrderParams['paymentDetails'],
            $directOrderParams['billingAddress'],
            $directOrderParams['currencyCode'],
            $directOrderParams['amount'],
            $directOrderParams['acceptHeader'],
            $directOrderParams['userAgentHeader'],
            $directOrderParams['riskData']
        );
        $this->_wplogger->info($orderSimpleXml);
        $response = $this->_request->sendDdcRequest(
            $directOrderParams['orderCode'],
            $this->worldpayhelper->getXmlUsername(),
            $this->worldpayhelper->getXmlPassword(),
            $authenticationurl,
            $orderSimpleXml
        );
        return $response;
    }

    /**
     * Create verified token for 3DS
     *
     * @param array $directOrderParams
     * @return mixed
     */
    public function _createVerifiedTokenFor3Ds($directOrderParams)
    {

        $this->_wplogger->info(
            '########## Submitting create VerifiedToken For 3Ds. OrderCode: '
            . $directOrderParams['orderCode'] . ' ##########'
        );
        //build json request for verified token
        $verifiedTokenResponse= $this->getVerfiedTokenResponse($directOrderParams);
        $responseToArray = json_decode($verifiedTokenResponse, true);
        if (isset($responseToArray['outcome']) && $responseToArray['outcome'] == 'verified') {

                $directOrderParams['verifiedToken'] = $responseToArray['_links']['tokens:token']['href'];
                /*Conflict Resolution*/
            if ($responseToArray['response_code']==409
                && !empty($responseToArray['_links']['tokens:conflicts']['href'])) {
                $conflictResponse = $this->resolveConflict(
                    $this->worldpayhelper->getXmlUsername(),
                    $this->worldpayhelper->getXmlPassword(),
                    $responseToArray['_links']['tokens:conflicts']['href']
                );
            }
                $directOrderParams['paymentDetails']['paymentType'] = 'TOKEN-SSL';
                $directOrderParams['paymentDetails']['token_url'] = $directOrderParams['verifiedToken'];
                $this->_wplogger->info('verified outcome came  ...............................');

                $customerId = $this->customerSession->getCustomer()->getId();
                //check save card for graphql order
                $quote = $this->quoteFactory->create()->load($directOrderParams['quoteId']);
                $customerId = $quote->getCustomer()->getId();

                //check save card is checked by user
            if ($customerId && isset($directOrderParams['paymentDetails']['saveMyCard'])
                        && $directOrderParams['paymentDetails']['saveMyCard'] == 1) {
                $this->_wplogger->info('customer is logged in  ...............................');
                $this->_wplogger->info('sending detailed tokem req  ...............................');
                //get detailed token
                $conflictResponse = isset($conflictResponse)?$conflictResponse:null;
                $tokenDetailResponseToArray = $this->savedCardOperationsForRegisteredCustomer(
                    $directOrderParams,
                    $customerId,
                    $conflictResponse
                );
                //save detailed token in session for later use
                $this->customerSession->setIsSavedCardRequested(true);
                $this->customerSession->setDetailedToken($tokenDetailResponseToArray);
            }
                //required to delete for guest user.
                $this->customerSession->setVerifiedDetailedToken($directOrderParams['verifiedToken']);
                return $directOrderParams;
        } else {
            $this->handleVerfiedTokenFailureCases($responseToArray);
        }
    }

    /**
     * Handle verified token failure cases
     *
     * @param array $responseToArray
     * @throw Exception
     */
    public function handleVerfiedTokenFailureCases($responseToArray)
    {
        $message = $this->worldpayhelper->getCreditCardSpecificException('CCAM18');
        if (isset($responseToArray['message'])) {
            if ($responseToArray['message'] === "Session could not be found") {
                $message =  $responseToArray['message']. " Please refresh and try again." ;
            } else {
                $message = $responseToArray['message'];
            }
        }
            throw new \Magento\Framework\Exception\LocalizedException(__($message));
    }

    /**
     * Order 3DS2 secure
     *
     * @param array $directOrderParams
     * @param array $threeDSecureParams
     * @return mixed
     */
    public function order3Ds2Secure($directOrderParams, $threeDSecureParams)
    {
        $verificationResponse = $threeDSecureParams;
        if ($threeDSecureParams['outcome'] ==='challenged') {
            $this->_wplogger->info(
                '########## Submitting get 3Ds verification request. OrderCode: '
                . $directOrderParams['orderCode'] . ' ##########'
            );
            $this->xmldirectorder = new \Sapient\AccessWorldpay\Model\JsonBuilder\ThreeDsVerifiaction();

            $verificationRequest = $this->xmldirectorder->build(
                $directOrderParams,
                $threeDSecureParams['challenge']['reference']
            );
            $verificationUrl = $threeDSecureParams['_links']['3ds:verify']['href'];

            $this->_wplogger->info($verificationRequest);

            $verificationResponse = $this->_request->sendDdcRequest(
                $directOrderParams['orderCode'],
                $this->worldpayhelper->getXmlUsername(),
                $this->worldpayhelper->getXmlPassword(),
                $verificationUrl,
                $verificationRequest
            );
        }
        $this->_wplogger->info($verificationResponse['outcome']);
        if (in_array(
            strtoupper($verificationResponse['outcome']),
            $this->threeDsValidResponse
        )) {
            $directOrderParams['threeDSecureConfig'] = $verificationResponse;
            if ($this->worldpayhelper->getCcIntegrationMode() == 'direct') {
                $response = $this->order($directOrderParams);
            } else {
                $response = $this->websdkorder($directOrderParams);
            }
            return $response;
        } else {
            $this->handle3DsFailureCases($verificationResponse);
        }
    }

    /**
     * Handle 3DS failure cases
     *
     * @param mixed $verificationResponse
     * @throws \Exception
     */
    public function handle3DsFailureCases($verificationResponse)
    {
        if (!isset($verificationResponse)) {
            throw new \Magento\Framework\Exception\LocalizedException(
                __($this->worldpayhelper->getCreditCardSpecificException('CCAM6'))
            );
        } else {
            throw new \Magento\Framework\Exception\LocalizedException(
                __($this->worldpayhelper->getCreditCardSpecificException('CCAM19'))
            );
        }
    }

    /**
     * Create session href for direct
     *
     * @param array $orderParams
     * @return string
     */
    public function createSessionHrefForDirect($orderParams)
    {
        $url = str_replace(
            '/payments/authorizations',
            '/verifiedTokens/sessions',
            $this->_getUrl()
        );
        $this->_wplogger->info(
            '########## Submitting get Session Href request for direct integration. ##########'
        );
        $params = json_encode($orderParams);
        $sesshrefresponse = $this->_request->getSessionHrefForDirect(
            $url,
            $params,
            $this->worldpayhelper->getXmlUsername(),
            $this->worldpayhelper->getXmlPassword()
        );
        $sessionHref = json_decode($sesshrefresponse, true);
        return $sessionHref['_links']['verifiedTokens:session'];
    }

     /**
      * Send token update XML to Worldpay server
      *
      * @param SavedToken $tokenModel
      * @param \Magento\Customer\Model\Customer $customer
      * @param int $storeId
      * @return mixed
      */
    public function tokenUpdate(
        SavedToken $tokenModel,
        \Magento\Customer\Model\Customer $customer,
        $storeId
    ) {
        $this->_wplogger->info('########## Submitting token update. TokenId: ' . $tokenModel->getId() . ' ##########');
        $requestParameters = [
            'tokenModel'   => $tokenModel,
            'customer'     => $customer,
            'merchantCode' => $this->worldpayhelper->getMerchantCode($tokenModel->getMethod()),
        ];
        /** @var SimpleXMLElement $simpleXml */
        $this->tokenUpdateXml = new \Sapient\AccessWorldpay\Model\XmlBuilder\TokenUpdate($requestParameters);
        $tokenUpdateSimpleXml = $this->tokenUpdateXml->build();

        return $this->_sendRequest(
            dom_import_simplexml($tokenUpdateSimpleXml)->ownerDocument,
            $this->worldpayhelper->getXmlUsername($tokenModel->getMethod()),
            $this->worldpayhelper->getXmlPassword($tokenModel->getMethod())
        );
    }

     /**
      * Send token delete XML to Worldpay server
      *
      * @param SavedToken $tokenModel
      * @param \Magento\Customer\Model\Customer $customer
      * @param int $storeId
      * @return mixed
      */
    public function tokenDelete(
        SavedToken $tokenModel,
        \Magento\Customer\Model\Customer $customer,
        $storeId
    ) {
        $this->_wplogger->info('########## Submitting token Delete. TokenId: ' . $tokenModel->getId() . ' ##########');

        $requestParameters = [
            'tokenModel'   => $tokenModel,
            'customer'     => $customer,
            'merchantCode' => $this->worldpayhelper->getMerchantCode($tokenModel->getMethod()),
        ];

        /** @var SimpleXMLElement $simpleXml */
        $this->tokenDeleteXml = new Sapient\AccessWorldpay\Model\XmlBuilder\TokenDelete($requestParameters);
        $tokenDeleteSimpleXml = $this->tokenDeleteXml->build();

        return $this->_sendRequest(
            dom_import_simplexml($tokenDeleteSimpleXml)->ownerDocument,
            $this->worldpayhelper->getXmlUsername($tokenModel->getMethod()),
            $this->worldpayhelper->getXmlPassword($tokenModel->getMethod())
        );
    }

     /**
      * Send token inquiry XML to Worldpay server
      *
      * @param SavedToken $tokenModel
      * @return mixed
      */
    public function tokenInquiry(
        SavedToken $tokenModel
    ) {
        $this->_wplogger->info('########## Submitting token inquiry. TokenId: ' . $tokenModel->getId() . ' ##########');
        $username = $this->worldpayhelper->getXmlUsername();
        $password = $this->worldpayhelper->getXmlPassword();
        $tokenUrl = $tokenModel->getToken();
        $response = $this->_request->getTokenInquiry($tokenUrl, $username, $password);
        $tokenDetailResponseToArray = json_decode($response, true);
        return $tokenDetailResponseToArray;
    }
     /**
      * Send token inquiry XML to Worldpay server
      *
      * @param string $tokenModelUrl
      * @return mixed
      */
    public function getTokenDelete($tokenModelUrl)
    {
        $this->_wplogger->info('########## Deleting token . ##########');
        $username = $this->worldpayhelper->getXmlUsername();
        $password = $this->worldpayhelper->getXmlPassword();
        $response = $this->_request->getTokenDelete($tokenModelUrl, $username, $password);
        return $response;
    }

    /**
     * Put token expiry
     *
     * @param SavedToken $tokenModel
     * @param string $cardHolderNameUrl
     * @return mixed
     */
    public function putTokenExpiry(SavedToken $tokenModel, $cardHolderNameUrl)
    {
        $this->_wplogger->info('########## Submitting token Expiry request.  ##########');
         $requestConfiguration = [
            'tokenModel' => $tokenModel
         ];
         $this->xmlcapture = new \Sapient\AccessWorldpay\Model\JsonBuilder\TokenExpiryUpdate($requestConfiguration);
         $simpleXml = $this->xmlcapture->build();
         return $this->_request->putRequest(
             $this->worldpayhelper->getXmlUsername(),
             $this->worldpayhelper->getXmlPassword(),
             $cardHolderNameUrl,
             $simpleXml
         );
    }

    /**
     * Put token name
     *
     * @param SavedToken $tokenModel
     * @param string $cardHolderNameUrl
     * @return mixed
     */
    public function putTokenName(SavedToken $tokenModel, $cardHolderNameUrl)
    {
        $this->_wplogger->info('########## Submitting token CardHolderName request.  ##########');
        $requestConfiguration = [
           'tokenModel' => $tokenModel
        ];
        $this->xmlcapture = new \Sapient\AccessWorldpay\Model\JsonBuilder\TokenNameUpdate($requestConfiguration);

        $simpleXml = $this->xmlcapture->build();
        return $this->_request->putRequest(
            $this->worldpayhelper->getXmlUsername(),
            $this->worldpayhelper->getXmlPassword(),
            $cardHolderNameUrl,
            $simpleXml
        );
    }

    /**
     * Resolve Request Token Conflict
     *
     * @param string $username
     * @param string $password
     * @param string $conflictUrl
     * @return mixed
     */
    public function resolveConflict($username, $password, $conflictUrl)
    {
        return $this->_request->resolveConflict($username, $password, $conflictUrl);
    }

    /**
     * Send direct saved card request
     *
     * @param array $directOrderParams
     * @param mixed $requestConfiguration
     * @return mixed
     */
    public function _sendDirectSavedCardRequest($directOrderParams, $requestConfiguration)
    {
        $tokenData = $this->worldpayhelper->getSelectedSavedCardTokenData(
            $directOrderParams['paymentDetails']['tokenId']
        );
        if (!empty($tokenData[0]['cardonfile_auth_link'])) {
            $this->_wplogger->info(
                '########## Submitting direct order card on file authorization request. OrderCode: '
                . $directOrderParams['orderCode'] . ' ##########'
            );
            $directOrderParams['paymentDetails']['cardOnFileAuthorization'] = $tokenData[0]['cardonfile_auth_link'];
            $directOrderParams['paymentDetails']['paymentType'] = 'TOKEN-SSL';
            $cardOnFileAuthArrayResponse = $this->_getDirectCardOnFileAuthorization(
                $directOrderParams,
                $requestConfiguration
            );
            if (isset($cardOnFileAuthArrayResponse['outcome'])
                && $cardOnFileAuthArrayResponse['outcome'] === 'authorized') {
                $xml = $this->_request->_array2xml(
                    $cardOnFileAuthArrayResponse,
                    false,
                    $directOrderParams['orderCode']
                );
                $this->customerSession->setUsedSavedCard(true);
                return $xml;
            } else {
                return $this->_handleFailureCases($cardOnFileAuthArrayResponse);
            }
        } else {
            return $this->_getFirstDirectCardOnFileVerification($directOrderParams, $requestConfiguration);
        }
    }

    /**
     * Get first direct card on file verification
     *
     * @param array $directOrderParams
     * @param mixed $requestConfiguration
     * @return mixed
     */
    public function _getFirstDirectCardOnFileVerification($directOrderParams, $requestConfiguration)
    {
        $this->_wplogger->info(''
                . '########## Submitting direct order card on file verification request. OrderCode: '
                . $directOrderParams['orderCode'] . ' ##########');
        $directOrderParams['paymentDetails']['cardOnfileVerificationCheck'] = true;
        $cardOnFileVerificationResponse = $this->_getDirectCardOnFileVerification(
            $directOrderParams,
            $requestConfiguration
        );
        $cardOnFileArrayResponse = json_decode($cardOnFileVerificationResponse, true);
        if (isset($cardOnFileArrayResponse['outcome']) && $cardOnFileArrayResponse['outcome'] == 'verified') {
            $directOrderParams['paymentDetails']['cardOnFileAuthorization'] =
                    $cardOnFileArrayResponse['_links']['payments:cardOnFileAuthorize']['href'];
            $directOrderParams['paymentDetails']['paymentType'] = 'TOKEN-SSL';
            $this->customerSession->setUsedSavedCard(true);
            $directOrderParams['paymentDetails']['cardOnfileVerificationCheck'] = false;
            return $this->_getFirstDirectAuthorization($directOrderParams, $requestConfiguration);
        } else {
            return $this->_handleFailureCases($cardOnFileArrayResponse);
        }
    }

    /**
     * Get first direct authorization
     *
     * @param array $directOrderParams
     * @param mixed $requestConfiguration
     * @return mixed
     */
    public function _getFirstDirectAuthorization($directOrderParams, $requestConfiguration)
    {
        $this->_wplogger->info(
            '########## Submitting direct order card on file authorization request. OrderCode: '
            . $directOrderParams['orderCode'] . ' ##########'
        );
        $cardOnFileAuthArrayResponse = $this->_getDirectCardOnFileAuthorization(
            $directOrderParams,
            $requestConfiguration
        );
        if (isset($cardOnFileAuthArrayResponse['outcome']) && $cardOnFileAuthArrayResponse['outcome'] == 'authorized') {
            $cardOnFileAuthLink = $cardOnFileAuthArrayResponse['_links']['payments:cardOnFileAuthorize']['href'];
            $this->_wplogger->info('##    Saving card on file auth link to accessworldpay verifiedtoken.............');
            $this->updateAccessWorldpayment->_setCardOnFileAuthorizeLink(
                $directOrderParams['paymentDetails']['tokenId'],
                $cardOnFileAuthLink
            );
            $this->_wplogger->info('##    Saving done ...........................');
            $xml = $this->_request->_array2xml($cardOnFileAuthArrayResponse, false, $directOrderParams['orderCode']);
            return $xml;
        } else {
            return $this->_handleFailureCases($cardOnFileAuthArrayResponse);
        }
    }

    /**
     * Get direct card on file verification
     *
     * @param array $directOrderParams
     * @param mixed $requestConfiguration
     * @return mixed
     */
    public function _getDirectCardOnFileVerification($directOrderParams, $requestConfiguration)
    {
        $this->xmldirectorder = new \Sapient\AccessWorldpay\Model\JsonBuilder\DirectOrder($requestConfiguration);
        $customerId = $this->customerSession->getCustomer()->getId();
        $ordercode = $customerId.'-'.time();
        //$url = 'https://try.access.worldpay.com/verifications/accounts/dynamic/cardOnFile';
        $url = str_replace('/payments/authorizations', '/verifications/accounts/dynamic/cardOnFile', $this->_getUrl());
        $amount = 0;
        $orderSimpleXml = $this->xmldirectorder->build(
            $directOrderParams['merchantCode'],
            $ordercode,
            $directOrderParams['orderDescription'],
            $directOrderParams['currencyCode'],
            $amount,
            $directOrderParams['paymentDetails'],
            $directOrderParams['cardAddress'],
            $directOrderParams['shopperEmail'],
            $directOrderParams['acceptHeader'],
            $directOrderParams['userAgentHeader'],
            $directOrderParams['shippingAddress'],
            $directOrderParams['billingAddress'],
            $directOrderParams['shopperId'],
            $directOrderParams['quoteId'],
            $directOrderParams['threeDSecureConfig']
        );
        $this->_wplogger->info($orderSimpleXml);
        return $this->_request->sendSavedCardCardOnFileVerificationRequest(
            $ordercode,
            $this->worldpayhelper->getXmlUsername(),
            $this->worldpayhelper->getXmlPassword(),
            $url,
            $orderSimpleXml
        );
    }

    /**
     * Get direct card on file authorization
     *
     * @param array $directOrderParams
     * @param mixed $requestConfiguration
     * @return mixed
     */
    public function _getDirectCardOnFileAuthorization($directOrderParams, $requestConfiguration)
    {
        if (!$this->worldpayhelper->is3DSecureEnabled() && $this->worldpayhelper->isExemptionEngineEnable()) {
            $exemptionData = $this->sendExemptionAssesmentRequest($directOrderParams);
            $directOrderParams['paymentDetails']['exemptionResult'] = $exemptionData;
        }

        $this->xmldirectorder = new \Sapient\AccessWorldpay\Model\JsonBuilder\DirectOrder($requestConfiguration);

        $orderSimpleXml = $this->xmldirectorder->build(
            $directOrderParams['merchantCode'],
            $directOrderParams['orderCode'],
            $directOrderParams['orderDescription'],
            $directOrderParams['currencyCode'],
            $directOrderParams['amount'],
            $directOrderParams['paymentDetails'],
            $directOrderParams['cardAddress'],
            $directOrderParams['shopperEmail'],
            $directOrderParams['acceptHeader'],
            $directOrderParams['userAgentHeader'],
            $directOrderParams['shippingAddress'],
            $directOrderParams['billingAddress'],
            $directOrderParams['shopperId'],
            $directOrderParams['quoteId'],
            $directOrderParams['threeDSecureConfig']
        );
        $this->_wplogger->info($orderSimpleXml);
        return $this->_request->savedCardSendRequest(
            $directOrderParams['orderCode'],
            $this->worldpayhelper->getXmlUsername(),
            $this->worldpayhelper->getXmlPassword(),
            $directOrderParams['paymentDetails']['cardOnFileAuthorization'],
            $orderSimpleXml
        );
    }

    /**
     * Get websdk card on file verification
     *
     * @param array $directOrderParams
     * @return mixed
     */
    public function _getWebSdkCardOnFileVerification($directOrderParams)
    {
        if (!isset($directOrderParams['threeDSecureConfig'])) {
            $directOrderParams['threeDSecureConfig'] = '';
        }
        $this->_wplogger->info(
            '########## getWebSdkCardOnFileVerification. OrderCode: '
            . $directOrderParams['orderCode'] . ' ##########'
        );
        $requestConfiguration = [
            'threeDSecureConfig' => $directOrderParams['threeDSecureConfig']
        ];
        $this->xmldirectorder = new \Sapient\AccessWorldpay\Model\JsonBuilder\WebSdkOrder();
        $customerId = $this->customerSession->getCustomer()->getId();
        $ordercode = $customerId.'-'.time();
        //$url = 'https://try.access.worldpay.com/verifications/accounts/dynamic/cardOnFile';
        $url = str_replace('/payments/authorizations', '/verifications/accounts/dynamic/cardOnFile', $this->_getUrl());
        $amount = 0;
        $orderSimpleXml = $this->xmldirectorder->build(
            $directOrderParams['merchantCode'],
            $ordercode,
            $directOrderParams['orderDescription'],
            $directOrderParams['currencyCode'],
            $amount,
            $directOrderParams['paymentDetails'],
            $directOrderParams['cardAddress'],
            $directOrderParams['shopperEmail'],
            $directOrderParams['acceptHeader'],
            $directOrderParams['userAgentHeader'],
            $directOrderParams['shippingAddress'],
            $directOrderParams['billingAddress'],
            $directOrderParams['shopperId'],
            $directOrderParams['quoteId'],
            $directOrderParams['threeDSecureConfig']
        );
        $this->_wplogger->info($orderSimpleXml);
        return $this->_request->sendSavedCardCardOnFileVerificationRequest(
            $ordercode,
            $this->worldpayhelper->getXmlUsername(),
            $this->worldpayhelper->getXmlPassword(),
            $url,
            $orderSimpleXml
        );
    }

    /**
     * Get web sdk card on file authorization
     *
     * @param array $directOrderParams
     * @return mixed
     */
    public function _getWebSdkCardOnFileAuthorization($directOrderParams)
    {
        if (!$this->worldpayhelper->is3DSecureEnabled() && $this->worldpayhelper->isExemptionEngineEnable()) {
            $exemptionData = $this->sendExemptionAssesmentRequest($directOrderParams);
            $directOrderParams['paymentDetails']['exemptionResult'] = $exemptionData;
        }

        if (!isset($directOrderParams['threeDSecureConfig'])) {
            $directOrderParams['threeDSecureConfig'] = '';
        }
        $this->xmldirectorder = new \Sapient\AccessWorldpay\Model\JsonBuilder\WebSdkOrder();

        $orderSimpleXml = $this->xmldirectorder->build(
            $directOrderParams['merchantCode'],
            $directOrderParams['orderCode'],
            $directOrderParams['orderDescription'],
            $directOrderParams['currencyCode'],
            $directOrderParams['amount'],
            $directOrderParams['paymentDetails'],
            $directOrderParams['cardAddress'],
            $directOrderParams['shopperEmail'],
            $directOrderParams['acceptHeader'],
            $directOrderParams['userAgentHeader'],
            $directOrderParams['shippingAddress'],
            $directOrderParams['billingAddress'],
            $directOrderParams['shopperId'],
            $directOrderParams['quoteId'],
            $directOrderParams['threeDSecureConfig']
        );
        $this->_wplogger->info($orderSimpleXml);
        return $this->_request->savedCardSendRequest(
            $directOrderParams['orderCode'],
            $this->worldpayhelper->getXmlUsername(),
            $this->worldpayhelper->getXmlPassword(),
            $directOrderParams['paymentDetails']['cardOnFileAuthorization'],
            $orderSimpleXml
        );
    }

    /**
     * Handle failure cases
     *
     * @param array $errorResponse
     */
    public function _handleFailureCases($errorResponse)
    {
        $message = $this->worldpayhelper->getCreditCardSpecificException('CCAM18');
        if (isset($errorResponse['errorName']) && isset($errorResponse['message'])) {
            if ($errorResponse['errorName'] === 'maximumUpdatesExceeded') {
                $message = $this->worldpayhelper->getCreditCardSpecificException('CCAM14') ;
            } elseif (preg_match('#Unable to locate token#', $errorResponse['message']) ||
                    preg_match('#Requested token does not exist#', $errorResponse['message'])) {
                $message = $this->worldpayhelper->getCreditCardSpecificException('CCAM9');
            } else {
                $message = $errorResponse['message'];
            }
        }
        $this->_wplogger->error($message);
        throw new \Magento\Framework\Exception\LocalizedException(__($message));
    }

    /**
     * Send websdk saved card request
     *
     * @param array $directOrderParams
     * @return mixed
     */
    public function _sendWebSdkSavedCardRequest($directOrderParams)
    {
        $tokenData = $this->worldpayhelper->getSelectedSavedCardTokenData(
            $directOrderParams['paymentDetails']['tokenId']
        );
        if (!empty($tokenData[0]['cardonfile_auth_link'])) {
            $this->_wplogger->info(
                '########## Submitting websdk order card on file authorization request. OrderCode: '
                . $directOrderParams['orderCode'] . ' ##########'
            );
            $directOrderParams['paymentDetails']['cardOnFileAuthorization'] = $tokenData[0]['cardonfile_auth_link'];
            $cardOnFileAuthArrayResponse = $this->_getWebSdkCardOnFileAuthorization(
                $directOrderParams
            );
            if (isset($cardOnFileAuthArrayResponse['outcome'])
                && $cardOnFileAuthArrayResponse['outcome'] === 'authorized') {
                $xml = $this->_request->_array2xml(
                    $cardOnFileAuthArrayResponse,
                    false,
                    $directOrderParams['orderCode']
                );
                $this->customerSession->setUsedSavedCard(true);
                return $xml;
            } else {
                return $this->_handleFailureCases($cardOnFileAuthArrayResponse);
            }
        } else {
            return $this->_getFirstWebSdkCardOnFileVerification($directOrderParams);
        }
    }

    /**
     * Get first websdk card on file verification
     *
     * @param array $directOrderParams
     * @return mixed
     */
    public function _getFirstWebSdkCardOnFileVerification($directOrderParams)
    {
        $this->_wplogger->info(
            '########## Submitting websdk card on file verification request. OrderCode: '
            . $directOrderParams['orderCode'] . ' ##########'
        );
        $directOrderParams['paymentDetails']['cardOnfileVerificationCheck'] = true;
        $cardOnFileVerificationResponse = $this->_getWebSdkCardOnFileVerification($directOrderParams);
        $cardOnFileArrayResponse = json_decode($cardOnFileVerificationResponse, true);
        if (isset($cardOnFileArrayResponse['outcome']) && $cardOnFileArrayResponse['outcome'] == 'verified') {
            $directOrderParams['paymentDetails']['cardOnFileAuthorization'] =
                    $cardOnFileArrayResponse['_links']['payments:cardOnFileAuthorize']['href'];
            $this->customerSession->setUsedSavedCard(true);
            $directOrderParams['paymentDetails']['cardOnfileVerificationCheck'] = false;
            return $this->_getFirstWebSdkAuthorization($directOrderParams);
        } else {
            return $this->_handleFailureCases($cardOnFileArrayResponse);
        }
    }

    /**
     * Get first websdk authorization
     *
     * @param array $directOrderParams
     * @return mixed
     */
    public function _getFirstWebSdkAuthorization($directOrderParams)
    {
        $this->_wplogger->info(
            '########## Submitting websdk card on file authorization request. OrderCode: '
            . $directOrderParams['orderCode'] . ' ##########'
        );
        $cardOnFileAuthArrayResponse = $this->_getWebSdkCardOnFileAuthorization($directOrderParams);
        if (isset($cardOnFileAuthArrayResponse['outcome']) && $cardOnFileAuthArrayResponse['outcome'] == 'authorized') {
            $cardOnFileAuthLink = $cardOnFileAuthArrayResponse['_links']['payments:cardOnFileAuthorize']['href'];
            $this->_wplogger->info('##    Saving card on file auth link to accessworldpay verifiedtoken.............');
            $this->updateAccessWorldpayment->_setCardOnFileAuthorizeLink(
                $directOrderParams['paymentDetails']['tokenId'],
                $cardOnFileAuthLink
            );
            $this->_wplogger->info('##    Saving done ...........................');
            $xml = $this->_request->_array2xml($cardOnFileAuthArrayResponse, false, $directOrderParams['orderCode']);
            return $xml;
        } else {
            return $this->_handleFailureCases($cardOnFileAuthArrayResponse);
        }
    }

    /**
     * Send wallet order XML to Worldpay server
     *
     * @param array $walletOrderParams
     * @return mixed
     */
    public function walletsOrder($walletOrderParams)
    {
        $loggerMsg = '########## Submitting wallet order request. OrderCode: ';
        $this->_wplogger->info($loggerMsg . $walletOrderParams['orderCode'] . ' ##########');
        $walletOrderParams['paymentDetails']['entityRef']= $this->worldpayhelper->getMerchantEntityReference();
        $walletOrderParams['paymentDetails']['narrative']= $this->worldpayhelper->getNarrative();
        $this->jsonredirectorder = new \Sapient\AccessWorldpay\Model\JsonBuilder\WalletOrder();
            $walletSimpleJson = $this->jsonredirectorder->build(
                $walletOrderParams['merchantCode'],
                $walletOrderParams['orderCode'],
                $walletOrderParams['orderDescription'],
                $walletOrderParams['currencyCode'],
                $walletOrderParams['amount'],
                $walletOrderParams['paymentType'],
                $walletOrderParams['shopperEmail'],
                $walletOrderParams['acceptHeader'],
                $walletOrderParams['userAgentHeader'],
                $walletOrderParams['protocolVersion'],
                $walletOrderParams['signature'],
                $walletOrderParams['signedMessage'],
                $walletOrderParams['shippingAddress'],
                $walletOrderParams['billingAddress'],
                $walletOrderParams['cusDetails'],
                $walletOrderParams['shopperIpAddress'],
                $walletOrderParams['paymentDetails']
            );
        $this->_wplogger->info('Sending Request To Googlepay');
        $this->_wplogger->info($walletSimpleJson);
        return $this->_sendGoogleRequest(
            $walletOrderParams['orderCode'],
            $this->worldpayhelper->getXmlUsername(),
            $this->worldpayhelper->getXmlPassword(),
            $this->_getUrl(),
            $walletSimpleJson
        );
    }

    /**
     * Process the request
     *
     * @param string $orderCode
     * @param string $username
     * @param string $password
     * @param string $url
     * @param SimpleXmlElement $xml
     * @return SimpleXmlElement $response
     */
    protected function _sendGoogleRequest($orderCode, $username, $password, $url, $xml)
    {
        $response = $this->_request->sendGooglePayRequest(
            $orderCode,
            $username,
            $password,
            $url,
            $xml
        );

        return $response;
    }

    /**
     * Event inquiry
     *
     * @param string $orderid
     * @param string|null $url
     * @return SimpleXmlElement $response
     */
    public function eventInquiry($orderid, $url = null)
    {
        if ($this->worldpayhelper->isWorldPayEnable()) {
            try {
                $collectionData = $this->omsCollectionFactory->create()
                        ->addFieldToSelect(['awp_order_code','awp_events_param'])
                        ->addFieldToFilter('order_increment_id', ['eq' => $orderid ]);
                    $collectionData = $collectionData->getData();
                if ($collectionData) {
                    $eventUrl = $collectionData[0]['awp_events_param'];
                } else {
                    throw new \Magento\Framework\Exception\LocalizedException(
                        __('No available event link found to synchronize the status')
                    );
                }
                $url = !empty($url) ? $url : $eventUrl;
                $orderCode = $collectionData[0]['awp_order_code'];
                $paymentType = $this->worldpayhelper->getOrderPaymentType($orderCode);

                $this->_wplogger->info(
                    '########## Submitting events request. Order: '
                    . $orderCode . ' ##########'
                );

                $xml = $this->_request->sendEventRequest(
                    $orderCode,
                    $this->worldpayhelper->getXmlUsername(),
                    $this->worldpayhelper->getXmlPassword(),
                    $url,
                    $paymentType
                );
                return $xml;
            } catch (Exception $e) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __($e->getMessage())
                );
            }
        }
        throw new \Magento\Framework\Exception\LocalizedException(
            __('Access Worldpay Service Not Available')
        );
    }

    /**
     * Ach order
     *
     * @param array $directOrderParams
     * @return mixed
     */
    public function achOrder($directOrderParams)
    {
        $this->_wplogger->info("Inside PaymentServiceRequest.php : achOrder() .....................");
        $loggerMsg = '########## Submitting achOrder request. OrderCode: ';
        $this->_wplogger->info($loggerMsg . $directOrderParams['orderCode'] . ' ##########');
        $url = str_replace(
            '/payments/authorizations',
            '/payments/alternative/direct/sale',
            $this->_getUrl()
        );
        $this->jsonredirectorder = new \Sapient\AccessWorldpay\Model\JsonBuilder\ACHOrder();
        $achSimpleJson = $this->jsonredirectorder->build(
            $directOrderParams['orderCode'],
            $directOrderParams['merchantCode'],
            $directOrderParams['orderDescription'],
            $directOrderParams['currencyCode'],
            $directOrderParams['amount'],
            $directOrderParams['paymentDetails'],
            $directOrderParams['shopperEmail'],
            $directOrderParams['acceptHeader'],
            $directOrderParams['userAgentHeader'],
            $directOrderParams['shippingAddress'],
            $directOrderParams['billingAddress'],
            $directOrderParams['shopperId'],
            $directOrderParams['quoteId'],
            $directOrderParams['statementNarrative']
        );
        $this->_wplogger->info("Sending request to AchOrder");
        $this->_wplogger->info($achSimpleJson);
        return $this->_request->sendACHOrderRequest(
            $directOrderParams['orderCode'],
            $this->worldpayhelper->getXmlUsername(),
            $this->worldpayhelper->getXmlPassword(),
            $url,
            $achSimpleJson
        );
    }

    /**
     * Payment reversal
     *
     * @param string $orderid
     * @throw Exception
     */
    public function paymentReversal($orderid)
    {
        if ($this->worldpayhelper->isWorldPayEnable()) {
            try {
                $collectionData = $this->omsCollectionFactory->create()
                        ->addFieldToSelect(['awp_order_code','awp_reversal_param'])
                        ->addFieldToFilter('order_increment_id', ['eq' => $orderid ]);
                $collectionData = $collectionData->getData();
                if ($collectionData) {
                    $reversalUrl = $collectionData[0]['awp_reversal_param'];
                } else {
                    throw new \Magento\Framework\Exception\LocalizedException(
                        __('No available reversal link found for payment reversal.')
                    );
                }

                $orderCode = $collectionData[0]['awp_order_code'];

                $this->_wplogger->info(
                    '########## Submitting reversal request. Order: '
                    . $orderCode . ' ##########'
                );

                $xml = $this->_request->sendReversalRequest(
                    $orderCode,
                    $this->worldpayhelper->getXmlUsername(),
                    $this->worldpayhelper->getXmlPassword(),
                    $reversalUrl,
                    null
                );
                return $xml;
            } catch (Exception $e) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __($e->getMessage())
                );
            }
        }
        throw new \Magento\Framework\Exception\LocalizedException(
            __('Access Worldpay Service Not Available')
        );
    }
}
