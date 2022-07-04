<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Sapient\AccessWorldpay\Model\InstantPurchase\CreditCard;

use Magento\InstantPurchase\PaymentMethodIntegration\PaymentTokenFormatterInterface;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Sapient\AccessWorldpay\Model\ResourceModel\SavedToken;

class TokenFormatter implements PaymentTokenFormatterInterface
{
     
    /**
     * @var $_worldpaymentFactory
     */
    protected $_worldpaymentFactory;
    /**
     * Most used credit card types
     * @var array
     */
    public static $baseCardTypes = [
        'AMEX-SSL' => 'American Express',
        'VISA-SSL' => 'Visa',
        'ECMC-SSL' => 'MasterCard',
        'DISCOVER-SSL' => 'Discover',
        'JCB-SSL' => 'Japanese Credit Bank',
        'CARTEBLEUE-SSL' => 'Carte Bleue',
        'MAESTRO-SSL' => 'Maestro',
        'DANKORT-SSL' => 'Dankort',
        'CB-SSL' => 'Carte Bancaire',
        'DINERS-SSL' => 'Diners',
    ];

    /**
     * TokenFormatter constructor
     *
     * @param \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger
     * @param \Sapient\AccessWorldpay\Model\SavedTokenFactory $savedWPFactory
     * @param \Sapient\AccessWorldpay\Helper\Data $wpdata
     * @param SavedToken $savedtoken
     */
    public function __construct(
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger,
        \Sapient\AccessWorldpay\Model\SavedTokenFactory $savedWPFactory,
        \Sapient\AccessWorldpay\Helper\Data $wpdata,
        SavedToken $savedtoken
    ) {
        $this->wplogger = $wplogger;
        $this->savecard = $savedWPFactory;
        $this->wpdata = $wpdata;
        $this->savedtoken = $savedtoken;
    }

    /**
     * @inheritdoc
     */
    public function formatPaymentToken(PaymentTokenInterface $paymentToken): string
    {
        $details = json_decode($paymentToken->getTokenDetails() ?: '{}', true);
        if (!isset($details['type'], $details['maskedCC'], $details['expirationDate'])) {
            throw new \InvalidArgumentException('Invalid Worldpay credit card token details.');
        }

        if (isset(self::$baseCardTypes[$details['type']])) {
            $ccType = self::$baseCardTypes[$details['type']];
        } else {
            $ccType = $details['type'];
        }
            $formatted = sprintf(
                '%s: %s, %s: %s ,%s: %s',
                __('Credit Card'),
                $ccType,
                __('ending'),
                $details['maskedCC'],
                __('expires'),
                $details['expirationDate']
            );
        return $formatted;
    }
}
