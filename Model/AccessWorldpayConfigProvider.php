<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Payment\Helper\Data as PaymentHelper;
use Sapient\AccessWorldpay\Model\PaymentMethods\CreditCards as WorldPayCCPayment;
use Magento\Checkout\Model\Cart;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Asset\Source;
use Sapient\AccessWorldpay\Model\SavedTokenFactory;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Configuration provider for worldpayment rendering payment page.
 */
class AccessWorldpayConfigProvider implements ConfigProviderInterface
{
    /**
     * @var string[]
     */
    protected $methodCodes = [
        'worldpay_cc',
        'worldpay_apm',
        'worldpay_wallets'
    ];

    /**
     * @var array
     */
    private $icons = [];

    /**
     * @var \Magento\Payment\Model\Method\AbstractMethod[]
     */
    protected $methods = [];
    /**
     * @var \Sapient\AccessWorldpay\Model\PaymentMethods\Creditcards
     */
    protected $payment ;
    /**
     * @var \Sapient\AccessWorldpay\Helper\Data
     */
    protected $worldpayHelper;
    /**
     * @var Magento\Checkout\Model\Cart
     */
    protected $cart;
    /**
     * @var \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger
     */
    protected $wplogger;

    public const CC_VAULT_CODE = "worldpay_cc_vault";
    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @var $fileDriver
     */
    protected $fileDriver;

    /**
     * @param \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger
     * @param \Sapient\AccessWorldpay\Helper\Data $helper
     * @param PaymentHelper $paymentHelper
     * @param WorldPayCCPayment $payment
     * @param Cart $cart
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Backend\Model\Session\Quote $adminquotesession
     * @param \Sapient\AccessWorldpay\Model\Utilities\PaymentMethods $paymentmethodutils
     * @param \Magento\Backend\Model\Auth\Session $backendAuthSession
     * @param Repository $assetRepo
     * @param RequestInterface $request
     * @param Source $assetSource
     * @param SerializerInterface $serializer
     * @param SavedTokenFactory $savedTokenFactory
     * @param \Magento\Framework\Filesystem\Driver\File $fileDriver
     */
    public function __construct(
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger,
        \Sapient\AccessWorldpay\Helper\Data $helper,
        PaymentHelper $paymentHelper,
        WorldPayCCPayment $payment,
        Cart $cart,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Backend\Model\Session\Quote $adminquotesession,
        \Sapient\AccessWorldpay\Model\Utilities\PaymentMethods $paymentmethodutils,
        \Magento\Backend\Model\Auth\Session $backendAuthSession,
        Repository $assetRepo,
        RequestInterface $request,
        Source $assetSource,
        SerializerInterface $serializer,
        SavedTokenFactory $savedTokenFactory,
        \Magento\Framework\Filesystem\Driver\File $fileDriver
    ) {

        $this->wplogger = $wplogger;
        foreach ($this->methodCodes as $code) {
            $this->methods[$code] = $paymentHelper->getMethodInstance($code);
        }
        $this->cart = $cart;
        $this->payment = $payment;
        $this->worldpayHelper = $helper;
        $this->customerSession = $customerSession;
        $this->backendAuthSession = $backendAuthSession;
        $this->adminquotesession = $adminquotesession;
        $this->paymentmethodutils = $paymentmethodutils;
        $this->assetRepo = $assetRepo;
        $this->request = $request;
        $this->assetSource = $assetSource;
        $this->serializer = $serializer;
        $this->savedTokenFactory = $savedTokenFactory;
        $this->fileDriver = $fileDriver;
    }

    /**
     * @inheritdoc
     */
    public function getConfig()
    {
        $config = [];
        $params = ['_secure' => $this->request->isSecure()];
        foreach ($this->methodCodes as $code) {
            if ($this->methods[$code]->isAvailable()) {
                $config['payment']['total'] = $this->cart->getQuote()->getGrandTotal();
                $config['payment']['minimum_amount'] = $this->payment->getMinimumAmount();
                if ($code=='worldpay_cc') {
                    $config['payment']['ccform']["availableTypes"][$code] = $this->getCcTypes();
                } elseif ($code=='worldpay_wallets') {
                    $config['payment']['ccform']["availableTypes"][$code] = $this->getWalletsTypes($code);
                } else {
                    $config['payment']['ccform']["availableTypes"][$code] = $this->getApmTypes($code);
                }
                $config['payment']['ccform']["hasVerification"][$code] = true;
                $config['payment']['ccform']["hasSsCardType"][$code] = false;
                $config['payment']['ccform']["months"][$code] = $this->getMonths();
                $config['payment']['ccform']["years"][$code] = $this->getYears();
                $config['payment']['ccform']["cvvImageUrl"][$code] = $this->assetRepo->
                        getUrlWithParams('Sapient_AccessWorldpay::images/cc/cvv.png', $params);
                $config['payment']['ccform']["ssStartYears"][$code] = $this->getStartYears();
                $config['payment']['ccform']['intigrationmode'] = $this->getIntigrationMode();
                $config['payment']['ccform']['myaccountexceptions'] = $this->getMyAccountException();
                $config['payment']['ccform']['creditcardexceptions'] = $this->getCreditCardException();
                $config['payment']['ccform']['generalexceptions'] = $this->getGeneralException();
                $config['payment']['ccform']['achdetails'] = $this->worldpayHelper->getACHDetails();
                $config['payment']['ccform']['apmtitle'] = $this->getApmtitle();
                $config['payment']['ccform']['cctitle'] = $this->getCCtitle();
                $config['payment']['ccform']['isCvcRequired'] = $this->getCvcRequired();
                $config['payment']['ccform']['saveCardAllowed'] = $this->worldpayHelper->getSaveCard();
                $config['payment']['ccform']['tokenizationAllowed'] = $this->worldpayHelper->getTokenization();
                $config['payment']['ccform']['paymentMethodSelection'] = $this->getPaymentMethodSelection();
                $config['payment']['ccform']['paymentTypeCountries'] = $this->paymentmethodutils->
                        getPaymentTypeCountries();
                $config['payment']['ccform']['is3DSecureEnabled'] = $this->worldpayHelper->is3DSecureEnabled();
                $config['payment']['ccform']['savedCardList'] = $this->getSaveCardList();
                $config['payment']['ccform']['savedCardCount'] = count($this->getSaveCardList());
                $config['payment']['ccform']['savedCardEnabled'] = $this->getIsSaveCardAllowed();
                $config['payment']['ccform']['wpicons'] = $this->getIcons();
                $config['payment']['ccform']['websdk'] = $this->worldpayHelper->getWebSdkJsPath();
                $config['payment']['ccform']['walletstitle'] = $this->getWalletstitle();

                /*Labels*/
                $config['payment']['ccform']['myaccountlabels'] = $this->getMyAccountLabels();
                $config['payment']['ccform']['checkoutlabels'] = $this->getCheckoutLabels();
                $config['payment']['ccform']['adminlabels'] = $this->getAdminLabels();
                /* Merchant Identity for SessionHref call*/
                $config['payment']['ccform']['merchantIdentity'] = $this->worldpayHelper->getMerchantIdentity();
                /* Disclaimer  */
                $config['payment']['ccform']['disclaimerMessage'] = $this->worldpayHelper->getDisclaimerMessage();
                $config['payment']['ccform']['isDisclaimerMessageEnabled'] = $this->worldpayHelper
                        ->isDisclaimerMessageEnable();
                $config['payment']['ccform']['isDisclaimerMessageMandatory'] = $this->worldpayHelper
                        ->isDisclaimerMessageMandatory();
                /* GooglePay */
                $config['payment']['ccform']['isGooglePayEnable'] = $this->worldpayHelper->isGooglePayEnable();
                $config['payment']['ccform']['googlePaymentMethods'] = $this->worldpayHelper->googlePaymentMethods();
                $config['payment']['ccform']['googleAuthMethods'] = $this->worldpayHelper->googleAuthMethods();
                $config['payment']['ccform']['googleGatewayMerchantname'] = $this->worldpayHelper->
                        googleGatewayMerchantname();
                $config['payment']['ccform']['googleGatewayMerchantid'] = $this->worldpayHelper->
                        googleGatewayMerchantid();
                $config['payment']['ccform']['googleMerchantname'] = $this->worldpayHelper->googleMerchantname();
                $config['payment']['ccform']['googleMerchantid'] = $this->worldpayHelper->googleMerchantid();
                if ($this->worldpayHelper->getEnvironmentMode() == 'Live Mode') {
                    $config['payment']['general']['environmentMode'] = "PRODUCTION";
                } else {
                    $config['payment']['general']['environmentMode'] = "TEST";
                }

                /* Apple Configuration */
                $config['payment']['ccform']['appleMerchantid'] = $this->worldpayHelper->appleMerchantId();
                /* APM ACH Pay Narrative */
                $config['payment']['ccform']['narrative'] = $this->worldpayHelper->getNarrative();
            }
        }
        return $config;
    }

    /**
     * Retrieve cc exception
     *
     * @return array
     */
    public function getCreditCardException()
    {
        $ccdata= $this->unserializeValue($this->worldpayHelper->getCreditCardException());
        $result=[];
        $data=[];
        if (is_array($ccdata) || is_object($ccdata)) {
            foreach ($ccdata as $key => $value) {

                $result['exception_code']=$key;
                $result['exception_messages'] = $value['exception_messages'];
                $result['exception_module_messages'] = $value['exception_module_messages'];
                array_push($data, $result);

            }
        }
        return $data;
    }

    /**
     * Create a value from a storable representation
     *
     * @param int|float|string $value
     * @return array
     */
    protected function unserializeValue($value)
    {
        if (is_string($value) && !empty($value)) {
            return $this->serializer->unserialize($value);
        } else {
            return [];
        }
    }

    /**
     * Get General Exception
     *
     * @return string
     */
    public function getGeneralException()
    {
        $generaldata=$this->unserializeValue($this->worldpayHelper->getGeneralException());
        $result=[];
        $data=[];
        if (is_array($generaldata) || is_object($generaldata)) {
            foreach ($generaldata as $key => $value) {

                $result['exception_code']=$key;
                $result['exception_messages'] = $value['exception_messages'];
                $result['exception_module_messages'] = $value['exception_module_messages'];
                array_push($data, $result);

            }
        }
        return $data;
    }
    /**
     * Retrieve list of cc integration mode details
     *
     * @return String
     */
    public function getIntigrationMode()
    {
        return $this->worldpayHelper->getCcIntegrationMode();
    }

    /**
     * Retrieve list of cc types
     *
     * @param string $paymentconfig
     * @return Array
     */
    public function getCcTypes($paymentconfig = "cc_config")
    {
        $options = $this->worldpayHelper->getCcTypes($paymentconfig);
        $isSavedCardEnabled = $this->getIsSaveCardAllowed();
        if ($isSavedCardEnabled && !empty($this->getSaveCardList())) {
            $options['savedcard'] = 'Use Saved Card';
        }
        return $options;
    }

    /**
     * Check if the saved card option is enabled?
     *
     * @return boolean
     */
    public function getIsSaveCardAllowed()
    {
        if ($this->worldpayHelper->getSaveCard()) {
            return true;
        }
        return false;
    }

    /**
     * Retrieve list of months
     *
     * @return array
     */
    public function getMonths()
    {
        return [
            "01" => "01 - January",
            "02" => "02 - February",
            "03" => "03 - March",
            "04" => "04 - April",
            "05" => "05 - May",
            "06" => "06 - June",
            "07" => "07 - July",
            "08" => "08 - August",
            "09" => "09 - September",
            "10"=> "10 - October",
            "11"=> "11 - November",
            "12"=> "12 - December"
        ];
    }

    /**
     * Retrieve a list of the next ten years
     *
     * @return array
     */
    public function getYears()
    {
        $years = [];
        for ($i=0; $i<=10; $i++) {
            $year = (string)($i+date('Y'));
            $years[$year] = $year;
        }
        return $years;
    }

    /**
     * Retrieve a list of the previos five years
     *
     * @return array
     */
    public function getStartYears()
    {
        $years = [];
        for ($i=5; $i>=0; $i--) {
            $year = (string)(date('Y')-$i);
            $years[$year] = $year;
        }
        return $years;
    }

    /**
     * Retrieve cc title
     *
     * @return string
     */
    public function getCCtitle()
    {
        return $this->worldpayHelper->getCcTitle();
    }

    /**
     * Retrieve apm title
     *
     * @return string
     */
    public function getApmtitle()
    {
        return $this->worldpayHelper->getApmTitle();
    }

    /**
     * Check if CVC is required?
     *
     * @return boolean
     */
    public function getCvcRequired()
    {
        return $this->worldpayHelper->isCcRequireCVC();
    }

    /**
     * Retrieve payment method selection
     *
     * @return string
     */
    public function getPaymentMethodSelection()
    {
        return $this->worldpayHelper->getPaymentMethodSelection();
    }

    /**
     * Get icons for available payment methods
     *
     * @return array
     */
    public function getIcons()
    {
        if (!empty($this->icons)) {
            return $this->icons;
        }
        $ccTypes = $this->worldpayHelper->getCcTypes();
        $walletsTypes = $this->worldpayHelper->getWalletsTypes('worldpay_wallets');
        $apmTypes = $this->worldpayHelper->getApmTypes('worldpay_apm');
        $allTypePayments = array_unique(array_merge($ccTypes, $apmTypes));
        $allTypePayments = array_unique(array_merge($allTypePayments, $walletsTypes));

        foreach (array_keys($allTypePayments) as $code) {
            if (!array_key_exists($code, $this->icons)) {
                $asset = $this->createAsset('Sapient_AccessWorldpay::images/cc/' . strtolower($code) . '.png');
                $placeholder = $this->assetSource->findSource($asset);
                               
                if ($placeholder) {
                    list($width, $height) = getimagesizefromstring($asset->getUrl());
                    $this->icons[$code] = [
                        'url' => $asset->getUrl(),
                        'width' => $width,
                        'height' => $height
                    ];
                }
                $personalisedLogoXmlPath = strtolower(str_replace('-', '_', $code));
                if ($this->usePersonalisedLogo($personalisedLogoXmlPath) &&
                $this->getPersonalisedLogoValues($personalisedLogoXmlPath)) {
                     /* custom logo Path */
                    $personalisedLogoPath = 'sapient_accessworldpay/images/';
                    $urlMedia = $this->worldpayHelper->getBaseUrlMedia($personalisedLogoPath);
                    $mediaDirectory = $this->worldpayHelper->getMediaDirectory($personalisedLogoPath);
                    $absoulteMediaUrl = $urlMedia. $this->getPersonalisedLogoValues($personalisedLogoXmlPath);
                    $mediaSourceUrl = $mediaDirectory. $this->getPersonalisedLogoValues($personalisedLogoXmlPath);
                   
                    if ($this->fileDriver->isExists($mediaSourceUrl)) {
                        list($width, $height) = getimagesizefromstring($absoulteMediaUrl);
                        $this->icons[$code] = [
                            'url' => $absoulteMediaUrl,
                            'width' => '50px',
                            'height' => '30px',
                            'vertical-align' => 'middle'
                        ];
                    }
                }
            }
        }
        return $this->icons;
    }
    /**
     * Create a file asset that's subject of fallback system
     *
     * @param string $fileId
     * @param array $params
     * @return \Magento\Framework\View\Asset\File
     */
    public function createAsset($fileId, array $params = [])
    {
        $params = array_merge(['_secure' => $this->request->isSecure()], $params);
        return $this->assetRepo->createAsset($fileId, $params);
    }

    /**
     * Retrieve my account exception
     *
     * @return array
     */
    public function getMyAccountException()
    {
        $generaldata=$this->unserializeValue($this->worldpayHelper->getMyAccountException());
        $result=[];
        $data=[];
        if (is_array($generaldata) || is_object($generaldata)) {
            foreach ($generaldata as $key => $value) {

                $result['exception_code']=$key;
                $result['exception_messages'] = $value['exception_messages'];
                $result['exception_module_messages'] = $value['exception_module_messages'];
                array_push($data, $result);

            }
        }
        return $data;
    }

    /**
     * Retrieve my account labels
     *
     * @return array
     */
    public function getMyAccountLabels()
    {
        $generaldata=$this->unserializeValue($this->worldpayHelper->getMyAccountLabels());
        $result=[];
        $data=[];
        if (is_array($generaldata) || is_object($generaldata)) {
            foreach ($generaldata as $key => $value) {

                $result['wpay_label_code']=$key;
                $result['wpay_label_desc'] = $value['wpay_label_desc'];
                $result['wpay_custom_label'] = $value['wpay_custom_label'];
                array_push($data, $result);

            }
        }
        return $data;
    }

    /**
     * Retrieve checkout labels
     *
     * @return array
     */
    public function getCheckoutLabels()
    {
        $generaldata=$this->unserializeValue($this->worldpayHelper->getCheckoutLabels());
        $result=[];
        $data=[];
        if (is_array($generaldata) || is_object($generaldata)) {
            foreach ($generaldata as $key => $value) {

                $result['wpay_label_code']=$key;
                $result['wpay_label_desc'] = $value['wpay_label_desc'];
                $result['wpay_custom_label'] = $value['wpay_custom_label'];
                array_push($data, $result);

            }
        }
        return $data;
    }

    /**
     * Retrieve admin labels
     *
     * @return array
     */
    public function getAdminLabels()
    {
        $generaldata=$this->unserializeValue($this->worldpayHelper->getAdminLabels());
        $result=[];
        $data=[];
        if (is_array($generaldata) || is_object($generaldata)) {
            foreach ($generaldata as $key => $value) {

                $result['wpay_label_code']=$key;
                $result['wpay_label_desc'] = $value['wpay_label_desc'];
                $result['wpay_custom_label'] = $value['wpay_custom_label'];
                array_push($data, $result);

            }
        }
        return $data;
    }

    /**
     * Get Saved card List of customer
     */
    public function getSaveCardList()
    {
        $savedCardsList = [];
        $isSavedCardEnabled = $this->getIsSaveCardAllowed();
        if ($isSavedCardEnabled && $this->customerSession->isLoggedIn()) {
            $savedCardsList = $this->savedTokenFactory->create()->getCollection()
            ->addFieldToFilter('customer_id', $this->customerSession->getCustomerId())->getData();
        }
        return $savedCardsList;
    }

    /**
     * Retrieve list of apm types
     *
     * @param string $code
     * @return array
     */
    public function getApmTypes($code)
    {
        return $this->worldpayHelper->getApmTypes($code);
    }

    /**
     * Retrieve list of wallets types
     *
     * @param string $code
     * @return array
     */
    public function getWalletsTypes($code)
    {
        return $this->worldpayHelper->getWalletsTypes($code);
    }

    /**
     * Retrieve wallets title
     *
     * @return string
     */
    public function getWalletstitle()
    {
        return $this->worldpayHelper->getWalletsTitle();
    }
    /**
     * Get logo uploaded file value
     *
     * @param string $code
     * @return string
     */
    public function getPersonalisedLogoValues($code)
    {
        $ccDataConfig = $this->worldpayHelper->getPersonalisedCClogo($code);
        if (!empty($ccDataConfig)) {
            return $ccDataConfig;
        }
        $apmDataConfig = $this->worldpayHelper->getPersonalisedApmlogo($code);
        if (!empty($apmDataConfig)) {
            return $apmDataConfig;
        }
        $walletDataConfig = $this->worldpayHelper->getPersonalisedWalletlogo($code);
        if (!empty($walletDataConfig)) {
            return $walletDataConfig;
        }
        return null;
    }

    /**
     * Check Logo config enabled
     *
     * @param string $code
     * @return bool
     */
    public function usePersonalisedLogo($code)
    {
        if ($this->worldpayHelper->isPersonalisedPaymentLogoEnabled()) {
            if ($this->worldpayHelper->isCcLogoConfigEnabled($code)) {
                return true;
            } elseif ($this->worldpayHelper->isApmLogoConfigEnabled($code)) {
                return true;
            } elseif ($this->worldpayHelper->isWalletLogoConfigEnabled($code)) {
                return true;
            }
            return false;
        }
        return false;
    }
}
