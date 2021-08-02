<?php

namespace Sapient\AccessWorldpay\Controller\Savedcard;

use Magento\Framework\App\Action\Context;
use Exception;

class Instantredirect extends \Magento\Framework\App\Action\Action
{
    protected $checkoutSession;

    public function __construct(
        Context $context,
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger,
        \Magento\Checkout\Model\Session $checkoutSession
    ) {
        $this->wplogger = $wplogger;
        $this->checkoutSession = $checkoutSession;
        parent::__construct($context);
    }

    public function execute()
    {
        $threeDSecureChallengeParams = $this->checkoutSession->get3Dsparams();
        $instantRedirectUrl = $this->_redirect->getRefererUrl();
//      $this->messageManager->getMessages(true);
        if ($threeDSecureChallengeParams) {
            $this->checkoutSession->setInstantPurchaseRedirectUrl($instantRedirectUrl);
            return $this->resultRedirectFactory->create()->setPath('worldpay/threedsecure/auth', ['_current' => true]);
        } else {
            return $this->resultRedirectFactory->create()->setUrl($instantRedirectUrl);
        }
    }
}
