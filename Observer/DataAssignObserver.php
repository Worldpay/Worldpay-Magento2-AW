<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Sapient\AccessWorldpay\Observer;

use Magento\Framework\Event\Observer;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Quote\Api\Data\PaymentInterface;

/**
 * Adds the payment info to the payment object
 *
 * @deprecated 100.3.3 Starting from Magento 2.3.4 Authorize.net
 * payment method core integration is deprecated in favor of
 * official payment integration available on the marketplace
 */
class DataAssignObserver extends AbstractDataAssignObserver
{
    /**
     * @var array
     */
    private $additionalInformationList = [
        'cc_name',
        'cc_number',
        'cc_exp_month',
        'cc_exp_year',
        'cart_id',
        'cvc',
        'save_card',
        'cvcHref',
        'sessionHref',
        'tokenId',
        'tokenUrl',
        'customer_id',
        'is_graphql',
        'googlepayToken',
        'applepayToken',
        'cc_type',
        'ach_account',
        'ach_accountNumber',
        'ach_routingNumber',
        'ach_checknumber',
        'ach_companyname',
        'ach_emailaddress',
        'statementNarrative'
    ];

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer)
    {
        $data = $this->readDataArgument($observer);
        $additionalData = $data->getData(PaymentInterface::KEY_ADDITIONAL_DATA);
        $additionalInformationData = isset($additionalData['additional_information'])
                ? $additionalData['additional_information'] : null;

        if (!is_array($additionalData)) {
            return;
        }

        $paymentInfo = $this->readPaymentModelArgument($observer);

        foreach ($this->additionalInformationList as $additionalInformationKey) {
            if (!empty($additionalInformationData)) {
                if (isset($additionalInformationData[$additionalInformationKey])) {
                    $this->setData($paymentInfo, $additionalInformationKey, $additionalInformationData);
                }
            } else {
                if (isset($additionalData[$additionalInformationKey])) {
                    $this->setData($paymentInfo, $additionalInformationKey, $additionalData);
                }
            }
        }
    }

    public function setData($paymentInfo, $key, $additionalData)
    {
        $paymentInfo->setAdditionalInformation(
            $key,
            $additionalData[$key]
        );
    }
}
