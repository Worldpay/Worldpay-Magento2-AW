<?php

/**
 * @copyright 2020 Sapient
 */

namespace Sapient\AccessWorldpay\Model\XmlBuilder;

use Sapient\AccessWorldpay\Model\XmlBuilder\Config\ThreeDSecureConfig;
use Sapient\AccessWorldpay\Logger\AccessWorldpayLogger;

/**
 * Build xml for Direct Order request
 */
class DirectOrder
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
     * @param string $shippingAddress
     * @param float $billingAddress
     * @param string $shopperId
     * @param string $quoteId
     * @param string $verifiedToken
     * @return SimpleXMLElement $xml
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
        $verifiedToken
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
        $this->verifiedToken = $verifiedToken;

        $jsonData = $this->_addOrderElement();

        return json_encode($jsonData);
    }

    /**
     * Add order and its child tag to xml
     *
     * @return array $order
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
     * Add description  to json Obj
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

        if ($this->verifiedToken != '') {
            $instruction['paymentInstrument'] = $this->_addVerifiedTokenInfo();

        } else {
            $instruction['paymentInstrument'] = $this->_addPaymentInfo();
        }
        return $instruction;
    }

    /**
     * Add narrative Info
     *
     * @param
     */
    private function _addNarrativeInfo()
    {
        $narrationData = ["line1" => $this->paymentDetails['narrative']];
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
     * Add payment Info
     *
     * @param array $paymentData
     */
    private function _addPaymentInfo()
    {
        if (isset($this->paymentDetails['cardHolderName'])) {
            $paymentData = ["type" => "card/plain",
                "cardHolderName" => $this->paymentDetails['cardHolderName'],
                "cardNumber" => $this->paymentDetails['cardNumber'],
                "cardExpiryDate" => ["month" => (int) $this->paymentDetails['expiryMonth'],
                 "year" => (int) $this->paymentDetails['expiryYear']],
                 "cvc" => $this->paymentDetails['cvc']];
            return $paymentData;
        } else {

            $obj = \Magento\Framework\App\ObjectManager::getInstance();

            $quote = $obj->get(\Magento\Checkout\Model\Session::class)->getQuote()->load($this->quoteId);

            $addtionalData = $quote->getPayment()->getOrigData();
            $ccData = $addtionalData['additional_information'];

            $paymentData =
                ["type" => "card/plain",
                 "cardHolderName" => $ccData['cc_name'],
                "cardNumber" => $ccData['cc_number'],
                "cardExpiryDate" => ["month" => (int) $ccData['cc_exp_month'],
                "year" => (int) $ccData['cc_exp_year']],
                "cvc" => $ccData['cvc']];
            return $paymentData;
        }
    }

    /**
     * Return verified token
     *
     * @return array $paymentData
     */
    private function _addVerifiedTokenInfo()
    {
        $paymentData = ["type" => "card/token",
                        "href" => $this->verifiedToken];
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
        return round($amount, self::EXPONENT, PHP_ROUND_HALF_EVEN) * pow(10, self::EXPONENT);
    }
}
