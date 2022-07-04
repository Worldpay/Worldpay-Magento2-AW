<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Sapient\AccessWorldpay\Model;

use Magento\QuoteGraphQl\Model\Cart\Payment\AdditionalDataProviderInterface;
use Magento\Framework\Stdlib\ArrayManager;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;

/**
 * SetPaymentMethod additional data provider model for Authorizenet payment method
 *
 * @deprecated 100.3.1 Starting from Magento 2.3.4 Authorize.net
 * payment method core integration is deprecated in favor of
 * official payment integration available on the marketplace
 */
class WorldpayWalletDataProvider implements AdditionalDataProviderInterface
{
    private const PATH_ADDITIONAL_DATA = 'worldpay_cc';
    private const WALLET_PATH_ADDITIONAL_DATA = 'worldpay_wallets';

    /**
     * @var ArrayManager
     */
    private $arrayManager;

    /**
     * @param ArrayManager $arrayManager
     * @param \Magento\Authorization\Model\CompositeUserContext $userContext
     * @param \Magento\Framework\Stdlib\DateTime\DateTime $dateTime
     * @param \Sapient\AccessWorldpay\Helper\Data $helper
     */
    public function __construct(
        ArrayManager $arrayManager,
        \Magento\Authorization\Model\CompositeUserContext $userContext,
        \Magento\Framework\Stdlib\DateTime\DateTime $dateTime,
        \Sapient\AccessWorldpay\Helper\Data $helper
    ) {
        $this->arrayManager = $arrayManager;
        $this->userContext = $userContext;
        $this->dateTime = $dateTime;
        $this->worldpayHelper = $helper;
    }

    /**
     * Return additional data
     *
     * @param array $data
     * @return array
     * @throws GraphQlInputException
     */
    public function getData(array $data): array
    {

        if (!isset($data[self::PATH_ADDITIONAL_DATA]['applepayToken'])
            && !isset($data[self::PATH_ADDITIONAL_DATA]['googlepayToken'])) {
            throw new GraphQlInputException(
                __('Wallet token is missing.')
            );
        }

        if (isset($data[self::PATH_ADDITIONAL_DATA]['applepayToken'])
            && !empty($data[self::PATH_ADDITIONAL_DATA]['applepayToken'])
            && !$this->worldpayHelper->isApplePayEnable()) {
            throw new GraphQlInputException(
                __('Applepay Wallet not available.')
            );
        }

        if (isset($data[self::PATH_ADDITIONAL_DATA]['googlepayToken'])
            && !empty($data[self::PATH_ADDITIONAL_DATA]['googlepayToken'])
            && !$this->worldpayHelper->isGooglePayEnable()) {
            throw new GraphQlInputException(
                __('Googlepay Wallet not available.')
            );
        }

        /* Variable to identify graphQL call*/

        $data[self::PATH_ADDITIONAL_DATA]['is_graphql'] = 1;
        
        $additionalData = $this->arrayManager->get(static::PATH_ADDITIONAL_DATA, $data);
        //print_r($additionalData);
        return $additionalData;
    }
}
