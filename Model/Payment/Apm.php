<?php
declare(strict_types=1);

namespace Sapient\AccessWorldpay\Model\Payment;

class Apm extends \Magento\Payment\Model\Method\AbstractMethod
{
    /**
     * @var string $_code
     */
    protected $_code = "apm";

    /**
     * @var string $_isOffline
     */
    protected $_isOffline = true;
}
