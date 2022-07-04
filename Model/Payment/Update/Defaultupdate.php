<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Payment\Update;

use Sapient\AccessWorldpay\Model\Payment\Update\Base;
use Sapient\AccessWorldpay\Model\Payment\UpdateInterface;

class Defaultupdate extends Base implements UpdateInterface
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
        $paymentType = $this->_configHelper->getOrderPaymentType($this->_paymentState->getOrderCode());
        $isValidPaymentTransition = $paymentType == 'ACH_DIRECT_DEBIT-SSL'
                ? $this->_isValidPaymentStatusTransition($order, $this->_getAllowedPaymentStatuses($order))
                : true;
        
        if (!empty($order) && $isValidPaymentTransition) {
            $this->_worldPayPayment->updateAccessWorldpayPayment($this->_paymentState);
        } else {
            throw new \Magento\Framework\Exception\LocalizedException(__('invalid state transition'));
        }
    }
    
    /**
     * Get Allowed Payment Status
     *
     * @param \Sapient\AccessWorldpay\Model\Order $order
     * @return array
     */
    private function _getAllowedPaymentStatuses(\Sapient\AccessWorldpay\Model\Order $order)
    {
        if (!empty($order) && $order->hasWorldPayPayment()) {
            return [
                \Sapient\AccessWorldpay\Model\Payment\StateInterface::STATUS_SENT_FOR_SETTLEMENT,
                \Sapient\AccessWorldpay\Model\Payment\StateInterface::STATUS_CANCELLED,
                \Sapient\AccessWorldpay\Model\Payment\StateInterface::STATUS_ERROR,
                \Sapient\AccessWorldpay\Model\Payment\StateInterface::STATUS_REFUSED,
                \Sapient\AccessWorldpay\Model\Payment\StateInterface::STATUS_SENT_FOR_REFUND,
                \Sapient\AccessWorldpay\Model\Payment\StateInterface::STATUS_REFUND_FAILED,
                \Sapient\AccessWorldpay\Model\Payment\StateInterface::STATUS_CAPTURED,
                \Sapient\AccessWorldpay\Model\Payment\StateInterface::STATUS_PARTIAL_CAPTURED,
                \Sapient\AccessWorldpay\Model\Payment\StateInterface::STATUS_REFUNDED,
                \Sapient\AccessWorldpay\Model\Payment\StateInterface::STATUS_PARTIAL_REFUNDED
                ];
        } else {
            throw new \Magento\Framework\Exception\LocalizedException(__('No Payment'));
        }
    }
    
    /**
     * Check isValid payment status transition
     *
     * @param array $order
     * @param string $allowedPaymentStatuses
     * @return true|false
     */
    private function _isValidPaymentStatusTransition($order, $allowedPaymentStatuses)
    {
        $existingPaymentStatus = preg_replace('/\s+/', '_', trim($order->getPaymentStatus()));
        $newPaymentStatus = $this->_paymentState->getPaymentStatus();
        if (in_array($existingPaymentStatus, $allowedPaymentStatuses)
            && ($newPaymentStatus == 'SENT_FOR_AUTHORIZATION' || $newPaymentStatus == 'AUTHORIZED')) {
            return false;
        } else {
            return true;
        }
    }
}
