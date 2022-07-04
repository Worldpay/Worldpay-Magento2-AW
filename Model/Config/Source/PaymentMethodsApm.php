<?php
/**
 * @copyright 2017 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Config\Source;

class PaymentMethodsApm extends \Magento\Framework\App\Config\Value
{
    /**
     * ToOptionArray
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'ACH_DIRECT_DEBIT-SSL', 'label' => __('ACH Pay')]
        ];
    }
}
