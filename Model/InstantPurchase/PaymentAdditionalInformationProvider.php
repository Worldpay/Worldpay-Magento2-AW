<?php
namespace Sapient\AccessWorldpay\Model\InstantPurchase;

use Magento\InstantPurchase\PaymentMethodIntegration\PaymentAdditionalInformationProviderInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Sapient\AccessWorldpay\Logger\AccessWorldpayLogger;

class PaymentAdditionalInformationProvider implements PaymentAdditionalInformationProviderInterface
{

    /**
     * PaymentAdditionalInformationProvider constructor
     *
     * @param AccessWorldpayLogger $logger
     * @param \Magento\Checkout\Model\Session $checkoutSession
     */
    public function __construct(
        AccessWorldpayLogger $logger,
        \Magento\Checkout\Model\Session $checkoutSession
    ) {
        $this->logger = $logger;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * @inheritdoc
     */
    public function getAdditionalInformation(PaymentTokenInterface $paymentToken): array
    {
        $vaultCardDetails = json_decode($paymentToken->getDetails());
        $dfId = $this->checkoutSession->getDfReferenceId();
        if ($dfId === null) {
            return [
            'cc_type' => $vaultCardDetails->type,
            'card_brand' => str_replace('-SSL', '', $vaultCardDetails->type),
            'token' => $paymentToken->getGatewayToken(),
            
            ];
        } else {
            $this->checkoutSession->unsDfReferenceId();
            return [
            'cc_type' => $vaultCardDetails->type,
            'card_brand' => str_replace('-SSL', '', $vaultCardDetails->type),
            'token' => $paymentToken->getGatewayToken() ,
            'collectionReference' => $dfId
            ];
        }
    }
}
