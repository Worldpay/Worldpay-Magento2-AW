<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Controller\Redirectresult;

use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Action\Context;
use Sapient\AccessWorldpay\Model\Payment\StateResponse as PaymentStateResponse;

/**
 * if got notification to get cancel order from worldpay then redirect to  cart page and display the notice
 */

class Cancel extends \Magento\Framework\App\Action\Action
{
    /**
     * @var Magento\Framework\View\Result\PageFactory
     */
    protected $pageFactory;

    /**
     * Constructor
     *
     * @param Context $context
     * @param PageFactory $pageFactory
     * @param \Sapient\AccessWorldpay\Model\Order\Service $orderservice
     * @param \Sapient\AccessWorldpay\Model\Checkout\Service $checkoutservice
     * @param \Sapient\AccessWorldpay\Model\Payment\Service $paymentservice
     * @param \Sapient\AccessWorldpay\Model\Request\AuthenticationService $authenticatinservice
     * @param \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger
     */
    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        \Sapient\AccessWorldpay\Model\Order\Service $orderservice,
        \Sapient\AccessWorldpay\Model\Checkout\Service $checkoutservice,
        \Sapient\AccessWorldpay\Model\Payment\Service $paymentservice,
        \Sapient\AccessWorldpay\Model\Request\AuthenticationService $authenticatinservice,
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger
    ) {
        $this->pageFactory = $pageFactory;
        $this->orderservice = $orderservice;
        $this->wplogger = $wplogger;
        $this->checkoutservice = $checkoutservice;
        $this->paymentservice = $paymentservice;
        $this->authenticatinservice = $authenticatinservice;
        return parent::__construct($context);
    }

    /**
     * Execute
     */
    public function execute()
    {

        $this->wplogger->info('worldpay returned cancel url');
        if (!$this->orderservice->getAuthorisedOrder()) {
            return $this->resultRedirectFactory->create()->setPath('checkout/cart', ['_current' => true]);
        }
        $order = $this->orderservice->getAuthorisedOrder();
        $magentoorder = $order->getOrder();
        $notice = $this->_getCancellationNoticeForOrder($magentoorder);
        $this->messageManager->addNotice($notice);
        $params = $this->getRequest()->getParams();
        if ($this->authenticatinservice->requestAuthenticated($params)) {
            if (isset($params['orderKey'])) {
                $this->_applyPaymentUpdate(PaymentStateResponse::createFromCancelledResponse($params), $order);
            }
        }
        return $this->resultRedirectFactory->create()->setPath('checkout/cart', ['_current' => true]);
    }

    /**
     * GetCancellationNoticeForOrder
     *
     * @param string $order
     */
    private function _getCancellationNoticeForOrder($order)
    {

        $incrementId = $order->getIncrementId();

        $message = $incrementId === null
            ? __('Order Cancelled')
            : __('Order #'. $incrementId.' Cancelled');

        return $message;
    }

    /**
     * ApplyPaymentUpdate
     *
     * @param string $paymentState
     * @param string $order
     */
    private function _applyPaymentUpdate($paymentState, $order)
    {
        try {
            $this->_paymentUpdate = $this->paymentservice
                        ->createPaymentUpdateFromWorldPayResponse($paymentState);
            $this->_paymentUpdate->apply($order->getPayment(), $order);
        } catch (Exception $e) {
            $this->wplogger->error($e->getMessage());
        }
    }
}
