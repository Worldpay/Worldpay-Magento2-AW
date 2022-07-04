<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Notification;

use Sapient\AccessWorldpay\Api\HistoryInterface;

class History implements HistoryInterface
{

    /**
     * Constructor
     *
     * @param \Sapient\AccessWorldpay\Model\HistoryNotification $historyNotification
     */
    public function __construct(
        \Sapient\AccessWorldpay\Model\HistoryNotification $historyNotification
    ) {
        $this->historyNotification = $historyNotification;
    }
    /**
     * Returns Order Notification
     * @api
     * @param Integer $order
     * @return json $result.
     */
    public function getHistory($order)
    {
        $result="";
        if (isset($order)) {
                $result = $this->historyNotification->getCollection()
                        ->addFieldToFilter('order_id', ['eq' => trim($order)])->getData();
        } else {
                $result = 'Order Id is null';
        }
        return $result;
    }
}
