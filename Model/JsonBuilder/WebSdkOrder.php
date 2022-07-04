<?php


namespace Sapient\AccessWorldpay\Model\JsonBuilder;

class WebSdkOrder
{
    public const EXPONENT = 2;

    /**
     * @var string
     */
    private $merchantCode;
    /**
     * @var string
     */
    private $orderCode;
    /**
     * @var string
     */
    private $orderDescription;
    /**
     * @var string
     */
    private $currencyCode;
    /**
     * @var float
     */
    private $amount;
    /**
     * @var array
     */
    protected $paymentDetails;
    /**
     * @var array
     */
    private $cardAddress;
    /**
     * @var string
     */
    protected $shopperEmail;
    /**
     * @var string
     */
    protected $acceptHeader;
    /**
     * @var string
     */
    protected $userAgentHeader;
    /**
     * @var array
     */
    private $shippingAddress;
    /**
     * @var array
     */
    private $billingAddress;
    /**
     * @var mixed|null
     */
    protected $paResponse = null;
    /**
     * @var bool|null
     */
    private $echoData = null;
    /**
     * @var string
     */
    private $shopperId;
    /**
     * @var string
     */
    private $quoteId;
    /**
     * 3D secure config
     *
     * @var Sapient\AccessWorldpay\Model\JsonBuilder\Config\ThreeDSecureConfig
     */
    private $threeDSecureConfig;

    /**
     * Build jsonObj for processing Request
     *
     * @param string $merchantCode
     * @param string $orderCode
     * @param string $orderDescription
     * @param string $currencyCode
     * @param float $amount
     * @param array $paymentDetails
     * @param array $cardAddress
     * @param string $shopperEmail
     * @param string $acceptHeader
     * @param string $userAgentHeader
     * @param string $shippingAddress
     * @param float $billingAddress
     * @param string $shopperId
     * @param string $quoteId
     * @param \Sapient\AccessWorldpay\Model\JsonBuilder\Config\ThreeDSecureConfig $threeDSecureConfig
     * @return string
     */
    public function build(
        $merchantCode,
        $orderCode,
        $orderDescription,
        $currencyCode,
        $amount,
        $paymentDetails,
        $cardAddress,
        $shopperEmail,
        $acceptHeader,
        $userAgentHeader,
        $shippingAddress,
        $billingAddress,
        $shopperId,
        $quoteId,
        $threeDSecureConfig
    ) {
        $this->merchantCode = $merchantCode;
        $this->orderCode = $orderCode;
        $this->orderDescription = $orderDescription;
        $this->currencyCode = $currencyCode;
        $this->amount = $amount;
        $this->paymentDetails = $paymentDetails;
        $this->cardAddress = $cardAddress;
        $this->shopperEmail = $shopperEmail;
        $this->acceptHeader = $acceptHeader;
        $this->userAgentHeader = $userAgentHeader;
        $this->shippingAddress = $shippingAddress;
        $this->billingAddress = $billingAddress;
        $this->shopperId = $shopperId;
        $this->quoteId = $quoteId;
        $this->threeDSecureConfig =$threeDSecureConfig;

        $jsonData = $this->_addOrderElement();

        return json_encode($jsonData);
    }

    /**
     * Build an order data array
     *
     * @return array
     */
    private function _addOrderElement()
    {
        $orderData = [];

        $orderData['transactionReference'] = $this->_addTransactionRef();
        $orderData['merchant'] = $this->_addMerchantInfo();
        $orderData['instruction'] = $this->_addInstructionInfo();
        if ($this->threeDSecureConfig != '' || isset($this->paymentDetails['exemptionResult']['riskProfile'])) {
            $orderData['customer'] = $this->_addCustomer();
        }
        return $orderData;
    }

    /**
     * Add customer risk profile data to json Obj
     *
     * @return array
     */
    private function _addCustomer()
    {
        //logic not to send riskprofile for cardonfile verification
        $verifationcallCheck = !isset($this->paymentDetails['cardOnfileVerificationCheck'])
                || (isset($this->paymentDetails['cardOnfileVerificationCheck'])
                    && !($this->paymentDetails['cardOnfileVerificationCheck']))? true : false;
        $customer =[];
        if (isset($this->paymentDetails['exemptionResult']['riskProfile']) && $verifationcallCheck) {
            $customer["riskProfile"]  = $this->paymentDetails['exemptionResult']['riskProfile']['href'];
        }
        if ($this->threeDSecureConfig != '') {
            $customer["authentication"] = $this->_addAuthenticationData();
        }
        return $customer;
    }

    /**
     * Add authentication data to json Obj
     *
     * @return array
     */
    private function _addAuthenticationData()
    {
        $authenticationData =[];
        $authenticationData["version"] = $this->threeDSecureConfig['authentication']['version'];
        $authenticationData["type"] = "3DS";
        $authenticationData["eci"] = $this->threeDSecureConfig['authentication']['eci'];
        if (isset($this->threeDSecureConfig['authentication']['authenticationValue'])) {
            $authenticationData["authenticationValue"] = $this->
                    threeDSecureConfig['authentication']['authenticationValue'];
        }
        if (isset($this->threeDSecureConfig['authentication']['transactionId'])) {
            $authenticationData["transactionId"] = $this->
                    threeDSecureConfig['authentication']['transactionId'];
        }

        return $authenticationData;
    }

    /**
     * Add order code to jsonObj
     *
     * @return array
     */
    private function _addTransactionRef()
    {
        return $this->orderCode;
    }

    /**
     * Add merchant entity referecne to jsonObj
     *
     * @return array
     */
    private function _addMerchantInfo()
    {
        $merchantData = ["entity" => $this->paymentDetails['entityRef']];
        return $merchantData;
    }

    /**
     * Add instruction info data's to jsonObj
     *
     * @return array
     */
    private function _addInstructionInfo()
    {
        $instruction = [];
        $instruction['narrative'] = $this->_addNarrativeInfo();
        $instruction['value'] = $this->_addValueInfo();
        $instruction['paymentInstrument'] = $this->_addPaymentInfo();

        return $instruction;
    }

    /**
     * Add description to json Obj
     *
     * @return array
     */
    private function _addNarrativeInfo()
    {
        $narrationData = ["line1" => $this->paymentDetails['narrative']];
        return $narrationData;
    }

    /**
     * Add currency and amount to json Obj
     *
     * @return array
     */
    private function _addValueInfo()
    {
        $valueData = ["currency" => $this->currencyCode, "amount" => $this->_amountAsInt($this->amount)];
        return $valueData;
    }

    /**
     * Add payment info data's to jsonObj
     *
     * @return array
     */
    private function _addPaymentInfo()
    {
        $paymentData = [];
        if (isset($this->paymentDetails['verifiedToken'])) {
            $paymentData['type'] = 'card/token';
            $paymentData['href'] = $this->paymentDetails['verifiedToken'];
            return $paymentData;
        } elseif (isset($this->paymentDetails['tokenId']) && isset($this->paymentDetails['cvcHref'])) {
            $paymentData['type'] = 'card/tokenized';
            $paymentData['href'] = $this->paymentDetails['tokenHref'];
            if (isset($this->paymentDetails['cardOnFileAuthorization'])) {
                $paymentData1 = [];
                $paymentData1['type'] = 'card/checkout';
                $paymentData1['tokenHref'] = $this->paymentDetails['tokenHref'];
                $paymentData1['cvcHref'] = $this->paymentDetails['cvcHref'];
                return $paymentData1;
            }
            return $paymentData;
        }
    }

    /**
     * Returns the rounded value of num to specified precision
     *
     * @param float $amount
     * @return int
     */
    private function _amountAsInt($amount)
    {
        return round($amount, self::EXPONENT, PHP_ROUND_HALF_EVEN) * pow(10, self::EXPONENT);
    }
}
