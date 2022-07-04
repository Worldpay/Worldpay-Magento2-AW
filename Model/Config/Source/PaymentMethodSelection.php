<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Config\Source;

class PaymentMethodSelection implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @return array
     */
    public const RADIO_BUTTONS = 'radio';
    public const DROPDOWN_MENU = 'dropdown';

    /**
     * ToOptionArray
     *
     * @return array
     */
    public function toOptionArray()
    {

        return [
            ['value' => self::RADIO_BUTTONS, 'label' => __('Radio Buttons')],
            ['value' => self::DROPDOWN_MENU, 'label' => __('Dropdown Menu')],
        ];
    }
}
