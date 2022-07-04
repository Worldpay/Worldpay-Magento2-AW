<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Controller\Redirectresult;

use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Action\Context;
use Sapient\AccessWorldpay\Model\Payment\StateResponse as PaymentStateResponse;
use Magento\Framework\Exception\LocalizedException;

/**
 * after deleting the card redirect to the pending page
 */
class Pending extends \Magento\Framework\App\Action\Action
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
     * @param \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger
     */
    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        \Sapient\AccessWorldpay\Model\Order\Service $orderservice,
        \Sapient\AccessWorldpay\Model\Checkout\Service $checkoutservice,
        \Sapient\AccessWorldpay\Model\Payment\Service $paymentservice,
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger
    ) {
        $this->pageFactory = $pageFactory;
        $this->wplogger = $wplogger;
        $this->orderservice = $orderservice;
        $this->checkoutservice = $checkoutservice;
        $this->paymentservice = $paymentservice;
        return parent::__construct($context);
    }

    /**
     * Execute
     */
    public function execute()
    {
        $this->wplogger->info('worldpay returned pending url');
        if (!$this->orderservice->getAuthorisedOrder()) {
            return $this->resultRedirectFactory->create()->setPath('checkout/cart', ['_current' => true]);
        }
        $order = $this->orderservice->getAuthorisedOrder();
        $magentoorder = $order->getOrder();
        $params = $this->getRequest()->getParams();
        try {
            if ($params) {
                $this->_applyPaymentUpdate(PaymentStateResponse::createFromPendingResponse($params), $order);
            }
        } catch (Exception $e) {
            $this->wplogger->error($e->getMessage());
            $this->checkoutservice->clearSession();
            $this->orderservice->removeAuthorisedOrder();
            $this->wplogger->error($e->getMessage());
            if ($e->getMessage() == 'invalid state transition') {
                 return $this->pageFactory->create();
            } else {
                 return $this->resultRedirectFactory->create()->
                         setPath('checkout/cart', ['_current' => true]);
            }
        }
        $this->checkoutservice->clearSession();
        $this->orderservice->removeAuthorisedOrder();
        return $this->pageFactory->create();
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
            throw new \Magento\Framework\Exception\LocalizedException($e->getMessage());
        }
    }
}
