<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Payment;

interface UpdateInterface
{
    /**
     * Apply
     *
     * @param Payment $payment
     */
    public function apply($payment);

    /**
     * Get target order code
     *
     * @return string ordercode
     */
    public function getTargetOrderCode();
}
