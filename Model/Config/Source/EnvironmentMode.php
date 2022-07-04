<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Config\Source;

class EnvironmentMode implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * ToOptionArray method
     *
     * @return array
     */
    public function toOptionArray()
    {

        return [
            ['value' => 'Test Mode', 'label' => __('Test Mode')],
            ['value' => 'Live Mode', 'label' => __('Live Mode')],
        ];
    }
}
