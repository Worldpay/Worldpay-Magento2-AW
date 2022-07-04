<?php

namespace Sapient\AccessWorldpay\Model\JsonBuilder;

/**
 * Build json for RedirectOrder request
 */
class WalletOrder
{
    public const EXPONENT = 2;

   /**
    * @var $merchantCode
    */
    private $merchantCode;
    /**
     * @var $orderCode
     */
    private $orderCode;
    /**
     * @var $orderDescription
     */
    private $orderDescription;
    /**
     * @var $currencyCode
     */
    private $currencyCode;
    /**
     * @var $amount
     */
    private $amount;
    /**
     * @var $paymentType
     */
    private $paymentType;
    /**
     * @var $exponent
     */
    private $exponent;
    /**
     * @var $sessionId
     */
    private $sessionId;
    /**
     * @var $cusDetails
     */
    private $cusDetails;
    /**
     * @var $shopperIpAddress
     */
    private $shopperIpAddress;
    /**
     * @var $paymentDetails
     */
    private $paymentDetails;
    /**
     * @var $shippingAddress
     */
    private $shippingAddress;
    /**
     * @var $acceptHeader
     */
    protected $acceptHeader;
    /**
     * @var $userAgentHeader
     */
    protected $userAgentHeader;

    /**
     * Build xml for processing Request
     *
     * @param string $merchantCode
     * @param string $orderCode
     * @param string $orderDescription
     * @param string $currencyCode
     * @param float $amount
     * @param string $paymentType
     * @param string $shopperEmail
     * @param string $acceptHeader
     * @param string $userAgentHeader
     * @param string $protocolVersion
     * @param string $signature
     * @param string $signedMessage
     * @param array $shippingAddress
     * @param array $billingAddress
     * @param string $cusDetails
     * @param string $shopperIpAddress
     * @param array $paymentDetails
     * @return SimpleXMLElement $xml
     */
    public function build(
        $merchantCode,
        $orderCode,
        $orderDescription,
        $currencyCode,
        $amount,
        $paymentType,
        $shopperEmail,
        $acceptHeader,
        $userAgentHeader,
        $protocolVersion,
        $signature,
        $signedMessage,
        $shippingAddress,
        $billingAddress,
        $cusDetails,
        $shopperIpAddress,
        $paymentDetails
    ) {
        $this->merchantCode = $merchantCode;
        $this->orderCode = $orderCode;
        $this->orderDescription = $orderDescription;
        $this->currencyCode = $currencyCode;
        $this->amount = $amount;
        $this->paymentType = $paymentType;
        $this->shopperEmail = $shopperEmail;
        $this->acceptHeader = $acceptHeader;
        $this->userAgentHeader = $userAgentHeader;
        $this->protocolVersion = $protocolVersion;
        $this->signature = $signature;
        $this->signedMessage = $signedMessage;
        $this->shippingAddress = $shippingAddress;
        $this->billingAddress = $billingAddress;
        $this->cusDetails = $cusDetails;
        $this->shopperIpAddress = $shopperIpAddress;
        $this->paymentDetails = $paymentDetails;
        $this->exponent = self::EXPONENT;
        $jsonData = $this->_addOrderElement();
        return json_encode($jsonData);
    }

    /**
     * Add order tag to xml
     *
     * @return SimpleXMLElement $order
     */
    private function _addOrderElement()
    {
        $orderData = [];
        $orderData['transactionReference'] = $this->_addTransactionRef();
        $orderData['merchant'] = $this->_addMerchantInfo();
        $orderData['instruction'] = $this->_addInstructionInfo();
        $orderData['shopperLanguageCode'] = "en";
        return $orderData;
    }

    /**
     * Returns the rounded value of num to specified precision
     *
     * @param float $amount
     * @return int
     */
    private function _amountAsInt($amount)
    {
        return round($amount, $this->exponent, PHP_ROUND_HALF_EVEN) * pow(10, $this->exponent);
    }

    /**
     * Add transaction Ref
     *
     * @return string
     */
    private function _addTransactionRef()
    {
        return $this->orderCode;
    }

    /**
     * Add merchant Info
     *
     * @return array
     */
    private function _addMerchantInfo()
    {
        $merchantData = ["entity" => $this->paymentDetails['entityRef']];
        return $merchantData;
    }

    /**
     * Add instruction Info
     *
     * @return string
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
     * Add narrative Info
     *
     * @return string
     */
    private function _addNarrativeInfo()
    {
        $narrationData = ["line1" => $this->paymentDetails['narrative']];
        return $narrationData;
    }

    /**
     * Add value Info
     *
     * @return array
     */
    private function _addValueInfo()
    {
        $valueData = ["currency" => $this->currencyCode, "amount" => $this->_amountAsInt($this->amount)];
        return $valueData;
    }

    /**
     * Add payment Info
     *
     * @return array
     */
    private function _addPaymentInfo()
    {
        $paymentData = ["type" => "card/wallet+goolepay",
                "walletToken" => json_encode($this->getGoolepayToken())];
        return $paymentData;
    }

    /**
     * Get googlepay token
     *
     * @return array
     */
    private function getGoolepayToken()
    {
        return ["protocolVersion"=>$this->protocolVersion,
                "signature"=>$this->signature,
                "signedMessage"=>$this->signedMessage
                ];
    }
}
