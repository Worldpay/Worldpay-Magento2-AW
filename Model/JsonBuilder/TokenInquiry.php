<?php
/**
 * @copyright 2017 Sapient
 */
namespace Sapient\AccessWorldpay\Model\JsonBuilder;

/**
 * Build xml for token inquiry request
 */
class TokenInquiry
{
    /**
     * @var Mage_Customer_Model_Customer
     */
    private $customer;

    /**
     * @var Sapient_WorldPay_Model_Token
     */
    private $tokenModel;

    /**
     * @var string
     */
    protected $merchantCode;

    /**
     * Constructor
     *
     * @param array $args
     */
    public function __construct(array $args = [])
    {
        if (isset($args['tokenModel']) && $args['tokenModel'] instanceof \Sapient\AccessWorldPay\Model\SavedToken) {
            $this->tokenModel = $args['tokenModel'];
        }

        if (isset($args['customer']) && $args['customer'] instanceof \Magento\Customer\Model\Customer) {
            $this->customer = $args['customer'];
        }

        if (isset($args['merchantCode'])) {
            $this->merchantCode = $args['merchantCode'];
        }
    }

    /**
     * Build xml for processing Request
     *
     * @return SimpleXMLElement $xml
     */
    public function build()
    {
        $xml = new \SimpleXMLElement(self::ROOT_ELEMENT);
        $xml['version'] = '1.4';
        $xml['merchantCode'] = $this->merchantCode;

        $inquiry = $this->_addInquiryElement($xml);
        $this->_addTokenInquiryElement($inquiry);

        return $xml;
    }

    /**
     * Add inquiry Element
     *
     * @param SimpleXMLElement $xml
     * @return SimpleXMLElement
     */
    private function _addInquiryElement($xml)
    {
        return $xml->addChild('inquiry'); 
    }

    /**
     * Add tokenInquiry element
     *
     * @param SimpleXMLElement $inquiry
     * @return SimpleXMLElement $xml
     */
    private function _addTokenInquiryElement($inquiry)
    {
        $tokenInquiry = $inquiry->addChild('paymentTokenInquiry');
        $tokenInquiry['tokenScope'] = $this->tokenModel->getTokenType();

        if ($this->tokenModel->getTokenType() == self::TOKEN_SCOPE) {
            $tokenInquiry->addChild('authenticatedShopperID', $this->customer->getId());
        }
        $tokenInquiry->addChild('paymentTokenID', $this->tokenModel->getTokenCode());
        
        return $tokenInquiry;
    }
}
