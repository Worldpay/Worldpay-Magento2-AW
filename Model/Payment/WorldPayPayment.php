<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Payment;

/**
 * Updating Risk gardian
 */
class WorldPayPayment
{
    /**
     * @var object $omsDataFactory
     */
    protected $omsDataFactory;
    /**
     * @var object $partialSettlementsFactory
     */
    protected $partialSettlementsFactory;
    /**
     * Constructor
     *
     * @param \Sapient\AccessWorldpay\Model\AccessWorldpaymentFactory $worldpaypayment
     * @param \Sapient\AccessWorldpay\Model\OmsDataFactory $omsDataFactory
     * @param \Sapient\AccessWorldpay\Model\PartialSettlementsFactory $partialSettlementsFactory
     */
    public function __construct(
        \Sapient\AccessWorldpay\Model\AccessWorldpaymentFactory $worldpaypayment,
        \Sapient\AccessWorldpay\Model\OmsDataFactory $omsDataFactory,
        \Sapient\AccessWorldpay\Model\PartialSettlementsFactory $partialSettlementsFactory
    ) {
        $this->worldpaypayment = $worldpaypayment;
        $this->omsDataFactory = $omsDataFactory;
        $this->partialSettlementsFactory = $partialSettlementsFactory;
    }

    /**
     * Updating Risk gardian
     *
     * @param \Sapient\AccessWorldpay\Model\Payment\StateInterface $paymentState
     */
    public function updateAccessWorldpayPayment(\Sapient\AccessWorldpay\Model\Payment\StateInterface $paymentState)
    {
        $wpp = $this->worldpaypayment->create();

        $wpp = $wpp->loadByAccessWorldpayOrderId($paymentState->getOrderCode());
        if (strtoupper($paymentState->getPaymentStatus()) !== "UNKNOWN") {
            $wpp->setData('payment_status', $paymentState->getPaymentStatus());
        }
//        $wpp->setData('card_number', $paymentState->getCardNumber());
//        $wpp->setData('avs_result', $paymentState->getAvsResultCode());
//        $wpp->setData('cvc_result', $paymentState->getCvcResultCode());
//        $wpp->setData('risk_score', $paymentState->getRiskScore());
//        $wpp->setData('risk_provider', $paymentState->getAdvancedRiskProvider());
//        $wpp->setData('risk_provider_score', $paymentState->getAdvancedRiskProviderScore());
//        $wpp->setData('risk_provider_id', $paymentState->getAdvancedRiskProviderId());
//        $wpp->setData('risk_provider_threshold', $paymentState->getAdvancedRiskProviderThreshold());
//        $wpp->setData('risk_provider_final', $paymentState->getAdvancedRiskProviderFinalScore());
//        $wpp->setData('refusal_code', $paymentState->getPaymentRefusalCode());
//        $wpp->setData('refusal_description', $paymentState->getPaymentRefusalDescription());
//        $wpp->setData('aav_address_result_code', $paymentState->getAAVAddressResultCode());
//        $wpp->setData('avv_postcode_result_code', $paymentState->getAAVPostcodeResultCode());
//        $wpp->setData('aav_cardholder_name_result_code', $paymentState->getAAVCardholderNameResultCode());
//        $wpp->setData('aav_telephone_result_code', $paymentState->getAAVTelephoneResultCode());
//        $wpp->setData('aav_email_result_code', $paymentState->getAAVEmailResultCode());
        
        $wpp->save();
        $this->saveOmsData($paymentState);
    }
    /**
     * Save worldpay data in DB
     *
     * @param  string $paymentState
     */
    public function saveOmsData($paymentState)
    {
        $orderCode = $paymentState->getOrderCode();
        $oms = $this->omsDataFactory->create();
        $omsData = $oms->getCollection()
                        ->addFieldToSelect('awp_cancel_param')
                        ->addFieldToFilter(
                            'order_increment_id',
                            ['eq' => current(explode('-', $orderCode))]
                        )->getData();
        if ($paymentState
            && (strtoupper($paymentState->getPaymentStatus())== 'AUTHORIZED')
            && ($paymentState->getLinks()!==null)
            && !isset($omsData[0])) {
            $responseLinks = $paymentState->getLinks();
 
            $cancelLink = $settleLink = $partialSettleLink = $eventsLink = '';
            //foreach($responseLinks as $key => $link){
            if (isset($responseLinks['cancel'])) {
                $cancelLink = $responseLinks['cancel'][0];
            }
            if (isset($responseLinks['settle'])) {
                $settleLink = $responseLinks['settle'][0];
            }
            if (isset($responseLinks['partialSettle'])) {
                $partialSettleLink =  $responseLinks['partialSettle'][0];
            }
            if (isset($responseLinks['events'])) {
                $eventsLink = $responseLinks['events'][0];
            }
            //}
            //$orderCode = $paymentState->getOrderCode();
            $orderId = current(explode('-', $orderCode));
            $omsData['order_increment_id'] = $orderId;
            $omsData['awp_order_code'] = $orderCode;
            $omsData['awp_payment_status'] = $paymentState->getPaymentStatus();
            $omsData['awp_cancel_param'] = $cancelLink;
            $omsData['awp_settle_param'] = $settleLink;
            $omsData['awp_partial_settle_param'] = $partialSettleLink;
            $omsData['awp_events_param'] = $eventsLink;
            //$oms = $this->omsDataFactory->create();
            $oms->setData($omsData)->save();
        }
    }
}
