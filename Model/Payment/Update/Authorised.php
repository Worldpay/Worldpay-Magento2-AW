<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Payment\Update;

use Sapient\AccessWorldpay\Model\Payment\StateInterface;
use Sapient\AccessWorldpay\Model\Payment\Update\Base;
use Sapient\AccessWorldpay\Model\Payment\UpdateInterface;

class Authorised extends Base implements UpdateInterface
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
        if (empty($order)) {
            $this->_applyUpdate($payment);
            $this->_worldPayPayment->updateAccessWorldpayPayment($this->_paymentState);
        } else {
            $this->_assertValidPaymentStatusTransition($order, $this->_getAllowedPaymentStatuses($order));
            $this->_applyUpdate($order->getPayment(), $order);
            $this->_worldPayPayment->updateAccessWorldpayPayment($this->_paymentState);
        }
    }

    /**
     * Apply update payment
     *
     * @param array $payment
     * @param array $order
     */
    private function _applyUpdate($payment, $order = null)
    {
        $payment->setTransactionId(time());
        $payment->setIsTransactionClosed(0);
        if (!empty($order) &&
            ($order->getPaymentStatus() == \Sapient\AccessWorldpay\Model\Payment\StateInterface::
                STATUS_SENT_FOR_AUTHORISATION)) {
            //$currencycode = $this->_paymentState->getCurrency();
            //$currencysymbol = $this->_configHelper->getCurrencySymbol($currencycode);
            //$amount = $this->_amountAsInt($this->_paymentState->getAmount());
            $magentoorder = $order->getOrder();
            $magentoorder->addStatusToHistory($magentoorder->getStatus(), 'Authorized amount of ');
            $transaction = $payment->addTransaction('authorization', null, false, null);
            $transaction->save();
            $magentoorder->save();
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
            if ($this->_isDirectIntegrationMode($order)) {
                 return [
                \Sapient\AccessWorldpay\Model\Payment\StateInterface::STATUS_SENT_FOR_AUTHORISATION,
                \Sapient\AccessWorldpay\Model\Payment\StateInterface::STATUS_AUTHORISED
                 ];
            }
            return [\Sapient\AccessWorldpay\Model\Payment\StateInterface::STATUS_SENT_FOR_AUTHORISATION];
        } else {
            throw new \Magento\Framework\Exception\LocalizedException(__('No Payment'));
        }
    }

    /**
     * Check if integration mode is direct
     *
     * @param array $order
     * @return bool
     */
    private function _isDirectIntegrationMode(\Sapient\AccessWorldpay\Model\Order $order)
    {
        return $this->_configHelper->getIntegrationModelByPaymentMethodCode(
            $order->getPaymentMethodCode(),
            $order->getStoreId()
        )
            === \Sapient\AccessWorldpay\Model\PaymentMethods\AbstractMethod::DIRECT_MODEL;
    }

    /**
     * Check if integration mode is redirect
     *
     * @param object $order
     * @return bool
     */
    private function _isRedirectIntegrationMode(\Sapient\AccessWorldpay\Model\Order $order)
    {
        return $this->_configHelper->getIntegrationModelByPaymentMethodCode(
            $order->getPaymentMethodCode(),
            $order->getStoreId()
        )
            === \Sapient\AccessWorldpay\Model\PaymentMethods\AbstractMethod::REDIRECT_MODEL;
    }
}
