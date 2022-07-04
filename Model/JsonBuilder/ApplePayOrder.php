<?php


namespace Sapient\AccessWorldpay\Model\JsonBuilder;

class ApplePayOrder
{
    public const EXPONENT = 2;

    /**
     * @var string
     */
    private $orderCode;
    /**
     * @var string
     */
    private $merchantCode;
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
     * @var string
     */
    protected $shopperEmail;
    /**
     * @var string
     */
    protected $protocolVersion;
    /**
     * @var string
     */
    protected $signature;
    /**
     * @var array
     */
    private $data;
    /**
     * @var string
     */
    private $ephemeralPublicKey;
    /**
     * @var string
     */
    protected $publicKeyHash;
    /**
     * @var string
     */
    private $transactionId;

    /**
     * Build xml for processing Request
     *
     * @param string $merchantCode
     * @param string $orderCode
     * @param string $orderDescription
     * @param string $currencyCode
     * @param float $amount
     * @param string $shopperEmail
     * @param string $protocolVersion
     * @param string $signature
     * @param array $data
     * @param string $ephemeralPublicKey
     * @param string $publicKeyHash
     * @param string $transactionId
     */
    public function build(
        $merchantCode,
        $orderCode,
        $orderDescription,
        $currencyCode,
        $amount,
        $shopperEmail,
        $protocolVersion,
        $signature,
        $data,
        $ephemeralPublicKey,
        $publicKeyHash,
        $transactionId
    ) {
        $this->merchantCode = $merchantCode;
        $this->orderCode = $orderCode;
        $this->orderDescription = $orderDescription;
        $this->currencyCode = $currencyCode;
        $this->amount = $amount;
         $this->shopperEmail = $shopperEmail;
         $this->protocolVersion = $protocolVersion;
         $this->signature = $signature;
         $this->data = $data;
         $this->ephemeralPublicKey = $ephemeralPublicKey;
         $this->publicKeyHash = $publicKeyHash;
         $this->transactionId = $transactionId;

        $jsonData = $this->_addOrderElement();
        return $jsonData;
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

        return $orderData;
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
        $merchantData = ["entity" => $this->merchantCode['entityRef']];
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
        $narrationData = ["line1" => $this->merchantCode['narrative']];
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
        $paymentData = [];
        $paymentData = ["type" => "card/wallet+applepay",
                "walletToken" => $this->getApplePayToken()];
            return $paymentData;
    }

    /**
     * Get applepay token
     *
     * @return array
     */
    private function getApplePayToken()
    {
        $appleToken = [
                "version"=>$this->protocolVersion,
                "data"=>$this->data,
                "signature"=>$this->signature,
                "header"=> $this->getPublicKeyHash()
                ];
        return $appleToken;
    }

    /**
     * Get Public Key Hash
     *
     * @return array
     */

    private function getPublicKeyHash()
    {
        return $applePublicHash = [
               "transactionId"=>$this->transactionId,
               "ephemeralPublicKey"=>$this->ephemeralPublicKey,
               "publicKeyHash"=>$this->publicKeyHash,
               ];
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
