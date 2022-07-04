<?php

declare(strict_types=1);
namespace Sapient\AccessWorldpay\Model\Resolver;

use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\GraphQl\Config\Element\Field;

class CustomLabelsSettings implements ResolverInterface
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
        $adminsetting=[];
        $checkoutsetting=[];
        $accountsetting=[];
        $accountsettings = $this->scopeConfig
        ->getValue(
            'worldpay_custom_labels/my_account_labels/my_account_label',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        $accountsettings = json_decode($accountsettings, true);
        if ($accountsettings !== null) {
            foreach ($accountsettings as $key => $settings) {
                $accountsetting[] =
                ['labelCode'=>$key,'defaultLabel'
                =>$settings['wpay_label_desc'],'customLabel'=>$settings['wpay_custom_label']];
            }
        }
        $adminsettings = $this->scopeConfig
        ->getValue(
            'worldpay_custom_labels/admin_labels/admin_label',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        $adminsettings = $adminsettings!==null?json_decode($adminsettings, true):null;
        if ($adminsettings !== null) {
            foreach ($adminsettings as $key => $settings) {
                $adminsetting[] =
                ['labelCode'=>$key,'defaultLabel'
                =>$settings['wpay_label_desc'],'customLabel'=>$settings['wpay_custom_label']];
            }
        }
        $checkoutsettings = $this->scopeConfig
        ->getValue(
            'worldpay_custom_labels/checkout_labels/checkout_label',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        $checkoutsettings = json_decode($checkoutsettings, true);
        if ($checkoutsettings !== null) {
            foreach ($checkoutsettings as $key => $settings) {
                $checkoutsetting[] =
                ['labelCode'=>$key,'defaultLabel'
                =>$settings['wpay_label_desc'],'customLabel'=>$settings['wpay_custom_label']];
            }
        }
        $settings[] =[
            'accountLabels'=>['setting'=>['list'=>$accountsetting]],
            'adminLabels'=>['setting'=>['list'=>$adminsetting]],
            'checkoutLabels'=>['setting'=>['list'=>$checkoutsetting]]
            
        ];
        foreach ($settings as $key => $value) {
            if (empty($value) || !is_array($value)) {
                unset($settings[$key]);
            }
        }
        return ['settings' => $settings];
    }
}
