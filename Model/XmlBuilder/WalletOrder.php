<?php

namespace Sapient\AccessWorldpay\Model\XmlBuilder;

/**
 * Build xml for RedirectOrder request
 */
class WalletOrder
{
    public const DYNAMIC3DS_DO3DS = 'do3DS';
    public const DYNAMIC3DS_NO3DS = 'no3DS';
    public const EXPONENT = 2;
    public const ROOT_ELEMENT = <<<EOD
<?xml version="1.0" encoding="UTF-8"?><!DOCTYPE paymentService PUBLIC '-//WorldPay/DTD WorldPay PaymentService v1//EN'
        'http://dtd.worldpay.com/paymentService_v1.dtd'> <paymentService/>
EOD;

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
     * @var string
     */
    private $paymentType;
    /**
     * @var float
     */
    private $exponent;
    /**
     * @var mixed|null
     */
    protected $paResponse = null;
    /**
     * @var string|null
     */
    protected $dfReferenceId = null;
    /**
     * @var string
     */
    private $sessionId;
    /**
     * @var Sapient\Worldpay\Model\XmlBuilder\Config\ThreeDSecure
     */
    protected $threeDSecureConfig;
    /**
     * @var string
     */
    private $cusDetails;
    /**
     * @var string
     */
    private $shopperIpAddress;
    /**
     * @var array
     */
    private $paymentDetails;
    /**
     * @var array
     */
    private $shippingAddress;
    /**
     * @var string
     */
    protected $acceptHeader;
    /**
     * @var string
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
        $xml = new \SimpleXMLElement(self::ROOT_ELEMENT);
        $xml['merchantCode'] = $this->merchantCode;
        $xml['version'] = '1.4';
        $submit = $this->_addSubmitElement($xml);
        $jsonData = $this->_addOrderElement($submit);
        return json_encode($jsonData);
        //return $xml;
    }

     /**
      * Build xml for 3ds2 processing Request
      *
      * @param string $merchantCode
      * @param string $orderCode
      * @param array $paymentDetails
      * @param string $dfReferenceId
      * @return SimpleXMLElement $xml
      */
    public function build3Ds2Secure(
        $merchantCode,
        $orderCode,
        $paymentDetails,
        $dfReferenceId
    ) {
        $this->merchantCode = $merchantCode;
        $this->dfReferenceId = $dfReferenceId;
        $this->orderCode = $orderCode;
        $this->paymentDetails = $paymentDetails;
        $xml = new \SimpleXMLElement(self::ROOT_ELEMENT);
        $xml['merchantCode'] = $this->merchantCode;
        $xml['version'] = '1.4';
        $submit = $this->_addSubmitElement($xml);
        $this->_addOrderElement($submit);
        return $xml;
    }
    
    /**
     * Add submit tag to xml
     *
     * @param SimpleXMLElement $xml
     * @return SimpleXMLElement
     */
    private function _addSubmitElement($xml)
    {
        return $xml->addChild('submit');
    }

    /**
     * Build xml for 3dsecure processing Request
     *
     * @param string $merchantCode
     * @param string $orderCode
     * @param array $paymentDetails
     * @param mixed $paResponse
     * @param bool $echoData
     * @return SimpleXMLElement $xml
     */
    public function build3DSecure(
        $merchantCode,
        $orderCode,
        $paymentDetails,
        $paResponse,
        $echoData
    ) {
         $this->merchantCode = $merchantCode;
        $this->paResponse = $paResponse;
        $this->echoData = $echoData;
        $this->orderCode = $orderCode;
        $this->paymentDetails = $paymentDetails;
        $xml = new \SimpleXMLElement(self::ROOT_ELEMENT);
        $xml['merchantCode'] = $this->merchantCode;
        $xml['version'] = '1.4';
        $submit = $this->_addSubmitElement($xml);
        $this->_addOrderElement($submit);
        return $xml;
    }
    /**
     * Add order tag to xml
     *
     * @param SimpleXMLElement $submit
     * @return SimpleXMLElement $order
     */
    private function _addOrderElement($submit)
    {
        $orderData = [];
        $orderData['transactionReference'] = $this->_addTransactionRef();
        $orderData['merchant'] = $this->_addMerchantInfo();
        $orderData['instruction'] = $this->_addInstructionInfo();
        $orderData['shopperLanguageCode'] = "en";
        return $orderData;
    }
    /**
     * Add Customer Risk Data  and its child tag to xml
     *
     * @param SimpleXMLElement $order
     */
    protected function _addCustomerRiskData($order)
    {
        $riskData = $order->addChild('riskData');
        $accountCreatedDate = strtotime($this->cusDetails['created_at']);
        $accountUpdatedDate = strtotime($this->cusDetails['updated_at']);
        
        $orderCreateDate = strtotime($this->cusDetails['order_details']['created_at']);
        $orderUpdateDate = strtotime($this->cusDetails['order_details']['updated_at']);
        if ($this->shippingAddress) {
            $shippingNameMatchesAccountName = ($this->billingAddress['firstName'] == $this->
            shippingAddress['firstName']) ? 'true' : 'false';
        } else {
            $shippingNameMatchesAccountName = 'false';
        }
        //Authentication risk data
        $authenticationRiskData = $riskData->addChild('authenticationRiskData');
        $authenticationRiskData['authenticationMethod'] = !empty($this->shopperId)? 'localAccount' : 'guestCheckout';
        $authenticationTimestampElement = $authenticationRiskData->addChild('authenticationTimestamp');
        $dateElement = $authenticationTimestampElement->addChild('date');
        $dateElement['second'] = date("s");
        $dateElement['minute'] = date("i");
        $dateElement['hour'] = date("H");
        $dateElement['dayOfMonth'] = date("d");
        $dateElement['month'] = date("m");
        $dateElement['year'] = date("Y");
        
        //shoppper account risk data
        $shopperAccountRiskData = $riskData->addChild('shopperAccountRiskData');
        $shopperAccountRiskData['transactionsAttemptedLastDay'] = $this->cusDetails['order_count']['last_day_count'];
        $shopperAccountRiskData['transactionsAttemptedLastYear'] = $this->cusDetails['order_count']['last_year_count'];
        $shopperAccountRiskData['purchasesCompletedLastSixMonths'] = $this->
            cusDetails['order_count']['last_six_months_count'];
        $shopperAccountRiskData['addCardAttemptsLastDay'] = $this->cusDetails['card_count'];
        $shopperAccountRiskData['previousSuspiciousActivity'] = 'false';
        $shopperAccountRiskData['shippingNameMatchesAccountName'] = $shippingNameMatchesAccountName;
        $shopperAccountRiskData['shopperAccountAgeIndicator'] = $this->cusDetails['shopperAccountAgeIndicator'];
        $shopperAccountRiskData['shopperAccountChangeIndicator'] = $this->cusDetails['shopperAccountChangeIndicator'];
        $shopperAccountRiskData['shopperAccountPasswordChangeIndicator'] = $this->
            cusDetails['shopperAccountPasswordChangeIndicator'];
        $shopperAccountRiskData['shopperAccountShippingAddressUsageIndicator'] = $this->
            cusDetails['shopperAccountShippingAddressUsageIndicator'];
        $shopperAccountRiskData['shopperAccountPaymentAccountIndicator'] = $this->
            cusDetails['shopperAccountPaymentAccountIndicator'];
        
        $shopperAccountRiskDataElement = $shopperAccountRiskData->addChild('shopperAccountCreationDate');
        $shopperAccountRiskDataElementChild = $shopperAccountRiskDataElement->addChild('date');
        $shopperAccountRiskDataElementChild['dayOfMonth'] = date("d", $accountCreatedDate);
        $shopperAccountRiskDataElementChild['month'] = date("m", $accountCreatedDate);
        $shopperAccountRiskDataElementChild['year'] = date("Y", $accountCreatedDate);
        
        $shopperAccountRiskDataElement1 = $shopperAccountRiskData->addChild('shopperAccountModificationDate');
        $shopperAccountRiskDataElementChild1 = $shopperAccountRiskDataElement1->addChild('date');
        $shopperAccountRiskDataElementChild1['dayOfMonth'] = date("d", $accountUpdatedDate);
        $shopperAccountRiskDataElementChild1['month'] = date("m", $accountUpdatedDate);
        $shopperAccountRiskDataElementChild1['year'] = date("Y", $accountUpdatedDate);
        
        $shopperAccountPasswordChangeAttribute = $shopperAccountRiskData->addChild('shopperAccountPasswordChangeDate');
        $shopperAccountPasswordChangeElement = $shopperAccountPasswordChangeAttribute->addChild('date');
        $shopperAccountPasswordChangeElement['dayOfMonth'] = date("d", $accountUpdatedDate);
        $shopperAccountPasswordChangeElement['month'] = date("m", $accountUpdatedDate);
        $shopperAccountPasswordChangeElement['year'] = date("Y", $accountUpdatedDate);
        
        $shopperAccountShippingAddressAttribute = $shopperAccountRiskData->
            addChild('shopperAccountShippingAddressFirstUseDate');
        $shopperAccountShippingAddressElement = $shopperAccountShippingAddressAttribute->addChild('date');
        $shopperAccountShippingAddressElement['dayOfMonth'] = date("d", $orderCreateDate);
        $shopperAccountShippingAddressElement['month'] = date("m", $orderCreateDate);
        $shopperAccountShippingAddressElement['year'] = date("Y", $orderCreateDate);
        
        $shopperAccountPaymentAccountFirstUseDateAttribute = $shopperAccountRiskData->
            addChild('shopperAccountPaymentAccountFirstUseDate');
        $shopperAccountPaymentAccountFirstUseDateElement = $shopperAccountPaymentAccountFirstUseDateAttribute->
            addChild('date');
        $shopperAccountPaymentAccountFirstUseDateElement['dayOfMonth'] = date("d", $orderUpdateDate);
        $shopperAccountPaymentAccountFirstUseDateElement['month'] = date("m", $orderUpdateDate);
        $shopperAccountPaymentAccountFirstUseDateElement['year'] = date("Y", $orderUpdateDate);
        
        // Transaction Risk Data
        $transactionRiskData = $riskData->addChild('transactionRiskData');
        $transactionRiskData['shippingMethod'] = 'other';
        /* Set Delivery time if exists */
        $transactionRiskData['deliveryEmailAddress'] = $this->shopperEmail;
        $transactionRiskData['reorderingPreviousPurchases'] = $this->cusDetails['order_details']['previous_purchase'];
        $transactionRiskData['preOrderPurchase'] = 'false';
        $transactionRiskData['giftCardCount'] = 0;
        return $riskData;
    }
    /**
     * Add Additional3Ds data and its child tag to xml
     *
     * @param SimpleXMLElement $order
     */
    protected function _addAdditional3DsElement($order)
    {
        $dfReferenceId = isset($this->paymentDetails['dfReferenceId']) ? $this->paymentDetails['dfReferenceId'] : '';
        if ($dfReferenceId) {
            $addisional3DsElement = $order->addChild('additional3DSData');
            $addisional3DsElement['dfReferenceId'] = $this->paymentDetails['dfReferenceId'];
            $addisional3DsElement['challengeWindowSize'] = "390x400";
            $addisional3DsElement['challengePreference'] = "challengeMandated";
            return $addisional3DsElement;
        }
    }
      /**
       * Add dynamicInteractionType and its attribute tag to xml
       *
       * @param SimpleXMLElement $order
       */
    private function _addDynamic3DSElement($order)
    {
        if (! $this->threeDSecureConfig->isDynamic3DEnabled()) {
            return;
        }

        $threeDSElement = $order->addChild('dynamic3DS');
        if ($this->threeDSecureConfig->is3DSecureCheckEnabled()) {
            $threeDSElement['overrideAdvice'] = self::DYNAMIC3DS_DO3DS;
        } else {
            $threeDSElement['overrideAdvice'] = self::DYNAMIC3DS_NO3DS;
        }
    }

    /**
     * Add description tag to xml
     *
     * @param SimpleXMLElement $order
     */
    private function _addDescriptionElement($order)
    {
        $description = $order->addChild('description');
        $this->_addCDATA($description, $this->orderDescription);
    }

    /**
     * Add amount tag to xml
     *
     * @param SimpleXMLElement $order
     */
    private function _addAmountElement($order)
    {
        $amountElement = $order->addChild('amount');
        $amountElement['currencyCode'] = $this->currencyCode;
        $amountElement['exponent'] = $this->exponent;
        $amountElement['value'] = $this->_amountAsInt($this->amount);
    }

    /**
     * Add PaymentDetails and its child tag to xml
     *
     * @param SimpleXMLElement $order
     */
    private function _addPaymentDetailsElement($order)
    {
        $paymentDetails = $order->addChild('paymentDetails');

        $paymentType = $paymentDetails->addChild($this->paymentType);
        $paymentType->addChild('protocolVersion', $this->protocolVersion);
        $paymentType->addChild('signature', $this->signature);
        $paymentType->addChild('signedMessage', $this->signedMessage);
        //$paymentType->addChild('session', $this->signedMessage);
        $session = $paymentDetails->addChild('session');
        $session['id'] = $this->paymentDetails['sessionId'];
        $session['shopperIPAddress'] = $this->shopperIpAddress;
        if ($this->paResponse) {
            $info3DSecure = $paymentType->addChild('info3DSecure');
            $info3DSecure->addChild('paResponse', $this->paResponse);
        }
    }

    /**
     * Add shopper and its child tag to xml
     *
     * @param SimpleXMLElement $order
     */
    private function _addShopperElement($order)
    {
        $shopper = $order->addChild('shopper');

        $shopper->addChild('shopperEmailAddress', $this->shopperEmail);
        $browser = $shopper->addChild('browser');

        $acceptHeader = $browser->addChild('acceptHeader');
        $this->_addCDATA($acceptHeader, $this->acceptHeader);

        $userAgentHeader = $browser->addChild('userAgentHeader');
        $this->_addCDATA($userAgentHeader, $this->userAgentHeader);
        return $shopper;
    }

    /**
     * Add cdata to xml
     *
     * @param SimpleXMLElement $element
     * @param string $content
     */
    private function _addCDATA($element, $content)
    {
        $node = dom_import_simplexml($element);
        $no   = $node->ownerDocument;
        $node->appendChild($no->createCDATASection($content));
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
     * @param
     */
    private function _addTransactionRef()
    {
        return $this->orderCode;
    }

    /**
     * Add merchant Info
     *
     * @param
     */
    private function _addMerchantInfo()
    {
        $merchantData = ["entity" => "default"];
        return $merchantData;
    }

    /**
     * Add instruction Info
     *
     * @param
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
     * @param
     */
    private function _addNarrativeInfo()
    {
        $narrationData = ["line1" => "trading name"];
        return $narrationData;
    }

    /**
     * Add value Info
     *
     * @param
     */
    private function _addValueInfo()
    {
        $valueData = ["currency" => $this->currencyCode, "amount" => $this->_amountAsInt($this->amount)];
        return $valueData;
    }

    /**
     * Add Payment Info
     *
     * @param
     */
    private function _addPaymentInfo()
    {
        $paymentData = ["type" => "card/wallet+goolepay",
                "walletToken" => json_encode($this->getGoolepayToken())];
        return $paymentData;
    }
    /**
     * Return GoolepayToken
     *
     * @return string GoolepayToken
     */
    
    private function getGoolepayToken()
    {
        return ["protocolVersion"=>$this->protocolVersion,
                "signature"=>$this->signature,
                "signedMessage"=>$this->signedMessage
                ];
    }
}
