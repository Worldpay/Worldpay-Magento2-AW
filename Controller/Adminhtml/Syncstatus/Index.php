<?php

namespace Sapient\AccessWorldpay\Controller\Adminhtml\Syncstatus;

use Magento\Framework\View\Result\PageFactory;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Exception;
use Sapient\AccessWorldpay\Helper\GeneralException;

class Index extends \Magento\Backend\App\Action
{
    /**
     * @var $pageFactory
     */
    protected $pageFactory;
    /**
     * @var $rawBody
     */
    protected $_rawBody;
    /**
     * @var $orderId
     */
    private $_orderId;
    /**
     * @var $order
     */
    private $_order;
    /**
     * @var $paymentUpdate
     */
    private $_paymentUpdate;
    /**
     * @var $tokenState
     */
    private $_tokenState;
    /**
     * @var $helper
     */
    private $helper;
    /**
     * @var $storeManager
     */
    private $storeManager;
    
    /**
     * Constructor
     *
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param \Sapient\AccessWorldpay\Logger\WorldpayLogger $wplogger
     * @param \Sapient\AccessWorldpay\Model\Payment\Service $paymentservice
     * @param \Sapient\AccessWorldpay\Model\Order\Service $orderservice
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Sapient\AccessWorldpay\Model\PaymentMethods\PaymentOperations $paymentoperations
     * @param \Sapient\AccessWorldpay\Helper\GeneralException $helper
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger,
        \Sapient\AccessWorldpay\Model\Payment\Service $paymentservice,
        \Sapient\AccessWorldpay\Model\Order\Service $orderservice,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Sapient\AccessWorldpay\Model\PaymentMethods\PaymentOperations $paymentoperations,
        \Sapient\AccessWorldpay\Helper\GeneralException $helper
    ) {

        parent::__construct($context);
        $this->wplogger = $wplogger;
        $this->paymentservice = $paymentservice;
        $this->orderservice = $orderservice;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->helper = $helper;
        $this->storeManager = $storeManager;
        $this->paymentoperations = $paymentoperations;
    }
    
    /**
     * Execute
     */
    public function execute()
    {
        $this->_loadOrder();
        $storeid = $this->_order->getOrder()->getStoreId();
        $store = $this->storeManager->getStore($storeid)->getCode();
        try {
            $this->_fetchPaymentUpdate();
            $this->_registerWorldPayModel();
            $this->_applyPaymentUpdate();
            $this->_updateOrderStatus();
        } catch (Exception $e) {
            $this->wplogger->error($e->getMessage());
            if ($e->getMessage() == 'same state') {
                 $this->messageManager->addSuccess($this->helper->getConfigValue('ACAM3', $store));
            } else {
                $this->messageManager->addError(
                    $this->helper->getConfigValue('ACAM4', $store).': ' . $e->getMessage()
                );
            }
            return $this->_redirectBackToOrderView();
        }

        $this->messageManager->addSuccess($this->helper->getConfigValue('ACAM3', $store));
        return $this->_redirectBackToOrderView();
    }
    
    /**
     * Load Order
     */
    private function _loadOrder()
    {
        $this->_orderId = (int) $this->_request->getParam('order_id');
        $this->_order = $this->orderservice->getById($this->_orderId);
    }
    
    /**
     * Fetch Payment Update
     */
    private function _fetchPaymentUpdate()
    {
        $xml = $this->paymentservice->getPaymentUpdateXmlForOrder($this->_order);
        $this->_paymentUpdate = $this->paymentservice->createPaymentUpdateFromWorldPayXml($xml);
        //$this->_tokenState = new \Sapient\Worldpay\Model\Token\StateXml($xml);
    }

    /**
     * Register worldpay model
     */
    private function _registerWorldPayModel()
    {
        $this->paymentservice->setGlobalPaymentByPaymentUpdate($this->_paymentUpdate);
    }
    
    /**
     * Apply payment update
     */
    private function _applyPaymentUpdate()
    {
        try {
            $this->_paymentUpdate->apply($this->_order->getPayment(), $this->_order);
        } catch (Exception $e) {
            $this->wplogger->error($e->getMessage());
            throw new \Magento\Framework\Exception\LocalizedException(
                __($e->getMessage())
            );
        }
    }
    
    /**
     * Redirect back to order view
     */
    private function _redirectBackToOrderView()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath($this->_redirect->getRefererUrl());
        return $resultRedirect;
    }
    
    /**
     * Update order status
     */
    private function _updateOrderStatus()
    {
        $this->paymentoperations->updateOrderStatus($this->_order);
    }
}
