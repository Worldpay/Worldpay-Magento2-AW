<?php
/**
 *
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Helper;

class Registry extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     *
     * @var $registry
     */
    protected $_registry;

    /**
     * Constructer
     *
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Framework\Registry $registry
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\Registry $registry
    ) {

         $this->_registry = $registry;
         parent::__construct($context);
    }

    /**
     * Remove All Data
     */
    public function removeAllData()
    {
        $keys = [
            'worldpayRedirectUrl',
        ];

        foreach ($keys as $key) {
            $this->removeDataFromRegistry($key);
        }

        return $this;
    }

    /**
     * Get Worldpay Redirect Url
     */
    public function getworldpayRedirectUrl()
    {
        return $this->getDataFromRegistry('worldpayRedirectUrl');
    }

    /**
     * Set Worldpay Redirect Url
     *
     * @param string $data
     */
    public function setworldpayRedirectUrl($data)
    {
        return $this->addDataToRegistry('worldpayRedirectUrl', $data);
    }

    /**
     * Add Data To Registry
     *
     * @param string $name
     * @param string $data
     */
    public function addDataToRegistry($name, $data)
    {
        $this->removeDataFromRegistry($name);

        $this->_registry->register($name, $data);

        return $this;
    }

    /**
     * Get Data From Registry
     *
     * @param string $name
     */
    public function getDataFromRegistry($name)
    {
        if ($data = $this->_registry->registry($name)) {
            return $data;
        }

        return false;
    }

    /**
     * Remove Data From Registry
     *
     * @param string $name
     */
    public function removeDataFromRegistry($name)
    {
        $this->_registry->unregister($name);

        return $this;
    }
}
