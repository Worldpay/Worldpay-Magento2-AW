<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Authorisation;

use Exception;

class HostedPaymentPageService extends \Magento\Framework\DataObject
{
   
    /** @var  \Sapient\AccessWorldpay\Model\Checkout\Hpp\State */
    protected $_status;
    /** @var  \Sapient\AccessWorldpay\Model\Response\RedirectResponse */
    protected $_redirectResponseModel;

    /**
     * Constructor
     * @param \Sapient\AccessWorldpay\Model\Mapping\Service $mappingservice
     * @param \Sapient\AccessWorldpay\Model\Request\PaymentServiceRequest $paymentservicerequest
     * @param \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger
     * @param \Sapient\AccessWorldpay\Model\Response\RedirectResponse $redirectresponse
     * @param \Sapient\AccessWorldpay\Helper\Registry $registryhelper
     * @param \Sapient\AccessWorldpay\Model\Checkout\Hpp\State $hppstate
     * @param \Magento\Checkout\Model\Session $checkoutsession
     * @param \Magento\Framework\UrlInterface $urlInterface
     */
    public function __construct(
        \Sapient\AccessWorldpay\Model\Mapping\Service $mappingservice,
        \Sapient\AccessWorldpay\Model\Request\PaymentServiceRequest $paymentservicerequest,
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger,
        \Sapient\AccessWorldpay\Model\Response\RedirectResponse $redirectresponse,
        \Sapient\AccessWorldpay\Helper\Registry $registryhelper,
        \Sapient\AccessWorldpay\Model\Checkout\Hpp\State $hppstate,
        \Magento\Checkout\Model\Session $checkoutsession,
        \Magento\Framework\UrlInterface $urlInterface
    ) {
        $this->mappingservice = $mappingservice;
        $this->paymentservicerequest = $paymentservicerequest;
        $this->wplogger = $wplogger;
        $this->redirectresponse = $redirectresponse;
        $this->registryhelper = $registryhelper;
        $this->checkoutsession = $checkoutsession;
        $this->hppstate = $hppstate;
        $this->_urlInterface = $urlInterface;
    }
    /**
     * Handles provides authorization data for Hosted Payment Page integration
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

        $redirectOrderParams = $this->mappingservice->collectRedirectOrderParameters(
            $orderCode,
            $quote,
            $orderStoreId,
            $paymentDetails
        );

        $response = $this->paymentservicerequest->redirectOrder($redirectOrderParams);
       
        $this->_getStatus()
            ->reset()
            ->init($this->_getRedirectResponseModel()->getRedirectUrl($response));

        $payment->setIsTransactionPending(1);
        $this->registryhelper->setworldpayRedirectUrl($this->_urlInterface->getUrl('worldpay/hostedpaymentpage/pay'));

        $this->checkoutsession->setWpRedirecturl($this->_urlInterface->getUrl('worldpay/hostedpaymentpage/pay'));
    }

    /**
     * Get redirect response model
     *
     * @return  \Sapient\AccessWorldpay\Model\Response\RedirectResponse
     */
    protected function _getRedirectResponseModel()
    {
        if ($this->_redirectResponseModel === null) {
            $this->_redirectResponseModel = $this->redirectresponse;
        }

        return $this->_redirectResponseModel;
    }

    /**
     * Get hosted payment page status
     *
     * @return  \Sapient\AccessWorldpay\Model\Checkout\Hpp\State
     */
    protected function _getStatus()
    {
        if ($this->_status === null) {
            $this->_status = $this->hppstate;
        }

        return $this->_status;
    }
}
