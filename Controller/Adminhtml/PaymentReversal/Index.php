<?php
/**
 * @copyright 2021 Sapient
 */
namespace Sapient\AccessWorldpay\Controller\Adminhtml\PaymentReversal;

use Magento\Framework\View\Result\PageFactory;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Exception;
use Sapient\Worldpay\Helper\GeneralException;

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
     * @var $abstractMethod
     */
    private $abstractMethod;
    
    /**
     * Constructor
     *
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param \Sapient\AccessWorldpay\Logger\WorldpayLogger $wplogger
     * @param \Sapient\AccessWorldpay\Model\Payment\Service $paymentservice
     * @param \Sapient\AccessWorldpay\Model\Token\WorldpayToken $worldpaytoken
     * @param \Sapient\AccessWorldpay\Model\Order\Service $orderservice
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Sapient\AccessWorldpay\Model\PaymentMethods\PaymentOperations $abstractMethod
     * @param \Sapient\AccessWorldpay\Helper\GeneralException $helper
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger,
        \Sapient\AccessWorldpay\Model\Payment\Service $paymentservice,
        \Sapient\AccessWorldpay\Model\Token\WorldpayToken $worldpaytoken,
        \Sapient\AccessWorldpay\Model\Order\Service $orderservice,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Sapient\AccessWorldpay\Model\PaymentMethods\PaymentOperations $abstractMethod,
        \Sapient\AccessWorldpay\Helper\GeneralException $helper
    ) {

        parent::__construct($context);
        $this->wplogger = $wplogger;
        $this->paymentservice = $paymentservice;
        $this->orderservice = $orderservice;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->worldpaytoken = $worldpaytoken;
        $this->helper = $helper;
        $this->storeManager = $storeManager;
        $this->abstractMethod = $abstractMethod;
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
            $this->abstractMethod->canPaymentReversal($this->_order);
            $this->_fetchPaymentUpdate();
            $this->_registerWorldPayModel();
            $this->_applyPaymentUpdate();

        } catch (Exception $e) {
            $this->wplogger->error($e->getMessage());
            $codeErrorMessage = 'Payment Reversal Action Failed';
            $camErrorMessage = $this->helper->getConfigValue('AACH05', $store);
            $codeMessage = 'Payment reversal already executed and is in process.';
            $camMessage = $this->helper->getConfigValue('AACH02', $store);
            $message = $camMessage? $camMessage : $codeMessage;
            $errorMessage = $camErrorMessage? $camErrorMessage : $codeErrorMessage;

            if ($e->getMessage() == 'same state') {
                  $this->messageManager->addSuccess($message);
            } else {
                $this->messageManager->addError($errorMessage .': '. $e->getMessage());
            }
            return $this->_redirectBackToOrderView();
        }
        $codeMessage = 'Payment Reversal Executed. Please run Sync Status after sometime.';
        $camMessage = $this->helper->getConfigValue('AACH01', $store);
        $message = $camMessage? $camMessage : $codeMessage;
        $this->messageManager->addSuccess($message);
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
    }
    
    /**
     * Register worldpay model
     */
    private function _registerWorldPayModel()
    {
        $this->paymentservice->setGlobalPaymentByPaymentUpdate($this->_paymentUpdate);
    }
    
    /**
     * Apply Payment Update
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
     * Redirect Back To Order View
     */
    private function _redirectBackToOrderView()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath($this->_redirect->getRefererUrl());
        return $resultRedirect;
    }
}
