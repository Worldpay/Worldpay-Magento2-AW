<?php
namespace Sapient\AccessWorldpay\Controller\Hostedpaymentpage;

class Jwt extends \Magento\Framework\App\Action\Action
{
    /**
     * @var $pageFactory
     */
    protected $_pageFactory;

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\View\Result\PageFactory $pageFactory
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $pageFactory
    ) {
        $this->_pageFactory = $pageFactory;
        return parent::__construct($context);
    }

    /**
     * Execute
     */
    public function execute()
    {
        $this->getResponse()->setNoCacheHeaders();
        return $this->_pageFactory->create();
    }
}
