<?php

declare(strict_types=1);

namespace Sapient\AccessWorldpay\Model\Resolver;

use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\ContextInterface;
use Magento\Framework\GraphQl\Config\Element\Field;

class ApplepaySettings implements ResolverInterface
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
                 
        $settings[] =[
            'enabled' => $this->scopeConfig->getValue(
                'worldpay/wallets_config/apple_pay_wallets_config/enabled',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            ),
            'certificationkey' => $this->scopeConfig->getValue(
                'worldpay/wallets_config/apple_pay_wallets_config/certification_key',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            ),
            'certificationcrt' => $this->scopeConfig->getValue(
                'worldpay/wallets_config/apple_pay_wallets_config/certification_crt',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            ),
            'certificationpassword' => $this->scopeConfig->getValue(
                'worldpay/wallets_config/apple_pay_wallets_config/certification_password',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            ),
            'merchantname' => $this->scopeConfig->getValue(
                'worldpay/wallets_config/apple_pay_wallets_config/merchant_name',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            ),
            'domainname' => $this->scopeConfig->getValue(
                'worldpay/wallets_config/apple_pay_wallets_config/domain_name',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            )
        ];
       
        return ['settings' => $settings];
    }
}
