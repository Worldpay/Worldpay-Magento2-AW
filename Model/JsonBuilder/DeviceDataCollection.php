<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Sapient\AccessWorldpay\Model\JsonBuilder;

class DeviceDataCollection
{
    
    /**
     * @var string
     */
    private $orderCode;
    /**
     * Order payment details
     *
     * @var array
     */
    private $paymentDetails;
    
    /**
     * Build jsonObj for processing Request
     *
     * @param string $orderCode
     * @param array $paymentDetails
     * @return string
     */
    public function build(
        $orderCode,
        $paymentDetails
    ) {
        $this->orderCode = $orderCode;
        $this->paymentDetails = $paymentDetails;
        $jsonData = $this->_addOrderElement();
        return json_encode($jsonData);
    }
    
    /**
     * Build an order data array
     *
     * @return array
     */
    private function _addOrderElement()
    {
        $orderData = [];
       
        $orderData['transactionReference'] = $this->_addTransactionRef();
        $orderData['merchant'] = $this->_addMerchantInfo();
        if (isset($this->paymentDetails['cvcHref']) || isset($this->paymentDetails['sessionHref'])) {
            return $orderData;
        } else {
            $orderData['paymentInstrument'] =  $this->_addPaymentInfo();
            ;
        }
        return $orderData;
    }
    
    /**
     * Add order code to jsonObj
     *
     * @return array
     */
    private function _addTransactionRef()
    {
        return $this->orderCode;
    }
    
    /**
     * Add merchant entity referecne to jsonObj
     *
     * @return array
     */
    private function _addMerchantInfo()
    {
        $merchantData = ["entity" =>$this->paymentDetails['entityRef']];
        return $merchantData;
    }
    
//    private function _addInstructionInfo()
//    {
//        $instruction = array();
//        $instruction['paymentInstrument'] = $this->_addPaymentInfo();
//        return $instruction;
//    }
    
    /**
     * Add payment info data's to jsonObj
     *
     * @return array
     */
    private function _addPaymentInfo()
    {
        if (isset($this->paymentDetails['token_url'])) {
            $paymentData = ["type" => "card/tokenized",
               "href" =>$this->paymentDetails['token_url'] ];
            return $paymentData;
        } else {
            $paymentData = ["type" => "card/front",
                             "cardHolderName" => $this->paymentDetails['cardHolderName'],
                             "cardNumber" => $this->paymentDetails['cardNumber'],
                             "cardExpiryDate" => ["month" => (int)$this->paymentDetails['expiryMonth'],
                             "year" => (int)$this->paymentDetails['expiryYear']]];
            return $paymentData;
        }
    }
}
