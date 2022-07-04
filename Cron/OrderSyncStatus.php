<?php

namespace Sapient\AccessWorldpay\Cron;

use \Magento\Framework\App\ObjectManager;
use \Magento\Sales\Model\ResourceModel\Order\CollectionFactoryInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Exception;

/**
 * Model for order sync status based on configuration set by admin
 */
class OrderSyncStatus
{
    /**
     *
     * @var \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger
     */
    protected $_logger;
    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\CollectionFactory
     */
    private $orderCollectionFactory;

    /**
     * @var $_orderId
     */
    private $_orderId;

    /**
     * @var $_order
     */
    private $_order;
    /**
     * @var \Sapient\AccessWorldpay\Model\Payment\Service
     */
    private $_paymentUpdate;
    
    /**
     * Constructor
     *
     * @param JsonFactory $resultJsonFactory
     * @param \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger
     * @param \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory
     * @param \Sapient\AccessWorldpay\Helper\Data $worldpayhelper
     * @param \Sapient\AccessWorldpay\Model\Payment\Service $paymentservice
     * @param \Sapient\AccessWorldpay\Model\Order\Service $orderservice
     * @param \Sapient\AccessWorldpay\Model\PaymentMethods\PaymentOperations $paymentoperations
     */
    public function __construct(
        JsonFactory $resultJsonFactory,
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        \Sapient\AccessWorldpay\Helper\Data $worldpayhelper,
        \Sapient\AccessWorldpay\Model\Payment\Service $paymentservice,
        \Sapient\AccessWorldpay\Model\Order\Service $orderservice,
        \Sapient\AccessWorldpay\Model\PaymentMethods\PaymentOperations $paymentoperations
    ) {
        $this->_logger = $wplogger;
        $this->_orderCollectionFactory = $orderCollectionFactory;
        $this->worldpayhelper = $worldpayhelper;
        $this->paymentservice = $paymentservice;
        $this->orderservice = $orderservice;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->paymentoperations = $paymentoperations;
    }
    
    /**
     * Get the list of orders to be sync the status
     */
    public function execute()
    {
        $this->_logger->info('Orders sync status executed on - '.date('Y-m-d H:i:s'));
        $orderIds = $this->getOrderIds();
        if (!empty($orderIds)) {
            foreach ($orderIds as $order) {
                $this->_loadOrder($order['entity_id']);
                $this->createSyncRequest();
            }
        }
        return $this;
    }
    
    /**
     * Get the list of orders to be Sync
     *
     * @return array List of order IDs
     */
    public function getOrderIds()
    {
        $curdate = date("Y-m-d H:i:s");
        $timelimit = $this->worldpayhelper->getOrderSyncInterval();
        $maxDate = strtotime(date("Y-m-d H:i:s", strtotime($curdate)) . " -30 min");
        $cronMaxDate = date('Y-m-d H:i:s', $maxDate);
        
        $minDate = strtotime(date("Y-m-d H:i:s", strtotime($curdate)) . " -$timelimit hour");
        $cronMinDate = date('Y-m-d H:i:s', $minDate);
        
        $status = $this->worldpayhelper->getSyncOrderStatus();
        $orderStatus = explode(',', $status);
        $orders = $this->getOrderCollectionFactory()->create();
        $orders->distinct(true);
        $orders->addFieldToSelect(['entity_id','increment_id','created_at']);
        $orders->addFieldToFilter('main_table.status', ['in' => $orderStatus]);
        $orders->addFieldToFilter('main_table.created_at', ['lteq' => $cronMaxDate]);
        $orders->addFieldToFilter('main_table.created_at', ['gteq' => $cronMinDate]);
        $orders->join(['wp' => 'worldpay_payment'], 'wp.order_id=main_table.increment_id', '');
        $orders->join(['og' => 'sales_order_grid'], 'og.entity_id=main_table.entity_id', '');
        
        $orderIds = $orders->getData();
        return $orderIds;
    }
    
    /**
     * Get Order Collection
     *
     * @return CollectionFactoryInterface
     */
    private function getOrderCollectionFactory()
    {
        if ($this->orderCollectionFactory === null) {

            $this->orderCollectionFactory = ObjectManager::getInstance()->get(CollectionFactoryInterface::class);
        }
        return $this->orderCollectionFactory;
    }
    /**
     * Load Order
     *
     * @param int $orderId
     * @return string
     */
    private function _loadOrder($orderId)
    {
        $this->_orderId = $orderId;
        $this->_order = $this->orderservice->getById($this->_orderId);
    }
    
    /**
     * Create Sync Request
     *
     * @return bool
     */
    public function createSyncRequest()
    {
        try {
            $this->_fetchPaymentUpdate();
            $this->_registerWorldPayModel();
            $this->_applyPaymentUpdate();
            $this->_updateOrderStatus();
        } catch (Exception $e) {
            $this->_logger->error($e->getMessage());
            if ($e->getMessage() == 'same state') {
                $this->_logger->error('Payment synchronized successfully!!');
            } else {
                $this->_logger->error('Synchronising Payment Status failed: ' . $e->getMessage());
            }
        }
        return true;
    }
    /**
     * Fetch Payment Update
     *
     * @return string
     */
    private function _fetchPaymentUpdate()
    {
        $xml = $this->paymentservice->getPaymentUpdateXmlForOrder($this->_order);
        $this->_paymentUpdate = $this->paymentservice->createPaymentUpdateFromWorldPayXml($xml);
        //$this->_tokenState = new \Sapient\Worldpay\Model\Token\StateXml($xml);
    }
    /**
     * Register WorldPay Model
     *
     * @return string
     */
    private function _registerWorldPayModel()
    {
        $this->paymentservice->setGlobalPaymentByPaymentUpdate($this->_paymentUpdate);
    }
    /**
     * Apply Payment Update
     *
     * @return string
     */
    private function _applyPaymentUpdate()
    {
        try {
            $this->_paymentUpdate->apply($this->_order->getPayment(), $this->_order);
        } catch (Exception $e) {
            throw new \Magento\Framework\Exception\LocalizedException(__($e->getMessage()));
        }
    }
    /**
     * Update Order Status
     *
     * @return string
     */
    private function _updateOrderStatus()
    {
        $this->paymentoperations->updateOrderStatus($this->_order);
    }
}
