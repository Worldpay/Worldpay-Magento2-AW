<?php

namespace Sapient\AccessWorldpay\Model\JsonBuilder;

class ThreeDsAuthentication
{
    
    public const EXPONENT = 2;
    /**
     * @var string
     */
    private $orderCode;
    /**
     * @var array
     */
    private $paymentDetails;
    /**
     * @var array
     */
    private $billingAddress;
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
    private $acceptHeader;
    /**
     * @var string
     */
    private $userAgentHeader;
    /**
     * Customer Risk Data
     *
     * @var array
     */
    private $riskData;
    
    /**
     * Build jsonObj for processing Request
     *
     * @param string $orderCode
     * @param array $paymentDetails
     * @param float $billingAddress
     * @param string $currencyCode
     * @param float $amount
     * @param string $acceptHeader
     * @param string $userAgentHeader
     * @param array $riskData
     * @return string
     */
    public function build(
        $orderCode,
        $paymentDetails,
        $billingAddress,
        $currencyCode,
        $amount,
        $acceptHeader,
        $userAgentHeader,
        $riskData
    ) {
        $this->orderCode = $orderCode;
        $this->paymentDetails = $paymentDetails;
        $this->billingAddress = $billingAddress;
        $this->currencyCode = $currencyCode;
        $this->amount = $amount;
        $this->acceptHeader = $acceptHeader;
        $this->userAgentHeader = $userAgentHeader;
        $this->riskData = $riskData;
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
        $orderData['deviceData'] = $this->_addDeviceData();
        $orderData['challenge'] = $this->_addUrl();
        if (isset($this->riskData)) {
            $orderData['riskData'] = $this->_addRiskData();
        }
        
        return $orderData;
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
        $instruction['paymentInstrument'] = $this->_addPaymentInfo();
        //$instruction['billingAddress'] = $this ->_addBillingAddress();
        $instruction['value'] = $this->_addValue();
        return $instruction;
    }
    
    /**
     * Add payment info data's to jsonObj
     *
     * @return array
     */
    private function _addPaymentInfo()
    {
        if (isset($this->paymentDetails['token_url'])) {
            $tokenurl = $this->paymentDetails['token_url'];
        } else {
            $tokenurl = $this->paymentDetails['tokenHref'];
        }
        $paymentData = ["type" => "card/tokenized",
                        "href" => $tokenurl
                             ];
        return $paymentData;
    }
    
    /**
     * Add billing address to jsonObj
     *
     * @return array
     */
    private function _addBillingAddress()
    {
        $billingData = ["address1" => $this->billingAddress['street'],
                             "postalCode" => $this->billingAddress['postalCode'],
                             "city" =>$this->billingAddress['city'],
                             "countryCode" => $this->billingAddress['countryCode']];
        return $billingData;
    }
    
    /**
     * Add currency and amount to jsonObj
     *
     * @return array
     */
    private function _addValue()
    {
        $valueData = ["currency" =>$this->currencyCode,
            "amount" =>$this->_amountAsInt($this->amount)];
        return $valueData;
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
    
    /**
     * Add device data to jsonObj
     *
     * @return array
     */
    private function _addDeviceData()
    {
        if (isset($this->paymentDetails['collectionReference'])) {
            $deviceData ["collectionReference"] = $this->paymentDetails['collectionReference'];
        }
        $deviceData[ "acceptHeader" ]= $this->acceptHeader;
        $deviceData[ "userAgentHeader"] = $this->userAgentHeader;
        return $deviceData;
    }
    
    /**
     * Add return url and preference to jsonObj
     *
     * @return array
     */
    private function _addUrl()
    {
        $urlData = [ "returnUrl" => $this->paymentDetails['url'], "preference" => $this->paymentDetails['preference']];
        return $urlData;
    }
    
    /**
     * Add Risk Data to jsonObj
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
     * Add Risk Account Data to jsonObj
     *
     * @return array
     */
    private function addRiskAccountData()
    {
        $account = [
           "type"  => $this->riskData['type'],
           "email" => $this->riskData['email'],
           "previousSuspiciousActivity" => $this->riskData['suspiciousActivity'],
           "history" => [
             "createdAt" =>$this->riskData['createdAt'],
             "modifiedAt" =>$this->riskData['modifiedAt']
           ],
        ];
        
        return $account;
    }
    
    /**
     * Add Risk Transaction Data to jsonObj
     *
     * @return array
     */
    private function addRiskTransactionData()
    {
        $transaction = [
           "firstName"  => $this->riskData['firstName'],
           "lastName" => $this->riskData['lastName']
        ];
        if (isset($this->riskData['phoneNumber']) && $this->riskData['phoneNumber']!='') {
            $transaction["phoneNumber"] = $this->riskData['phoneNumber'];
        }
        return $transaction;
    }
    
    /**
     * Add Risk Shipping Data to jsonObj
     *
     * @return array
     */
    private function addRiskShippingData()
    {
        $shipping = [
         "nameMatchesAccountName" => $this->riskData['nameMatchesAccountName'] ,
         "email" => $this->riskData['email']
        ];
        
        return $shipping;
    }
}
