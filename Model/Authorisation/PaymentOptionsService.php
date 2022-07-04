<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Authorisation;

use Exception;

class PaymentOptionsService extends \Magento\Framework\DataObject
{
   
    /**
     * Constructor
     * @param \Sapient\AccessWorldpay\Model\Mapping\Service $mappingservice
     * @param \Sapient\AccessWorldpay\Model\Request\PaymentServiceRequest $paymentservicerequest
     * @param \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger
     * @param \Sapient\AccessWorldpay\Helper\Data $worldpayhelper
     */
    public function __construct(
        \Sapient\AccessWorldpay\Model\Mapping\Service $mappingservice,
        \Sapient\AccessWorldpay\Model\Request\PaymentServiceRequest $paymentservicerequest,
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger,
        \Sapient\AccessWorldpay\Helper\Data $worldpayhelper
    ) {
        $this->mappingservice = $mappingservice;
        $this->paymentservicerequest = $paymentservicerequest;
        $this->wplogger = $wplogger;
        $this->worldpayhelper = $worldpayhelper;
    }
    /**
     * Handles provides authorization data for redirect
     *
     * It initiates a  XML request to WorldPay and registers worldpayRedirectUrl
     *
     * @param string $countryId
     * @param string $paymenttype
     * @return array
     */
    public function collectPaymentOptions(
        $countryId,
        $paymenttype
    ) {
        $paymentOptionParams = $this->mappingservice->collectPaymentOptionsParameters(
            $countryId,
            $paymenttype
        );

        $response = $this->paymentservicerequest->paymentOptionsByCountry($paymentOptionParams);
        $responsexml = simplexml_load_string($response);

        $paymentoptions =  $this->getPaymentOptions($responsexml);
        return $paymentoptions;
    }

    /**
     * Get payment options
     *
     * @param SimpleXMLElement $xml
     * @return array|null
     */
    private function getPaymentOptions($xml)
    {
        if (isset($xml->reply->paymentOption)) {
            return (array) $xml->reply->paymentOption;
        }
        return null;
    }
}
