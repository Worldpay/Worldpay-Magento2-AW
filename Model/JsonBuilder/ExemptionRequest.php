<?php

namespace Sapient\AccessWorldpay\Model\JsonBuilder;

use Sapient\AccessWorldpay\Logger\AccessWorldpayLogger;

class ExemptionRequest
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
     * @var string
     */
    protected $paResponse = null;
    /**
     * @var string
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
     * @var array
     */
    private $riskData;

    /**
     * Build xml for processing Request
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
     * @param array $shippingAddress
     * @param array $billingAddress
     * @param int $shopperId
     * @param int $quoteId
     * @param array $riskData
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
        $riskData
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
        $this->riskData =$riskData;

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
        if (isset($this->riskData)) {
            $orderData['riskData'] = $this->_addRiskData();
        }
        
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
        $instruction['paymentInstrument'] = $this->_addPaymentInfo();
        $instruction['value'] = $this->_addValueInfo();
        
        return $instruction;
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
        if ($this->paymentDetails['paymentType'] == 'TOKEN-SSL'
            || $this->paymentDetails['paymentType'] == 'savedcard') {
            $paymentData['type'] = "card/tokenized";
            $paymentData['href'] = (isset($this->paymentDetails['token_url'])
                && !empty($this->paymentDetails['token_url']))
                ?  $this->paymentDetails['token_url'] : $this->paymentDetails['tokenHref'];
            return $paymentData;
        }
    }

    /**
     * Add Risk data Info
     *
     * @return array
     */
    private function _addRiskData()
    {
        $riskData = [
           "account" => $this->addRiskAccountData(),
           "transaction" => $this->addRiskTransactionData(),
           "shipping" => $this->addRiskShippingData()
           ];
           
        return $riskData;
    }

    /**
     * Add risk account data Info
     *
     * @return array
     */
    private function addRiskAccountData()
    {
        $account = [
           "email" => $this->riskData['email'],
        ];
        if (isset($this->riskData['dateOfBirth'])) {
            $account["dateOfBirth"] = $this->riskData['dateOfBirth'];
        }
        
        return $account;
    }

    /**
     * Add risk transaction data Info
     *
     * @return array
     */
    private function addRiskTransactionData()
    {
        $transaction = [
           "firstName"  => $this->riskData['firstName'],
           "lastName" => $this->riskData['lastName']
        ];
        
        return $transaction;
    }

    /**
     * Add risk shipping data Info
     *
     * @return array
     */
    private function addRiskShippingData()
    {
        $shipping = [
         "firstName" => $this->riskData['shippingAddress']['firstName'],
         "lastName" => $this->riskData['shippingAddress']['lastName'],
         "address" => $this->_addShippingAddress()
        ];
        
        return $shipping;
    }
    
    /**
     * Add shipping address
     *
     * @return array
     */
    private function _addShippingAddress()
    {
        $address = [
          "address1" =>$this->riskData['shippingAddress']['street'],
          "postalCode" => $this->riskData['shippingAddress']['postalCode'],
          "city" => $this->riskData['shippingAddress']['city'],
          "state" => $this->riskData['shippingAddress']['state'],
          "countryCode" => $this->riskData['shippingAddress']['countryCode'],
            
        ];
        
        return $address;
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
