/*browser:true*/
/*global define*/
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
        'ko',
        'Magento_Checkout/js/action/set-payment-information',
        'Magento_Checkout/js/model/error-processor',
        'Magento_Checkout/js/model/url-builder',
        'mage/storage',
        'Magento_Checkout/js/model/full-screen-loader',
        'Magento_Checkout/js/view/summary/abstract-total',
        'websdk'
    ],
    function (Component, $, quote, customer,validator, url, placeOrderAction, redirectOnSuccessAction,ko, setPaymentInformationAction, errorProcessor, urlBuilder, storage, fullScreenLoader,websdk) {
        'use strict';
        //Valid card number or not.
        var ccTypesArr = ko.observableArray([]);
        var paymentService = false;
        var filtersavedcardLists = ko.observableArray([]);
        var billingAddressCountryId = "";
        var disclaimerFlag = null;
        window.disclaimerDialogue = null;
        if (quote.billingAddress()) {
            billingAddressCountryId = quote.billingAddress._latestValue.countryId;
        }
        $.validator.addMethod('worldpay-validate-number', function (value) {
            if (value) {
                return evaluateRegex(value, "^[0-9]{12,20}$");
            }
        },
        $.mage.__(getCreditCardExceptions('CCAM0')));

        //Valid Card or not.
        $.validator.addMethod('worldpay-cardnumber-valid', function (value) {
            return doLuhnCheck(value);
        }, 
        $.mage.__(getCreditCardExceptions('CCAM2')));

        //Regex for valid card number.
        function evaluateRegex(data, re) {
            var patt = new RegExp(re);
            return patt.test(data);
        }
        function getCreditCardExceptions (exceptioncode){
                var ccData=window.checkoutConfig.payment.ccform.creditcardexceptions;
                  for (var key in ccData) {
                    if (ccData.hasOwnProperty(key)) {  
                        var cxData=ccData[key];
                    if(cxData['exception_code'].includes(exceptioncode)){
                        return cxData['exception_module_messages']?cxData['exception_module_messages']:cxData['exception_messages'];
                    }
                    }
                }
            }

        function doLuhnCheck(value) {
            var nCheck = 0;
            var nDigit = 0;
            var bEven = false;
            value = value.replace(/\D/g, "");

            for (var n = value.length - 1; n >= 0; n--) {
                var cDigit = value.charAt(n);
                nDigit = parseInt(cDigit, 10);

                if (bEven) {
                    if ((nDigit *= 2) > 9) {
                        nDigit -= 9;
                    }
                }

                nCheck += nDigit;
                bEven = !bEven;
            }

            return (nCheck % 10) === 0;
        }
         // 3DS2 part Start
        
        var jwtUrl = url.build('worldpay/hostedpaymentpage/jwt');
        
        function createJwt(){
            
            var encryptedBin = '';
            fullScreenLoader.startLoader();
            $('body').append('<iframe src="'+jwtUrl+'?instrument='+encryptedBin+'" name="jwt_frm" id="jwt_frm" style="display: none"></iframe>');
        }
        
        function pad (str, max) {
            return str.length < max ? pad("0" + str, max) : str;
        }
        
        // 3DS2 part End
        return Component.extend({
            defaults: {
                intigrationmode: window.checkoutConfig.payment.ccform.intigrationmode,
                redirectAfterPlaceOrder: (window.checkoutConfig.payment.ccform.intigrationmode == 'web_sdk') ? true : false,
               // isPlaceOrderActionAllowed: false,
                websdkTemplate: 'Sapient_AccessWorldpay/payment/websdk',
                cardHolderName:'',
                saveMyCard:false,
                disclaimerFlag:false
            },

            initialize: function () {
                this._super();
                this.selectedCCType(null);
                this.filtercardajax();
                this.initPaymentKeyEvents();
                
            },
           enablePayNow: function() {
              if (this.getCode() === "worldpay_cc") {
                  return true;
              }else{
                  return false;
              }
            },
            initObservable: function () {
                var that = this;
                this._super();
                quote.billingAddress.subscribe(function (newAddress) {
                if (quote.billingAddress._latestValue != null  && quote.billingAddress._latestValue.countryId != 'TO') {
                    that.filtercardajax();
                }
               });
            return this;
            },
            filtercardajax: function(){
                if (quote.billingAddress._latestValue == null) {
                    return;
                }
                var ccavailabletypes = this.getCcAvailableTypes();
                var savedcardlists = window.checkoutConfig.payment.ccform.savedCardList;
                var filtercclist = {};
                var filtercards = [];
                var cckey,ccvalue;
                var serviceUrl = urlBuilder.createUrl('/worldpay/payment/types', {});
                var payload = {
                    countryId: quote.billingAddress._latestValue.countryId
                };
                fullScreenLoader.startLoader();
                filtercards = savedcardlists;
                for(var key in ccavailabletypes) {
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
                filtersavedcardLists(filtercards);
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
            paymentToken:ko.observable(),
            

            getCode: function() {
                return 'worldpay_cc';
            },

            loadEventAction: function(data, event){
                if ((data.ccValue)) {
                    if (data.ccValue=="savedcard") {
                        $("#saved-Card-Visibility-Enabled").show();
                        $(".cc-Visibility-Enabled").children().prop('disabled',true);
                        $("#saved-Card-Visibility-Enabled").children().prop('disabled',false);
                        $(".cc-Visibility-Enabled").hide();
                        $("#worldpay_cc_save-card_div").hide();
                    }else{
                        $("#worldpay_cc_save-card_div").show();
                        $(".cc-Visibility-Enabled").children().prop('disabled',false);
                        $("#saved-Card-Visibility-Enabled").children().prop('disabled',true);
                        $("#saved-Card-Visibility-Enabled").hide();
                        $(".cc-Visibility-Enabled").show();
                    }
                } else {
                    if (data.selectedCCType() =="savedcard") {
                        $("#saved-Card-Visibility-Enabled").show();
                        $(".cc-Visibility-Enabled").children().prop('disabled',true);
                        $("#saved-Card-Visibility-Enabled").children().prop('disabled',false);
                        $(".cc-Visibility-Enabled").hide();
                        $("#worldpay_cc_save-card_div").hide();
                    }else{
                        $("#worldpay_cc_save-card_div").show();
                        $(".cc-Visibility-Enabled").children().prop('disabled',false);
                        $("#saved-Card-Visibility-Enabled").children().prop('disabled',true);
                        $("#saved-Card-Visibility-Enabled").hide();
                        $(".cc-Visibility-Enabled").show();
                    }
                }
                $('#disclaimer-error').html('');
            },
            initPaymentKeyEvents: function(){
                var that = this;
                $('.checkout-container').on('keyup', '.payment_cc_number', function(e){
                    that.loadCCKeyDownEventAction(this, e);
                })
            },

            loadCCKeyDownEventAction: function(el, event){
                var curVal = $(el).val();

                var $ccNumberContain = $(el).parents('.ccnumber_withcardtype');
                var piCardType = '';

                var visaRegex = new RegExp('^4[0-9]{0,15}$'),
                mastercardRegex = new RegExp(
                '^(?:5[1-5][0-9]{0,2}|222[1-9]|22[3-9][0-9]|2[3-6][0-9]{0,2}|27[01][0-9]|2720)[0-9]{0,12}$'
                ),
                amexRegex = new RegExp('^3$|^3[47][0-9]{0,13}$'),
                discoverRegex = new RegExp('^6[05]$|^601[1]?$|^65[0-9][0-9]?$|^6(?:011|5[0-9]{2})[0-9]{0,12}$'),
                jcbRegex = new RegExp('^35(2[89]|[3-8][0-9])'),
                dinersRegex = new RegExp('^36'),
                maestroRegex = new RegExp('^(5018|5020|5038|6304|6759|676[1-3])'),
                unionpayRegex = new RegExp('^62[0-9]{0,14}$|^645[0-9]{0,13}$|^65[0-9]{0,14}$');

                // get rid of spaces and dashes before using the regular expression
                curVal = curVal.replace(/ /g, '').replace(/-/g, '');

                // checks per each, as their could be multiple hits
                if (curVal.match(visaRegex)) {
                    piCardType = 'visa';
                    $ccNumberContain.addClass('is_visa');
                } else {
                    $ccNumberContain.removeClass('is_visa');
                }

                if (curVal.match(mastercardRegex)) {
                    piCardType = 'mastercard';
                    $ccNumberContain.addClass('is_mastercard');
                } else {
                    $ccNumberContain.removeClass('is_mastercard');
                }

                if (curVal.match(amexRegex)) {
                    piCardType = 'amex';
                    $ccNumberContain.addClass('is_amex');
                } else {
                    $ccNumberContain.removeClass('is_amex');
                }

                if (curVal.match(discoverRegex)) {
                    piCardType = 'discover';
                    $ccNumberContain.addClass('is_discover');
                } else {
                    $ccNumberContain.removeClass('is_discover');
                }

                if (curVal.match(unionpayRegex)) {
                    piCardType = 'unionpay';
                    $ccNumberContain.addClass('is_unionpay');
                } else {
                    $ccNumberContain.removeClass('is_unionpay');
                }

                if (curVal.match(jcbRegex)) {
                    piCardType = 'jcb';
                    $ccNumberContain.addClass('is_jcb');
                } else {
                    $ccNumberContain.removeClass('is_jcb');
                }

                if (curVal.match(dinersRegex)) {
                    piCardType = 'diners';
                    $ccNumberContain.addClass('is_diners');
                } else {
                    $ccNumberContain.removeClass('is_diners');
                }

                if (curVal.match(maestroRegex)) {
                    piCardType = 'maestro';
                    $ccNumberContain.addClass('is_maestro');
                } else {
                    $ccNumberContain.removeClass('is_maestro');
                }

                // if nothing is a hit we add a class to fade them all out
                if (
                    curVal !== '' &&
                    !curVal.match(visaRegex) &&
                    !curVal.match(mastercardRegex) &&
                    !curVal.match(amexRegex) &&
                    !curVal.match(discoverRegex) &&
                    !curVal.match(jcbRegex) &&
                    !curVal.match(dinersRegex) &&
                    !curVal.match(maestroRegex) &&
                    !curVal.match(unionpayRegex)
                ) {
                    $ccNumberContain.addClass('is_nothing');
                } else {
                    $ccNumberContain.removeClass('is_nothing');
                }
            },
            
            
            getTemplate: function(){
                    return this.websdkTemplate;
            },
            threeDSEnabled: function(){
                return window.checkoutConfig.payment.ccform.is3DSecureEnabled;
            },
             /**
             * Get payment icons
             * @param {String} type
             * @returns {Boolean}
             */
            getIcons: function (type) {
                return window.checkoutConfig.payment.ccform.wpicons.hasOwnProperty(type) ?
                    window.checkoutConfig.payment.ccform.wpicons[type]
                    : false;
            },
            getSavedCardsList:function(){
                return filtersavedcardLists;
            },

            getSavedCardsCount: function(){
                if(window.checkoutConfig.payment.ccform.savedCardCount > 0){
                    return window.checkoutConfig.payment.ccform.savedCardCount;
                }else{
                    document.getElementById('worldpay_cc-savedcard-form').style.display = 'none';
                    document.getElementById('worldpay_cc-newcard-form').style.display = 'block';
                    return window.checkoutConfig.payment.ccform.savedCardCount;
                }
            },
            getTitle: function() {
               return window.checkoutConfig.payment.ccform.cctitle ;
            },
            hasVerification:function() {
               return window.checkoutConfig.payment.ccform.isCvcRequired ;
            },
            getSaveCardAllowed: function(){
                if(customer.isLoggedIn()){
                    return window.checkoutConfig.payment.ccform.saveCardAllowed;
                }
            },
            isTokenizationEnabled: function(){
                if(customer.isLoggedIn()){
                    return window.checkoutConfig.payment.ccform.tokenizationAllowed;
                }
            },
            disclaimerMessage: function(){
                if(customer.isLoggedIn()){
                    return window.checkoutConfig.payment.ccform.disclaimerMessage;
                }
            },
            isDisclaimerMessageEnabled: function(){
                if(customer.isLoggedIn()){
                    return window.checkoutConfig.payment.ccform.isDisclaimerMessageEnabled;
                }
            },
            isDisclaimerMessageMandatory: function(){
                if(customer.isLoggedIn()){
                    return window.checkoutConfig.payment.ccform.isDisclaimerMessageMandatory;
                }
            },
            isActive: function() {
                return true;
            },
            paymentMethodSelection: function() {
                return window.checkoutConfig.payment.ccform.paymentMethodSelection;
            },
            isSavedCardEnabled: function(){
                if(customer.isLoggedIn()){
                    if(window.checkoutConfig.payment.ccform.savedCardEnabled){
                        return false;
                    }else{
                        return true;
                    }
                }else{
                    return false;
                }
            },
            getselectedCCType : function(){
                var classList = document.getElementById("card-form").classList;
                    if (classList.contains("visa")) {
                        return "VISA-SSL";
                    }else if (classList.contains("mastercard")) {
                        return "ECMC-SSL";
                    }else if(classList.contains("amex")) {
                        return "AMEX-SSL";
                    }else if (classList.contains("discover")) {
                        return "DISCOVER-SSL";
                    }else if (classList.contains("unionpay")) {
                        return "UNIONPAY-SSL";
                    }else if (classList.contains("jcb")) {
                        return "JCB-SSL";
                    }else if (classList.contains("diners")) {
                        return "DINERS-SSL";
                    }else if (classList.contains("maestro")) {
                        return "MAESTRO-SSL";
                    }else if (classList.contains("dankort")) {
                        return "DANKORT-SSL";
                    } else {
                        return "Invalid";
                    }
           },

            /**
             * @override
             */
            getData: function () {
                return {
                    'method': "worldpay_cc",
                    'additional_data': {
                        'cc_type': this.isSavedCardPayment ? "savedcard" : this.getselectedCCType(),
                        'cc_name': $('#card-name').val(),
                        'sessionHref': window.sessionHref,
                        'cvcHref': window.cvcHref,
                        'isSavedCardPayment': this.isSavedCardPayment,
                        'save_my_card': $('#' + this.getCode() + '_save_card').is(":checked"),
                        'disclaimerFlag': this.disclaimerFlag,
                        'tokenId': this.paymentToken,
                        'collectionReference':window.sessionId
                    }
                };
            },

            getDisclaimerValue: function (){
                if($('#' + this.getCode() + '_save_card').is(":checked")
                   && this.isDisclaimerMessageMandatory()
                   && this.isDisclaimerMessageEnabled()
                   && !this.disclaimerFlag){
                        $('#disclaimer-error').css('display', 'block');
                        $('#disclaimer-error').html($.mage.__(getCreditCardExceptions('CCAM1')));
                        document.getElementById("card-submit").disabled = true;
                        return false;
                }else{
                    $('#disclaimer-error').css('display', 'none');
                    document.getElementById("card-submit").disabled = false;
                    return true;
                }
            },
            resetDisclaimer:function () {
               if(!$('#' + this.getCode() + '_save_card').is(":checked")){
                   this.disclaimerFlag = false;
               }
               this.getDisclaimerValue();
            },
            useSavedCard:function(){
                if (document.getElementById('use_saved_card').checked) {
                    $("#new_card").prop("checked", false);
                    document.getElementById('worldpay_cc-newcard-form').style.display = 'none';
                    document.getElementById('worldpay_cc-savedcard-form').style.display = 'block';
                    this.isSavedCardSelected();
                    this.isSavedCardPayment = true;
                }
                else { 
                    $("#new_card").prop("checked", true);
                    document.getElementById('worldpay_cc-savedcard-form').style.display = 'none';
                    document.getElementById('worldpay_cc-newcard-form').style.display = 'block';
                    this.isSavedCardPayment = false;
                }
            },
            isSavedCardSelected:function(){
                if($("input[name='payment[token_to_use]']:checked").val()){
                    document.getElementById("cvv-submit").disabled = false;
                }else{
                    document.getElementById("cvv-submit").disabled = true;
                }
            },
            getRegexCode:function(cardType){
                if ('AMEX' == cardType) {
                    return /^[0-9]{4}$/;
                }else{
                    return /^[0-9]{3}$/;
                }
            },
            preparePayment:function() {
                var self = this;
                this.redirectAfterPlaceOrder = false;
                var form = document.getElementById("card-form");
                this.paymentToken = null;
                //var clear = document.getElementById("clear");
                document.getElementById("card-name").placeholder= this.getCheckoutLabels('CO4');
                var callDdc= true;
                var merchantIdentity = this.getMerchantIdentity(); 
                Worldpay.checkout.init(
                    {
                        id: merchantIdentity,
                        form: "#card-form",
                        fields: {
                            pan: {
                                selector: "#card-pan",
                                placeholder: this.getCheckoutLabels('CO3')
                                },
                            expiry: {
                                selector: "#card-expiry",
                                placeholder: this.getCheckoutLabels('CO11')
                                },
                            cvv: {
                                selector: "#card-cvv",
                                placeholder: this.getCheckoutLabels('CO5')
                                }
                        }
                    },
                    function (error, checkout) {
                        if (error) {
                            console.error(error);
                            return;
                        }
                        form.addEventListener("submit", function (event) {
                            event.preventDefault();
                          
                            checkout.generateSessionState(function (error, sessionState) {
                                var ccname = $('#card-name').val();
                                if (error ||(ccname === '' || ccname ===null)) {
                                    console.error(error);
                                    $("#errors").text(getCreditCardExceptions('CCAM5'));
                                    $("#SessionAlert").text("");
                                    window.sessionHref='';
                                    callDdc=false;

                                    return;
                                }
                                var ccTypesEnabled = Object.values(window.checkoutConfig.payment.ccform.availableTypes);
                                
                                var enteredCCType = function(classList){
                                    if (classList.contains("visa")) {
                                        return "VISA-SSL";
                                    }else if (classList.contains("mastercard")) {
                                        return "ECMC-SSL";
                                    }else if(classList.contains("amex")) {
                                        return "AMEX-SSL";
                                    }else if (classList.contains("discover")) {
                                        return "DISCOVER-SSL";
                                    }else if (classList.contains("unionpay")) {
                                        return "UNIONPAY-SSL";
                                    }else if (classList.contains("jcb")) {
                                        return "JCB-SSL";
                                    }else if (classList.contains("diners")) {
                                        return "DINERS-SSL";
                                    }else if (classList.contains("maestro")) {
                                        return "MAESTRO-SSL";
                                    }else if (classList.contains("dankort")) {
                                        return "DANKORT-SSL";
                                    } 
                                };
                                
                                var sessionhref= sessionState;
                                    
                                //window.sessionHref = sessionhref;
                                window.cvcHref = '';

//                                 setTimeout(function(){ var sessionState=''; window.sessionHref='';
//                                     $("#errors").text("");
//                                     $("#SessionAlert").text("Session Expired. Please reload and enter the card details again.");fullScreenLoader.stopLoader(); $('#card-submit').prop('disabled', true);  }, 60000);         

                                if (sessionhref) {
                                    if(enteredCCType(document.getElementById("card-form").classList) == 'DINERS-SSL'
                                       || enteredCCType(document.getElementById("card-form").classList) == 'DANKORT-SSL'
                                       || !ccTypesEnabled[0].hasOwnProperty(enteredCCType(document.getElementById("card-form").classList))){
                                            $("#errors").text(getCreditCardExceptions('CCAM3'));
                                            return;
                                    }
                                    if (self.threeDSEnabled()) {
                                        window.sessionHref = sessionhref;
                                        fullScreenLoader.startLoader();
                                        if(callDdc) {
                                       //window.sessionHref = sessionhref;
                                       self.getDdcUrl();
                                       callDdc = false;
                                        
                                    }
                                        window.addEventListener("message", function(event) {
                                        //fullScreenLoader.startLoader();            
                                            var data = JSON.parse(event.data);
                                                //console.warn('Merchant received a message:', data);
                                                if (data !== undefined && data.Status && window.sessionId !== data.SessionId) {
                                                    window.sessionId = data.SessionId;
                                                  fullScreenLoader.stopLoader();
                                                  self.placeOrder();
                                                  
                                                }
                                        }, false);
                                        fullScreenLoader.stopLoader();
                                    }else{
                                        window.sessionHref = sessionhref;
                                        fullScreenLoader.stopLoader();
                                        self.placeOrder();
                                    }
                                }
                             });
                             
                             
                        });
                    }
                );
            },
            getDdcUrl : function () {
                var payload = {
                        cartId: quote.getQuoteId(),
                        paymentData: this.getData()
                    };
                fullScreenLoader.startLoader();
                storage.post(
                    urlBuilder.createUrl('/worldpay/payment/ddcrequest', {}),
                    JSON.stringify(payload),
                    true
                ).done(
                    function (response) {
                        createJwt();
                        fullScreenLoader.stopLoader();
                        
                    }
                ).fail(
                    function (response) {
                        fullScreenLoader.stopLoader();
                        window.cvcHref='';
                        if(response.hasOwnProperty("responseJSON")){
                         $("#errors_cvv").text(response["responseJSON"]["message"]);
                     }
                    }
                );
                
            },
            prepareSavedcardPayment:function() {
                var self = this;
                var callDdc= true;
                var form = document.getElementById("cvv-form");
                this.paymentToken = $("input[name='payment[token_to_use]']:checked").val();
                var merchantIdentity = this.getMerchantIdentity(); 
                var iscvcRequired = this.hasVerification();
                if(this.isSavedCardPayment) {
                    window.sessionHref ='';
                    $('#card-name').val('');
                }
                if (iscvcRequired) {
                Worldpay.checkout.init(
                    {
                        id: merchantIdentity,
                        form: "#cvv-form",
                        fields: {
                            cvvOnly: {
                                selector: "#cvv-field",
                                placeholder: this.getCheckoutLabels('CO5')
                            }
                        }
                    },
                    function (error, checkout) {
                        if (error) {
                            console.error(error);
                            return;
                        }
                        form.addEventListener("submit", function (event) {
                            event.preventDefault();
                            checkout.generateSessionState(function (error, sessionState) {
                                if (error) {
                                    console.error(error);
                                    $("#errors_cvv").text(getCreditCardExceptions('CCAM23'));
                                    callDdc=false;
                                    return;
                                }
                                var cvchref = sessionState;
                                 
                                window.cvcHref = cvchref;
                                window.sessionHref = '';

//                                 setTimeout(function(){ var sessionState=''; window.sessionHref=''; 
//                                     $("#Session_Alert").text("Session Expired. Please try again."); window.location.reload(); }, 60000);         

                                if (cvchref) {
                                    if (self.threeDSEnabled() ) {
                                        fullScreenLoader.startLoader();
                                        if (callDdc) {
                                        self.getDdcUrl();
                                        callDdc = false;
                                        }
                                         window.addEventListener("message", function(event) {
                                            var data = JSON.parse(event.data);
                                                //console.warn('Merchant received a message:', data);
                                                if (data !== undefined && data.Status) {
                                                    window.sessionId = data.SessionId;
                                                  fullScreenLoader.stopLoader();
                                                  self.placeOrder();
                                                }
                                        }, false);
                                        fullScreenLoader.stopLoader();
                                    }else {
                                            fullScreenLoader.stopLoader();
                                             self.placeOrder();
                                           }
                                }
                                
                             }
                                     );
                         
                        });
                    }
                );
            } else {
                window.cvcHref = '';
                window.sessionHref = '';
                    $(document).on('change', 'input[name="payment[token_to_use]"]', function() {
                               $('#cvv-submit').css("background-color", "green");
                        });

                form.addEventListener("submit", function (event) {
                    if (self.threeDSEnabled() ) {
                                        fullScreenLoader.startLoader();
                                        if (callDdc) {
                                        self.getDdcUrl();
                                        callDdc = false;
                                        }
                                         window.addEventListener("message", function(event) {
                                            var data = JSON.parse(event.data);
                                                //console.warn('Merchant received a message:', data);
                                                if (data !== undefined && data.Status) {
                                                    window.sessionId = data.SessionId;
                                                  fullScreenLoader.stopLoader();
                                                  self.placeOrder();
                                                }
                                        }, false);
                                        fullScreenLoader.stopLoader();
                                    }else {
                                        fullScreenLoader.stopLoader();
                                        self.placeOrder();
            }
            });
            }
        
            },
            afterPlaceOrder: function (data, event) {
             window.location.replace(url.build('worldpay/threedsecure/auth'));
            },
            getIntigrationMode: function(){
                return window.checkoutConfig.payment.ccform.intigrationmode;
            },
            getMerchantIdentity: function() {
                return window.checkoutConfig.payment.ccform.merchantIdentity;
            },
            disclaimerPopup: function(){
                var that = this;
                $("#dialog").dialog({
                    autoResize: true,
                    show: "clip",
                    hide: "clip",
                    height: 350,
                    width: 450,
                    autoOpen: false,
                    modal: true,
                    position: { my: "center", at: "center", of: window },
                    draggable: false,
                    buttons: {
                        Agree: function() { 
                            $( this ).dialog( "close" );
                            that.disclaimerFlag = true;
                            window.disclaimerDialogue = true;
                            $('#disclaimer-error').css('display', 'none');
                            $('#' + that.getCode() + '_save_card').prop( "checked", true );
                            document.getElementById("card-submit").disabled = false;
                        },
                        Disagree: function() { 
                            $( this ).dialog( "close" );
                            that.disclaimerFlag = false;
                            window.disclaimerDialogue = false;
                            
                            if ($('#' + that.getCode() + '_save_card').is(":checked")
                                && that.isDisclaimerMessageMandatory() 
                                && that.isDisclaimerMessageEnabled()) {
                                $('#disclaimer-error').css('display', 'block');
                                $('#disclaimer-error').html($.mage.__(getCreditCardExceptions('CCAM1')));
                            }
                            if(that.isDisclaimerMessageEnabled()){
                                that.saveMyCard = '';
                                $('#' + that.getCode() + '_save_card').prop( "checked", false );
                                document.getElementById("card-submit").disabled = false;
                                $('#disclaimer-error').css('display', 'none');
                            }
                           
                        }
                    },
                    open: function (type, data) {
                        $(this).parent().appendTo("#disclaimer");
                    }
                });
                $('#dialog').dialog('open');            
            }
        });
    }
);