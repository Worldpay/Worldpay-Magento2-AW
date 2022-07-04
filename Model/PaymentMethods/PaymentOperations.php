<?php
/**
 * @copyright 2017 Sapient
 */
namespace Sapient\AccessWorldpay\Model\PaymentMethods;
 
class PaymentOperations extends \Sapient\AccessWorldpay\Model\PaymentMethods\AbstractMethod
{
    /**
     * Update status for void order abstract method
     *
     * @param array $order
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function updateOrderStatus($order)
    {
        if (!empty($order)) {
            $payment = $order->getPayment();
            $mageOrder = $order->getOrder();

            $worldPayPayment = $this->worldpaypaymentmodel->loadByPaymentId($mageOrder->getIncrementId());
            if (isset($worldPayPayment)) {
                $paymentStatus = preg_replace('/\s+/', '_', trim($worldPayPayment->getPaymentStatus()));
                $this->_wplogger->info('Updating order status');
                $this->updateOrder(strtoupper($paymentStatus), $mageOrder);
            } else {
                $this->_wplogger->info('No Payment');
                throw new \Magento\Framework\Exception\LocalizedException(__('No Payment'));
            }
        } else {
            $this->_wplogger->info('No Payment');
            throw new \Magento\Framework\Exception\LocalizedException(__('No Payment'));
        }
    }
    /**
     * Update Order
     *
     * @param string $paymentStatus
     * @param array $mageOrder
     * @return $this
     */
    public function updateOrder($paymentStatus, $mageOrder)
    {
        switch ($paymentStatus) {
            case 'SENT_FOR_SETTLEMENT':
                $mageOrder->setState(\Magento\Sales\Model\Order::STATE_PROCESSING, true);
                $mageOrder->setStatus(\Magento\Sales\Model\Order::STATE_PROCESSING);
                $mageOrder->save();
                break;
            case 'REFUNDED':
                $mageOrder->setState(\Magento\Sales\Model\Order::STATE_CLOSED, true);
                $mageOrder->setStatus(\Magento\Sales\Model\Order::STATE_CLOSED);
                $mageOrder->save();
                break;
            case 'REFUNDED_BY_MERCHANT':
                $mageOrder->setState(\Magento\Sales\Model\Order::STATE_CLOSED, true);
                $mageOrder->setStatus(\Magento\Sales\Model\Order::STATE_CLOSED);
                $mageOrder->save();
                break;
            case 'CANCELLED':
                $mageOrder->setState(\Magento\Sales\Model\Order::STATE_CANCELED, true);
                $mageOrder->setStatus(\Magento\Sales\Model\Order::STATE_CANCELED);
                $mageOrder->save();
                break;
            case 'VOIDED':
                $mageOrder->setState(\Magento\Sales\Model\Order::STATE_CLOSED, true);
                $mageOrder->setStatus(\Magento\Sales\Model\Order::STATE_CLOSED);
                $mageOrder->save();
                break;
            case 'REFUSED':
                $mageOrder->setState(\Magento\Sales\Model\Order::STATE_CANCELED, true);
                $mageOrder->setStatus(\Magento\Sales\Model\Order::STATE_CANCELED);
                $mageOrder->save();
                break;
            case 'REVERSED':
                $mageOrder->setState(\Magento\Sales\Model\Order::STATE_CLOSED, true);
                $mageOrder->setStatus(\Magento\Sales\Model\Order::STATE_CLOSED);
                $mageOrder->save();
                break;
            default:
                break;
        }
    }
    /**
     * Can Payment Reversal abstract method
     *
     * @param array $order
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function canPaymentReversal($order)
    {
        $payment = $order->getPayment();
        $mageOrder = $order->getOrder();
        $worldPayPayment = $this->worldpaypaymentmodel->loadByPaymentId($mageOrder->getIncrementId());
        $paymenttype = $worldPayPayment->getPaymentType();
        if ($paymenttype === 'ACH_DIRECT_DEBIT-SSL'
            && !(strtoupper($worldPayPayment->getPaymentStatus()) === 'REVERSED')) {
            $xml = $this->paymentservicerequest->paymentReversal($mageOrder->getIncrementId());
            $xml = new \SimpleXmlElement($xml);
            $payment->setTransactionId(time());

            if ($xml && isset($xml->_links)) {
                $responseLinks = $xml->_links;
                if (isset($responseLinks->events->href)) {
                    $eventsLink = $responseLinks->events->href;
                    $eventResponsexml = $this->paymentservicerequest
                    ->eventInquiry($mageOrder->getIncrementId(), $eventsLink);
                    $eventResponsexml = new \SimpleXmlElement($eventResponsexml);
                }
            }
        } else {
            throw new \Magento\Framework\Exception\LocalizedException(__('The reversal action is not available.'
                    . 'Possible reason this was already executed for this order. '
                    . 'Please check Payment Status below for confirmation.'));
        }
    }
}
