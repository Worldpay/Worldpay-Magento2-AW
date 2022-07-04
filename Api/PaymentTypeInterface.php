<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Api;
 
interface PaymentTypeInterface
{
    /**
     * Retrive Payment Types
     *
     * @api
     * @param string $countryId
     * @return json
     */
    public function getPaymentType($countryId);
}
