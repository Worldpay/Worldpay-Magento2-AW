<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Payment\Update;

use Exception;
use \Magento\Framework\Exception\LocalizedException;

class Base
{
    /**
     * @var $_paymentState
     */
    protected $_paymentState;
    
    /**
     * @var $_worldPayPayment
     */
    protected $_worldPayPayment;
    
    /**
     * @var STATUS_PROCESSING
     */
    public const STATUS_PROCESSING = 'processing';
   
    /**
     * @var STATUS_CANCELLED
     */
    public const STATUS_CANCELLED = 'cancelled';
   
    /**
     * @var $_worldPayPayment
     */
    public const STATUS_CLOSED = 'closed';

    /**
     * Constructor
     *
     * @param \Sapient\AccessWorldpay\Model\Payment\StateInterface $paymentState
     * @param \Sapient\AccessWorldpay\Model\Payment\WorldPayPayment $worldPayPayment
     */
    public function __construct(
        \Sapient\AccessWorldpay\Model\Payment\StateInterface $paymentState,
        \Sapient\AccessWorldpay\Model\Payment\WorldPayPayment $worldPayPayment
    ) {

        $this->_paymentState = $paymentState;
        $this->_worldPayPayment = $worldPayPayment;
    }

    /**
     * Get order code
     *
     * @return string ordercode
     */
    public function getTargetOrderCode()
    {
        return $this->_paymentState->getOrderCode();
    }

    /**
     * Check payment Status
     *
     * @param object $order
     * @param array $allowedPaymentStatuses
     * @return null
     * @throws Exception
     */
    protected function _assertValidPaymentStatusTransition($order, $allowedPaymentStatuses)
    {
        $this->_assertPaymentExists($order);
        
        $existingPaymentStatus = $order->getPaymentStatus();
        
        $newPaymentStatus = $this->_paymentState->getPaymentStatus();
        
        if (in_array($existingPaymentStatus, $allowedPaymentStatuses)) {
            return;
        }

        if ($existingPaymentStatus == $newPaymentStatus) {
            throw new \Magento\Framework\Exception\LocalizedException(__('same state'));
        }

        throw new \Magento\Framework\Exception\LocalizedException(__('invalid state transition'));
    }

    /**
     * Check if order is not placed throgh worldpay payment
     *
     * @param object $order
     * @throws Exception
     */
    private function _assertPaymentExists($order)
    {
        if (!$order->hasWorldPayPayment()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('No payment'));
        }
    }

   /**
    * Convert worldpay amount to magento amount
    *
    * @param float $amount
    * @return int
    */
    protected function _amountAsInt($amount)
    {
        return round($amount, 2, PHP_ROUND_HALF_EVEN) / pow(10, 2);
    }
}
