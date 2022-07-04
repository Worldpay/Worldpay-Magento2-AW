<?php
/**
 * @copyright 2017 Sapient
 */
namespace Sapient\AccessWorldpay\Model\JsonBuilder;

/**
 * Build xml for Capture request
 */
class Capture
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
    private $requestType;

    /**
     * Build xml for processing Request
     *
     * @param string $merchantCode
     * @param string $orderCode
     * @param string $currencyCode
     * @param float $amount
     * @param string $requestType
     * @return SimpleXMLElement $xml
     */
    public function build($merchantCode, $orderCode, $currencyCode, $amount, $requestType)
    {
        $this->merchantCode = $merchantCode;
        $this->orderCode = $orderCode;
        $this->currencyCode = $currencyCode;
        $this->amount = $amount;
        $this->requestType = $requestType;

        $jsonData = $this->_addCapture();
        return json_encode($jsonData);
    }

    /**
     * Add tag capture to Json
     *
     * @return array $captureData
     */
    private function _addCapture()
    {
        if ($this->requestType == 'partial_capture') {
            $captureData = [];

            $captureData['value'] = $this->_addValue();
            $captureData['reference'] = 'Partial-capture-for-'.$this->orderCode;
        } else {
            $captureData = '';
        }
        return $captureData;
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
