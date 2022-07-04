<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Payment\Update;

use Sapient\AccessWorldpay\Model\Payment\UpdateInterface;
use Sapient\AccessWorldpay\Model\Payment\Update\Base;

class Refunded extends Base implements UpdateInterface
{
    /**
     * @var $_configHelper
     */
    private $_configHelper;
    /**
     * @var REFUND_COMMENT
     */
    public const REFUND_COMMENT = 'Refund request PROCESSED by the bank.';

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
        $reference = $this->_paymentState->getJournalReference(
            \Sapient\AccessWorldpay\Model\Payment\StateInterface::STATUS_REFUNDED
        );
        if (isset($reference) && !empty($order)) {
            $message = self::REFUND_COMMENT . ' Reference: ' . $reference;
            $order->refund($reference, $message);
        }
        $this->_worldPayPayment->updateAccessWorldpayPayment($this->_paymentState);
    }
}
