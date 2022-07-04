<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Response;

class AdminhtmlResponse extends \Sapient\AccessWorldpay\Model\Response\ResponseAbstract
{
    /**
     * Parse Capture Response
     *
     * @param SimpleXmlElement $xml
     * @return void
     */
    public function parseCaptureResponse($xml)
    {
        $document = new \SimpleXmlElement($xml);
        return $document;
    }
    
    /**
     * Parse Refund Response
     *
     * @param SimpleXmlElement $xml
     * @return void
     */
    public function parseRefundResponse($xml)
    {
        $document = new \SimpleXmlElement($xml);
        return $document;
    }

    /**
     * Parse Inquiry Response
     *
     * @param SimpleXmlElement $xml
     * @return void
     */
    public function parseInquiryResponse($xml)
    {
        $document = new \SimpleXmlElement($xml);
        return $document;
    }
}
