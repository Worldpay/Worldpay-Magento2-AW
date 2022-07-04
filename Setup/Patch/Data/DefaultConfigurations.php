<?php
/**
 * Copyright © Sapient, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Sapient\AccessWorldpay\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Serialize\SerializerInterface;

class DefaultConfigurations implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface $moduleDataSetup
     */
    private $moduleDataSetup;

    /**
     * @var \Magento\Config\Model\Config\Factory $configFactory
     */
    protected $configFactory;
    /**
     * @var SerializerInterface $serializer
     */
    protected $serializer;

    /**
     * DefaultConfigurations constructor
     *
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param \Magento\Config\Model\Config\Factory $configFactory
     * @param SerializerInterface $serializer
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        \Magento\Config\Model\Config\Factory $configFactory,
        SerializerInterface $serializer
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->configFactory = $configFactory;
        $this->serializer = $serializer;
    }
    /**
     * Do Upgrade
     *
     * @return void
     */
    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        // Default Labels and configurations
        $this->saveAccountMessages();
        $this->saveAdminMessages();
        $this->saveCheckoutMessages();
        $this->saveCheckoutLabels();
        $this->saveAccountLabels();
        $this->moduleDataSetup->getConnection()->endSetup();
    }
    /**
     * Convert array to string
     *
     * @param array $exceptionValues
     */
    public function convertArrayToString($exceptionValues)
    {
        $resultArray = [];
        foreach ($exceptionValues as $row) {
             $payment_type = $row['exception_code'];
            $rs['exception_messages'] = $row['exception_messages'];
            $rs['exception_module_messages'] = $row['exception_module_messages'];
            $resultArray[$payment_type] = $rs;
        }
        return $this->serializer->serialize($resultArray);
    }
    /**
     * Convert array string to labels
     *
     * @param array $exceptionValues
     */
    public function convertArrayToStringForLabels($exceptionValues)
    {
        $resultArray = [];
        foreach ($exceptionValues as $row) {
            $payment_type = $row['wpay_label_code'];
            $rs['wpay_label_desc'] = $row['wpay_label_desc'];
            $rs['wpay_custom_label'] = $row['wpay_custom_label'];
            $resultArray[$payment_type] = $rs;
        }
         return $this->serializer->serialize($resultArray);
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [];
    }
    /**
     *  Save Account level Messages
     */
    public function saveAccountMessages()
    {
        $index = time();
        $exceptionValues = [$index . '_0' => ["exception_code" => "MCAM0",
                    "exception_messages" => "Please verify the Billing Address"
                    . " in your Address Book before adding new card!",
                    "exception_module_messages" => ""],
                $index . '_1' => ["exception_code" => "MCAM1",
                    "exception_messages" =>
                    "Token doesnot exist anymore, Please delete the card.",
                    "exception_module_messages" => ""],
                $index . '_2' => ["exception_code" => "MCAM2",
                    "exception_messages" => "The card has been updated.",
                    "exception_module_messages" => ""],
                $index . '_3' => ["exception_code" => "MCAM3",
                    "exception_messages" =>
                    "Duplicate Entry, This card number is already saved",
                    "exception_module_messages" => ""],
                $index . '_4' => ["exception_code" => "MCAM4",
                    "exception_messages" =>
                    "Entered card number does not match " .
                    "with the selected card type",
                    "exception_module_messages" => ""],
                $index . '_5' => ["exception_code" => "MCAM5",
                    "exception_messages" =>
                    "Please, Enter 3 digit valid Card Verification Number",
                    "exception_module_messages" => ""],
                $index . '_6' => ["exception_code" => "MCAM6",
                    "exception_messages" => "Item is deleted successfully",
                    "exception_module_messages" => ""],
                $index . '_7' => ["exception_code" => "MCAM9",
                    "exception_messages" =>
                    "There appears to be an issue with your stored data," .
                    " please review in your account and update details as applicable.",
                    "exception_module_messages" => ""],
                $index . '_8' => ["exception_code" => "MCAM10",
                    "exception_messages" => "Please select the card type",
                    "exception_module_messages" => ""],
                $index . '_9' => ["exception_code" => "MCAM11",
                    "exception_messages" =>
                    "You already appear to have this card number stored,"
                    . " we have updated your saved card details with " .
                    "the new data, you can verify this from my " .
                    "saved cards section in my account dashboard",
                    "exception_module_messages" => ""],
                $index . '_10' => ["exception_code" => "MCAM12",
                    "exception_messages" => "Please, "
                    . "Enter 4 digit valid Card Verification Number",
                    "exception_module_messages" => ""],
                $index . '_11' => ["exception_code" => "MCAM13",
                    "exception_messages" =>
                    "You already appear to have this card number stored, " .
                    "if your card details have changed, " .
                    "you can update these via the my cards section.",
                    "exception_module_messages" => ""],
                $index . '_12' => ["exception_code" => "MCAM14",
                    "exception_messages" => "Your card could not be saved",
                    "exception_module_messages" => ""],
                $index . '_13' => ["exception_code" => "GMCAM0",
                    "exception_messages" =>
                    "The current customer is not authorized.",
                    "exception_module_messages" => ""],
                $index . '_14' => ["exception_code" => "GMCAM1",
                    "exception_messages" =>
                    "id is missing from request. Please specify the id.",
                    "exception_module_messages" => ""],
                $index . '_15' => ["exception_code" => "GMCAM2",
                    "exception_messages" =>
                    "tokenid is missing from request. " .
                    "Please specify the tokenid.",
                    "exception_module_messages" => ""],
                $index . '_16' => ["exception_code" => "GMCAM3",
                    "exception_messages" =>
                    "tokenid supplied does not exist. " .
                    "Please verify the tokenid.",
                    "exception_module_messages" => ""],
                $index . '_17' => ["exception_code" => "GMCAM4",
                    "exception_messages" =>
                    "Token data supplied does not exist for current customer",
                    "exception_module_messages" => ""],
                $index . '_18' => ["exception_code" => "GMCAM5",
                    "exception_messages" =>
                    "Please try after some time",
                    "exception_module_messages" => ""],
                $index . '_19' => ["exception_code" => "GMCAM6",
                    "exception_messages" =>
                    "The current customer is not authorized.",
                    "exception_module_messages" => ""],
                $index . '_20' => ["exception_code" => "GMCAM7",
                    "exception_messages" =>
                    "The current customer is not authorized.",
                    "exception_module_messages" => ""],
                $index . '_21' => ["exception_code" => "GMCAM8",
                    "exception_messages" =>
                    "id is missing from request. Please specify the id.",
                    "exception_module_messages" => ""],
                $index . '_22' => ["exception_code" => "GMCAM9",
                    "exception_messages" =>
                    "tokenid is missing from request. " .
                    "Please specify the tokenid",
                    "exception_module_messages" => ""],
                $index . '_23' => ["exception_code" => "GMCAM10",
                    "exception_messages" =>
                    "cardholdername is missing from request. " .
                    "Please specify the cardholdername",
                    "exception_module_messages" => ""],
                $index . '_24' => ["exception_code" => "GMCAM11",
                    "exception_messages" =>
                    "cardexpirymonth is missing from request. " .
                    "Please specify the cardexpirymonth",
                    "exception_module_messages" => ""],
                $index . '_25' => ["exception_code" => "GMCAM12",
                    "exception_messages" => "invalid expiry month",
                    "exception_module_messages" => ""],
                $index . '_26' => ["exception_code" => "GMCAM13",
                    "exception_messages" =>
                    "cardexpiryyear is missing from request. " .
                    "Please specify the cardexpiryyear",
                    "exception_module_messages" => ""],
                $index . '_27' => ["exception_code" => "GMCAM14",
                    "exception_messages" => "invalid expiry year",
                    "exception_module_messages" => ""],
                $index . '_28' => ["exception_code" => "GMCAM15",
                    "exception_messages" => "invalid expiry date",
                    "exception_module_messages" => ""],
                $index . '_29' => ["exception_code" => "GMCAM16",
                    "exception_messages" =>
                    "tokenid supplied does not exist. Please verify the tokenid",
                    "exception_module_messages" => ""],
                $index . '_30' => ["exception_code" => "GMCAM17",
                    "exception_messages" =>
                    "Token data supplied does not exist for current customer",
                    "exception_module_messages" => ""]
            ];
            
            $exceptionCodes = $this->convertArrayToString($exceptionValues);
            $configData = [
                'section' => 'worldpay_exceptions',
                'website' => null,
                'store'   => null,
                'groups'  => [
                    'my_account_alert_codes' => [
                        'fields' => [
                            'response_codes' => [
                                'value' => $exceptionCodes
                            ],
                        ],
                    ],
                ],
            ];
         /** @var \Magento\Config\Model\Config $configModel */
            $configModel = $this->configFactory->create(['data' => $configData]);
            $configModel->save();
    }

    /**
     *  Save Account level Messages
     */
    public function saveAdminMessages()
    {
        $index = time();
        $exceptionValues = [$index . '_0' => ["exception_code" => "ACAM12",
                    "exception_messages" =>
                    "Error Code %s already exist!",
                    "exception_module_messages" => ""],
                $index . '_1' => ["exception_code" => "ACAM13",
                    "exception_messages" =>
                    "Detected only whitespace character for code",
                    "exception_module_messages" => ""],
                $index . '_2' => ["exception_code" => "ACAM3",
                    "exception_messages" => "Payment synchronized successfully!!",
                    "exception_module_messages" => ""],
                $index . '_3' => ["exception_code" => "ACAM4",
                    "exception_messages" => "Synchronising Payment Status failed",
                    "exception_module_messages" => ""],
                    $index . '_4' => ["exception_code" => "AACH01",
                    "exception_messages" => "Payment Reversal Executed. Please run Sync Status after sometime",
                    "exception_module_messages" => ""],
                $index . '_5' => ["exception_code" => "AACH02",
                    "exception_messages" => "Payment reversal already executed and is in process.",
                    "exception_module_messages" => ""],
                $index . '_6' => ["exception_code" => "AACH03",
                    "exception_messages" => "Refund/Partial Refunds not supported for this order."
                    . "Please reach out to Worldpay to intiate from acquirer level",
                    "exception_module_messages" => ""],
                $index . '_7' => ["exception_code" => "AACH04",
                    "exception_messages" => "Partial Reversal not supported.",
                    "exception_module_messages" => ""]
            ];
            $exceptionCodes = $this->convertArrayToString($exceptionValues);
            $configData = [
                'section' => 'worldpay_exceptions',
                'website' => null,
                'store'   => null,
                'groups'  => [
                    'adminexceptions' => [
                        'fields' => [
                            'general_exception' => [
                                'value' => $exceptionCodes
                            ],
                        ],
                    ],
                ],
            ];
        /** @var \Magento\Config\Model\Config $configModel */
            $configModel = $this->configFactory->create(['data' => $configData]);
            $configModel->save();
    }

    /**
     * Save Checkout Messages
     */
    public function saveCheckoutMessages()
    {
        $index = time();
        $exceptionValues = [$index . '_0' => ["exception_code" => "CCAM0",
                    "exception_messages" =>
                    "Card number should contain between 12" .
                    " and 20 numeric characters",
                    "exception_module_messages" => ""],
                $index . '_1' => ["exception_code" => "CCAM1",
                    "exception_messages" =>
                    "Please, Verify the disclaimer!" .
                    " before saving the card",
                    "exception_module_messages" => ""],
                $index . '_2' => ["exception_code" => "CCAM2",
                    "exception_messages" =>
                    "The card number entered is invalid",
                    "exception_module_messages" => ""],
                $index . '_3' => ["exception_code" => "CCAM3",
                    "exception_messages" =>
                    "Card brand is not supported.",
                    "exception_module_messages" => ""],
                $index . '_4' => ["exception_code" => "CCAM4",
                    "exception_messages" =>
                    "Please, enter valid Card Verification Number",
                    "exception_module_messages" => ""],
                $index . '_5' => ["exception_code" => "CCAM5",
                    "exception_messages" =>
                    "Error: Please verify your data",
                    "exception_module_messages" => ""],
                $index . '_6' => ["exception_code" => "CCAM6",
                    "exception_messages" =>
                    "Session Expired. Please try again.",
                    "exception_module_messages" => ""],
                $index . '_7' => ["exception_code" => "CCAM7",
                    "exception_messages" =>
                    "Something went wrong while processing your order." .
                    " Please try again later.",
                    "exception_module_messages" => ""],
                $index . '_8' => ["exception_code" => "CCAM8",
                    "exception_messages" => "Please try after some time",
                    "exception_module_messages" => ""],
                $index . '_9' => ["exception_code" => "CCAM9",
                    "exception_messages" =>
                    "Error: Token does not exist." .
                    "Please delete it from My Account",
                    "exception_module_messages" => ""],
                $index . '_10' => ["exception_code" => "CCAM10",
                    "exception_messages" =>
                    "Unfortunately the order could not be processed." .
                    "Please contact us or try again later",
                    "exception_module_messages" => ""],
                $index . '_11' => ["exception_code" => "CCAM11",
                    "exception_messages" =>
                    "Order %s has been declined, " .
                    "please check your details and try again",
                    "exception_module_messages" => ""],
                $index . '_12' => ["exception_code" => "CCAM12",
                    "exception_messages" =>
                    "An unexpected error occurred, " .
                    "Please try to place the order again.",
                    "exception_module_messages" => ""],
                $index . '_13' => ["exception_code" => "CCAM13",
                    "exception_messages" =>
                    "An error occurred on the server." .
                    " Please try to place the order again.",
                    "exception_module_messages" => ""],
                $index . '_14' => ["exception_code" => "CCAM14",
                    "exception_messages" =>
                    "Your token is no longer scheme compliant. " .
                    "Please delete this card on my account" .
                    " and save new card.",
                    "exception_module_messages" => ""],
                $index . '_15' => ["exception_code" => "CCAM15",
                    "exception_messages" =>
                    "Invalid Payment Type. Please Refresh and check again",
                    "exception_module_messages" => ""],
                $index . '_16' => ["exception_code" => "CCAM16",
                    "exception_messages" =>
                    "Invalid Configuration. Please Refresh and check again",
                    "exception_module_messages" => ""],
                $index . '_17' => ["exception_code" => "CCAM17",
                    "exception_messages" =>
                    "No matching order found in WorldPay to refund." .
                    " Please visit your WorldPay merchant"
                    . "interface and refund the order manually.",
                    "exception_module_messages" => ""],
                $index . '_18' => ["exception_code" => "CCAM18",
                    "exception_messages" =>
                    "Something happened, please refresh" .
                    " the page and try again.",
                    "exception_module_messages" => ""],
                $index . '_19' => ["exception_code" => "CCAM19",
                    "exception_messages" => "Authentication Failed",
                    "exception_module_messages" => ""],
                $index . '_20' => ["exception_code" => "CCAM20",
                    "exception_messages" =>
                    "Invalid Payment Details." .
                    " Please Refresh and check again",
                    "exception_module_messages" => ""],
                $index . '_21' => ["exception_code" => "CCAM21",
                    "exception_messages" =>
                    "Maximum number of updates for this token exceeded",
                    "exception_module_messages" => ""],
                $index . '_22' => ["exception_code" => "CCAM22",
                    "exception_messages" =>
                    "Please select one of the options",
                    "exception_module_messages" => ""],
                $index . '_23' => ["exception_code" => "CCAM23",
                    "exception_messages" =>
                    "Error: Please, Enter CVV",
                    "exception_module_messages" => ""],
                $index . '_24' => ["exception_code" => "GCCAM0",
                    "exception_messages" =>
                    "Required parameter \"worldpay_cc\" for" .
                    " \"payment_method\" is missing.",
                    "exception_module_messages" => ""],
                $index . '_25' => ["exception_code" => "GCCAM1",
                    "exception_messages" =>
                    "Required parameter \"cc_name\" for" .
                    " \"worldpay_cc\" is missing.",
                    "exception_module_messages" => ""],
                $index . '_26' => ["exception_code" => "GCCAM2",
                    "exception_messages" =>
                    "Required parameter \"cc_number\" for" .
                    " \"worldpay_cc\" is missing.",
                    "exception_module_messages" => ""],
                $index . '_27' => ["exception_code" => "GCCAM3",
                    "exception_messages" =>
                    "Required parameter \"cc_exp_month\" for" .
                    " \"worldpay_cc\" is missing.",
                    "exception_module_messages" => ""],
                $index . '_28' => ["exception_code" => "GCCAM4",
                    "exception_messages" => "invalid expiry month",
                    "exception_module_messages" => ""],
                $index . '_29' => ["exception_code" => "GCCAM5",
                    "exception_messages" =>
                    "Required parameter \"cc_exp_year\" for" .
                    " \"worldpay_cc\" is missing.",
                    "exception_module_messages" => ""],
                $index . '_30' => ["exception_code" => "GCCAM6",
                    "exception_messages" => "invalid expiry year",
                    "exception_module_messages" => ""],
                $index . '_31' => ["exception_code" => "GCCAM7",
                    "exception_messages" => "invalid expiry date",
                    "exception_module_messages" => ""],
                $index . '_32' => ["exception_code" => "GCCAM8",
                    "exception_messages" =>
                    "Required parameter \"cvc\" for" .
                    " \"worldpay_cc\" is missing.",
                    "exception_module_messages" => ""],
                $index . '_33' => ["exception_code" => "GCCAM9",
                    "exception_messages" =>
                    "Required parameter \"save_card\" for" .
                    " \"worldpay_cc\" is missing.",
                    "exception_module_messages" => ""],
                $index . '_34' => ["exception_code" => "GCCAM10",
                    "exception_messages" =>
                    "Invalid Data passed for AccessCheckout(WebSDK) integration. 
                    Please refer user guide for configuration for WebSDK",
                    "exception_module_messages" => ""]
            ];
            $exceptionCodes = $this->convertArrayToString($exceptionValues);
            $configData = [
                'section' => 'worldpay_exceptions',
                'website' => null,
                'store'   => null,
                'groups'  => [
                    'ccexceptions' => [
                        'fields' => [
                            'cc_exception' => [
                                'value' => $exceptionCodes
                                 ],
                        ],
                    ],
                ],
            ];
        /** @var \Magento\Config\Model\Config $configModel */
            $configModel = $this->configFactory->create(['data' => $configData]);
            $configModel->save();
    }

    /**
     *  Save Checkout labels
     */
    public function saveCheckoutLabels()
    {
        $index = time();
            $labelvalues = [$index.'_0' => ["wpay_label_code" => "CO1",
                                        "wpay_label_desc" => "New Card",
                                        "wpay_custom_label" => ""],
                                    $index.'_1' => ["wpay_label_code" => "CO2",
                                        "wpay_label_desc" => "We Accept",
                                        "wpay_custom_label" => ""],
                                    $index.'_2' => ["wpay_label_code" => "CO3",
                                        "wpay_label_desc" => "Card Number",
                                        "wpay_custom_label" => ""],
                                    $index.'_3' => ["wpay_label_code" => "CO4",
                                        "wpay_label_desc" => "Card Holder Name",
                                        "wpay_custom_label" => ""],
                                    $index.'_4' => ["wpay_label_code" => "CO5",
                                        "wpay_label_desc" => "CVV",
                                        "wpay_custom_label" => ""],
                                    $index.'_5' => ["wpay_label_code" => "CO6",
                                        "wpay_label_desc" => "Month",
                                        "wpay_custom_label" => ""],
                                    $index.'_6' => ["wpay_label_code" => "CO7",
                                        "wpay_label_desc" => "Year",
                                        "wpay_custom_label" => ""],
                                    $index.'_7' => ["wpay_label_code" => "CO8",
                                        "wpay_label_desc" => "Save This Card",
                                        "wpay_custom_label" => ""],
                                    $index.'_8' => ["wpay_label_code" => "CO9",
                                        "wpay_label_desc" => "Important Disclaimer!",
                                        "wpay_custom_label" => ""],
                                    $index.'_9' => ["wpay_label_code" => "CO10",
                                        "wpay_label_desc" => "Pay Now",
                                        "wpay_custom_label" => ""],
                                    $index.'_10' => ["wpay_label_code" => "CO11",
                                        "wpay_label_desc" => "MM/YY",
                                        "wpay_custom_label" => ""],
                                    $index.'_11' => ["wpay_label_code" => "CO12",
                                        "wpay_label_desc" => "Saved cards",
                                        "wpay_custom_label" => ""],
                                    $index.'_12' => ["wpay_label_code" => "CO13",
                                        "wpay_label_desc" => "Use Saved Card",
                                        "wpay_custom_label" => ""],
                                    $index.'_13' => ["wpay_label_code" => "CO14",
                                        "wpay_label_desc" => "Place Order",
                                        "wpay_custom_label" => ""],
                                    $index.'_14' => ["wpay_label_code" => "CO15",
                                        "wpay_label_desc" => "Saved Card feature will be "
                                        . "available only if enabled by Merchant.",
                                        "wpay_custom_label" => ""],
                                    $index.'_15' => ["wpay_label_code" => "CO16",
                                        "wpay_label_desc" => "Card Verification Number",
                                        "wpay_custom_label" => ""],
                                    $index.'_16' => ["wpay_label_code" => "CO17",
                                        "wpay_label_desc" => "Disclaimer!",
                                        "wpay_custom_label" => ""],
                                        $index.'_17' => ["wpay_label_code" => "APM01",
                                        "wpay_label_desc" => "Bank Account Types",
                                        "wpay_custom_label" => ""],
                                    $index.'_18' => ["wpay_label_code" => "APM02",
                                        "wpay_label_desc" => "Account Number",
                                        "wpay_custom_label" => ""],
                                    $index.'_19' => ["wpay_label_code" => "APM03",
                                        "wpay_label_desc" => "Routing Number",
                                        "wpay_custom_label" => ""],
                                    $index.'_20' => ["wpay_label_code" => "APM04",
                                        "wpay_label_desc" => "Check Number",
                                        "wpay_custom_label" => ""],
                                    $index.'_21' => ["wpay_label_code" => "APM05",
                                        "wpay_label_desc" => "Company Name",
                                        "wpay_custom_label" => ""],
                                    $index.'_22' => ["wpay_label_code" => "APM06",
                                        "wpay_label_desc" => "Email Address",
                                        "wpay_custom_label" => ""],
                                    $index.'_23' => ["wpay_label_code" => "APM07",
                                        "wpay_label_desc" => "Purpose Of Transaction",
                                        "wpay_custom_label" => ""]
                                ];
            $labelcodes = $this->convertArrayToStringForLabels($labelvalues);
            $configData = [
                'section' => 'worldpay_custom_labels',
                'website' => null,
                'store'   => null,
                'groups'  => [
                    'checkout_labels' => [
                        'fields' => [
                            'checkout_label' => [
                                'value' => $labelcodes
                            ],
                        ],
                    ],
                ],
            ];
        /** @var \Magento\Config\Model\Config $configModel */
            $configModel = $this->configFactory->create(['data' => $configData]);
            $configModel->save();
    }

    /**
     *  Save Account labels
     */
    public function saveAccountLabels()
    {
        $index = time();
            $labelvalues = [ $index.'_0' => ["wpay_label_code" => "AC1",
                                        "wpay_label_desc" => "Card Holder Name",
                                        "wpay_custom_label" => ""],
                                    $index.'_1' => ["wpay_label_code" => "AC2",
                                        "wpay_label_desc" => "Card Brand",
                                        "wpay_custom_label" => ""],
                                    $index.'_2' => ["wpay_label_code" => "AC3",
                                        "wpay_label_desc" => "Card Number",
                                        "wpay_custom_label" => ""],
                                    $index.'_3' => ["wpay_label_code" => "AC4",
                                        "wpay_label_desc" => "Card Expiry Month",
                                        "wpay_custom_label" => ""],
                                    $index.'_4' => ["wpay_label_code" => "AC5",
                                        "wpay_label_desc" => "Card Expiry Year",
                                        "wpay_custom_label" => ""],
                                    $index.'_5' => ["wpay_label_code" => "AC6",
                                        "wpay_label_desc" => "Update",
                                        "wpay_custom_label" => ""],
                                    $index.'_6' => ["wpay_label_code" => "AC7",
                                        "wpay_label_desc" => "Update Saved Card",
                                        "wpay_custom_label" => ""],
                                    $index.'_7' => ["wpay_label_code" => "AC8",
                                        "wpay_label_desc" => "Card Information",
                                        "wpay_custom_label" => ""],
                                    $index.'_8' => ["wpay_label_code" => "AC9",
                                        "wpay_label_desc" => "Expiry Month/Year",
                                        "wpay_custom_label" => ""],
                                    $index.'_9' => ["wpay_label_code" => "AC10",
                                        "wpay_label_desc" => "Delete",
                                        "wpay_custom_label" => ""],
                                    $index.'_10' => ["wpay_label_code" => "AC11",
                                        "wpay_label_desc" => "Add New Card",
                                        "wpay_custom_label" => ""],
                                    $index.'_11' => ["wpay_label_code" => "AC12",
                                        "wpay_label_desc" => "Credit Card Type",
                                        "wpay_custom_label" => ""],
                                    $index.'_12' => ["wpay_label_code" => "AC13",
                                        "wpay_label_desc" => "Save",
                                        "wpay_custom_label" => ""],
                                    $index.'_13' => ["wpay_label_code" => "AC14",
                                        "wpay_label_desc" => "My Saved Card",
                                        "wpay_custom_label" => ""],
                                    $index.'_14' => ["wpay_label_code" => "AC15",
                                        "wpay_label_desc" => "Important Disclaimer!",
                                        "wpay_custom_label" => ""],
                                    $index.'_15' => ["wpay_label_code" => "AC16",
                                        "wpay_label_desc" => "Disclaimer!",
                                        "wpay_custom_label" => ""],
                                    $index.'_16' => ["wpay_label_code" => "AC17",
                                        "wpay_label_desc" => "CVV",
                                        "wpay_custom_label" => ""],
                                    $index.'_17' => ["wpay_label_code" => "AC18",
                                        "wpay_label_desc" => "Default Billing Address",
                                        "wpay_custom_label" => ""],
                                    $index.'_18' => ["wpay_label_code" => "AC19",
                                        "wpay_label_desc" => "You have no Saved Card.",
                                        "wpay_custom_label" => ""]
                                ];
            $labelcodes = $this->convertArrayToStringForLabels($labelvalues);
            $configData = [
                'section' => 'worldpay_custom_labels',
                'website' => null,
                'store'   => null,
                'groups'  => [
                    'my_account_labels' => [
                        'fields' => [
                            'my_account_label' => [
                                'value' => $labelcodes
                            ],
                        ],
                    ],
                ],
            ];
         /** @var \Magento\Config\Model\Config $configModel */
            $configModel = $this->configFactory->create(['data' => $configData]);
            $configModel->save();
    }
}
