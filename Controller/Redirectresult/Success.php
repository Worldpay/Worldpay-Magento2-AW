<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Controller\Redirectresult;

use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Action\Context;
 
 /**
  * remove authorized order from card and Redirect to success page
  */
class Success extends \Magento\Framework\App\Action\Action
{
    /**
     * @var Magento\Framework\View\Result\PageFactory
     */
    protected $pageFactory;

    /**
     * Constructor
     *
     * @param Context $context
     * @param PageFactory $pageFactory
     * @param \Sapient\AccessWorldpay\Model\Order\Service $orderservice
     * @param \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger
     */
    public function __construct(
        Context $context,
        PageFactory $pageFactory,
        \Sapient\AccessWorldpay\Model\Order\Service $orderservice,
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger
    ) {
        $this->pageFactory = $pageFactory;
        $this->orderservice = $orderservice;
        $this->wplogger = $wplogger;
        return parent::__construct($context);
    }
    
    /**
     * Execute
     */
    public function execute()
    {
        $this->wplogger->info('worldpay returned success url');
        $this->orderservice->redirectOrderSuccess();
        $this->orderservice->removeAuthorisedOrder();
        return $this->resultRedirectFactory->create()->setPath('checkout/onepage/success', ['_current' => true]);
    }
}
