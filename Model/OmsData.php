<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model;

/**
 * Resource Model
 */
class OmsData extends \Magento\Framework\Model\AbstractModel
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init(\Sapient\AccessWorldpay\Model\ResourceModel\OmsData::class);
    }

    /**
     * Load worldpay Order Details
     *
     * @param string $order_id
     * @return Sapient\AccessWorldpay\Model\OmsData
     */
    public function loadByAccessWorldpayOrderCode($order_id)
    {
        if (!$order_id) {
            return;
        }
        $id = $this->getResource()->loadByAccessWorldpayOrderCode($order_id);
        return $this->load($id);
    }
    
    /**
     * Load worldpay Order Details
     *
     * @param int $order_increment_id
     * @return Sapient\AccessWorldpay\Model\OmsData
     */
    public function loadByOrderIncrementId($order_increment_id)
    {
        if (!$order_increment_id) {
            return;
        }
        $id = $this->getResource()->loadByOrderIncrementId($order_increment_id);
        return $this->load($id);
    }
}
