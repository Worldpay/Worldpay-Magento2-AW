<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Sapient\AccessWorldpay\Block;

use Magento\Framework\App\DefaultPathInterface;
use Magento\Framework\View\Element\Template\Context;
use Sapient\AccessWorldpay\Model\AccessWorldpayConfigProvider;
use Sapient\AccessWorldpay\Helper\Data;

class SavedCardLink extends \Magento\Framework\View\Element\Html\Link\Current
{
    /**
     * @var $_scopeConfig
     */
    protected $_scopeConfig = null;

    /**
     * Constructor
     *
     * @param \Magento\Backend\Block\Template\Context $context
     * @param AccessWorldpayConfigProvider $config
     * @param \Sapient\Worldpay\Helper\Data $helper
     * @param DefaultPathInterface $defaultPath
     * @param array $data
     */
    public function __construct(
        Context $context,
        AccessWorldpayConfigProvider $config,
        Data $helper,
        DefaultPathInterface $defaultPath,
        array $data = []
    ) {
        parent::__construct($context, $defaultPath);
        $this->config = $config;
        $this->helper = $helper;
    }

    /**
     * Display Html
     *
     * @return string
     */
    public function _toHtml()
    {
        
        if ($this->helper->isWorldPayEnable() && $this->checkSaveCardTabToBeEnabled()) {
             return parent::_toHtml();
        } else {
            return '';
        }
    }
    /**
     * Check Save Card TabTo Be Enabled
     *
     * @return string
     */
    public function checkSaveCardTabToBeEnabled()
    {
        
        if ($this->helper->getSaveCard() ||
            !empty($this->config->getSaveCardList())) {
            return true;
        }
    }
}
