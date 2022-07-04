<?php
/**
 * @copyright 2017 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Token;

use Sapient\AccessWorldpay\Model\SavedToken;

/**
 * Communicate with WP server and gives back meaningful answer object
 */
class Service
{

    /**
     * @var Sapient\WorldPay\Model\Request\PaymentServiceRequest
     */
    protected $_paymentServiceRequest;

    /**
     * Constructor
     *
     * @param \Sapient\Worldpay\Model\Payment\Update\Factory $paymentupdatefactory
     * @param \Sapient\Worldpay\Model\Request\PaymentServiceRequest $paymentservicerequest
     * @param \Sapient\Worldpay\Model\AccessWorldpayment $worldpayPayment
     * @param \Sapient\Worldpay\Logger\AccessWorldpayLogger $wplogger
     */
    public function __construct(
        \Sapient\AccessWorldpay\Model\Payment\Update\Factory $paymentupdatefactory,
        \Sapient\AccessWorldpay\Model\Request\PaymentServiceRequest $paymentservicerequest,
        \Sapient\AccessWorldpay\Model\AccessWorldpayment $worldpayPayment,
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger
    ) {
        $this->_wplogger = $wplogger;
        $this->paymentupdatefactory = $paymentupdatefactory;
        $this->_paymentServiceRequest = $paymentservicerequest;
        $this->worldpayPayment = $worldpayPayment;
    }

    /**
     * Send token update request to WP server and gives back the answer
     *
     * @param Sapient\AccessWorldpay\Model\Token $tokenModel
     * @param \Magento\Customer\Model\Customer $customer
     * @param $storeId
     * @return Sapient\AccessWorldpay\Model\Token\UpdateXml
     */
//    public function getTokenUpdate(
//        SavedToken $tokenModel,
//        \Magento\Customer\Model\Customer $customer,
//        $storeId
//    ) {
//        $rawXml = $this->_paymentServiceRequest->tokenUpdate($tokenModel, $customer, $storeId);
//        $xml = simplexml_load_string($rawXml);
//        return new UpdateXml($xml);
//    }

    /**
     * Send token delete request to WP server and gives back the answer
     *
     * @param Sapient\Worldpay\Model\Token $tokenModel
     * @param \Magento\Customer\Model\Customer $customer
     * @param $storeId
     * @return Sapient\Worldpay\Model\Token\DeleteXml
     */
//    public function getTokenDelete(
//        SavedToken $tokenModel,
//        \Magento\Customer\Model\Customer $customer,
//        $storeId
//    ) {
//        $rawXml = $this->_paymentServiceRequest->tokenDelete($tokenModel, $customer, $storeId);
//        $xml = simplexml_load_string($rawXml);
//        return new DeleteXml($xml);
//    }
    
    /**
     * Send token inquiry request to WP server and gives back the answer
     *
     * @param SavedToken $tokenModel
     * @return Sapient\Worldpay\Model\Token\InquiryXml
     */
    public function getTokenInquiry(
        SavedToken $tokenModel
    ) {
        return $this->_paymentServiceRequest->tokenInquiry($tokenModel);
    }
     /**
      * Send token inquiry request to WP server and gives back the answer
      *
      * @param mixed $tokenModelUrl
      * @return Sapient\Worldpay\Model\Token\InquiryXml
      */
    public function getTokenDelete(
        $tokenModelUrl
    ) {
        return $this->_paymentServiceRequest->getTokenDelete($tokenModelUrl);
    }
     /**
      * Send token inquiry request to WP server and gives back the answer
      *
      * @param SavedToken $tokenModel
      * @param string $cardHolderNameUrl
      * @return Sapient\Worldpay\Model\Token\InquiryXml
      */
    public function putTokenExpiry(
        SavedToken $tokenModel,
        $cardHolderNameUrl
    ) {
        return $this->_paymentServiceRequest->putTokenExpiry($tokenModel, $cardHolderNameUrl);
    }
     /**
      * Send token inquiry request to WP server and gives back the answer
      *
      * @param SavedToken $tokenModel
      * @param string $cardHolderNameUrl
      * @return Sapient\Worldpay\Model\Token\InquiryXml
      */
    public function putTokenName(
        SavedToken $tokenModel,
        $cardHolderNameUrl
    ) {
        return $this->_paymentServiceRequest->putTokenName($tokenModel, $cardHolderNameUrl);
    }
}
