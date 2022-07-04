<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Checkout;

use Magento\Checkout\Model\Cart as CustomerCart;

class Service
{

    /**
     * @param \Magento\Checkout\Model\Session $checkoutsession
     * @param CustomerCart $cart
     * @param \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger
     */
    public function __construct(
        \Magento\Checkout\Model\Session $checkoutsession,
        CustomerCart $cart,
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger
    ) {

        $this->checkoutsession = $checkoutsession;
        $this->cart = $cart;
        $this->wplogger = $wplogger;
    }
    
    /**
     * Get ClearSession
     */
    public function clearSession()
    {
        $this->checkoutsession->clearQuote();
    }

    /**
     * Get Reactivate QuoteForOrder
     *
     * @param \Sapient\AccessWorldpay\Model\Order $order
     */
    public function reactivateQuoteForOrder(\Sapient\AccessWorldpay\Model\Order $order)
    {

        $mageOrder = $order->getOrder();
        if ($mageOrder->isObjectNew()) {
            return;
        }

        $this->checkoutsession->restoreQuote();
        $this->cart->save();
        $this->wplogger->info('cart restored');
    }
}
