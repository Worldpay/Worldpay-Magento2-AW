<?php
declare(strict_types=1);

namespace Sapient\AccessWorldpay\Model\Payment;

class Hpp extends \Magento\Payment\Model\Method\AbstractMethod
{
    /**
     * @var string
     */
    protected $_code = "hpp";
    
    /**
     * @var boolean
     */
    protected $_isOffline = true;
}
