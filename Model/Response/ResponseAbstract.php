<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Response;

use Exception;
use Magento\Framework\Exception\LocalizedException;

/**
 * Abstract class used for reading the xml
 */
abstract class ResponseAbstract
{
    public const INTERNAL_ERROR = 1;
    public const PARSE_ERROR = 2;
    public const SECURITY_ERROR = 4;
    public const INVALID_REQUEST_ERROR = 5;
    public const INVALID_CONTENT_ERROR = 6;
    public const PAYMENT_DETAILS_ERROR = 7;

    /**
     * @var SimpleXmlElement
     */
    protected $_responseXml;

    /**
     * @var string
     */
    protected $_merchantCode;

    /**
     * @var string
     */
    protected $_paymentStatus;

    /**
     * @var string
     */
    protected $_payAsOrder;

    /**
     * @var \Magento\Framework\Exception\LocalizedException
     */
    protected $_errorMessage;

    /**
     * @var string
     */
    protected $_wpOrderId;

    /**
     * Get Xml
     *
     * @return SimpleXMLElement
     */
    public function getXml()
    {
        return $this->_responseXml;
    }

    /**
     * Set Response
     *
     * @param SimpleXmlElement $response
     * @return $this
     */
    public function setResponse($response)
    {
        try {
            $this->_responseXml = new \SimpleXmlElement($response);
            //$this->_merchantCode = $this->_responseXml['merchantCode'];
        } catch (Exception $e) {
            throw new \Magento\Framework\Exception\LocalizedException(
                "Could not parse response XML".$e->getMessage()
            );
        }

        return $this;
    }
}
