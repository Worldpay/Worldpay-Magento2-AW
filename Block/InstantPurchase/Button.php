<?php

namespace Sapient\AccessWorldpay\Block\InstantPurchase;

use Magento\Framework\View\Element\Template\Context;
use Magento\InstantPurchase\Model\Config;
use Magento\Framework\View\Element\Template;
use Magento\Framework\Session\SessionManagerInterface;

/**
 * Configuration for JavaScript instant purchase button component.
 *
 * @api
 * @since 100.2.0
 */
class Button extends Template
{
    /**
     * @var Config
     */
    private $instantPurchaseConfig;

    /**
     * @var ScopeConfigInterface
     */
    protected $_scopeConfig;
    /**
     * @var SessionManagerInterface
     */
    protected $session;

    /**
     * Constructor
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\InstantPurchase\Model\Config $instantPurchaseConfig
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\Session\SessionManagerInterface $session
     * @param array $data
     */
    public function __construct(
        Context $context,
        Config $instantPurchaseConfig,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Session\SessionManagerInterface $session,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->instantPurchaseConfig = $instantPurchaseConfig;
        $this->_scopeConfig = $scopeConfig;
        $this->session = $session;
    }

    /**
     * Checks if button enabled.
     *
     * @return bool
     * @since 100.2.0
     */
    public function isEnabled(): bool
    {
        return $this->instantPurchaseConfig->isModuleEnabled($this->getCurrentStoreId());
    }

    /**
     * @inheritdoc
     * @since 100.2.0
     */
    public function getJsLayout(): string
    {
        $buttonText = $this->instantPurchaseConfig->getButtonText($this->getCurrentStoreId());
        $purchaseUrl = $this->getUrl('worldpay/button/placeOrder', ['_secure' => true]);
        
        // String data does not require escaping here and handled on transport level and on client side
        $this->jsLayout['components']['instant-purchase']['config']['buttonText'] = $buttonText;
        $this->jsLayout['components']['instant-purchase']['config']['purchaseUrl'] = $purchaseUrl;
        return parent::getJsLayout();
    }

    /**
     * Returns active store view identifier.
     *
     * @return int
     */
    private function getCurrentStoreId(): int
    {
        return $this->_storeManager->getStore()->getId();
    }
}
