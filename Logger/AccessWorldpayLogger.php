<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Logger;

class AccessWorldpayLogger extends \Monolog\Logger
{
    /**
     *  Add Record
     *
     * @param string $level
     * @param string $message
     * @param array $context
     */
    public function addRecord($level, $message, array $context = []) : bool
    {
        $ObjectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $logEnabled = (bool) $ObjectManager->get(
            \Magento\Framework\App\Config\ScopeConfigInterface::class
        )
                ->getValue('worldpay/general_config/enable_logging');
        if ($logEnabled) {
            return parent::addRecord($level, $message, $context);
        }
        return false;
    }
}
