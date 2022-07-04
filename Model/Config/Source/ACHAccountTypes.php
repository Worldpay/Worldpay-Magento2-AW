<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Config\Source;

class ACHAccountTypes extends \Magento\Framework\App\Config\Value
{
    /**
     * ToOptionArray
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
        ['value' => 'Checking', 'label' => __('Checking')],
        ['value' => 'Savings', 'label' => __('Savings')],
        ['value' => 'Corporate', 'label' => __('Corporate')],
        ['value' => 'Corp Savings', 'label' => __('Corp Savings')],

        ];
    }
}
