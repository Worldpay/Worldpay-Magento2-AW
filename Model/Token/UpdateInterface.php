<?php
/**
 * @copyright 2017 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Token;

/**
 * Interface Sapient_WorldPay_Model_Token_UpdateInterface
 *
 * Describe what can be read from WP's token update response
 */
interface UpdateInterface
{
    /**
     * Getting token code
     *
     * @return string
     */
    public function getTokenCode();

    /**
     * Is success?
     *
     * @return boolean
     */
    public function isSuccess();
}
