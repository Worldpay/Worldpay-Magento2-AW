/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*browser:true*/
/*global define*/
define(
    [
        'jquery',
        'uiComponent',
        'Magento_Checkout/js/model/payment/renderer-list'
    ],
    function (
        $,
        Component,
        rendererList
    ) {
        'use strict';
        var integrationmode = window.checkoutConfig.payment.ccform.intigrationmode;
        if (integrationmode === 'web_sdk') {
        var CCcomponent = 'Sapient_AccessWorldpay/js/view/payment/method-renderer/websdk-method';
       }else {
         var CCcomponent = 'Sapient_AccessWorldpay/js/view/payment/method-renderer/cc-method';  
       }
        var APMcomponent = 'Sapient_AccessWorldpay/js/view/payment/method-renderer/apm-method';
        var Walletscomponent = 'Sapient_AccessWorldpay/js/view/payment/method-renderer/wallets-method';
        var Hppcomponent = 'Sapient_AccessWorldpay/js/view/payment/method-renderer/hpp-method';

        var methods = [
            {type: 'worldpay_cc', component: CCcomponent},
            {type: 'worldpay_apm', component: APMcomponent},
            {type: 'worldpay_wallets', component: Walletscomponent},
            {type: 'hpp', component: Hppcomponent}
        ];

         $.each(methods, function (k, method) {
            rendererList.push(method);
        });

        return Component.extend({});
    }
);
