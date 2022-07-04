<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Payment;

/**
 * Describe what can be read from WP's xml response
 */
interface StateInterface
{
    /**
     * @var STATUS_SENT_FOR_AUTHORIZATION
     */
    public const STATUS_SENT_FOR_AUTHORISATION = 'SENT_FOR_AUTHORIZATION';
    
    /**
     * @var STATUS_AUTHORIZED
     */
    public const STATUS_AUTHORISED = 'AUTHORIZED';
    
    /**
     * @var STATUS_CANCELLED
     */
    public const STATUS_CANCELLED = 'CANCELLED';
    
    /**
     * @var STATUS_PENDING_PAYMENT
     */
    public const STATUS_PENDING_PAYMENT = 'PENDING_PAYMENT';
    
    /**
     * @var STATUS_REFUSED
     */
    public const STATUS_REFUSED = 'REFUSED';
    
    /**
     * @var STATUS_ERROR
     */
    public const STATUS_ERROR = 'ERROR';
    
    /**
     * @var STATUS_SETTLED
     */
    public const STATUS_SETTLED = 'SETTLED';
    
    /**
     * @var STATUS_SETTLED_BY_MERCHANT
     */
    public const STATUS_SETTLED_BY_MERCHANT = 'SETTLED_BY_MERCHANT';
    
    /**
     * @var STATUS_CHARGED_BACK
     */
    public const STATUS_CHARGED_BACK = 'CHARGED_BACK';

    /**
     * @var STATUS_CHARGEBACK_REVERSED
     */
    public const STATUS_CHARGEBACK_REVERSED = 'CHARGEBACK_REVERSED';
    
    /**
     * @var STATUS_INFORMATION_SUPPLIED
     */
    public const STATUS_INFORMATION_SUPPLIED = 'INFORMATION_SUPPLIED';
    
    /**
     * @var STATUS_INFORMATION_REQUESTED
     */
    public const STATUS_INFORMATION_REQUESTED = 'INFORMATION_REQUESTED';
    
    /**
     * @var STATUS_CAPTURED
     */
    public const STATUS_CAPTURED = 'CAPTURED';
    
    /**
     * @var STATUS_PARTIAL_CAPTURED
     */
    public const STATUS_PARTIAL_CAPTURED = 'PARTIAL_CAPTURED';
    
    /**
     * @var STATUS_SENT_FOR_REFUND
     */
    public const STATUS_SENT_FOR_REFUND = 'SENT_FOR_REFUND';
   
    /**
     * @var STATUS_REFUNDED
     */
    public const STATUS_REFUNDED = 'REFUNDED';
   
    /**
     * @var STATUS_PARTIAL_REFUNDED
     */
    public const STATUS_PARTIAL_REFUNDED = 'PARTIAL_REFUNDED';
    /**
     * @var STATUS_REFUND_WEBFORM_ISSUED
     */
    public const STATUS_REFUND_WEBFORM_ISSUED = 'REFUND_WEBFORM_ISSUED';
    
    /**
     * @var STATUS_REFUND_EXPIRED
     */
    public const STATUS_REFUND_EXPIRED = 'REFUND_EXPIRED';
    
    /**
     * @var STATUS_REFUND_FAILED
     */
    public const STATUS_REFUND_FAILED = 'REFUND_FAILED';
   
    /**
     * @var STATUS_REFUNDED_BY_MERCHANT
     */
    public const STATUS_REFUNDED_BY_MERCHANT = 'REFUNDED_BY_MERCHANT';
   
    /**
     * @var STATUS_SENT_FOR_SETTLEMENT
     */
    public const STATUS_SENT_FOR_SETTLEMENT = 'SENT_FOR_SETTLEMENT';
   
    /**
     * Get getPaymentStatus
     */
    public function getPaymentStatus();
   
    /**
     * Get getOrderCode
     */
    public function getOrderCode();
  
    /**
     * Get getJournalReference
     *
     * @param string $state
     * @return string
     */
    public function getJournalReference($state);
  
    /**
     * Get getLinks
     */
    public function getLinks();
//    public function getAmount();
//    public function getMerchantCode();
//    public function getRiskScore();
//    public function getPaymentMethod();
//    public function getCardNumber();
//    public function getAvsResultCode();
//    public function getCvcResultCode();
//    public function getAdvancedRiskProvider();
//    public function getAdvancedRiskProviderId();
//    public function getAdvancedRiskProviderThreshold();
//    public function getAdvancedRiskProviderScore();
//    public function getAdvancedRiskProviderFinalScore();
//    public function getPaymentRefusalCode();
//    public function getPaymentRefusalDescription();
//    public function getJournalReference($state);
//    public function isAsyncNotification();
//    public function isDirectReply();
//    public function getAAVAddressResultCode();
//    public function getAAVPostcodeResultCode();
//    public function getAAVCardholderNameResultCode();
//    public function getAAVTelephoneResultCode();
//    public function getAAVEmailResultCode();
//    public function getCurrency();
}
