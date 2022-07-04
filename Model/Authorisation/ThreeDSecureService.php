<?php

namespace Sapient\AccessWorldpay\Model\Authorisation;

use Exception;

class ThreeDSecureService extends \Magento\Framework\DataObject
{
    public const CART_URL = 'checkout/cart';
    
    /**
     * ThreeDSecureService constructor
     *
     * @param \Sapient\AccessWorldpay\Model\Request\PaymentServiceRequest $paymentservicerequest
     * @param \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger
     * @param \Sapient\AccessWorldpay\Model\Response\DirectResponse $directResponse
     * @param \Sapient\AccessWorldpay\Model\Payment\Service $paymentservice
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Sapient\AccessWorldpay\Model\Order\Service $orderservice
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     * @param \Sapient\AccessWorldpay\Model\Payment\UpdateAccessWorldpaymentFactory $updateWorldPayPayment
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Sapient\AccessWorldpay\Helper\Data $worldpayHelper
     */
    public function __construct(
        \Sapient\AccessWorldpay\Model\Request\PaymentServiceRequest $paymentservicerequest,
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger,
        \Sapient\AccessWorldpay\Model\Response\DirectResponse $directResponse,
        \Sapient\AccessWorldpay\Model\Payment\Service $paymentservice,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Framework\UrlInterface $urlBuilder,
        \Sapient\AccessWorldpay\Model\Order\Service $orderservice,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Sapient\AccessWorldpay\Model\Payment\UpdateAccessWorldpaymentFactory $updateWorldPayPayment,
        \Magento\Customer\Model\Session $customerSession,
        //\Sapient\AccessWorldpay\Model\Token\WorldpayToken $worldpaytoken,
        \Sapient\AccessWorldpay\Helper\Data $worldpayHelper
    ) {
        $this->paymentservicerequest = $paymentservicerequest;
        $this->wplogger = $wplogger;
        $this->directResponse = $directResponse;
        $this->paymentservice = $paymentservice;
        $this->checkoutSession = $checkoutSession;
        $this->urlBuilders    = $urlBuilder;
        $this->orderservice = $orderservice;
        $this->_messageManager = $messageManager;
        $this->updateWorldPayPayment = $updateWorldPayPayment;
        $this->customerSession = $customerSession;
        //$this->worldpaytoken = $worldpaytoken;
        $this->worldpayHelper = $worldpayHelper;
    }
    
    /**
     * Authenticate 3D data
     *
     * @param string $authenticationurl
     * @param array $directOrderParams
     * @return array
     */
    public function authenticate3Ddata($authenticationurl, $directOrderParams)
    {
        $response = $this->paymentservicerequest->authenticate3Ddata($authenticationurl, $directOrderParams);
        $this->checkoutSession->set3DschallengeData($response);
        return $response;
    }
}
