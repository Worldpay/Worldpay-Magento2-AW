<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Block\Checkout\Hpp\Json;

class Config extends \Magento\Framework\View\Element\Template
{

    /**
     * Constructor
     *
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Sapient\AccessWorldpay\Model\Checkout\Hpp\Json\Config\Factory $configfactory
     * @param \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Sapient\AccessWorldpay\Model\Checkout\Hpp\Json\Config\Factory $configfactory,
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger,
        array $data = []
    ) {
        $this->configfactory = $configfactory;
        $this->wplogger = $wplogger;
        parent::__construct($context, $data);
    }
    /**
     * Get toHtml
     *
     * @return json
     */
    protected function _toHtml()
    {
        return json_encode($this->_getConfig());
    }
    /**
     * Retrive config for checkout Iframe
     *
     * @return array
     */
    private function _getConfig()
    {

        $configFactory = $this->configfactory;
        $config = $configFactory->create('checkoutWorldPayLibraryObject', 'checkout-payment-worldpay-container');
        $jsConfig = [
            'type' => $config->getType(),
            'iframeIntegrationId' => $config->getIframeIntegrationID(),
            'iframeHelperURL' => $config->getIframeHelperURL(),
            'iframeBaseURL' => $config->getIframeBaseURL(),
            'url' => $config->getUrl(),
            'target' => $config->getTarget(),
            'debug' => $config->isDebug(),
            'language' => $config->getLanguage(),
            'country' => $config->getCountry(),
            'preferredPaymentMethod' => (string)$config->getPreferredPaymentMethod(),
            'successURL' => $config->getUrlConfig()->getSuccessURL(),
            'cancelURL' => $config->getUrlConfig()->getCancelURL(),
            'failureURL' => $config->getUrlConfig()->getFailureURL(),
            'pendingURL' => $config->getUrlConfig()->getPendingURL(),
            'errorURL' => $config->getUrlConfig()->getErrorURL(),
        ];
        return $jsConfig;
    }
}
