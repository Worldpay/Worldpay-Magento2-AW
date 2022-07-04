<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Config\Source;

class HppIntegration implements \Magento\Framework\Option\ArrayInterface
{
    /**
     * @return array
     */
    public const OPTION_VALUE_FULL_PAGE = 'full_page';
    public const OPTION_VALUE_IFRAME = 'iframe';

    /**
     * ToOptionArray
     *
     * @return array
     */
    public function toOptionArray()
    {

        return [
            ['value' => self::OPTION_VALUE_FULL_PAGE, 'label' => __('Full page')],
            ['value' => self::OPTION_VALUE_IFRAME, 'label' => __('Iframe')],
        ];
    }
}
