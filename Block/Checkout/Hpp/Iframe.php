<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Block\Checkout\Hpp;
 
class Iframe extends \Magento\Framework\View\Element\Template
{
    /**
     * Set default template
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('Sapient_AccessWorldpay::checkout/hpp/iframe.phtml');
    }
}
