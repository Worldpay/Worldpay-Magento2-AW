<?php
namespace Sapient\AccessWorldpay\Model\JsonBuilder;

class ACHOrder
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
     * @var array
     */
    protected $paymentDetails;
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
     * @var string
     */
    private $shopperId;
    /**
     * @var string
     */
    private $quoteId;
    /**
     * @var string
     */
    private $statementNarrative;

    /**
     * Build xml for processing Request
     *
     * @param string $orderCode
     * @param string $merchantCode
     * @param string $orderDescription
     * @param string $currencyCode
     * @param float $amount
     * @param array $paymentDetails
     * @param string $shopperEmail
     * @param string $acceptHeader
     * @param string $userAgentHeader
     * @param array $shippingAddress
     * @param array $billingAddress
     * @param int $shopperId
     * @param int $quoteId
     * @param string $statementNarrative
     */
    public function build(
        $orderCode,
        $merchantCode,
        $orderDescription,
        $currencyCode,
        $amount,
        $paymentDetails,
        $shopperEmail,
        $acceptHeader,
        $userAgentHeader,
        $shippingAddress,
        $billingAddress,
        $shopperId,
        $quoteId,
        $statementNarrative
    ) {
        $this->orderCode = $orderCode;
        $this->merchantCode = $merchantCode;
        $this->orderDescription = $orderDescription;
        $this->currencyCode = $currencyCode;
        $this->amount = $amount;
        $this->paymentDetails = $paymentDetails;
        $this->shopperEmail = $shopperEmail;
        $this->acceptHeader = $acceptHeader;
        $this->userAgentHeader = $userAgentHeader;
        $this->shippingAddress = $shippingAddress;
        $this->billingAddress = $billingAddress;
        $this->shopperId = $shopperId;
        $this->quoteId = $quoteId;
        $this->statementNarrative =$statementNarrative;
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
        return $orderData;
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
     * Add transaction Ref
     *
     * @return string
     */
    private function _addTransactionRef()
    {
        return $this->orderCode;
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
        $instruction['paymentInstrument'] = $this->_addPaymentInfo();
        $instruction['value'] = $this->_addValueInfo();
        return $instruction;
    }

    /**
     * Add narrative Info
     *
     * @return string
     */
    private function _addNarrativeInfo()
    {
        $narrationData = ["line1" => $this->statementNarrative];
        return $narrationData;
    }

    /**
     * Add value Info
     *
     * @return array
     */
    private function _addValueInfo()
    {
        $valueData = ["currency" => $this->currencyCode,
            "amount" => $this->_amountAsInt($this->amount)];
        return $valueData;
    }

    /**
     * Add payment Info
     *
     * @return array
     */
    private function _addPaymentInfo()
    {
        $paymentData = ["type" => $this->paymentDetails['type'],
            "accountType" => $this->paymentDetails['achaccount'],
            "accountNumber" => $this->paymentDetails['achAccountNumber'],
            "routingNumber" => $this->paymentDetails['achRoutingNumber']];
        if (isset($this->paymentDetails['achCheckNumber']) &&
                $this->paymentDetails['achCheckNumber']!="") {
            $paymentData['checkNumber'] = $this->paymentDetails['achCheckNumber'];
        }
        if (isset($this->paymentDetails['achCompanyName'])
                 && ($this->paymentDetails['achaccount'] == 'corporate'
                    || $this->paymentDetails['achaccount'] == 'corporateSavings')) {
            $paymentData['companyName'] = $this->paymentDetails['achCompanyName'];
        }
        if (isset($this->billingAddress)) {
            $paymentData['billingAddress'] = [
                "firstName" => $this->billingAddress['firstName'],
                "lastName" => $this->billingAddress['lastName'],
                "address1" => $this->billingAddress['street'],
                "postalCode" => $this->billingAddress['postalCode'],
                "city" => $this->billingAddress['city'],
                "state" => $this->billingAddress['state'],
                "countryCode" => $this->billingAddress['countryCode']
            ];
        }
        return $paymentData;
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
}
