<?php
/**
 * @copyright 2021 FIS
 */
namespace Sapient\AccessWorldpay\Helper;

use Sapient\AccessWorldpay\Model\Config\Source\HppIntegration as HPPI;
use Sapient\AccessWorldpay\Model\Config\Source\IntegrationMode as IM;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\App\Filesystem\DirectoryList;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * Core store config
     *
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;
    /**
     * @var $wplogger
     */
    protected $wplogger;
    /**
     * @var $storeManager
     */
    protected $_storeManager;
    /**
     * @var $filesystem
     */
    protected $_filesystem;

    /**
     * Serializer variable
     *
     * @var $serializer
     */
    private $serializer;
    private const MERCHANT_CONFIG = 'worldpay/merchant_config/';
    private const INTEGRATION_MODE = 'worldpay/cc_config/integration_mode';

    /**
     * Constructer
     *
     * @param \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\Locale\CurrencyInterface $localeCurrency
     * @param \Sapient\AccessWorldpay\Model\Utilities\PaymentMethods $paymentlist
     * @param \Magento\Sales\Model\OrderFactory $orderFactory
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Sapient\AccessWorldpay\Model\SavedTokenFactory $savecard
     * @param SerializerInterface $serializer
     * @param \Magento\Framework\App\ProductMetadataInterface $productmetadata
     * @param \Magento\Vault\Model\PaymentTokenManagement $paymentTokenManagement
     * @param \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory
     * @param \Sapient\AccessWorldpay\Model\AccessWorldpaymentFactory $worldpaypayment
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Locale\CurrencyInterface $localeCurrency,
        \Sapient\AccessWorldpay\Model\Utilities\PaymentMethods $paymentlist,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Sapient\AccessWorldpay\Model\SavedTokenFactory $savecard,
        SerializerInterface $serializer,
        \Magento\Framework\App\ProductMetadataInterface $productmetadata,
        \Magento\Vault\Model\PaymentTokenManagement $paymentTokenManagement,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        \Sapient\AccessWorldpay\Model\AccessWorldpaymentFactory $worldpaypayment,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->_scopeConfig = $scopeConfig;
        $this->wplogger = $wplogger;
        $this->paymentlist = $paymentlist;
        $this->localecurrency = $localeCurrency;
        $this->_checkoutSession = $checkoutSession;
        $this->orderFactory = $orderFactory;
        $this->_savecard = $savecard;
        $this->serializer = $serializer;
        $this->paymentTokenManagement = $paymentTokenManagement;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->worldpaypayment = $worldpaypayment;
        $this->productmetadata = $productmetadata;
        $this->_storeManager = $storeManager;
        $this->_filesystem = $filesystem;
    }

    /**
     * Is WorldPay Enable
     */
    public function isWorldPayEnable()
    {
        return (bool) $this->_scopeConfig->getValue(
            'worldpay/general_config/enable_worldpay',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get env mode
     */
    public function getEnvironmentMode()
    {
        return $this->_scopeConfig->getValue(
            'worldpay/general_config/environment_mode',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get test URL
     */
    public function getTestUrl()
    {
        return  $this->_scopeConfig->getValue(
            'worldpay/general_config/test_url',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get Live URL
     */
    public function getLiveUrl()
    {
        return  $this->_scopeConfig->getValue(
            'worldpay/general_config/live_url',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get merchant Code
     */
    public function getMerchantCode()
    {
        return  $this->_scopeConfig->getValue(
            'worldpay/general_config/merchant_code',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get Merchant Identity
     */
    public function getMerchantIdentity()
    {
        return  $this->_scopeConfig->getValue(
            'worldpay/general_config/merchant_identity',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get XML Username
     */
    public function getXmlUsername()
    {
        return  $this->_scopeConfig->getValue(
            'worldpay/general_config/xml_username',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get XML Password
     */
    public function getXmlPassword()
    {
        return  $this->_scopeConfig->getValue(
            'worldpay/general_config/xml_password',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get Merchant Entity Refrerence
     */
    public function getMerchantEntityReference()
    {
        return  $this->_scopeConfig->getValue(
            'worldpay/general_config/merchant_entity',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get Narrative
     */
    public function getNarrative()
    {
        return  $this->_scopeConfig->getValue(
            'worldpay/general_config/narrative',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Is Mac Enable
     */
    public function isMacEnabled()
    {
        return  $this->_scopeConfig->getValue(
            'worldpay/general_config/mac_enabled',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get Mac Screen
     */
    public function getMacSecret()
    {
        return  $this->_scopeConfig->getValue(
            'worldpay/general_config/mac_secret',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Is Logger Enable
     */
    public function isLoggerEnable()
    {
        return (bool) $this->_scopeConfig->getValue(
            'worldpay/general_config/enable_logging',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Is CreditCard Enabled
     */
    public function isCreditCardEnabled()
    {
        return (bool) $this->_scopeConfig->getValue(
            'worldpay/cc_config/enabled',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get Cc Title
     */
    public function getCcTitle()
    {
        return  $this->_scopeConfig->getValue(
            'worldpay/cc_config/title',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get Cc Types
     *
     * @param string $paymentconfig
     */
    public function getCcTypes($paymentconfig = "cc_config")
    {
        $allCcMethods =  [
            'AMEX-SSL'=>'American Express','VISA-SSL'=>'Visa',
            'ECMC-SSL'=>'MasterCard','DISCOVER-SSL'=>'Discover',
            'DINERS-SSL'=>'Diners','MAESTRO-SSL'=>'Maestro','AIRPLUS-SSL'=>'AirPlus',
            'AURORE-SSL'=>'Aurore','CB-SSL'=>'Carte Bancaire',
            'CARTEBLEUE-SSL'=>'Carte Bleue','DANKORT-SSL'=>'Dankort',
            'GECAPITAL-SSL'=>'GE Capital','JCB-SSL'=>'Japanese Credit Bank',
            'LASER-SSL'=>'Laser Card','UATP-SSL'=>'UATP',
        ];
        $configMethods =   explode(',', $this->_scopeConfig->getValue(
            'worldpay/'.$paymentconfig.'/paymentmethods',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        ));
        $activeMethods = [];
        foreach ($configMethods as $method) {
            $activeMethods[$method] = $allCcMethods[$method];
        }
        return $activeMethods;
    }

    /**
     * Is CC Required CVC
     */
    public function isCcRequireCVC()
    {
            return (bool) $this->_scopeConfig->getValue(
                'worldpay/cc_config/require_cvc',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            );
    }

    /**
     * Get saved card
     */
    public function getSaveCard()
    {
        return (bool) $this->_scopeConfig->getValue(
            'worldpay/cc_config/saved_card',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get tokenization
     */
    public function getTokenization()
    {
        return (bool) true;
    }

    /**
     * Get Cc integration mode
     */
    public function getCcIntegrationMode()
    {
        if ($this->isWebSdkIntegrationMode()) {
            return $this->_scopeConfig->getValue(
                'worldpay/cc_config/integration_mode',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            );
        } else {
            return IM::OPTION_VALUE_DIRECT;
        }
    }

    /**
     * Get pyment method section
     */
    public function getPaymentMethodSelection()
    {
        return  $this->_scopeConfig->getValue(
            'worldpay/general_config/payment_method_selection',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Is web sdk integration mode
     */
    public function isWebSdkIntegrationMode()
    {
        return $this->_scopeConfig->
                getValue(
                    'worldpay/cc_config/integration_mode',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                ) == IM::OPTION_VALUE_WEBSDK;
    }

    /**
     * Get Integration Model By Payment Method Code
     *
     * @param string $paymentMethodCode
     * @param string|int $storeId
     */
    public function getIntegrationModelByPaymentMethodCode($paymentMethodCode, $storeId)
    {
        if ($this->isWebSdkIntegrationMode() && $paymentMethodCode == 'worldpay_cc') {
            return $this->_scopeConfig->getValue(
                'worldpay/cc_config/integration_mode',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            );
        } else {
            return IM::OPTION_VALUE_DIRECT;
        }
    }

    /**
     * Is Iframe Integration
     *
     * @param string|int $storeId
     */
    public function isIframeIntegration($storeId = null)
    {
        return $this->_scopeConfig->getValue(
            'worldpay/cc_config/hpp_integration',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        ) == HPPI::OPTION_VALUE_IFRAME;
    }

    /**
     * Get Redirect IntegrationMode
     *
     * @param string|int $storeId
     */
    public function getRedirectIntegrationMode($storeId = null)
    {
        return $this->_scopeConfig->getValue(
            'worldpay/cc_config/hpp_integration',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get customer payment enable
     *
     * @param string|int $storeId
     */
    public function getCustomPaymentEnabled($storeId = null)
    {
        return $this->_scopeConfig->getValue(
            'worldpay/custom_paymentpages/enabled',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get Instllation ID
     *
     * @param string|int $storeId
     */
    public function getInstallationId($storeId = null)
    {
        return $this->_scopeConfig->getValue(
            'worldpay/custom_paymentpages/installation_id',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get Dynamic  Integration
     *
     * @param string|int $paymentMethodCode
     */
    public function getDynamicIntegrationType($paymentMethodCode)
    {
        return 'ECOMMERCE';
    }

    /**
     * Update error message
     *
     * @param string $message
     * @param string|int $orderid
     */
    public function updateErrorMessage($message, $orderid)
    {
        $updatemessage = [
            'Payment REFUSED' => sprintf($this->getCreditCardSpecificexception('CCAM11'), $orderid),
            'Gateway error' => $this->getCreditCardSpecificexception('CCAM12')

        ];
        if (array_key_exists($message, $updatemessage)) {
            return $updatemessage[$message];
        }

        if (empty($message)) {

            $message = $this->getCreditCardSpecificexception('CCAM12');
        }
        return $message;
    }

    /**
     * Get access worldpay AUTH cookie
     */
    public function getAccessWorldpayAuthCookie()
    {
        return $this->_checkoutSession->getAccessWorldpayAuthCookie();
    }

    /**
     * Set access worldpay AUTH cookie
     *
     * @param string $value
     */
    public function setAccessWorldpayAuthCookie($value)
    {
         return $this->_checkoutSession->setAccessWorldpayAuthCookie($value);
    }

    /**
     * Is 3ds  enable
     */
    public function is3DSecureEnabled()
    {
        return (bool) $this->_scopeConfig->getValue(
            'worldpay/3ds_config/do_3Dsecure',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get challange window size
     */
    public function getChallengeWindowSize()
    {
            return $this->_scopeConfig->getValue(
                'worldpay/3ds_config/challenge_window_size',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            );
    }

    /**
     * Get Default country
     *
     * @param string|int|null $storeId
     */
    public function getDefaultCountry($storeId = null)
    {
        return $this->_scopeConfig->getValue(
            'shipping/origin/country_id',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get Local Default
     *
     * @param string|int|null $storeId
     */
    public function getLocaleDefault($storeId = null)
    {
        return $this->_scopeConfig->getValue(
            'general/locale/code',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get currency symbol
     *
     * @param string $currencycode
     */
    public function getCurrencySymbol($currencycode)
    {
        return $this->localecurrency->getCurrency($currencycode)->getSymbol();
    }

    /**
     * Get quantity unite
     *
     * @param string $product
     */
    public function getQuantityUnit($product)
    {
        return 'product';
    }

    /**
     * Check stop auto invoice
     *
     * @param string $code
     * @param string $type
     */
    public function checkStopAutoInvoice($code, $type)
    {
        return $this->paymentlist->checkStopAutoInvoice($code, $type);
    }

    /**
     * Is 3ds request
     */
    public function isThreeDSRequest()
    {
        return $this->_checkoutSession->getIs3DSRequest();
    }

    /**
     * Get websdk js path
     */
    public function getWebSdkJsPath()
    {
        $envMode = $this->getEnvironmentMode();
        if ($envMode == 'Test Mode') {
            return $this->_scopeConfig->getValue(
                'worldpay/cc_config/test_websdk_url',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            );
        } else {
            return $this->_scopeConfig->getValue(
                'worldpay/cc_config/live_websdk_url',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            );
        }
    }

    /**
     * Get order description
     */
    public function getOrderDescription()
    {
        return $this->_scopeConfig->getValue(
            'worldpay/general_config/order_description',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Instant purchase enabled
     */
    public function instantPurchaseEnabled()
    {
        $instantPurchaseEnabled = false;
        $caseSensitiveVal = trim($this->getCcIntegrationMode());
        $caseSensVal  = strtoupper($caseSensitiveVal);
        $isSavedCardEnabled = $this->getSaveCard();
        if ($isSavedCardEnabled) {
            $instantPurchaseEnabled = (bool) $this->_scopeConfig->
                getValue(
                    'worldpay/quick_checkout_config/instant_purchase',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                );
        }
        return $instantPurchaseEnabled;
    }

    /**
     * Get order by order id
     *
     * @param string|int $orderId
     */
    public function getOrderByOrderId($orderId)
    {
        return $this->orderFactory->create()->load($orderId);
    }

    /**
     * Get payemnt title for orders
     *
     * @param string $order
     * @param string $paymentCode
     * @param \Sapient\AccessWorldpay\Model\AccessWorldpaymentFactory $worldpaypayment
     */
    public function getPaymentTitleForOrders(
        $order,
        $paymentCode,
        \Sapient\AccessWorldpay\Model\AccessWorldpaymentFactory $worldpaypayment
    ) {
        $order_id = $order->getIncrementId();
        $wpp = $worldpaypayment->create();
        $item = $wpp->loadByPaymentId($order_id);
        if ($paymentCode == 'worldpay_cc' || $paymentCode == 'worldpay_cc_vault') {
            return $this->getCcTitle() . "\n" . $item->getPaymentType();
        } elseif ($paymentCode == 'worldpay_apm') {
            return $this->getApmTitle() . "\n" . $item->getPaymentType();
        } elseif ($paymentCode == 'worldpay_wallets') {
            return $this->getWalletsTitle() . "\n" . $item->getPaymentType();
        } elseif ($paymentCode == 'worldpay_moto') {
            return $this->getMotoTitle() . "\n" . $item->getPaymentType();
        }
    }

    /**
     * Get card type
     *
     * @param string|int $cardNumber
     */
    public function getCardType($cardNumber)
    {
        switch ($cardNumber) {
            case (preg_match('/^4/', $cardNumber) >= 1):
                return 'VISA-SSL';
            case (preg_match(
                '/^(5[1-5][0-9]{0,2}|222[1-9]|22[3-9][0-9]|2[3-6][0-9]{0,2}|27[01][0-9]|2720)[0-9]{0,12}/',
                $cardNumber
            ) >= 1):
                return 'ECMC-SSL';
            case (preg_match('/^3[47]/', $cardNumber) >= 1):
                return 'AMEX-SSL';
            case (preg_match('/^36/', $cardNumber) >= 1):
                return 'DINERS-SSL';
            case (preg_match('/^30[0-5]/', $cardNumber) >= 1):
                return 'DINERS-SSL';
            case (preg_match('/^6(?:011|5)/', $cardNumber) >= 1):
                return 'DISCOVER-SSL';
            case (preg_match('/^35(2[89]|[3-8][0-9])/', $cardNumber) >= 1):
                return 'JCB-SSL';
            case (preg_match('/^62|88/', $cardNumber) >= 1):
                return 'CHINAUNIONPAY-SSL';
            case (preg_match('/^([6011]{4})([0-9]{12})/', $cardNumber) >= 1):
                return 'DISCOVER-SSL';
            case (preg_match('/^(5[06789]|6)[0-9]{0,}/', $cardNumber) >= 1):
                return 'MAESTRO-SSL';
            default:
                break;
        }
    }

    /**
     * Get Disclaimer Message
     */
    public function getDisclaimerMessage()
    {
        return $this->_scopeConfig->getValue(
            'worldpay/cc_config/configure_disclaimer/stored_credentials_disclaimer_message',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * IsDiscliamer Setting Enabled
     */
    public function isDisclaimerMessageEnable()
    {
        return (bool) $this->_scopeConfig->getValue(
            'worldpay/cc_config/configure_disclaimer/stored_credentials_message_enable',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * IsDiscliamer Message Mandatory
     */
    public function isDisclaimerMessageMandatory()
    {
        return (bool) $this->_scopeConfig->getValue(
            'worldpay/cc_config/configure_disclaimer/stored_credentials_flag',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Returns saved card token data
     *
     * @param string|int $tokenId
     *
     * @return saved card token value
     */
    public function getSelectedSavedCardTokenData($tokenId)
    {
        $selectedsavedCard = $this->_savecard->create()->getCollection()
                        ->addFieldToSelect(['token','cardonfile_auth_link','card_brand'])
                        ->addFieldToFilter('token_id', ['eq' => $tokenId]);

        $tokenData = $selectedsavedCard->getData();
        return $tokenData;
    }

    /**
     * Get my account exception
     */
    public function getMyAccountException()
    {
                return $this->_scopeConfig->getValue(
                    'worldpay_exceptions/my_account_alert_codes/response_codes',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                );
    }

    /**
     * Get My account specific exception
     *
     * @param string|int $exceptioncode
     */
    public function getMyAccountSpecificexception($exceptioncode)
    {

        $ccdata=$this->serializer->unserialize($this->getMyAccountException());
        if (is_array($ccdata) || is_object($ccdata)) {
            foreach ($ccdata as $key => $valuepair) {
                if ($key == $exceptioncode) {
                    return $valuepair['exception_module_messages']?$valuepair['exception_module_messages']:
                        $valuepair['exception_messages'];
                }
            }
        }
    }

    /**
     * Getcredit card exception
     */
    public function getCreditCardException()
    {
                return $this->_scopeConfig->getValue(
                    'worldpay_exceptions/ccexceptions/cc_exception',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                );
    }

    /**
     * Getcredit card specific exception
     *
     * @param string|int $exceptioncode
     */
    public function getCreditCardSpecificexception($exceptioncode)
    {

        $ccdata=$this->serializer->unserialize($this->getCreditCardException());
        if (is_array($ccdata) || is_object($ccdata)) {
            foreach ($ccdata as $key => $valuepair) {
                if ($key == $exceptioncode) {
                    return $valuepair['exception_module_messages']?$valuepair['exception_module_messages']:
                        $valuepair['exception_messages'];
                }
            }
        }
    }

    /**
     * Get general exception
     */
    public function getGeneralException()
    {
               return $this->_scopeConfig->getValue('worldpay_exceptions/adminexceptions/'
                       . 'general_exception', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
    }

    /**
     * Get token from value
     *
     * @param string $hash
     * @param string|int $customerId
     */
    public function getTokenFromVault($hash, $customerId)
    {
        $vaultData = $this->paymentTokenManagement->getByPublicHash($hash, $customerId);
        $tokenId = $vaultData['gateway_token'];
        $tokenData = $this->getSelectedSavedCardTokenData($tokenId);
        return $tokenData[0]['token'];
    }

    /**
     * Get my account lables
     */
    public function getMyAccountLabels()
    {
                return $this->_scopeConfig->getValue(
                    'worldpay_custom_labels/my_account_labels/my_account_label',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                );
    }

    /**
     * Get account label by code
     *
     * @param string|int $labelCode
     */
    public function getAccountLabelbyCode($labelCode)
    {
        $aLabels = $this->serializer->unserialize($this->getMyAccountLabels());
        if (is_array($aLabels) || is_object($aLabels)) {
            foreach ($aLabels as $key => $valuepair) {
                if ($key == $labelCode) {
                    return $valuepair['wpay_custom_label']?$valuepair['wpay_custom_label']:
                    $valuepair['wpay_label_desc'];
                }
            }
        }
    }

    /**
     * Get checkout labels
     */
    public function getCheckoutLabels()
    {
                return $this->_scopeConfig->getValue(
                    'worldpay_custom_labels/checkout_labels/checkout_label',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                );
    }

    /**
     * Get checkout lable by code
     *
     * @param string|int $labelCode
     */
    public function getCheckoutLabelbyCode($labelCode)
    {
        $aLabels = $this->serializer->unserialize($this->getCheckoutLabels());
        if (is_array($aLabels) || is_object($aLabels)) {
            foreach ($aLabels as $key => $valuepair) {
                if ($key == $labelCode) {
                    return $valuepair['wpay_custom_label']?$valuepair['wpay_custom_label']:
                    $valuepair['wpay_label_desc'];
                }
            }
        }
    }

    /**
     * Get admin labels
     */
    public function getAdminLabels()
    {
                return $this->_scopeConfig->getValue(
                    'worldpay_custom_labels/admin_labels/admin_label',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                );
    }

    /**
     * Check if token exist
     *
     * @param string $token
     */
    public function checkIfTokenExists($token)
    {
        $selectedsavedCard = $this->_savecard->create()->getCollection()
                        ->addFieldToSelect('token')
                        ->addFieldToFilter('token', ['eq' => $token]);

        $tokenData = $selectedsavedCard->getData();
        if (!empty($tokenData)) {
            return true;
        }
        return false;
    }

    /**
     * Is wallet enable
     */
    public function isWalletsEnabled()
    {
        return  $this->_scopeConfig->getValue(
            'worldpay/wallets_config/enabled',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get wallet title
     */
    public function getWalletsTitle()
    {
        return  $this->_scopeConfig->getValue(
            'worldpay/wallets_config/title',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Is google pay enable
     */
    public function isGooglePayEnable()
    {
        return (bool) $this->_scopeConfig->getValue(
            'worldpay/wallets_config/google_pay_wallets_config/enabled',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Google pay methods
     */
    public function googlePaymentMethods()
    {
        return $this->_scopeConfig->getValue(
            'worldpay/wallets_config/google_pay_wallets_config/paymentmethods',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Google auth methods
     */
    public function googleAuthMethods()
    {
        return $this->_scopeConfig->getValue(
            'worldpay/wallets_config/google_pay_wallets_config/authmethods',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Google getway merchant name
     */
    public function googleGatewayMerchantname()
    {
        return $this->_scopeConfig->getValue(
            'worldpay/wallets_config/google_pay_wallets_config/gateway_merchantname',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Google gateway merchant id
     */
    public function googleGatewayMerchantid()
    {
        return $this->_scopeConfig->getValue(
            'worldpay/wallets_config/google_pay_wallets_config/gateway_merchantid',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Google Merchant name
     */
    public function googleMerchantname()
    {
        return $this->_scopeConfig->getValue(
            'worldpay/wallets_config/google_pay_wallets_config/google_merchantname',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Google merchant id
     */
    public function googleMerchantid()
    {
        return $this->_scopeConfig->getValue(
            'worldpay/wallets_config/google_pay_wallets_config/google_merchantid',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Is apple pay enable
     */
    public function isApplePayEnable()
    {
        return (bool) $this->_scopeConfig->getValue(
            'worldpay/wallets_config/apple_pay_wallets_config/enabled',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Apple merchant id
     */
    public function appleMerchantId()
    {
        return $this->_scopeConfig->getValue(
            'worldpay/wallets_config/apple_pay_wallets_config/merchant_name',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get walletes type
     *
     * @param string $code
     */
    public function getWalletsTypes($code)
    {
        $activeMethods = [];
        if ($this->isGooglePayEnable()) {
            $activeMethods['PAYWITHGOOGLE-SSL'] = 'Google Pay';
        }
        if ($this->isApplePayEnable()) {
            $activeMethods['APPLEPAY-SSL'] = 'Apple Pay';
        }
        return $activeMethods;
    }

    /**
     * Get the first order details of customer by email
     *
     * @param string $customerEmailId
     * @return array Order Item data
     */
    public function getOrderDetailsByEmailId($customerEmailId)
    {
        $itemData = $this->orderCollectionFactory->create()->addAttributeToFilter(
            'customer_email',
            $customerEmailId
        )->getFirstItem()->getData();
        return $itemData;
    }

    /**
     * Get the orders count of customer by email
     *
     * @param string $customerEmailId
     * @return array List of order data
     */
    public function getOrdersCountByEmailId($customerEmailId)
    {
        $lastDayInterval = new \DateTime('yesterday');
        $lastYearInterval = new  \DateTime('-12 months');
        $lastSixMonthsInterval = new  \DateTime('-6 months');
        $ordersCount = [];

        $ordersCount['last_day_count'] = $this->getOrderIdsCount($customerEmailId, $lastDayInterval);
        $ordersCount['last_year_count'] = $this->getOrderIdsCount($customerEmailId, $lastYearInterval);
        $ordersCount['last_six_months_count'] = $this->getOrderIdsCount($customerEmailId, $lastSixMonthsInterval);
        return $ordersCount;
    }

    /**
     * Get the list of orders of customer by email
     *
     * @param string $customerEmailId
     * @param string $interval
     * @return array List of order IDs
     */
    public function getOrderIdsCount($customerEmailId, $interval)
    {
        $orders = $this->orderCollectionFactory->create();
        $orders->distinct(true);
        $orders->addFieldToSelect(['entity_id','increment_id','created_at']);
        $orders->addFieldToFilter('main_table.customer_email', $customerEmailId);
        $orders->addFieldToFilter('main_table.created_at', ['gteq' => $interval->format('Y-m-d H:i:s')]);
        $orders->join(['wp' => 'worldpay_payment'], 'wp.order_id=main_table.increment_id', ['payment_type']);
        $orders->join(['og' => 'sales_order_grid'], 'og.entity_id=main_table.entity_id', '');

        return count($orders);
    }

    /**
     * Returns cards count that are saved within 24 hrs
     *
     * @param string|int $customerId
     *
     * @return array count of saved cards
     */
    public function getSavedCardsCount($customerId)
    {
        $now = new \DateTime();
        $lastDay = new  \DateInterval(sprintf('P%dD', 1));
        $savedCards = $this->_savecard->create()->getCollection()
                        ->addFieldToSelect(['id'])
                        ->addFieldToFilter('customer_id', ['eq' => $customerId])
                        ->addFieldToFilter('created_at', ['lteq' => $now->format('Y-m-d H:i:s')]);
        return count($savedCards->getData());
    }

    /**
     * Get order sync interval
     */
    public function getOrderSyncInterval()
    {
        return $this->_scopeConfig->getValue(
            'worldpay/order_sync_status/order_sync_interval',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get order sync status
     */
    public function getSyncOrderStatus()
    {
        return $this->_scopeConfig->getValue(
            'worldpay/order_sync_status/order_status',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Is exception engin enable
     */
    public function isExemptionEngineEnable()
    {
        return (bool) $this->_scopeConfig->getValue(
            'worldpay/exemption_config/exemption_engine',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get Apm Type
     *
     * @param string $code
     */
    public function getApmTypes($code)
    {
        $allApmMethods = [
            'ACH_DIRECT_DEBIT-SSL' => 'ACH Pay'
        ];
        $data = $this->_scopeConfig->getValue(
            'worldpay/apm_config/paymentmethods',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        $activeMethods = [];

        if (!empty($data)) {
            $configMethods = explode(',', $data);
            foreach ($configMethods as $method) {
                $activeMethods[$method] = $allApmMethods[$method];
            }
        }
        return $activeMethods;
    }

    /**
     * Is apm enable
     */
    public function isApmEnabled()
    {
        return (bool) $this->_scopeConfig->getValue(
            'worldpay/apm_config/enabled',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get apm title
     */
    public function getApmTitle()
    {
        return $this->_scopeConfig->getValue(
            'worldpay/apm_config/title',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get Apm Payment Methods
     */
    public function getApmPaymentMethods()
    {
        return $this->_scopeConfig->getValue(
            'worldpay/apm_config/paymentmethods',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get ACH Details
     */
    public function getACHDetails()
    {
        $integrationmode = $this->getCcIntegrationMode();
        $apmmethods = $this->getApmTypes('worldpay_apm');
        if (array_key_exists("ACH_DIRECT_DEBIT-SSL", $apmmethods)) {
            $data = $this->getACHBankAccountTypes();
            return explode(",", $data);
        }
        return [];
    }

    /**
     * Get ACH Bank types
     */
    public function getACHBankAccountTypes()
    {
        return $this->_scopeConfig->getValue(
            'worldpay/apm_config/achaccounttypes',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Check order has invoice
     */
    public function checkOrderHasInvoices()
    {
        return $this->orderFactory->create()->hasInvoices();
    }

    /**
     * Get order payment type
     *
     * @param string|int $orderId
     */
    public function getOrderPaymentType($orderId)
    {
        $wpp = $this->worldpaypayment->create()->loadByAccessWorldpayOrderId($orderId);
        return $wpp->getPaymentType();
    }

    /**
     * Get challenge prefernce
     */
    public function getChallengePreference()
    {
        return $this->_scopeConfig->getValue(
            'worldpay/3ds_config/challenge_preference',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get current wopay plugin version
     */
    public function getCurrentWopayPluginVersion()
    {
        return $this->_scopeConfig->getValue(
            'worldpay/general_config/plugin_tracker/current_wopay_plugin_version',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get wopay plugin version history
     */
    public function getWopayPluginVersionHistory()
    {
        return $this->_scopeConfig->getValue(
            'worldpay/general_config/plugin_tracker/wopay_plugin_version_history',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get upgrade dates
     */
    public function getUpgradeDates()
    {
         return $this->_scopeConfig->getValue(
             'worldpay/general_config/plugin_tracker/upgrade_dates',
             \Magento\Store\Model\ScopeInterface::SCOPE_STORE
         );
    }

    /**
     * Get php version used
     */
    public function getPhpVersionUsed()
    {
        return phpversion();
    }

    /**
     * Get current magento version details
     */
    public function getCurrentMagentoVersionDetails()
    {
        $magento['Edition'] = $this->productmetadata->getEdition();
        $magento['Version'] = $this->productmetadata->getVersion();
        return $magento;
    }

    /**
     * Get plugin tracker details
     */
    public function getPluginTrackerdetails()
    {
        $details=[];
        $details['MERCHANT_ENTITY_REF'] = $this->getMerchantEntityReference();
        $details['MERCHANT_ID'] = $this->getMerchantCode();

        $magento = $this->getCurrentMagentoVersionDetails();
        $details['MAGENTO_EDITION'] = $magento['Edition'];
        $details['MAGENTO_VERSION'] = $magento['Version'];
        $details['PHP_VERSION'] = $this->getPhpVersionUsed();

        if (($this->getCurrentWopayPluginVersion()!=null) && !empty($this->getCurrentWopayPluginVersion())) {
            $details['CURRENT_WORLDPAY_PLUGIN_VERSION'] = $this->getCurrentWopayPluginVersion();
        }

        if (($this->getWopayPluginVersionHistory()!=null) && !empty($this->getWopayPluginVersionHistory())) {
            $details['WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE'] = $this->getWopayPluginVersionHistory();
        }

        if (($this->getUpgradeDates()!=null) && !empty($this->getUpgradeDates())) {
            $details['UPGRADE_DATES'] = $this->getUpgradeDates();
        }

        return $details;
    }
     /**
      *  Check if Payment Method Logo config is enabled
      */
    public function isPersonalisedPaymentLogoEnabled()
    {
        return $this->_scopeConfig->getValue(
            'worldpay/payment_method_logo_config/payment_method_logo_config_enabled',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get Credit card uploaded file value
     *
     * @param string|int $methodCode
     * @return string
     */
    public function getPersonalisedCClogo($methodCode)
    {
        return $this->_scopeConfig->getValue(
            'worldpay/payment_method_logo_config/cc/'.$methodCode.'/'.'logo_config',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get APM uploaded file value
     *
     * @param string|int $methodCode
     * @return string
     */
    public function getPersonalisedApmlogo($methodCode)
    {
        return $this->_scopeConfig->getValue(
            'worldpay/payment_method_logo_config/apm/'.$methodCode.'/'.'logo_config',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get Wallet uploaded file value
     *
     * @param string|int $methodCode
     * @return string
     */
    public function getPersonalisedWalletlogo($methodCode)
    {
        return $this->_scopeConfig->getValue(
            'worldpay/payment_method_logo_config/wallet/'.$methodCode.'/'.'logo_config',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Check if Credit Card config enable
     *
     * @param string|int $methodCode
     * @return bool
     */
    public function isCcLogoConfigEnabled($methodCode)
    {
        return $this->_scopeConfig->getValue(
            'worldpay/payment_method_logo_config/cc/'.$methodCode.'/'.'enabled',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Check if APM config enable
     *
     * @param string|int $methodCode
     * @return bool
     */
    public function isApmLogoConfigEnabled($methodCode)
    {
        return $this->_scopeConfig->getValue(
            'worldpay/payment_method_logo_config/apm/'.$methodCode.'/'.'enabled',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Check if Wallet config enable
     *
     * @param string|int $methodCode
     * @return bool
     */
    public function isWalletLogoConfigEnabled($methodCode)
    {
        return $this->_scopeConfig->getValue(
            'worldpay/payment_method_logo_config/wallet/'.$methodCode.'/'.'enabled',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get Media url with Path
     *
     * @param string $path
     */
    public function getBaseUrlMedia($path)
    {
        return $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA) . $path;
    }

    /**
     * Get Media Directory with path
     *
     * @param string $path
     */
    public function getMediaDirectory($path)
    {
        return $this->_filesystem->getDirectoryRead(DirectoryList::MEDIA)->getAbsolutePath(). $path;
    }
}
