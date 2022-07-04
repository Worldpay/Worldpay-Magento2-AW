<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Payment\Update;

use Sapient\AccessWorldpay\Model\Payment\Update\Base;
use Sapient\AccessWorldpay\Model\Payment\UpdateInterface;

class Cancelled extends Base implements UpdateInterface
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
    * Apply update payment status
    *
    * @param array $payment
    * @param array $order
    * @return string
    */
    public function apply($payment, $order = null)
    {
        if (!empty($order)) {
            $this->_assertValidPaymentStatusTransition($order, $this->_getAllowedPaymentStatuses());
            $order->cancel();
            $this->_worldPayPayment->updateAccessWorldpayPayment($this->_paymentState);
        }
    }

    /**
     * Get Allowed Payment Status
     *
     * @return array
     */
    protected function _getAllowedPaymentStatuses()
    {
        return [
            \Sapient\AccessWorldpay\Model\Payment\StateInterface::STATUS_SENT_FOR_AUTHORISATION,
            \Sapient\AccessWorldpay\Model\Payment\StateInterface::STATUS_AUTHORISED,
            \Sapient\AccessWorldpay\Model\Payment\StateInterface::STATUS_CAPTURED,
            \Sapient\AccessWorldpay\Model\Payment\StateInterface::STATUS_SENT_FOR_SETTLEMENT,
            \Sapient\AccessWorldpay\Model\Payment\StateInterface::STATUS_CANCELLED
        ];
    }
}
