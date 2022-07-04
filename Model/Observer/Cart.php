<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Observer;

use Magento\Framework\Event\ObserverInterface;
use Exception;

class Cart implements ObserverInterface
{
    /**
     * Constructor
     * @param \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger
     * @param \Sapient\AccessWorldpay\Model\Order\Service $orderservice
     * @param \Sapient\AccessWorldpay\Model\Checkout\Service $checkoutservice
     * @param \Magento\Checkout\Model\Session $checkoutsession
     */
    public function __construct(
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger,
        \Sapient\AccessWorldpay\Model\Order\Service $orderservice,
        \Sapient\AccessWorldpay\Model\Checkout\Service $checkoutservice,
        \Magento\Checkout\Model\Session $checkoutsession
    ) {
        $this->orderservice = $orderservice;
        $this->wplogger = $wplogger;
        $this->checkoutservice = $checkoutservice;
        $this->checkoutsession = $checkoutsession;
    }

   /**
    * Load the shopping cart from the latest authorized, but not completed order
    *
    * @param \Magento\Framework\Event\Observer $observer
    */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if ($this->checkoutsession->getauthenticatedOrderId()) {
            $order = $this->orderservice->getAuthorisedOrder();
            $this->checkoutservice->reactivateQuoteForOrder($order);
            $this->orderservice->removeAuthorisedOrder();
        }
    }
}
