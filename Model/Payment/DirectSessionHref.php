<?php
namespace Sapient\AccessWorldpay\Model\Payment;

use Sapient\AccessWorldpay\Api\DirectSessionHrefInterface;

class DirectSessionHref implements DirectSessionHrefInterface
{
    /**
     * Constructor
     *
     * @param \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger         $wplogger
     * @param \Sapient\AccessWorldpay\Helper\Data                         $worldpayHelper
     * @param \Sapient\AccessWorldpay\Model\Request\PaymentServiceRequest $paymentservicerequest
     * @param \Sapient\AccessWorldpay\Model\Payment\Service               $paymentservice
     * @param \Magento\Checkout\Model\Session                             $checkoutSession
     * @param \Magento\Customer\Model\Session                             $customerSession
     * @param \Magento\Quote\Model\Quote                                  $quote
     */
    public function __construct(
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger,
        \Sapient\AccessWorldpay\Helper\Data $worldpayHelper,
        \Sapient\AccessWorldpay\Model\Request\PaymentServiceRequest $paymentservicerequest,
        \Sapient\AccessWorldpay\Model\Payment\Service $paymentservice,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Quote\Model\Quote $quote
    ) {
        $this->wplogger = $wplogger;
        $this->worldpayHelper = $worldpayHelper;
        $this->_paymentservicerequest = $paymentservicerequest;
        $this->paymentservice = $paymentservice;
        $this->checkoutSession = $checkoutSession;
        $this->quote = $quote;
        $this->customerSession = $customerSession;
    }

    /**
     * Create Session Href
     *
     * @param int $id
     * @param array $paymentData
     * @return string
     */
    public function createSessionHref($id, $paymentData)
    {
         $aditionalData = $paymentData['additional_data'];
               $payment = [
                   'cardNumber' => $aditionalData['cc_number'],
                   'paymentType' => $aditionalData['cc_type'],
                   'cardHolderName' => $aditionalData['cc_name'],
                   'expiryMonth' => $aditionalData['cc_exp_month'],
                   'expiryYear' => $aditionalData['cc_exp_year'],
                   'cvc' => $aditionalData['cc_cid'],
               ];
               $orderParams = [];
               $orderParams['identity'] = $id;
               $orderParams['cardExpiryDate'] = ["month" =>$payment['expiryMonth'],"year"=>$payment['expiryYear']];
            //cvc disabled
               if (isset($payment['cvc']) && !$payment['cvc'] == '') {
                   $orderParams['cvc'] = $payment['cvc'];
               }
               $orderParams['cardNumber'] = $payment['cardNumber'];
               $sessionHref = $this->_paymentservicerequest->createSessionHrefForDirect($orderParams);
               return $sessionHref;
    }
}
