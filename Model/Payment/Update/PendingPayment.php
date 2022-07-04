<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Payment\Update;

use Sapient\AccessWorldpay\Model\Payment\UpdateInterface;
use Sapient\AccessWorldpay\Model\Payment\Update\Base;

class PendingPayment extends Base implements UpdateInterface
{
    /**
     * @var $_configHelper
     */
    private $_configHelper;
    /**
     * Constructor
     *
     * @param \Sapient\AccessWorldpay\Model\Payment\StateInterface $paymentState
     * @param \Sapient\AccessWorldpay\Model\Payment\WorldPayPayment $worldPayPayment
     * @param \Sapient\AccessWorldpay\Helper\Data $configHelper
     */
    public function __construct(
        \Sapient\AccessWorldpay\Model\Payment\StateInterface $paymentState,
        \Sapient\AccessWorldpay\Model\Payment\WorldPayPayment $worldPayPayment,
        \Sapient\AccessWorldpay\Helper\Data $configHelper
    ) {
        $this->_paymentState = $paymentState;
        $this->_worldPayPayment = $worldPayPayment;
        $this->_configHelper = $configHelper;
    }

    /**
     * Update Order status
     *
     * @param array $payment
     * @param array $order
     * @return string
     */
    public function apply($payment, $order = null)
    {
        if (!empty($order)) {
            $this->_worldPayPayment->updateAccessWorldpayPayment($this->_paymentState);
             $order->pendingPayment();
        }
    }
}
