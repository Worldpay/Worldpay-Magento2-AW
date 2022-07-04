<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model;

/**
 * Resource Model
 */
class AccessWorldpayment extends \Magento\Framework\Model\AbstractModel
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(\Sapient\AccessWorldpay\Model\ResourceModel\AccessWorldpayment::class);
    }

    /**
     * Retrieve worldpay payment Details
     *
     * @param string $orderId
     * @return Sapient\AccessWorldpay\Model\AccessWorldpayment
     */
    public function loadByPaymentId($orderId)
    {

        if (!$orderId) {
            return;
        }
        $id = $this->getResource()->loadByPaymentId($orderId);
        return $this->load($id);
    }

    /**
     * Load worldpay payment Details
     *
     * @param string $order_id
     * @return Sapient\AccessWorldpay\Model\AccessWorldpayment
     */
    public function loadByAccessWorldpayOrderId($order_id)
    {
        if (!$order_id) {
            return;
        }
        $id = $this->getResource()->loadByAccessWorldpayOrderId($order_id);
        return $this->load($id);
    }
}
