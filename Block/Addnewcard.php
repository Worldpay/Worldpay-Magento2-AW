<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Block;

class Addnewcard extends \Magento\Framework\View\Element\Template
{
    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $_customerSession;
    /**
     * @var array
     */
    protected static $_months;
     /**
      * @var array
      */
    protected static $_expiryYears;
    
    /**
     * Constructor
     *
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Sapient\AccessWorldpay\Helper\Data $worldpayHelper
     * @param \Magento\Customer\Helper\Session\CurrentCustomerAddress $currentCustomerAddress
     * @param \Magento\Customer\Model\Address\Config $addressConfig
     * @param \Magento\Customer\Model\Address\Mapper $addressMapper
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     * @param \Magento\Integration\Model\Oauth\TokenFactory $tokenModelFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Sapient\AccessWorldpay\Helper\Data $worldpayHelper,
        \Magento\Customer\Helper\Session\CurrentCustomerAddress $currentCustomerAddress,
        \Magento\Customer\Model\Address\Config $addressConfig,
        \Magento\Customer\Model\Address\Mapper $addressMapper,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Message\ManagerInterface $messageManager,
        \Magento\Integration\Model\Oauth\TokenFactory $tokenModelFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        array $data = []
    ) {
        $this->_customerSession = $customerSession;
        $this->worldpayHelper = $worldpayHelper;
        $this->currentCustomerAddress = $currentCustomerAddress;
        $this->_addressConfig = $addressConfig;
        $this->addressMapper = $addressMapper;
        $this->scopeConfig = $scopeConfig;
        $this->_messageManager = $messageManager;
        $this->_tokenModelFactory = $tokenModelFactory;
        $this->_storeManager = $storeManager;
        parent::__construct($context, $data);
    }
    /**
     * Get Cvc Enabled
     *
     * @return string
     */
    public function requireCvcEnabled()
    {
        return $this->worldpayHelper->isCcRequireCVC();
    }
    
    /**
     * Get Store code
     *
     * @return string
     */
    public function getStoreCode()
    {
        return $this->_storeManager->getStore()->getCode();
    }
    /**
     * Get SessionId
     *
     * @return string
     */
    public function getSessionId()
    {
        return $this->_customerSession->getSessionId();
    }
    /**
     * Get Customer Token
     *
     * @return string
     */
    public function getCustomerToken()
    {
        $customerId = $this->_customerSession->getCustomer()->getId();
        $customerToken = $this->_tokenModelFactory->create();
        return $customerToken->createCustomerToken($customerId)->getToken();
    }
    /**
     * Get Primary Billing Address
     *
     * @return string
     */
    public function getPrimaryBillingAddressHtml()
    {
        /** @var \Magento\Customer\Block\Address\Renderer\RendererInterface $renderer */
        $address = $this->currentCustomerAddress->getDefaultBillingAddress();
        if ($address) {
            $renderer = $this->_addressConfig->getFormatByCode('html')->getRenderer();
            return $renderer->renderArray($this->addressMapper->toFlatArray($address));
        } else {
            return $this->escapeHtml(__('You have not set a default billing address.'));
        }
    }
    /**
     * Get CreditCardType
     *
     * @return string
     */
    public function getCCtypes()
    {
        $cctypes = $this->worldpayHelper->getCcTypes();
        return $cctypes;
    }
    /**
     * Helps to build year html dropdown
     *
     * @return array
     */
    public function getMonths()
    {
        if (!self::$_months) {
            self::$_months = ['' => __('Month')];
            for ($i = 1; $i < 13; $i++) {
                $month = str_pad($i, 2, '0', STR_PAD_LEFT);
                self::$_months[$month] = date("$i - F", mktime(0, 0, 0, $i, 1));
            }
        }
        return self::$_months;
    }
    /**
     * Helps to build year html dropdown
     *
     * @return array
     */
    public function getExpiryYears()
    {
        if (!self::$_expiryYears) {
            self::$_expiryYears = ['' => __('Year')];
            $year = date('Y');
            $endYear = ($year + 20);
            while ($year < $endYear) {
                self::$_expiryYears[$year] = $year;
                $year++;
            }
        }
        return self::$_expiryYears;
    }

    /**
     * IsDisclaimer Enabled
     */
    public function getDisclaimerMessageEnable()
    {
        return $this->worldpayHelper->isDisclaimerMessageEnable();
    }

    /**
     * Get Disclaimer Message
     */
    public function getDisclaimerText()
    {
        return $this->worldpayHelper->getDisclaimerMessage();
    }
    
    /**
     * IsDisclaimer Mandatory
     */
    public function getDisclaimerMessageMandatory()
    {
        return $this->worldpayHelper->isDisclaimerMessageMandatory();
    }
    
    /**
     * IsCvcRequired
     */
    public function getIsCvcRequired()
    {
        return $this->worldpayHelper->isCcRequireCVC();
    }
    
    /**
     * Get Account Alert
     *
     * @param string $alertCode
     * @return string
     */
    public function getAccountAlert($alertCode)
    {
        return $this->worldpayHelper->getMyAccountSpecificexception($alertCode);
    }

    /**
     * Get Account Label
     *
     * @param string $labelCode
     * @return string
     */
    public function getAccountLabelbyCode($labelCode)
    {
        return $this->worldpayHelper->getAccountLabelbyCode($labelCode);
    }
}
