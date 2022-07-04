<?php

declare(strict_types=1);

namespace Sapient\AccessWorldpay\Model\Resolver;

use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Config\Element\Field;

class CamSettings implements ResolverInterface
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
        $worldpaysetting=[];
        $checkoutsetting=[];
        $accountsetting=[];
        $accountsettings = $this->scopeConfig->getValue(
            'worldpay_exceptions/my_account_alert_codes/response_codes',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        $accountsettings = json_decode($accountsettings, true);
        if ($accountsettings !== null) {
            foreach ($accountsettings as $key => $settings) {
                $accountsetting[] =['messageCode'=>$key,
                                    'actualMessage'=>$settings['exception_messages'],
                                    'customMessage'=>$settings['exception_module_messages']];
            }
        }
        $worldpaysettings = $this->scopeConfig->getValue(
            'worldpay_exceptions/adminexceptions/general_exception',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        $worldpaysettings = json_decode($worldpaysettings, true);
        if ($worldpaysettings !== null) {
            foreach ($worldpaysettings as $key => $settings) {
                $worldpaysetting[] =['messageCode'=>$key,
                                     'actualMessage'=>$settings['exception_messages'],
                                     'customMessage'=>$settings['exception_module_messages']];
            }
        }
        $checkoutsettings = $this->scopeConfig->getValue(
            'worldpay_exceptions/ccexceptions/cc_exception',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        $checkoutsettings = json_decode($checkoutsettings, true);
        if ($checkoutsettings !== null) {
            foreach ($checkoutsettings as $key => $settings) {
                $checkoutsetting[] =['messageCode'=>$key,
                                     'actualMessage'=>$settings['exception_messages'],
                                     'customMessage'=>$settings['exception_module_messages']];
            }
        }
        $settings[] =[
            'accountLevelMessage'=>['setting'=>['list'=>$accountsetting]],
            'accessWorldPayMessage'=>['setting'=>['list'=>$worldpaysetting]],
            'checkoutMessage'=>['setting'=>['list'=>$checkoutsetting]]
            
        ];
        foreach ($settings as $key => $value) {
            if (empty($value) || !is_array($value)) {
                unset($settings[$key]);
            }
        }
        return ['settings' => $settings];
    }
}
