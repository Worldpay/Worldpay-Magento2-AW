<?php
/**
 * @copyright 2017 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Payment\Update;

use Sapient\AccessWorldpay\Model\Payment\UpdateInterface;
use Sapient\AccessWorldpay\Model\Payment\Update\Base;

class Captured extends Base implements UpdateInterface
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
            $paymentStatus = $this->_paymentState->getPaymentStatus();
            $paymentType = $this->_configHelper->getOrderPaymentType($this->_paymentState->getOrderCode());
            if ($paymentType == 'ACH_DIRECT_DEBIT-SSL'
                && ($paymentStatus == 'SENT FOR SETTLEMENT' || $paymentStatus == 'SENT_FOR_SETTLEMENT')
                 && !$this->_configHelper->checkOrderHasInvoices()) {
                $order->capture();
                $this->_worldPayPayment->updateAccessWorldpayPayment($this->_paymentState);
            } elseif (($paymentStatus == 'SENT FOR SETTLEMENT' || $paymentStatus == 'SENT_FOR_SETTLEMENT')) {
                $order->capture();
                $this->_worldPayPayment->updateAccessWorldpayPayment($this->_paymentState);
            } else {

                $this->_assertValidPaymentStatusTransition($order, $this->_getAllowedPaymentStatuses());
                $order->capture();
                $this->_worldPayPayment->updateAccessWorldpayPayment($this->_paymentState);
            }
        } else {
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
            \Sapient\AccessWorldpay\Model\Payment\StateInterface::STATUS_PENDING_PAYMENT,
            \Sapient\AccessWorldpay\Model\Payment\StateInterface::STATUS_AUTHORISED
        ];
    }
}
