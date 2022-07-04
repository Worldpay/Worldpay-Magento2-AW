<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Controller\Redirectresult;

use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Action\Context;
use \Magento\Framework\Controller\ResultFactory;

/**
 * Display page in iframe
 */
class Iframe extends \Magento\Framework\App\Action\Action
{
    /**
     * @var Magento\Framework\View\Result\PageFactory
     */
    protected $pageFactory;

    /**
     * @var \Sapient\AccessWorldpay\Model\Checkout\Hpp\State
     */
    protected $_status;

    /**
     * Constructor
     *
     * @param Context $context
     * @param PageFactory $pageFactory
     * @param \Sapient\AccessWorldpay\Model\Checkout\Hpp\State $hppstate
     * @param \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger
     * @param ResultFactory $resultFactory
     */
    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        \Sapient\AccessWorldpay\Model\Checkout\Hpp\State $hppstate,
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger,
        ResultFactory $resultFactory
    ) {
        $this->pageFactory = $pageFactory;
        $this->wplogger = $wplogger;
        $this->hppstate = $hppstate;
        $this->resultFactory = $resultFactory;
        return parent::__construct($context);
    }
    
    /**
     * Execute
     */
    public function execute()
    {
        $this->_getStatus()->reset();

        $params = $this->getRequest()->getParams();

        $redirecturl = $this->_url->getBaseUrl();

        if (isset($params['status'])) {
            $currenturl = $this->_url->getCurrentUrl();
            $redirecturl = str_replace("iframe/status/", "", $currenturl);
        }

        $resContent = '<script>window.top.location.href = "'.$redirecturl.'";</script>';
        $result = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $result->setHeader('Content-Type', 'text/html');
        $result->setContents($resContent);
        return $result;
    }

    /**
     * Get Status
     */
    protected function _getStatus()
    {
        if ($this->_status === null) {
            $this->_status = $this->hppstate;
        }

        return $this->_status;
    }
}
