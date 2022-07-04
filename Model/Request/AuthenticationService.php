<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Request;

use Exception;

class AuthenticationService extends \Magento\Framework\DataObject
{

    /**
     * Constructor
     *
     * @param \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger
     * @param \Sapient\AccessWorldpay\Helper\Data $worldpayhelper
     */
    public function __construct(
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger,
        \Sapient\AccessWorldpay\Helper\Data $worldpayhelper
    ) {
        $this->_wplogger = $wplogger;
        $this->worldpayhelper = $worldpayhelper;
    }

    /**
     * Authentication request
     *
     * @param string $params
     * @param string $type
     *
     * @return bool
     */
    public function requestAuthenticated($params, $type = 'ecom')
    {
        return true;
    }
}
