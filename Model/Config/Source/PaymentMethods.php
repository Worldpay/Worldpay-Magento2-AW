<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Config\Source;

class PaymentMethods extends \Magento\Framework\App\Config\Value
{
    /**
     * ToOptionArray
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'AMEX', 'label' => __('American Express')],
            ['value' => 'VISA', 'label' => __('Visa')],
            ['value' => 'DISCOVER', 'label' => __('Discover')],
            ['value' => 'JCB', 'label' => __('Japanese Credit Bank')],
            ['value' => 'MASTERCARD', 'label' => __('Master Card')]
        ];
    }
}
