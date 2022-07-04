<?php


namespace Sapient\AccessWorldpay\Model\JsonBuilder;

class ThreeDsVerifiaction
{
    /**
     * @var string
     */
    private $orderCode;
    /**
     * Challenge window referecne
     *
     * @var array
     */
    private $challengeReference;
    
    /**
     * Build jsonObj for processing Request
     *
     * @param string $orderCode
     * @param array $challengeReference
     * @return string
     */
    public function build(
        $orderCode,
        $challengeReference
    ) {
        $this->orderCode = $orderCode;
        $this->challengeReference = $challengeReference;
        $jsonData = $this->_addOrderElement();
        return json_encode($jsonData);
    }
    
    /**
     * Build an ordered array
     *
     * @return array
     */
    private function _addOrderElement()
    {
        $orderData = [];
        $orderData['transactionReference'] = $this->_addTransactionRef();
        $orderData['merchant'] = $this->_addMerchantInfo();
        $orderData['challenge'] =  $this->_addChallenge();
        
        return $orderData;
    }
    
    /**
     * Add order code to jsonObj
     *
     * @return array
     */
    private function _addTransactionRef()
    {
        return $this->orderCode['orderCode'];
    }
    
    /**
     * Add merchant entity referecne to jsonObj
     *
     * @return array
     */
    private function _addMerchantInfo()
    {
        $merchantData = ["entity" => $this->orderCode['paymentDetails']['entityRef']];
        return $merchantData;
    }
    
    /**
     * Add challenge referecne to jsonObj
     *
     * @return array
     */
    private function _addChallenge()
    {
        $challengeData = ["reference" => $this->challengeReference];
        return $challengeData;
    }
}
