define(
    [
        'Magento_Payment/js/view/payment/cc-form',
        'jquery',
        'Magento_Checkout/js/model/quote',
        'Magento_Customer/js/model/customer',
        'Magento_Payment/js/model/credit-card-validation/validator',
        'mage/url',
        'Magento_Checkout/js/action/place-order',
        'Magento_Checkout/js/action/redirect-on-success',
         'Magento_Checkout/js/model/error-processor',
        'Magento_Checkout/js/model/url-builder',
        'mage/storage',
        'Magento_Checkout/js/model/full-screen-loader',
        'ko'
    ],
        function (Component, $, quote, customer,validator, url, placeOrderAction, redirectOnSuccessAction,errorProcessor, urlBuilder, storage, fullScreenLoader, ko) {
        'use strict';
         var ccTypesArr = ko.observableArray([]);
        var paymentService = false;
        var isACH = false;
        var billingAddressCountryId = "";
        if (quote.billingAddress()) {
            billingAddressCountryId = quote.billingAddress._latestValue.countryId;
        }
        var achAccountCodeMessage = 'Maximum allowed length of 17 exceeded';
        var achAccountDisplayMessage = getCreditCardExceptions('CACH01')?getCreditCardExceptions('CACH01'):achAccountCodeMessage;
        var achRoutingCodeMessage = 'Required length should be 8 or 9';
        var achRoutingDisplayMessage = getCreditCardExceptions('CACH02')?getCreditCardExceptions('CACH02'):achRoutingCodeMessage;
        var achCheckNumberCodeMessage = 'Maximum allowed length of 15 exceeded';
        var achCheckNumberDisplayMsg = getCreditCardExceptions('CACH03')?getCreditCardExceptions('CACH03'):achCheckNumberCodeMessage;
        var achCompanyCodeMessage = 'Maximum allowed length of 40 exceeded' ;
        var achCompanyDisplayMsg = getCreditCardExceptions('CACH04')?getCreditCardExceptions('CACH04'):achCompanyCodeMessage;

        $.validator.addMethod('worldpay-validate-ach-accountnumber', function (value) {
            if (value) {
                return evaluateRegex(value, "^[0-9]{0,17}$");
            }
        }, $.mage.__(achAccountDisplayMessage));
        $.validator.addMethod('worldpay-validate-ach-routingnumber', function (value) {
            if (value) {
                return evaluateRegex(value, "^[0-9]{8,9}$");
            }
        }, $.mage.__(achRoutingDisplayMessage));
        $.validator.addMethod('worldpay-validate-ach-checknumber', function (value) {
            if ((value) || value.length === 0) {
                return evaluateRegex(value, "^[0-9]{0,15}$");
            }
        }, $.mage.__(achCheckNumberDisplayMsg));
        $.validator.addMethod('worldpay-validate-ach-companyname', function (value) {
            if (value || value.length === 0) {
                return value.length<40;
            }
        }, $.mage.__(achCompanyDisplayMsg));
        function evaluateRegex(data, re) {
            var patt = new RegExp(re);
            return patt.test(data);
        }
        function getCreditCardExceptions (exceptioncode){
                var ccData=window.checkoutConfig.payment.ccform.creditcardexceptions;
                  for (var key in ccData) {
                    if (ccData.hasOwnProperty(key)) {
                        var cxData=ccData[key];
                    if(cxData['exception_code'] === exceptioncode){
                        return cxData['exception_module_messages']?cxData['exception_module_messages']:cxData['exception_messages'];
                    }
                    }
                }
        }
        return Component.extend({
            defaults: {
                redirectAfterPlaceOrder: false,
                redirectTemplate: 'Sapient_AccessWorldpay/payment/apm',
                ach_accountType:null,
                ach_accountnumber:null,
                ach_routingNumber:null,
                statementNarrative:null,
            },

            initialize: function () {
                this._super();
                this.selectedCCType(null);
                if(paymentService == false){
                    this.filterajax(1);
                }
            },

            initObservable: function () {
                var that = this;
                this._super();
                quote.billingAddress.subscribe(function (newAddress) {
                    that.checkPaymentTypes();
                    if (quote.billingAddress._latestValue != null && quote.billingAddress._latestValue.countryId != billingAddressCountryId) {
                        billingAddressCountryId = quote.billingAddress._latestValue.countryId;
                        that.filterajax(1);
                        paymentService = true;
                    }
               });
            return this;
            },

            filterajax: function(statusCheck){
                if(!statusCheck){
                    return;
                }
                if (quote.billingAddress._latestValue == null) {
                    return;
                }
                var ccavailabletypes = this.getCcAvailableTypes();
                var filtercclist = {};
                var cckey,ccvalue,typeKey,typeValue;
                var currencyCode = window.checkoutConfig.quoteData.base_currency_code;
                //var serviceUrl = urlBuilder.createUrl('/worldpay/payment/types', {});
                 var payload = {
                    countryId: quote.billingAddress._latestValue.countryId
                };
                var integrationMode = window.checkoutConfig.payment.ccform.intigrationmode;
                 fullScreenLoader.startLoader();

                        for (var key in ccavailabletypes) {
                        if(key === 'ACH_DIRECT_DEBIT-SSL' && !(payload['countryId'] ==='US' && currencyCode ==='USD'))
                        {
                            key=null;
                            isACH = false;
                        }
                        cckey = key;
                        ccvalue = ccavailabletypes[key];
                        filtercclist[cckey] = ccvalue;
                    }
                    var ccTypesArr1 = _.map(filtercclist, function (value, key) {
                        return {
                            'ccValue': key,
                            'ccLabel': value
                        };
                    });
                    fullScreenLoader.stopLoader();
                    ccTypesArr(ccTypesArr1);
            },

            getCcAvailableTypesValues : function(){
                 return ccTypesArr;
            },

            availableCCTypes : function(){
               return ccTypesArr;
            },

            getCheckoutLabels: function (labelcode) {
                var ccData = window.checkoutConfig.payment.ccform.checkoutlabels;
                for (var key in ccData) {
                    if (ccData.hasOwnProperty(key)) {
                        var cxData = ccData[key];
                        if (cxData['wpay_label_code'].includes(labelcode)) {
                            return cxData['wpay_custom_label'] ? cxData['wpay_custom_label'] : cxData['wpay_label_desc'];
                        }
                    }
                }
            },
            selectedCCType : ko.observable(),
            selectedACHAccountType:ko.observable(),
            achaccountnumber: ko.observable(),
            achroutingnumber: ko.observable(),
            achchecknumber: ko.observable(),
            achcompanyname: ko.observable(),
            achemailaddress:ko.observable(),
            stmtNarrative:ko.observable(),
            getTemplate: function(){
                    return this.redirectTemplate;
            },

            getCode: function() {
                return 'worldpay_apm';
            },
            getTitle: function() {
               return window.checkoutConfig.payment.ccform.apmtitle ;
            },

            isActive: function() {
                return true;
            },
            getACHAccounttypes: function(code) {
                var accounttypes = window.checkoutConfig.payment.ccform.achdetails;
                return accounttypes[code];
            },
            getNarrative: function() {
                return window.checkoutConfig.payment.ccform.narrative;
            },
            /**
             * @override
             */
            getData: function () {
                return {
                    'method': "worldpay_apm",
                    'additional_data': {
                        'cc_type': this.getselectedCCType(),
                        'ach_account': this.ach_accountType,
                        'ach_accountNumber': this.ach_accountnumber,
                        'ach_routingNumber': this.ach_routingnumber,
                        'ach_checknumber': this.ach_checknumber,
                        'ach_companyname': this.ach_companyname,
                        'ach_emailaddress': this.ach_emailaddress,
                        'statementNarrative': this.getStatementNarrative()
                    }
                };
            },
            getStatementNarrative : function() {
               if(this.statementNarrative!=null) {
                   return this.statementNarrative.replace(/\s/g, '')!=""?this.statementNarrative:this.getNarrative();
               }
               return this.statementNarrative?this.statementNarrative:this.getNarrative();
            },
             getselectedCCType : function(){
                if(this.paymentMethodSelection()=='radio'){
                    return $("input[name='apm_type']:checked").val();
                } else{
                    return  this.selectedCCType();
                }
            },

            getACHBankAccountTypes : function() {
                var accounttypes = _.map(window.checkoutConfig.payment.ccform.achdetails, function (value, key) {
                                           return {
                                              'accountCode': key,
                                              'accountText': value
                                    };
                                });
                return ko.observableArray(accounttypes);
            },
            showACH : function() {
                if(isACH && this.getselectedCCType() == 'ACH_DIRECT_DEBIT-SSL'){
                    return true;
                }
              return false;
            },

            showcompanynamecorporateaccount:function() {
                $('select.select-achtype').on('change', function() {
                    var getselectvalue = this.value;
                    if(getselectvalue == '2' || getselectvalue == '3'){
                        $('.ach-company-name').css('display','block');
                    }else{
                        $('.ach-company-name').css('display','none');
                    }
                });
            },

            paymentMethodSelection: function() {
                this.showcompanynamecorporateaccount();
                return window.checkoutConfig.payment.ccform.paymentMethodSelection;
            },

            preparePayment:function() {
                var self = this;
                var $form = $('#' + this.getCode() + '-form');
                if($form.validation() && $form.validation('isValid')){
                    if(this.getselectedCCType() == 'ACH_DIRECT_DEBIT-SSL'){
                        this.ach_accountType = this.getACHAccounttypes(this.selectedACHAccountType());
                        this.ach_accountnumber = this.achaccountnumber();
                        this.ach_routingnumber = this.achroutingnumber();
                        this.ach_checknumber = this.achchecknumber();
                        this.ach_companyname = this.achcompanyname();
                        this.ach_emailaddress = this.achemailaddress();
                    }
                    this.statementNarrative = this.stmtNarrative();
                    self.placeOrder();
                } else {
                    return $form.validation() && $form.validation('isValid');
                }
            },
            getIcons: function (type) {
                return window.checkoutConfig.payment.ccform.wpicons.hasOwnProperty(type) ?
                    window.checkoutConfig.payment.ccform.wpicons[type]
                    : false;
            },

            afterPlaceOrder: function (data, event) {
                if(this.getselectedCCType()=='ACH_DIRECT_DEBIT-SSL'){
                       window.location.replace(url.build('worldpay/threedsecure/auth'));
                }else{
                window.location.replace(url.build('worldpay/redirectresult/redirect'));
            }
            },
            checkPaymentTypes: function (data, event){
               if (data && data.ccValue) {
                    if(data.ccValue=='ACH_DIRECT_DEBIT-SSL'){
                        $(".ach-block").show();
                        $("#ach_pay").prop('disabled',false);
                    }
                     $(".statment-narrative").show();
                }else if(data){
                     if (data.selectedCCType() && data.selectedCCType() == 'ACH_DIRECT_DEBIT-SSL') {
                        $(".ach-block").show();
                        $("#ach_pay").prop('disabled',false);
                    }
                     $(".statment-narrative").show();
                }else {
                    $("#apm_ACH_DIRECT_DEBIT-SSL").prop('checked', false);
                    $("#ach_pay").prop('disabled',true);
                    $(".ach-block").hide();
                    $(".statment-narrative").hide();

                }
            }
        });
    }
);
