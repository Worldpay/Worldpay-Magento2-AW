<?php

namespace Sapient\AccessWorldpay\Model;

use Magento\QuoteGraphQl\Model\Cart\Payment\AdditionalDataProviderInterface;
use Magento\Framework\Stdlib\ArrayManager;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;

class WorldpayAPMDataProvider implements AdditionalDataProviderInterface
{
    public const PATH_ADDITIONAL_DATA = 'worldpay_cc';
    //private const WALLET_PATH_ADDITIONAL_DATA = 'worldpay_wallets';

    /**
     * @var $arrayManager
     */
    private $arrayManager;

    /**
     * Constructor
     *
     * @param ArrayManager                                        $arrayManager
     * @param \Magento\Authorization\Model\CompositeUserContext   $userContext
     * @param \Magento\Framework\Stdlib\DateTime\DateTime         $dateTime
     * @param \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger
     * @param \Sapient\AccessWorldpay\Helper\Data                 $helper
     */
    public function __construct(
        ArrayManager $arrayManager,
        \Magento\Authorization\Model\CompositeUserContext $userContext,
        \Magento\Framework\Stdlib\DateTime\DateTime $dateTime,
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger,
        \Sapient\AccessWorldpay\Helper\Data $helper
    ) {
        $this->arrayManager = $arrayManager;
        $this->userContext = $userContext;
        $this->dateTime = $dateTime;
        $this->worldpayHelper = $helper;
        $this->_wplogger = $wplogger;
    }

    /**
     * Get data
     *
     * @param  array  $data
     * @return true|flase
     */
    public function getData(array $data): array
    {
        if (!isset($data[self::PATH_ADDITIONAL_DATA]['ach_account']) ||
                 empty($data[self::PATH_ADDITIONAL_DATA]['ach_account'])) {
            throw new GraphQlInputException(
                __('Account Type is mandatory field.')
            );
        } else {
            $accounttype= strtoupper($data[self::PATH_ADDITIONAL_DATA]['ach_account']);
            $accounttypeAccepted = explode(",", (strtoupper($this->worldpayHelper->getACHBankAccountTypes())));
            if (!in_array($accounttype, $accounttypeAccepted)) {
                throw new GraphQlInputException(
                    __('Invalid Account Type.')
                );
            }
            $data[self::PATH_ADDITIONAL_DATA]['cc_type'] = "ACH_DIRECT_DEBIT-SSL";
        }

        if (!isset($data[self::PATH_ADDITIONAL_DATA]['ach_accountNumber']) ||
                 empty($data[self::PATH_ADDITIONAL_DATA]['ach_accountNumber'])) {
            throw new GraphQlInputException(
                __('Account Number is mandatory field.')
            );
        } else {
            $this->accountNumberValidation($data);
        }

        if (!isset($data[self::PATH_ADDITIONAL_DATA]['ach_routingNumber']) ||
                 empty($data[self::PATH_ADDITIONAL_DATA]['ach_routingNumber'])) {
            throw new GraphQlInputException(
                __('Routing Number is mandatory field.')
            );
        } else {
            $this->routingNumberValidation($data);
        }

        if (isset($data[self::PATH_ADDITIONAL_DATA]['ach_checknumber'])
                && !empty($data[self::PATH_ADDITIONAL_DATA]['ach_checknumber'])) {
            $this->checkNumberValidation($data);
        }

        if ($this->isSelectedAccountTypeCorp($data) &&
                ( !isset($data[self::PATH_ADDITIONAL_DATA]['ach_companyname'])
                || empty($data[self::PATH_ADDITIONAL_DATA]['ach_companyname']))) {
            throw new GraphQlInputException(
                __('Company Name is mandatory field.')
            );
        } elseif ($this->isSelectedAccountTypeCorp($data)) {
            $this->companyLengthValidation($data);
        }

        $data[self::PATH_ADDITIONAL_DATA]['statementNarrative'] = $this->statementNarrativeChecks($data);
        /* Variable to identify graphQL call*/

        $data[self::PATH_ADDITIONAL_DATA]['is_graphql'] = 1;

        $additionalData = $this->arrayManager->get(static::PATH_ADDITIONAL_DATA, $data);
        //print_r($additionalData);
        return $additionalData;
    }

    /**
     * Check accoutn SelectedAccountTypeCorp
     *
     * @param array $data
     * @return boolean
     */
    private function isSelectedAccountTypeCorp($data)
    {
        $accounttype= $data[self::PATH_ADDITIONAL_DATA]['ach_account'];
        if ($accounttype == "Corporate" || $accounttype == "Corp Savings") {
            return true;
        }
        return false;
    }

    /**
     * Check accountnNumber validation
     *
     * @param array $data
     * @throws Exception
     */
    private function accountNumberValidation($data)
    {
        $accountNumber = $data[self::PATH_ADDITIONAL_DATA]['ach_accountNumber'];
        if (strlen($accountNumber)>17) {
            throw new GraphQlInputException(
                __("Field: Account Number, Error: ".$this->worldpayHelper->getCreditCardSpecificexception('CACH01'))
            );
        } elseif (preg_match('#[^0-9]#', $accountNumber)) {
            throw new GraphQlInputException(
                __('Field: Account Number, Error: Invalid number')
            );
        }
    }

    /**
     * Routing number validation
     *
     * @param array $data
     * @throws Exception
     */
    private function routingNumberValidation($data)
    {
        $routingNumber = $data[self::PATH_ADDITIONAL_DATA]['ach_routingNumber'];
        if (strlen($routingNumber)<8 || strlen($routingNumber)>9) {
            throw new GraphQlInputException(
                __("Field: Routing Number, Error: ".$this->worldpayHelper->getCreditCardSpecificexception('CACH02'))
            );
        } elseif (preg_match('#[^0-9]#', $routingNumber)) {
            throw new GraphQlInputException(
                __('Field: Routing Number, Error: Invalid number')
            );
        }
    }

    /**
     * Check number validation
     *
     * @param array $data
     * @throws Exception
     */
    private function checkNumberValidation($data)
    {
        $checkNumber = $data[self::PATH_ADDITIONAL_DATA]['ach_checknumber'];
        if (strlen($checkNumber)>15) {
            throw new GraphQlInputException(
                __("Field: Check Number, Error: ".$this->worldpayHelper->getCreditCardSpecificexception('CACH03'))
            );
        } elseif (preg_match('#[^0-9]#', $checkNumber)) {
            throw new GraphQlInputException(
                __('Field: Check Number, Error: Invalid number')
            );
        }
    }

    /**
     * Check company length validation
     *
     * @param array $data
     * @throws Exception
     */
    private function companyLengthValidation($data)
    {
        $companyName = $data[self::PATH_ADDITIONAL_DATA]['ach_companyname'];
        if (strlen($companyName)>40) {
            throw new GraphQlInputException(
                __("Field: Company Name, Error: ".$this->worldpayHelper->getCreditCardSpecificexception('CACH04'))
            );
        }
    }

    /**
     * Check statement narrative checks
     *
     * @param array $data
     * @throws Exception
     */
    private function statementNarrativeChecks($data)
    {
        $narrative = $data[self::PATH_ADDITIONAL_DATA]['statementNarrative'];

        if (!isset($narrative) || empty($narrative) || strlen(trim($narrative)) == 0) {
            return $this->worldpayHelper->getNarrative();
        } else {
            return $narrative;
        }
    }
}
