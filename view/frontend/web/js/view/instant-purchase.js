/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
define([
    'ko',
    'jquery',
    'underscore',
    'uiComponent',
    'Magento_Ui/js/modal/confirm',
    'Magento_Customer/js/customer-data',
    'mage/url',
    'mage/template',
    'mage/translate',
    'text!Magento_InstantPurchase/template/confirmation.html',
    'mage/validation'
], function (ko, $, _, Component, confirm, customerData, urlBuilder, mageTemplate, $t, confirmationTemplate) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Sapient_AccessWorldpay/instant-purchase/instant-purchase',
            buttonText: $t('Instant Purchase'),
            purchaseUrl: urlBuilder.build('worldpay/button/placeOrder'),
            jwtUrl: urlBuilder.build('worldpay/hostedpaymentpage/jwt'),
            bin: null,
            sessionId: null,
            showButton: false,
            paymentToken: null,
            shippingAddress: null,
            billingAddress: null,
            shippingMethod: null,
            dfreference: null,
            productFormSelector: '#product_addtocart_form',
            confirmationTitle: $t('Instant Purchase Confirmation'),
            confirmationData: {
                message: $t('Are you sure you want to place order and pay?'),
                shippingAddressTitle: $t('Shipping Address'),
                billingAddressTitle: $t('Billing Address'),
                paymentMethodTitle: $t('Payment Method'),
                shippingMethodTitle: $t('Shipping Method')
            }
        },

        /** @inheritdoc */
        initialize: function () {
            var instantPurchase = customerData.get('instant-purchase');
            this._super();
            this.setPurchaseData(instantPurchase());
            instantPurchase.subscribe(this.setPurchaseData, this);
        },

        /** @inheritdoc */
        initObservable: function () {
            this._super()
                .observe('showButton paymentToken shippingAddress billingAddress shippingMethod dfreference');

            return this;
        },

        /**
         * Set data from customerData.
         *
         * @param {Object} data
         */
        setPurchaseData: function (data) {
            this.showButton(data.available);
            var fulltoken = data.paymentToken;
            if(window.is3DSecureEnabled && (typeof fulltoken !== 'undefined')){
                var that =this;
                var paymentTok = fulltoken['summary'].split(", bin:")[0];
                this.bin = fulltoken['summary'].split(", bin:")[1];
				fulltoken['summary'] = paymentTok;
                if(this.bin !== null) {
                var encryptedBin = btoa(this.bin);
                window.sessionId = this.sessionId;
                this.getDdcUrl(fulltoken);
                //this.showButton(data.available);
                window.addEventListener("message", function(event) {
                    that.showButton(true);
                    console.log("entered event listener");
                                    var data = JSON.parse(event.data);
                                    var envUrl;
                                        var data = JSON.parse(event.data);
                                        if (data !== undefined && data.Status) {
                                            var sessionId1 = data.SessionId;
                                            if(sessionId1){
                                                this.dfreference = sessionId1;
                                            } else {
                                               this.dfreference = this.sessionId; 
                                            }
                                            window.sessionId = this.dfreference;
                                            jQuery('[name=instant_purchase_dfreference]').val(this.dfreference);
                                         }
                                    
                                }, false);
                this.paymentToken(fulltoken);
                this.dfreference(window.sessionId);
            } else {
                alert('This card number cannot be used to place 3DS2 order now, please go and update this from My account first.');
                return; 
            }
            } else {
                 this.paymentToken(data.paymentToken);
                 this.dfreference("");
            }
            this.shippingAddress(data.shippingAddress);
            this.billingAddress(data.billingAddress);
            this.shippingMethod(data.shippingMethod);
        },
        
        /**
         * Method to hit ddc end point for 3ds2
         */
        getDdcUrl: function (fulltoken) {
            this.showButton(false);
            var that = this;
                var payload = {
                        cartId: '',
                        paymentData: {additional_data : fulltoken}
                    };
            $.ajax({
            type: 'POST',
            url: urlBuilder.build('rest/default/V1/worldpay/payment/ddcrequest'),
            data: JSON.stringify(payload),
            contentType: "application/json",
            crossDomain: true,
            success: function (msg) {
               // that.showButton(true);
                var jwtUrl = urlBuilder.build('worldpay/hostedpaymentpage/jwt')
                var encryptedBin = '';
                $('body').append('<iframe src="'+jwtUrl+'?instrument='+encryptedBin+'" name="jwt_frm" id="jwt_frm" style="display: none"></iframe>');

            },
            error: function (request, status, error) {
                console.error(error);
                
            }
        });
        },
        /**
         * Confirmation method
         */
        instantPurchase: function () {
                var form = $(this.productFormSelector),
                confirmTemplate = mageTemplate(confirmationTemplate),
                confirmData = _.extend({}, this.confirmationData, {
                    paymentToken: this.paymentToken().summary,
                    shippingAddress: this.shippingAddress().summary,
                    billingAddress: this.billingAddress().summary,
                    shippingMethod: this.shippingMethod().summary,
                    dfreference: this.dfreference(window.sessionId)
                });
            if (!(form.validation() && form.validation('isValid'))) {
                return;
            }
            confirm({
                title: this.confirmationTitle,
                content: confirmTemplate({
                    data: confirmData
                }),
                actions: {
                    /** @inheritdoc */
                    confirm: function () {
                                 $.ajax({
                            url: this.purchaseUrl,
                            data: form.serialize(),
                            type: 'post',
                            dataType: 'json',
                            success: function (data) {
                                if(window.is3DSecureEnabled) {
                                window.location.replace(urlBuilder.build('worldpay/savedcard/instantredirect'));
                            }
                            },
                            /** Show loader before send */
                            beforeSend: function () {
                                $('body').trigger('processStart');
                            }
                        }).always(function () {
                            $('body').trigger('processStop');
                        });
                    }.bind(this)
                }
            });
        }
    });
});
