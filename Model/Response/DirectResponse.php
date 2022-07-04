<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Response;

class DirectResponse extends \Sapient\AccessWorldpay\Model\Response\ResponseAbstract
{
    public const PAYMENT_AUTHORISED = 'AUTHORISED';

    /**
     * @var SimpleXmlElement
     */
    protected $_responseXml;
}
