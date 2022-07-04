<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Controller\Savedcard;

use Magento\Framework\App\Action\Context;

/**
 * Controller for List Customer saved credit cards
 */
class Addnewcard extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $_resultPageFactory;
    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;
    
    /**
     * Constructor
     *
     * @param Context $context
     * @param \Magento\Framework\View\Result\PageFactory $resultPageFactory
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Sapient\AccessWorldpay\Helper\Data $worldpayHelper
     */
    public function __construct(
        Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Customer\Model\Session $customerSession,
        \Sapient\AccessWorldpay\Helper\Data $worldpayHelper
    ) {
        parent::__construct($context);
        $this->_resultPageFactory = $resultPageFactory;
        $this->customerSession = $customerSession;
        $this->worldpayHelper = $worldpayHelper;
    }
    
    /**
     * List Saved credit Card
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        if (!$this->customerSession->isLoggedIn()) {
            $this->_redirect('customer/account/login');
            return;
        }
        $resultPage = $this->_resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set($this->worldpayHelper->getAccountLabelbyCode('AC11'));
        return $resultPage;
    }
}
