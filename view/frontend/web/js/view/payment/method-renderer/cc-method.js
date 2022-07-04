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
        'jquery/ui',
        'Magento_Checkout/js/view/summary/abstract-total'
    ],
    function (Component, $, quote, customer,validator, url, placeOrderAction, redirectOnSuccessAction,ko, setPaymentInformationAction, errorProcessor, urlBuilder, storage, fullScreenLoader) {
        'use strict';
        //Valid card number or not.
        var ccTypesArr = ko.observableArray([]);
        var filtersavedcardLists = ko.observableArray([]);
        var isInstalment = ko.observableArray([]);
        var checkInstal = ko.observable();
        var paymentService = false;
        var billingAddressCountryId = "";
        var dfReferenceId = "";
        var disclaimerFlag = null;
        window.disclaimerDialogue = null;
        var setrawDataNull = false;
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
        
        //Valid Card Brand
        $.validator.addMethod('worldpay-cardbrand-valid', function (value) {
            return checkCardBrandSupported();
        }, 
        $.mage.__(getCreditCardExceptions('CCAM3')));

        function checkCardBrandSupported() {
                var ccTypesEnabled = Object.values(window.checkoutConfig.payment.ccform.availableTypes);
                var selectedCCTypeValue = getCardType(document.getElementById("creditcardnumber").classList);
                if (selectedCCTypeValue == 'DANKORT-SSL' 
                   || selectedCCTypeValue == 'DINERS-SSL' 
                   || !ccTypesEnabled[0].hasOwnProperty(selectedCCTypeValue)) {
                    return false;
                } else {
                    return true;
                }
            }
            function getCardType(data) {
                if (data.contains("is_visa")) {
                    return "VISA-SSL";
                }
                if (data.contains("is_mastercard")) {
                    return "ECMC-SSL";
                }
                if (data.contains("is_amex")) {
                    return "AMEX-SSL";
                }
                if (data.contains("is_discover")) {
                    return "DISCOVER-SSL";
                }
                if (data.contains("is_dankort")) {
                    return "DANKORT-SSL";
                }
                if (data.contains("is_jcb")) {
                    return "JCB-SSL";
                }
                if (data.contains("is_diners")) {
                    return "DINERS-SSL";
                }
                if (data.contains("is_maestro")) {
                    return "MAESTRO-SSL";
                }
            }
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
        
        function createJwt(cardNumber){
            var bin = cardNumber;
            var encryptedBin = btoa(bin);
            //fullScreenLoader.startLoader();
            $('body').append('<iframe src="'+jwtUrl+'?instrument='+encryptedBin+'" name="jwt_frm" id="jwt_frm" style="display: none"></iframe>');
        }
        
        // 3DS2 part End
        
        
        
        return Component.extend({
            defaults: {
                intigrationmode: window.checkoutConfig.payment.ccform.intigrationmode,
                redirectAfterPlaceOrder: (window.checkoutConfig.payment.ccform.intigrationmode == 'direct') ? true : false,
                direcTemplate: 'Sapient_AccessWorldpay/payment/direct-cc',
                redirectTemplate: 'Sapient_AccessWorldpay/payment/redirect-cc',
                cardHolderName:'',
                SavedcreditCardVerificationNumber:'',
                saveMyCard:false,
            },

            initialize: function () {
                this._super();
                this.filtercardajax();
                this.initPaymentKeyEvents();
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
                if($('.option_error').length){
                    $('.option_error').remove();
                }
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
            
            getDdcUrl : function () {
                setrawDataNull = false;
                var that = this;
                var payload = {
                        cartId: quote.getQuoteId(),
                        paymentData: this.getData()
                    };
                fullScreenLoader.startLoader();
                storage.post(
                    urlBuilder.createUrl('/worldpay/payment/ddcrequest', {}),
                   //url.build('worldpay/threedsecure/ddcrequest'),
                    JSON.stringify(payload),
                    true
                ).done(
                    function (response) {
                        /** Do your code here */
                        console.log("entered done");
                        var bin = that.creditCardNumber();
                        var binNew = bin.substring(0,6);
                        createJwt(binNew);
                        //alert('Success');
                        fullScreenLoader.stopLoader();
                    }
                ).fail(
                    function (response) {
                        fullScreenLoader.stopLoader();
                        if(response.hasOwnProperty("responseJSON")){
                         $("#errors").text(response["responseJSON"]["message"]);
                         //console.error(response["responseJSON"]["message"]);
                        }
                        
                    }
                );
                
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
              
               
            paymentToken:ko.observable(),
            selectedInstalment: ko.observable(),

            getCode: function() {
                return 'worldpay_cc';
            },

            loadEventAction: function(data){
                if($('.option_error').length){
                     $('.option_error').remove();
                }
                if (data === this.getCode() + '_usesavedcard') {
                    $("#saved-Card-Visibility-Enabled").show();
                    $(".cc-Visibility-Enabled").children().prop('disabled',true);
                    $("#saved-Card-Visibility-Enabled").children().prop('disabled',false);
                    $(".cc-Visibility-Enabled").hide();
                    this.isSavedCardPayment = true;
                } else {
                    this.disableUseSavedCard();
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
                //maestroRegex = new RegExp('^(5018|5020|5038|6304|6759|676[1-3])'),
                maestroRegex = new RegExp('^(5018|5081|5044|5020|5038|603845|6304|6759|676[1-3]|6799|6220|504834|504817|504645)[0-9]{8,15}'),
                dankortRegex = new RegExp('^(5019{0,12})');

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

                if (curVal.match(dankortRegex)) {
                    piCardType = 'dankort';
                    $ccNumberContain.addClass('is_dankort');
                } else {
                    $ccNumberContain.removeClass('is_dankort');
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
                    !curVal.match(dankortRegex)
                ) {
                    $ccNumberContain.addClass('is_nothing');
                } else {
                    $ccNumberContain.removeClass('is_nothing');
                }
            },
            
            
            getTemplate: function(){
                if (this.intigrationmode == 'direct') {
                    return this.direcTemplate;
                } else{
                    return this.redirectTemplate;
                }
            },

             threeDSEnabled: function(){
                return window.checkoutConfig.payment.ccform.is3DSecureEnabled;
            },

            getSavedCardsList:function(){
                return filtersavedcardLists;
            },

            getSavedCardsCount: function(){
                if(window.checkoutConfig.payment.ccform.savedCardCount > 0){
                    return window.checkoutConfig.payment.ccform.savedCardCount;
                }else{
                    this.disableUseSavedCard();
                    return window.checkoutConfig.payment.ccform.savedCardCount;
                }
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

            getTitle: function() {
               return window.checkoutConfig.payment.ccform.cctitle ;
            },
            hasVerification:function() {
               return window.checkoutConfig.payment.ccform.isCvcRequired ;
            },
            getSaveCardAllowed: function(){
                if(customer.isLoggedIn()){
                    if(!window.checkoutConfig.payment.ccform.saveCardAllowed){
                        this.disableUseSavedCard();
                    }
                    return window.checkoutConfig.payment.ccform.saveCardAllowed;
                }else {
                    this.disableUseSavedCard();
                }
            },
            disableUseSavedCard: function(){
                $("#saved-Card-Visibility-Enabled").hide();
                $(".cc-Visibility-Enabled").children().prop('disabled',false);
                $("#saved-Card-Visibility-Enabled").children().prop('disabled',true);
                $(".cc-Visibility-Enabled").show();
                this.isSavedCardPayment = false;
            },
            isTokenizationEnabled: function(){
                if(customer.isLoggedIn()){
                    return window.checkoutConfig.payment.ccform.tokenizationAllowed;
                }
            },
            isStoredCredentialsEnabled: function(){
                if(customer.isLoggedIn()){
                    return window.checkoutConfig.payment.ccform.storedCredentialsAllowed;
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
            getselectedCCType : function(inputName){
                if(document.getElementById("creditcardnumber") != null) {
                if(document.getElementById("creditcardnumber").classList.contains("is_visa")){
                             return "VISA-SSL";
                         }
                          if(document.getElementById("creditcardnumber").classList.contains("is_mastercard")){
                             return "ECMC-SSL";
                         }
                          if(document.getElementById("creditcardnumber").classList.contains("is_amex")){
                            return "AMEX-SSL";
                         }
                          if(document.getElementById("creditcardnumber").classList.contains("is_discover")){
                            return "DISCOVER-SSL";
                         }
                          if(document.getElementById("creditcardnumber").classList.contains("is_dankort")){
                             return "DANKORT-SSL";
                         }
                          if(document.getElementById("creditcardnumber").classList.contains("is_jcb")){
                             return "JCB-SSL";
                         }
                          if(document.getElementById("creditcardnumber").classList.contains("is_diners")){
                             return "DINERS-SSL";
                         }
                          if(document.getElementById("creditcardnumber").classList.contains("is_maestro")){
                             return "MAESTRO-SSL";
                         }
                         else{
                             return "Invalid";
                         }
                     }
           },

            /**
             * @override
             */
            getData: function () {
                var card = this.getCardDetails();
                return {
                    'method': "worldpay_cc",
                    'additional_data': {
                        'cc_cid': card === null? null : card['cvv'],
                        'cc_type': this.isSavedCardPayment? "savedcard" : this.getselectedCCType('payment[cc_type]'),
                        'cc_exp_year': card === null? null :card['expyear'],
                        'cc_exp_month': card === null? null :card['expmonth'],
                        'cc_number': card === null? null :card['ccnumber'],
                        'cc_name': this.isSavedCardPayment? null : $('#' + this.getCode() + '_cc_name').val(),
                        'isSavedCardPayment': this.isSavedCardPayment,
                        'save_my_card': this.saveMyCard,
                        'collectionReference' : window.sessionId,             
                        'disclaimerFlag': this.disclaimerFlag,
                        'saved_cc_cid': this.isSavedCardPayment? $('.saved-cvv-number').val() : null,
                        'tokenId': this.isSavedCardPayment? $("input[name='payment[token_to_use]']:checked").val() : null,
                        'directSessionHref' : window.directSessionHref
                    }
                };
            },
            getCardDetails : function () {
              var cardDetails = [];
              cardDetails['cvv'] = this.creditCardVerificationNumber();
              cardDetails['expyear'] = this.creditCardExpYear();
              cardDetails['expmonth'] = this.creditCardExpMonth();
              cardDetails['ccnumber'] = this.creditCardNumber();
              if (setrawDataNull || this.isSavedCardPayment) {
                  return null;
              }
              return cardDetails;
            },
            isClientSideEncryptionEnabled:function(){
                if (this.getCsePublicKey()) {
                    return window.checkoutConfig.payment.ccform.cseEnabled;
                }
                return false;
            },
             getCsePublicKey:function(){
                return window.checkoutConfig.payment.ccform.csePublicKey;
            },
           
            getRegexCode:function(cardType){
                if ('AMEX' == cardType) {
                    return /^[0-9]{4}$/;
                }else{
                    return /^[0-9]{3}$/;
                }
            },
            getSessionHref : function () {
              setrawDataNull = false;
              window.directSessionHref ='';
              var self = this;
               var that = this;
               var merchantIdentity = this.getMerchantIdentity(); 
                var payload = {
                        id: merchantIdentity,
                        paymentData: this.getData()
                    };
                fullScreenLoader.startLoader();
                storage.post(
                    urlBuilder.createUrl('/worldpay/payment/sessions', {}),
                    JSON.stringify(payload),
                    true
                ).done(
                    function (response) {
                        /** Do your code here */
                        //console.log("entered done");
                        //console.log(response);
                        window.directSessionHref = response[0];
                        setrawDataNull = true;
                        self.placeOrder();
                        //alert('Success');
                        fullScreenLoader.stopLoader();
                    }
                ).fail(
                    function (response) {
                        fullScreenLoader.stopLoader();
                    }
                );  
            },
            preparePayment:function() {

                var self = this;
                this.redirectAfterPlaceOrder = false;
                var $form = $('#' + this.getCode() + '-form');
                   
                var saved_card_list = window.checkoutConfig.payment.ccform.savedCardList;
                
                var $savedCardForm = $('#' + this.getCode() + '-savedcard-form');
                var selectedSavedCardToken = $("input[name='payment[token_to_use]']:checked").val();
                var cc_type_selected = this.isSavedCardPayment ? "savedcard" : this.getselectedCCType('payment[cc_type]');
                if((this.getSaveCardAllowed() && (this.getSavedCardsCount() > 0) ) && customer.isLoggedIn() && !$('#worldpay_cc_newcard').is(':checked') && !$('#worldpay_cc_usesavedcard').is(':checked')){
                    if(!$('.option_error').length){
                        var errmsg=getCreditCardExceptions("CCAM22");
                     $('<div class="option_error" style="color:red;font-size: 1.2rem;"></div>').text(errmsg).insertBefore('#worldpay_cc_cc_type_div');
                    }
                    return false;
                }else{
                    if($('.option_error').length){
                     $('.option_error').remove();
                    }
                }
                 if (this.threeDSEnabled()) {
                     var bin = this.creditCardNumber();
                     if(cc_type_selected === 'savedcard'){
                    if($savedCardForm.validation() && $savedCardForm.validation('isValid') && selectedSavedCardToken){
                            var saved_card = saved_card_list.filter(savedcard => savedcard.token_id === selectedSavedCardToken);
                            var cardType = saved_card[0].card_brand;
                            this.paymentToken = selectedSavedCardToken;
                            var savedcvv = $('.saved-cvv-number').val();
                            var res = this.getRegexCode(cardType).exec(savedcvv);
                            if(savedcvv != res){
                                $('#saved-cvv-error').css('display', 'block');
                                $('#saved-cvv-error').html(getCreditCardExceptions('CCAM4'));
                                return false;
                            }
                            fullScreenLoader.startLoader();
                            window.sessionId = '';
                            this.getDdcUrl();
                            window.addEventListener("message", function(event) {
                                 //console.log("enetered event listener"); 
                                 fullScreenLoader.startLoader();
                                            var data = JSON.parse(event.data);
                                                //console.warn('Merchant received a message:', data);
                                                if (data !== undefined && data.Status) {
                                                   // window.sessionId = data.SessionId;
                                                    window.sessionId = data.SessionId;
                                                  fullScreenLoader.stopLoader();
                                                  self.placeOrder();
                                                }
                                        }, false);
                                       fullScreenLoader.stopLoader(); 
                    }else{
                        return $savedCardForm.validation() && $savedCardForm.validation('isValid');
                    }
                }else if($form.validation() && $form.validation('isValid')) {
                         //save card
                            this.saveMyCard = $('#' + this.getCode() + '_save_card').is(":checked");
                            //Disclaimer error message
                            if (this.saveMyCard && this.isDisclaimerMessageMandatory() && this.isDisclaimerMessageEnabled()
                                    && window.disclaimerDialogue === null) {
                                $('#disclaimer-error').css('display', 'block');
                                $('#disclaimer-error').html($.mage.__(getCreditCardExceptions('CCAM1')));
                                return false;
                            }
                            fullScreenLoader.startLoader();
                            var binNew = bin.substring(0,6);
                            window.sessionId = '';
                            this.getDdcUrl();
                            window.addEventListener("message", function(event) {
                                 console.log("enetered event listener");            
                                            var data = JSON.parse(event.data);
                                                //console.warn('Merchant received a message:', data);
                                                if (data !== undefined && data.Status && window.sessionId !== data.SessionId) {
                                                   // window.sessionId = data.SessionId;
                                                    window.sessionId = data.SessionId;
                                                    fullScreenLoader.stopLoader();
                                                    self.getSessionHref();
                                                   
                                                }
                                        }, false);
                        }
                 } else if(cc_type_selected === 'savedcard'){
                    if($savedCardForm.validation() && $savedCardForm.validation('isValid') && selectedSavedCardToken){
                            var saved_card = saved_card_list.filter(savedcard => savedcard.token_id === selectedSavedCardToken);
                            var cardType = saved_card[0].card_brand;
                            this.paymentToken = selectedSavedCardToken;
                            var savedcvv = $('.saved-cvv-number').val();
                            var res = this.getRegexCode(cardType).exec(savedcvv);
                            if(savedcvv != res){
                                $('#saved-cvv-error').css('display', 'block');
                                $('#saved-cvv-error').html(getCreditCardExceptions('CCAM4'));
                                return false;
                            }
                            fullScreenLoader.stopLoader();
                            self.placeOrder();
                    }else{
                        return $savedCardForm.validation() && $savedCardForm.validation('isValid');
                    }
                } else if($form.validation() && $form.validation('isValid')){
                    
                        //save card
                        this.saveMyCard = $('#' + this.getCode() + '_save_card').is(":checked");
                        //Disclaimer error message
                        if (this.saveMyCard && this.isDisclaimerMessageMandatory() && this.isDisclaimerMessageEnabled() && window.disclaimerDialogue === null) {
                            $('#disclaimer-error').css('display', 'block');
                            $('#disclaimer-error').html($.mage.__(getCreditCardExceptions('CCAM1')));
                            return false;
                        }
			if(!this.isSavedCardPayment){
                        this.getSessionHref();
			}
                    } else {
                        return $form.validation() && $form.validation('isValid');
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
                        },
                        Disagree: function() { 
                            $( this ).dialog( "close" );
                            that.disclaimerFlag = false;
                            window.disclaimerDialogue = false;
                            $('#disclaimer-error').css('display', 'none');
                            if(that.isDisclaimerMessageEnabled()){
                                that.saveMyCard = '';
                                $('#' + that.getCode() + '_save_card').prop( "checked", false );
                            }
                        }
                    },
                    open: function (type, data) {
                        $(this).parent().appendTo("#disclaimer");
                    }
                });
                $('#dialog').parent().css('position', 'absolute');
                $('#dialog').dialog('open');            
            }
        });
    }
);