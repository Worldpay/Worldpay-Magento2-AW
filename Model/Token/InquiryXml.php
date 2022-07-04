<?php
/**
 * @copyright 2017 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Token;

use Sapient\AccessWorldpay\Model\SavedToken;

/**
 * read from WP's token update response
 */
class InquiryXml implements UpdateInterface
{
    /**
     * @var SimpleXMLElement
     */
    private $_xml;

    /**
     * @param SimpleXMLElement $xml
     */
    public function __construct(\SimpleXMLElement $xml)
    {
        $this->_xml = $xml;
    }

    /**
     * Getting token code
     *
     * @return string
     */
    public function getTokenCode()
    {
        return (string)$this->_xml->reply->token->tokenDetails->paymentTokenID;
    }

    /**
     * Is success?
     *
     * @return bool
     */
    public function isSuccess()
    {
        return (string)$this->_xml->reply->token->paymentInstrument->cardDetails->derived->bin;
    }
}
