<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Controller\Notification;

use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Exception;
use Magento\Framework\Exception\LocalizedException;

class Index extends \Magento\Framework\App\Action\Action
{
    /**
     * @var Magento\Framework\View\Result\PageFactory
     */
    protected $pageFactory;

    /**
     * @var $rawBody
     */
    protected $_rawBody;
    /**
     * @var \Sapient\AccessWorldpay\Model\HistoryNotificationFactory
     */
    protected $historyNotification;
    /**
     * @var $fileDriver
     */
    protected $fileDriver;

    private const RESPONSE_OK = '[OK]';
    private const RESPONSE_FAILED = '[FAILED]';

    /**
     * Constructor
     *
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger
     * @param \Sapient\AccessWorldpay\Model\Payment\Service $paymentservice
     * @param \Sapient\AccessWorldpay\Model\Order\Service $orderservice
     * @param \Sapient\AccessWorldpay\Model\Response\DirectResponse $directResponse
     * @param \Sapient\AccessWorldpay\Model\Request $request
     * @param \Sapient\AccessWorldpay\Model\PaymentMethods\PaymentOperations $paymentoperations
     * @param \Sapient\AccessWorldpay\Model\HistoryNotificationFactory $historyNotification
     * @param \Magento\Framework\Filesystem\DriverInterface $fileDriver
     */
    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger,
        \Sapient\AccessWorldpay\Model\Payment\Service $paymentservice,
        \Sapient\AccessWorldpay\Model\Order\Service $orderservice,
        \Sapient\AccessWorldpay\Model\Response\DirectResponse $directResponse,
        \Sapient\AccessWorldpay\Model\Request $request,
        \Sapient\AccessWorldpay\Model\PaymentMethods\PaymentOperations $paymentoperations,
        \Sapient\AccessWorldpay\Model\HistoryNotificationFactory $historyNotification,
        \Magento\Framework\Filesystem\DriverInterface $fileDriver
    ) {
        parent::__construct($context);
        $this->wplogger = $wplogger;
        $this->paymentservice = $paymentservice;
        $this->orderservice = $orderservice;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->historyNotification = $historyNotification;
        $this->directResponse = $directResponse;
        $this->request = $request;
        $this->paymentoperations = $paymentoperations;
        $this->fileDriver = $fileDriver;
    }

    /**
     * Execute
     */
    public function execute()
    {
        $this->wplogger->info('notification index url hit');
        try {
            $xmlRequest = simplexml_load_string($this->_getRawBody());

            if ($xmlRequest instanceof \SimpleXMLElement) {
                $this->updateNotification($xmlRequest);
                $this->_createPaymentUpdate($xmlRequest);
                $this->_loadOrder();
                $this->_tryToApplyPaymentUpdate();
                $this->_updateOrderStatus();
                return $this->_returnOk();
            } else {

                $this->wplogger->error('Not a valid xml');
            }
        } catch (Exception $e) {
            $this->wplogger->error($e->getMessage());
            if ($e->getMessage() == 'invalid state transition' || $e->getMessage() == 'same state') {
                return $this->_returnOk();
            } else {
                return $this->_returnFailure();
            }
        }
    }

    /**
     * Get raw body
     */
    public function _getRawBody()
    {
        if (null === $this->_rawBody) {
          //  $body = file_get_contents('php://input');
            $body = $this->fileDriver->fileGetContents('php://input');
            if (strlen(trim($body)) > 0) {
                $this->_rawBody = $body;
            } else {
                $this->_rawBody = false;
            }
        }
        $this->wplogger->info("inside Notification-->getRawBody");
        $jsonData = json_decode($this->_rawBody, true);
        $xml = $this->request->_array2xml($jsonData, false);
//        $response = $this->directResponse->setResponse($xml);
//        $response->getXml();
       // $this->wplogger->info(print_r($response->getXml(),true));
        return $xml;
    }

    /**
     * Create Payment update
     *
     * @param xml|string $xmlRequest
     */
    private function _createPaymentUpdate($xmlRequest)
    {
        $this->_paymentUpdate = $this->paymentservice
            ->createPaymentUpdateFromWorldPayXml($xmlRequest);

        $this->_logNotification();
    }

    /**
     * Log notification
     */
    private function _logNotification()
    {
        $this->wplogger->info('########## Received notification ##########');
        $this->wplogger->info($this->_getRawBody());
        $this->wplogger->info('########## Payment update of type: '
                . get_class($this->_paymentUpdate). ' created ##########');
    }

    /**
     * Get order code
     */
    private function _loadOrder()
    {
        $orderCode = $this->_paymentUpdate->getTargetOrderCode();
        $orderIncrementId = current(explode('-', $orderCode));

        $this->_order = $this->orderservice->getByIncrementId($orderIncrementId);
    }

    /**
     * Try to apply payment update
     */
    private function _tryToApplyPaymentUpdate()
    {
        try {
            $this->_paymentUpdate->apply($this->_order->getPayment(), $this->_order);
        } catch (Exception $e) {
            $this->wplogger->error($e->getMessage());
            throw new \Magento\Framework\Exception\LocalizedException(__($e->getMessage()));
        }
    }

    /**
     * Return ok
     */
    public function _returnOk()
    {
        $resultJson = $this->resultJsonFactory->create();
        $resultJson->setHttpResponseCode(200);
        $resultJson->setData(self::RESPONSE_OK);
        return $resultJson;
    }

    /**
     * Return Failure
     */
    public function _returnFailure()
    {
        $resultJson = $this->resultJsonFactory->create();
        $resultJson->setHttpResponseCode(500);
        $resultJson->setData(self::RESPONSE_FAILED);
        return $resultJson;
    }

    /**
     * Save Notification
     *
     * @param string|xml $xml
     */
    private function updateNotification($xml)
    {
        $statusNode=$xml->result->eventDetails;
        $orderCode="";
        $paymentStatus="";
        if (isset($statusNode['transactionReference'])) {
            list($orderCode, $ordercode_last) = explode("-", $statusNode['transactionReference']);
        }
        if (isset($statusNode->type)) {
                $paymentStatus=$statusNode->type;
        }
        $hn = $this->historyNotification->create();
        $hn->setData('status', $paymentStatus);
        $hn->setData('order_id', trim($orderCode));
        $hn->save();
    }
    
    /**
     * Update order status
     */
    private function _updateOrderStatus()
    {
        $this->paymentoperations->updateOrderStatus($this->_order);
    }
}
