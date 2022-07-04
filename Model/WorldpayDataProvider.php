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
use Sapient\AccessWorldpay\Helper\Data;

/**
 * SetPaymentMethod additional data provider model for Authorizenet payment method
 *
 * @deprecated 100.3.1 Starting from Magento 2.3.4 Authorize.net
 * payment method core integration is deprecated in favor of
 * official payment integration available on the marketplace
 */
class WorldpayDataProvider implements AdditionalDataProviderInterface
{
    private const PATH_ADDITIONAL_DATA = 'worldpay_cc';

    /**
     * @var ArrayManager
     */
    private $arrayManager;
    
    /**
     * Constructor
     *
     * @param ArrayManager $arrayManager
     * @param \Magento\Authorization\Model\CompositeUserContext $userContext
     * @param \Magento\Framework\Stdlib\DateTime\DateTime $dateTime
     * @param Data $worldpayHelper
     */
    public function __construct(
        ArrayManager $arrayManager,
        \Magento\Authorization\Model\CompositeUserContext $userContext,
        \Magento\Framework\Stdlib\DateTime\DateTime $dateTime,
        Data $worldpayHelper
    ) {
        $this->arrayManager = $arrayManager;
        $this->userContext = $userContext;
        $this->dateTime = $dateTime;
        $this->worldpayHelper = $worldpayHelper;
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
        if (!isset($data[self::PATH_ADDITIONAL_DATA])) {
            throw new GraphQlInputException(
                __($this->worldpayHelper->getCreditCardSpecificexception('GCCAM0'))
            );
        }
        if ($this->worldpayHelper->getCcIntegrationMode() =='web_sdk'
                && !empty($data[self::PATH_ADDITIONAL_DATA]['cc_number'])) {
            $exceptionMessage = $this->worldpayHelper->getCreditCardSpecificexception('GCCAM10')?
                    $this->worldpayHelper->getCreditCardSpecificexception('GCCAM10'):
                'Invalid Data passed for AccessCheckout(WebSDK) integration. 
                Please refer user guide for configuration for WebSDK';
            throw new GraphQlInputException(
                __($exceptionMessage)
            );
        }
        if ($this->getIsCardValidationRequired($data)
            && isset($data[self::PATH_ADDITIONAL_DATA]['cc_name'])
            && empty($data[self::PATH_ADDITIONAL_DATA]['cc_name'])) {
            throw new GraphQlInputException(
                __($this->worldpayHelper->getCreditCardSpecificexception('GCCAM1'))
            );
        }
        if ($this->getIsCardValidationRequired($data)
            && isset($data[self::PATH_ADDITIONAL_DATA]['cc_number'])
            && empty($data[self::PATH_ADDITIONAL_DATA]['cc_number'])) {
            throw new GraphQlInputException(
                __($this->worldpayHelper->getCreditCardSpecificexception('GCCAM2'))
            );
        }
        if ($this->getIsCardValidationRequired($data)
            && isset($data[self::PATH_ADDITIONAL_DATA]['cc_exp_month'])
            && empty($data[self::PATH_ADDITIONAL_DATA]['cc_exp_month'])) {
            throw new GraphQlInputException(
                __($this->worldpayHelper->getCreditCardSpecificexception('GCCAM3'))
            );
        } elseif ($this->getIsCardValidationRequired($data)
                  && ($data[self::PATH_ADDITIONAL_DATA]['cc_exp_month']==0
                  || $data[self::PATH_ADDITIONAL_DATA]['cc_exp_month']>12)) {
            throw new GraphQlInputException(__(
                $this->worldpayHelper->getCreditCardSpecificexception('GCCAM4')
            ));
        }
        if ($this->getIsCardValidationRequired($data)
            && isset($data[self::PATH_ADDITIONAL_DATA]['cc_exp_year'])
            && empty($data[self::PATH_ADDITIONAL_DATA]['cc_exp_year'])) {
            throw new GraphQlInputException(
                __($this->worldpayHelper->getCreditCardSpecificexception('GCCAM5'))
            );
        } elseif ($this->getIsCardValidationRequired($data)
                  && ($this->dateTime->gmtDate('Y') > $data[self::PATH_ADDITIONAL_DATA]['cc_exp_year'])) {
            throw new GraphQlInputException(__(
                $this->worldpayHelper->getCreditCardSpecificexception('GCCAM6')
            ));
        }
        if ($this->getIsCardValidationRequired($data)
            && $this->dateTime->gmtDate('Y') == $data[self::PATH_ADDITIONAL_DATA]['cc_exp_year']
            && $this->dateTime->gmtDate('m') > $data[self::PATH_ADDITIONAL_DATA]['cc_exp_month']) {
            throw new GraphQlInputException(__(
                $this->worldpayHelper->getCreditCardSpecificexception('GCCAM7')
            ));
        }
        if ($this->getIsCardValidationRequired($data)
            && isset($data[self::PATH_ADDITIONAL_DATA]['cvc'])
            && empty($data[self::PATH_ADDITIONAL_DATA]['cvc'])) {
            throw new GraphQlInputException(
                __($this->worldpayHelper->getCreditCardSpecificexception('GCCAM8'))
            );
        }
        
        if ($this->getIsCardValidationRequired($data)
            && $data[self::PATH_ADDITIONAL_DATA]['save_card']=='') {
            throw new GraphQlInputException(
                __($this->worldpayHelper->getCreditCardSpecificexception('GCCAM9'))
            );
        }
        
        if (!isset($data[self::PATH_ADDITIONAL_DATA]['cvcHref'])) {
            throw new GraphQlInputException(
                __($this->worldpayHelper->getCreditCardSpecificexception('GCCAM8'))
            );
        }
        
        if (!isset($data[self::PATH_ADDITIONAL_DATA]['sessionHref'])) {
            throw new GraphQlInputException(
                __($this->worldpayHelper->getCreditCardSpecificexception('GCCAM8'))
            );
        }

        if ((isset($data[self::PATH_ADDITIONAL_DATA]['tokenId'])
             && !empty($data[self::PATH_ADDITIONAL_DATA]['tokenId']))) {
            $data[self::PATH_ADDITIONAL_DATA]['customer_id'] = $this->userContext->getUserId();
        }

        $data[self::PATH_ADDITIONAL_DATA]['is_graphql'] = 1;
        $additionalData = $this->arrayManager->get(static::PATH_ADDITIONAL_DATA, $data);
        
        return $additionalData;
    }
    /**
     * Get Card Validation Required
     *
     * @param array $data
     * @return bool
     */
    public function getIsCardValidationRequired($data)
    {
        if (isset($data[self::PATH_ADDITIONAL_DATA]['tokenId'])
            && !empty($data[self::PATH_ADDITIONAL_DATA]['tokenId'])) {
            return false;
        }
        if (isset($data[self::PATH_ADDITIONAL_DATA]['tokenUrl'])
            && !empty($data[self::PATH_ADDITIONAL_DATA]['tokenUrl'])) {
            return false;
        }
        return true;
    }
    /**
     * Data Validation Required
     *
     * @param array $data
     * @return bool
     */
    public function requiredDataValidation($data)
    {
        if (!isset($data[self::PATH_ADDITIONAL_DATA]['cvcHref'])
            || (isset($data[self::PATH_ADDITIONAL_DATA]['cvcHref'])
                && empty($data[self::PATH_ADDITIONAL_DATA]['cvcHref']))) {
            return false;
        }
        return true;
    }
}
