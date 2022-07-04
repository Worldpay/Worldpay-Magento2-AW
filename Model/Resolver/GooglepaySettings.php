<?php

declare(strict_types=1);

namespace Sapient\AccessWorldpay\Model\Resolver;

use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\ContextInterface;
use Magento\Framework\GraphQl\Config\Element\Field;

class GooglepaySettings implements ResolverInterface
{
    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }
    
    /**
     * Resolver
     *
     * @param Field $field
     * @param Context $context
     * @param ResolveInfo $info
     * @param array $value
     * @param array $args
     * @return array
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        $methods=[];
        $authoptions=[];
        $methodList = $this->scopeConfig->getValue(
            'worldpay/wallets_config/google_pay_wallets_config/paymentmethods',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        if ($methodList !== null) {
            $list = explode(',', $methodList);
            foreach ($list as $method) {
                $methods[] =$method;
            }
        }

        $optionList = $this->scopeConfig->getValue(
            'worldpay/wallets_config/google_pay_wallets_config/authmethods',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        if ($optionList !== null) {
            $options = explode(',', $optionList);
            foreach ($options as $option) {
                $authoptions[] =$option;
            }
        }
            $settings[] =[
                'enabled' => $this->scopeConfig->getValue(
                    'worldpay/wallets_config/google_pay_wallets_config/enabled',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                ),
                'paymentmethods'=>['method'=>$methods],
                'supportedauthentication'=>['option'=>$authoptions],
                'gatewayname' => $this->scopeConfig->getValue(
                    'worldpay/wallets_config/google_pay_wallets_config/gateway_merchantname',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                ),
                'gatewaymerchantid' => $this->scopeConfig->getValue(
                    'worldpay/wallets_config/google_pay_wallets_config/gateway_merchantid',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                ),
                'googlemerchantid' => $this->scopeConfig->getValue(
                    'worldpay/wallets_config/google_pay_wallets_config/google_merchantid',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                ),
                'googlemerchantname' => $this->scopeConfig->getValue(
                    'worldpay/wallets_config/google_pay_wallets_config/google_merchantname',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                ),
                'test3dscardholdername' => $this->scopeConfig->getValue(
                    'worldpay/wallets_config/google_pay_wallets_config/test_cardholdername',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE
                )
            ];
        
            return ['settings' => $settings];
    }
}
