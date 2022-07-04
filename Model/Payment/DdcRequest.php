<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Sapient\AccessWorldpay\Model\Payment;

use Sapient\AccessWorldpay\Api\DdcRequestInterface;

class DdcRequest implements DdcRequestInterface
{
    /**
     * Constructor
     *
     * @param \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger         $wplogger
     * @param \Sapient\AccessWorldpay\Helper\Data                         $worldpayHelper
     * @param \Sapient\AccessWorldpay\Model\Request\PaymentServiceRequest $paymentservicerequest
     * @param \Sapient\AccessWorldpay\Model\Payment\Service               $paymentservice
     * @param \Magento\Checkout\Model\Session                             $checkoutSession
     * @param \Magento\Customer\Model\Session                             $customerSession
     * @param \Magento\Quote\Model\Quote                                  $quote
     */
    public function __construct(
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger,
        \Sapient\AccessWorldpay\Helper\Data $worldpayHelper,
        \Sapient\AccessWorldpay\Model\Request\PaymentServiceRequest $paymentservicerequest,
        \Sapient\AccessWorldpay\Model\Payment\Service $paymentservice,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Quote\Model\Quote $quote
    ) {
        $this->wplogger = $wplogger;
        $this->worldpayHelper = $worldpayHelper;
        $this->_paymentservicerequest = $paymentservicerequest;
        $this->paymentservice = $paymentservice;
        $this->checkoutSession = $checkoutSession;
        $this->quote = $quote;
        $this->customerSession = $customerSession;
    }

    /**
     * Create device data collection request
     *
     * @param string $cartId
     * @param array $paymentData
     * @return string
     */
    public function createDdcRequest($cartId, $paymentData)
    {
        $orderParams = $this->collectDdcRequestData($cartId, $paymentData);
        $ddcresponse = $this->_paymentservicerequest->_createDeviceDataCollection($orderParams);
        if (isset($ddcresponse['outcome']) && $ddcresponse['outcome'] === 'initialized') {
            $this->checkoutSession->setDdcUrl($ddcresponse['deviceDataCollection']['url']);
            $this->checkoutSession->setDdcJwt($ddcresponse['deviceDataCollection']['jwt']);
            $this->checkoutSession->set3Dsparams($ddcresponse);
            //$this->checkoutSession->setDirectOrderParams($directOrderParams);
            $this->checkoutSession->setAuthOrderId($orderParams['orderCode']);
        } else {
            if ($ddcresponse['message'] === 'Requested token does not exist') {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __($this->worldpayHelper->getCreditCardSpecificException('CCAM9'))
                );
            } else {
                throw new \Magento\Framework\Exception\LocalizedException(__($ddcresponse['message']));
            }
        }
    }
   
    /**
     * Get Customer Id
     *
     * @return string
     */
    public function getCustomerId()
    {
        if ($this->customerSession->isLoggedIn()) {
            return $this->customerSession->getCustomer()->getId();
        }
    }

    /**
     * Save DDC request data
     *
     * @param array $paymentData
     * @param string $cartId
     * @return string
     */
    private function collectSavedCardDdcRequestData($paymentData, $cartId)
    {
        $this->wplogger->info("Inititializing device data for checkout for customer with cartId-" .
            $cartId);
        $tokendata = $this->worldpayHelper->getSelectedSavedCardTokenData(
            $paymentData['additional_data']['tokenId']
        );
        $token_url = $tokendata[0]['token'];
        return $token_url;
    }

    /**
     * Get device data for new card
     *
     * @param array $paymentData
     * @param string $cartId
     * @param array $aditionalData
     * @return string
     */
    public function collectNewCardDdcRequestData($paymentData, $cartId, $aditionalData)
    {
        $this->wplogger->info("Inititializing device data for checkout for customer with cartId-" .
            $cartId);
        if (isset($paymentData['additional_data']['sessionHref'])
            && $paymentData['additional_data']['sessionHref'] != '') {
            $payment['sessionHref'] = $aditionalData['sessionHref'];
        } else {
            $payment = [
                'cardNumber' => $aditionalData['cc_number'],
                'paymentType' => $aditionalData['cc_type'],
                'cardHolderName' => $aditionalData['cc_name'],
                'expiryMonth' => $aditionalData['cc_exp_month'],
                'expiryYear' => $aditionalData['cc_exp_year'],
                    //'cseEnabled' => $fullRequest->payment->cseEnabled
            ];
        }
        //cvc disabled
        if (isset($aditionalData['cc_cid']) && !$aditionalData['cc_cid'] == '') {
            $payment['cvc'] = $aditionalData['cc_cid'];
        }
        return $payment;
    }
    
    /**
     * Collect Ddc Request Data
     *
     * @param string $cartId
     * @param string $paymentData
     * @return string
     */
    private function collectDdcRequestData($cartId, $paymentData)
    {
        $aditionalData = $paymentData['additional_data'];
        $incrementId = '';
        //instant purchase flow
        if (isset($paymentData['additional_data']['publicHash'])) {
            $this->wplogger->info("Inititializing device data for Instant Purchase for cusomerID-" .
                    $this->getCustomerId());
            $summary = explode(' ', $paymentData['additional_data']['summary']);
            $index = array_search('ending:', $summary) + 1;
            $token_url = $this->worldpayHelper->getTokenFromVault(
                $paymentData['additional_data']['publicHash'],
                $this->getCustomerId()
            );
            $incrementId = $this->getCustomerId() . $summary[$index];
            $payment['token_url'] = $token_url;
        } elseif (isset($paymentData['additional_data']['tokenId'])
                  && $paymentData['additional_data']['tokenId'] != '') {
            //saved card flow
            $payment['token_url'] = $this->collectSavedCardDdcRequestData($paymentData, $cartId);
        } else {
            //new card flow
            $payment = $this->collectNewCardDdcRequestData($paymentData, $cartId, $aditionalData);
        }
        $orderParams = [];
        // $this->quote->reserveOrderId()->save();
        //entity ref
        $payment['entityRef'] = $this->worldpayHelper->getMerchantEntityReference();
        $orderParams['orderCode'] = $incrementId ? $incrementId . '-' . time() : $cartId . '-' . time();
        $orderParams['paymentDetails'] = $payment;
        return $orderParams;
    }
}
