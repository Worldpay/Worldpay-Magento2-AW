<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\JsonBuilder;

/**
 * Build xml for Refund request
 */
class Refund
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
    private $currencyCode;
    /**
     * @var float
     */
    private $amount;
    /**
     * @var string
     */
    private $refundReference;
    /**
     * @var string
     */
    private $requestType;

    /**
     * Build xml for processing Request
     *
     * @param string $merchantCode
     * @param string $orderCode
     * @param string $currencyCode
     * @param float $amount
     * @param string $refundReference
     * @param string $requestType
     * @return SimpleXMLElement $xml
     */
    public function build($merchantCode, $orderCode, $currencyCode, $amount, $refundReference, $requestType)
    {
        $this->merchantCode = $merchantCode;
        $this->orderCode = $orderCode;
        $this->currencyCode = $currencyCode;
        $this->amount = $amount;
        $this->refundReference = $refundReference;
        $this->requestType = $requestType;

        $jsonData = $this->_addRefundElement();
        return json_encode($jsonData);
    }

   /**
    * Add tag refund to Json
    *
    * @return array $refundData
    */
    private function _addRefundElement()
    {
        if ($this->requestType == 'partial_refund') {
            $refundData = [];

            $refundData['value'] = $this->_addValue();
            $refundData['reference'] = 'Partial-refund-for-'.$this->orderCode;
        } else {
            $refundData = '';
        }
        return $refundData;
    }
    
    /**
     * Add amount to Json
     *
     * @return array
     */
    private function _addValue()
    {
        $data  = [];
        $data['amount'] = $this->_amountAsInt($this->amount);
        $data['currency'] = $this->currencyCode;
        return $data;
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
