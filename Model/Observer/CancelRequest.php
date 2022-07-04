<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Observer;

use Magento\Framework\Event\ObserverInterface;
use Exception;
use \Magento\Framework\Exception\LocalizedException;

class CancelRequest implements ObserverInterface
{
    
    /**
     * @var \Sapient\AccessWorldpay\Model\OmsDataFactory
     */
    protected $omsDataFactory;
    
    /**
     * @var \Sapient\AccessWorldpay\Model\Request\PaymentServiceRequest
     */
    protected $paymentservicerequest;
    
    /**
     * $request params uses
     *
     * @var $request
     */
    protected $_request;
    
    public const CURL_POST = true;
    public const CURL_RETURNTRANSFER = true;
    public const CURL_NOPROGRESS = false;
    public const CURL_TIMEOUT = 60;
    public const CURL_VERBOSE = true;
    public const SUCCESS = 200;
    
    /**
     * Constructor
     *
     * @param \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger
     * @param \Magento\Framework\App\ResponseFactory $responseFactory
     * @param \Magento\Framework\UrlInterface $url
     * @param \Sapient\AccessWorldpay\Model\OmsDataFactory $omsDataFactory
     * @param \Sapient\AccessWorldpay\Model\ResourceModel\OmsData\CollectionFactory $omsCollectionFactory
     * @param \Sapient\AccessWorldpay\Model\Request\PaymentServiceRequest $paymentservicerequest
     * @param \Sapient\AccessWorldpay\Helper\Data $worldpayHelper
     * @param \Magento\Framework\HTTP\Client\Curl $curlrequest
     */
    public function __construct(
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger,
        \Magento\Framework\App\ResponseFactory $responseFactory,
        \Magento\Framework\UrlInterface $url,
        \Sapient\AccessWorldpay\Model\OmsDataFactory $omsDataFactory,
        \Sapient\AccessWorldpay\Model\ResourceModel\OmsData\CollectionFactory $omsCollectionFactory,
        \Sapient\AccessWorldpay\Model\Request\PaymentServiceRequest $paymentservicerequest,
        \Sapient\AccessWorldpay\Helper\Data $worldpayHelper,
        \Magento\Framework\HTTP\Client\Curl $curlrequest
    ) {
        $this->wplogger = $wplogger;
        $this->_responseFactory = $responseFactory;
        $this->_url = $url;
        $this->omsDataFactory = $omsDataFactory;
        $this->omsCollectionFactory = $omsCollectionFactory;
        $this->paymentservicerequest = $paymentservicerequest;
        $this->worldpayHelper = $worldpayHelper;
        $this->curlrequest = $curlrequest;
    }
    /**
     * Load the execute method
     *
     * @param \Magento\Framework\Event\Observer $observer
     */

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        $orderIncrementId = $order->getIncrementId();
        $userName = $this->worldpayHelper->getXmlUsername();
        $password = $this->worldpayHelper->getXmlPassword();
        if ($orderIncrementId) {
            $collectionData = $this->omsCollectionFactory->create()
                ->addFieldToSelect(['awp_cancel_param'])
                ->addFieldToFilter('order_increment_id', ['eq' => $orderIncrementId]);
            $collectionData = $collectionData->getData();
            if ($collectionData) {
                $cancelUrl = $collectionData[0]['awp_cancel_param'];
                $response = $this->sendRequest($cancelUrl, $userName, $password);
            }
        }
        return true;
    }
  
    /**
     * Load the sendRequest method
     *
     * @param string $cancelUrl
     * @param string $username
     * @param string $password
     */
    public function sendRequest($cancelUrl, $username, $password)
    {
        $quote = null;
        $request = $this->_getRequest();
        $this->wplogger->info('Initialising request');
        $request->setOption(CURLOPT_POST, self::CURL_POST);
        $request->setOption(CURLOPT_RETURNTRANSFER, self::CURL_RETURNTRANSFER);
        $request->setOption(CURLOPT_NOPROGRESS, self::CURL_NOPROGRESS);
        $request->setOption(CURLOPT_TIMEOUT, self::CURL_TIMEOUT);
        $request->setOption(CURLOPT_VERBOSE, self::CURL_VERBOSE);
        /*SSL verification false*/
        $request->setOption(CURLOPT_SSL_VERIFYHOST, false);
        $request->setOption(CURLOPT_SSL_VERIFYPEER, false);
        $request->setOption(CURLOPT_POSTFIELDS, '');
        $request->setOption(CURLOPT_USERPWD, $username.':'.$password);
        // Cookie Set to 2nd 3DS request only.
        //$cookie = $this->helper->getAccessWorldpayAuthCookie();
        $request->setOption(CURLOPT_HEADER, true);
        $request->setOption(CURLOPT_HTTPHEADER, ['Content-Type: application/vnd.worldpay.payments-v6+json']);
//        $request->setOption(CURLOPT_HTTPHEADER, array(
//        "Content-Type: application/vnd.worldpay.payments-v6+json",
//        "Authorization: Basic dG5lYnY4NmJjMHlwNG9uMTpsbDAxYTcwZDdhc2xubzYy"
//         ));
        $this->wplogger->info('Sending Json as: ' . $cancelUrl);

        $request->setOption(CURLINFO_HEADER_OUT, true);
        $request->post($cancelUrl, $quote);
        $result = $request->getBody();
        //$result = $request->execute();

        if (!$result) {
            $this->wplogger->info('Request could not be sent.');
            $this->wplogger->info($result);
            $this->wplogger->info(
                '########### END OF REQUEST - FAILURE WHILST TRYING TO SEND REQUEST ###########'
            );
            throw new \Magento\Framework\Exception\LocalizedException(
                'AccessWorldpay api service not available'
            );
        }
        $this->wplogger->info('Request successfully sent');
        $this->wplogger->info($result);
    }
    
    /**
     * Get Request method
     */
    private function _getRequest()
    {
        if ($this->_request === null) {
            $this->_request = $this->curlrequest;
        }
        
        return $this->_request;
    }
}
