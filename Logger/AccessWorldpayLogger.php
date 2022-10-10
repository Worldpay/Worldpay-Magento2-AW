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
     * @param int $level
     * @param string $message
     * @param array $context
     * @param DateTimeImmutable $datetime
     */
    public function addRecord(int $level, string $message, array $context = [], $datetime = null): bool
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
