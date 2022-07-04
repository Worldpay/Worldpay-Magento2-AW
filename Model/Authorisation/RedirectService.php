<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Authorisation;

use Exception;

class RedirectService extends \Magento\Framework\DataObject
{
   
    /** @var \Sapient\AccessWorldpay\Model\Response\RedirectResponse */
    protected $_redirectResponseModel;

    /**
     * Constructor
     * @param \Sapient\AccessWorldpay\Model\Mapping\Service $mappingservice
     * @param \Sapient\AccessWorldpay\Model\Request\PaymentServiceRequest $paymentservicerequest
     * @param \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger
     * @param \Sapient\AccessWorldpay\Model\Payment\Service $paymentservice
     * @param \Sapient\AccessWorldpay\Model\Response\RedirectResponse $redirectresponse
     * @param \Sapient\AccessWorldpay\Helper\Registry $registryhelper
     * @param \Magento\Checkout\Model\Session $checkoutsession
     * @param \Sapient\AccessWorldpay\Model\Utilities\PaymentMethods $paymentlist
     * @param \Sapient\AccessWorldpay\Helper\Data $worldpayhelper
     */
    public function __construct(
        \Sapient\AccessWorldpay\Model\Mapping\Service $mappingservice,
        \Sapient\AccessWorldpay\Model\Request\PaymentServiceRequest $paymentservicerequest,
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger,
        \Sapient\AccessWorldpay\Model\Payment\Service $paymentservice,
        \Sapient\AccessWorldpay\Model\Response\RedirectResponse $redirectresponse,
        \Sapient\AccessWorldpay\Helper\Registry $registryhelper,
        \Magento\Checkout\Model\Session $checkoutsession,
        \Sapient\AccessWorldpay\Model\Utilities\PaymentMethods $paymentlist,
        \Sapient\AccessWorldpay\Helper\Data $worldpayhelper
    ) {
        $this->mappingservice = $mappingservice;
        $this->paymentservicerequest = $paymentservicerequest;
        $this->wplogger = $wplogger;
        $this->paymentservice = $paymentservice;
        $this->redirectresponse = $redirectresponse;
        $this->registryhelper = $registryhelper;
        $this->checkoutsession = $checkoutsession;
        $this->paymentlist = $paymentlist;
        $this->worldpayhelper = $worldpayhelper;
    }
    /**
     * Handles provides authorization data for redirect
     *
     * It initiates a  XML request to WorldPay and registers worldpayRedirectUrl
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
        $this->checkoutsession->setauthenticatedOrderId($mageOrder->getIncrementId());
        if ($paymentDetails['additional_data']['cc_type'] == 'KlARNA-SSL') {
             $redirectOrderParams = $this->mappingservice->collectKlarnaOrderParameters(
                 $orderCode,
                 $quote,
                 $orderStoreId,
                 $paymentDetails
             );

            $response = $this->paymentservicerequest->redirectKlarnaOrder($redirectOrderParams);
        } elseif (!empty($paymentDetails['additional_data']['cc_bank'])
                  && $paymentDetails['additional_data']['cc_type'] == 'IDEAL-SSL') {
               $callbackurl = $this->redirectresponse->getCallBackUrl();
               $redirectOrderParams = $this->mappingservice->collectRedirectOrderParameters(
                   $orderCode,
                   $quote,
                   $orderStoreId,
                   $paymentDetails
               );
               $redirectOrderParams['cc_bank'] = $paymentDetails['additional_data']['cc_bank'];
               $redirectOrderParams['callbackurl'] = $callbackurl;

            $response = $this->paymentservicerequest->DirectIdealOrder($redirectOrderParams);
        } else {
            $redirectOrderParams = $this->mappingservice->collectRedirectOrderParameters(
                $orderCode,
                $quote,
                $orderStoreId,
                $paymentDetails
            );

            $response = $this->paymentservicerequest->redirectOrder($redirectOrderParams);
        }
        $successUrl = $this->_buildRedirectUrl(
            $this->_getRedirectResponseModel()->getRedirectLocation($response),
            $redirectOrderParams['paymentType'],
            $this->_getCountryForQuote($quote),
            $this->_getLanguageForLocale()
        );

        $payment->setIsTransactionPending(1);
        
        $this->registryhelper->setworldpayRedirectUrl($successUrl);
        $this->checkoutsession->setWpRedirecturl($successUrl);
    }

    /**
     * Build redirect url
     *
     * @param string $redirect
     * @param string $paymentType
     * @param string $countryCode
     * @param string $languageCode
     * @return string
     */
    private function _buildRedirectUrl($redirect, $paymentType, $countryCode, $languageCode)
    {
        $redirect .= '&preferredPaymentMethod=' . $paymentType;
        $redirect .= '&country=' . $countryCode;
        $redirect .= '&language=' . $languageCode;

        return $redirect;
    }

    /**
     * Get billing Country
     *
     * @param Quote $quote
     * @return string
     */
    private function _getCountryForQuote($quote)
    {
        $address = $quote->getBillingAddress();
        if ($address->getId()) {
            return $address->getCountry();
        }
        return $this->worldpayhelper->getDefaultCountry();
    }

    /**
     * Get local language code
     *
     * @return string
     */
    protected function _getLanguageForLocale()
    {
        $locale = $this->worldpayhelper->getLocaleDefault();
        if (substr($locale, 3, 2) == 'NO') {
            return 'no';
        }
        return substr($locale, 0, 2);
    }
    
    /**
     * Get redirect response model
     *
     * @return \Sapient\AccessWorldpay\Model\Response\RedirectResponse
     */
    protected function _getRedirectResponseModel()
    {
        if ($this->_redirectResponseModel === null) {
            $this->_redirectResponseModel = $this->redirectresponse;
        }
        return $this->_redirectResponseModel;
    }
}
