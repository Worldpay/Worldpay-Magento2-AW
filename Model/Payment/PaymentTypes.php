<?php
/**
 * @copyright 2020 Sapient
 */

namespace Sapient\AccessWorldpay\Model\Payment;

use Sapient\AccessWorldpay\Api\PaymentTypeInterface;

class PaymentTypes implements PaymentTypeInterface
{
    /**
     * Cunstructor
     *
     * @param \Sapient\AccessWorldpay\Model\Authorisation\PaymentOptionsService $paymentoptionsservice
     */
    
    public function __construct(
        \Sapient\AccessWorldpay\Model\Authorisation\PaymentOptionsService $paymentoptionsservice
    ) {
        $this->paymentoptionsservice = $paymentoptionsservice;
    }
    
    /**
     * Get Payment Type
     *
     * @param int $countryId [description]
     * @return string
     */
    public function getPaymentType($countryId)
    {
        $responsearray = [];
        $result = $this->paymentoptionsservice->collectPaymentOptions($countryId, $paymenttype = null);
        if (!empty($result)) {
            $responsearray = $result;
        }
        return json_encode($responsearray);
    }
}
