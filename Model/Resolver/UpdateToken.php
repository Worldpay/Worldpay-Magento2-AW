<?php
declare(strict_types=1);

namespace Sapient\AccessWorldpay\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\ContextInterface;
use Magento\Vault\Api\PaymentTokenManagementInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Sapient\AccessWorldpay\Model\Token\Service;
use Sapient\AccessWorldpay\Model\SavedTokenFactory;
use Sapient\AccessWorldpay\Model\Token\WorldpayToken;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Sapient\AccessWorldpay\Helper\Data;

class UpdateToken implements ResolverInterface
{
    /**
     * Constructor
     *
     * @param PaymentTokenManagementInterface $paymentTokenManagement
     * @param PaymentTokenRepositoryInterface $paymentTokenRepository
     * @param Service $tokenservice
     * @param SavedTokenFactory $savedtoken
     * @param WorldpayToken $worldpayToken
     * @param \Magento\Framework\Stdlib\DateTime\DateTime $dateTime
     * @param Data $worldpayHelper
     */
    public function __construct(
        PaymentTokenManagementInterface $paymentTokenManagement,
        PaymentTokenRepositoryInterface $paymentTokenRepository,
        Service $tokenservice,
        SavedTokenFactory $savedtoken,
        WorldpayToken $worldpayToken,
        \Magento\Framework\Stdlib\DateTime\DateTime $dateTime,
        Data $worldpayHelper
    ) {
        $this->paymentTokenManagement = $paymentTokenManagement;
        $this->paymentTokenRepository = $paymentTokenRepository;
        $this->tokenservice = $tokenservice;
        $this->savedtoken = $savedtoken;
        $this->worldpayToken = $worldpayToken;
        $this->dateTime = $dateTime;
        $this->worldpayHelper = $worldpayHelper;
    }
    /**
     * Resolver
     *
     * @param Field $field
     * @param Context $context
     * @param ResolveInfo $info
     * @param array $value
     * @param array $args
     * @return array
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ) {
        /** @var ContextInterface $context */
        if (false === $context->getExtensionAttributes()->getIsCustomer()) {
            throw new GraphQlAuthorizationException(__(
                $this->worldpayHelper->getMyAccountSpecificexception('GMCAM7')
            ));
        }
        
        if (!isset($args['id'])) {
            throw new GraphQlInputException(__(
                $this->worldpayHelper->getMyAccountSpecificexception('GMCAM8')
            ));
        }
        
        if (!isset($args['input']['tokenid'])) {
            throw new GraphQlInputException(__(
                $this->worldpayHelper->getMyAccountSpecificexception('GMCAM9')
            ));
        }
        
        if (!isset($args['input']['cardholdername'])) {
            throw new GraphQlInputException(__(
                $this->worldpayHelper->getMyAccountSpecificexception('GMCAM10')
            ));
        }
        
        if (!isset($args['input']['cardexpirymonth'])) {
            throw new GraphQlInputException(__(
                $this->worldpayHelper->getMyAccountSpecificexception('GMCAM11')
            ));
        } elseif ($args['input']['cardexpirymonth']==0
                  || $args['input']['cardexpirymonth']>12) {
            throw new GraphQlInputException(__(
                $this->worldpayHelper->getMyAccountSpecificexception('GMCAM12')
            ));
        }
        
        if (!isset($args['input']['cardexpiryyear'])) {
            throw new GraphQlInputException(__(
                $this->worldpayHelper->getMyAccountSpecificexception('GMCAM13')
            ));
        } elseif ($this->dateTime->gmtDate('Y') > $args['input']['cardexpiryyear']) {
            throw new GraphQlInputException(__(
                $this->worldpayHelper->getMyAccountSpecificexception('GMCAM14')
            ));
        }
        if ($this->dateTime->gmtDate('Y') == $args['input']['cardexpiryyear']
            && $this->dateTime->gmtDate('m')>$args['input']['cardexpirymonth']) {
            throw new GraphQlInputException(__(
                $this->worldpayHelper->getMyAccountSpecificexception('GMCAM15')
            ));
        }
        
        $id = $args['id'];
        $tokenid = $args['input']['tokenid'];
        $tokenUpdateData =[
            'cardholdername' => $args['input']['cardholdername'],
            'cardexpirymonth' => $args['input']['cardexpirymonth'],
            'cardexpiryyear' => $args['input']['cardexpiryyear']
        ];
        $customerId = $context->getUserId();
        $model = $this->savedtoken->create();
        $model->load($id);
        $card = [];
        if ($customerId == $model->getCustomerId()) {
            if ($model->getTokenId() == $tokenid) {
                $card = $this->updateToken($model, $customerId, $tokenUpdateData);
            } else {
                throw new GraphQlInputException(
                    __($this->worldpayHelper->getMyAccountSpecificexception('GMCAM16'))
                );
            }
            
        } else {
             throw new GraphQlInputException(
                 __($this->worldpayHelper->getMyAccountSpecificexception('GMCAM17'))
             );
        }
        
        return ['card' => $card];
    }
    /**
     * Update Token
     *
     * @param SavedToken $model
     * @param int $customerId
     * @param WorldPayToken $tokenUpdateData
     * @return $this
     */
    protected function updateToken($model, $customerId, $tokenUpdateData)
    {
        $card =[];
        $token = $this->_getTokenModel($model, $tokenUpdateData);
        $tokenInquiryResponse = $this->tokenservice->getTokenInquiry($token);
        if (isset($tokenInquiryResponse['errorName'])) {
            throw new GraphQlInputException(__($tokenInquiryResponse['message']));
        }
        $cardHolderNameUrl = $tokenInquiryResponse['_links']['tokens:cardHolderName'];
        $cardExpiryDateUrl = $tokenInquiryResponse['_links']['tokens:cardExpiryDate'];
        
        $tokennameresponse = $this->tokenservice->putTokenName(
            $token,
            $cardHolderNameUrl['href']
        );
        
        $tokenexpirydateresponse = $this->tokenservice->putTokenExpiry(
            $token,
            $cardExpiryDateUrl['href']
        );
        if ($tokennameresponse == 204 && $tokenexpirydateresponse == 204) {
            $token->save();
            $this->_applyVaultTokenUpdate($token, $customerId);
            $card = ["id" =>$token->getId(),
            "tokenid" => $token->getTokenId(),
            "cardnumber" => $token->getCardNumber(),
            "cardholdername" => $token->getCardholderName(),
            "cardexpirymonth" => $token->getCardExpiryMonth(),
            "cardexpiryyear" => $token->getCardExpiryYear(),
            "method" => $token->getMethod()
            ];
            return $card;
        } else {
            /* Exception Handling */
            throw new GraphQlInputException(__($tokenexpirydateresponse['message']));
        }
    }
    /**
     * Get Token Model
     *
     * @param SavedToken $model
     * @param WorldPayToken $tokenUpdateData
     * @return $this
     */
    protected function _getTokenModel($model, $tokenUpdateData)
    {
        if (trim(strtoupper($model->getCardholderName()))
                !== trim(strtoupper($tokenUpdateData['cardholdername']))) {
            $model->setCardholderName(trim($tokenUpdateData['cardholdername']));
        }
        $model->setCardExpiryMonth($tokenUpdateData['cardexpirymonth']);
        $model->setCardExpiryYear($tokenUpdateData['cardexpiryyear']);
       
        return $model;
    }
    /**
     * Apply Vault Token Update
     *
     * @param SavedToken $model
     * @param int $customerid
     * @return $this
     */
    protected function _applyVaultTokenUpdate($model, $customerid)
    {
        $existingVaultPaymentToken = $this->paymentTokenManagement->getByGatewayToken(
            $model->getTokenId(),
            'worldpay_cc',
            $customerid
        );
        $this->_saveVaultToken($existingVaultPaymentToken, $model);
    }
    /**
     * Save Vault Token
     *
     * @param PaymentTokenInterface $vaultToken
     * @param SavedToken $model
     * @return $this
     */
    protected function _saveVaultToken(PaymentTokenInterface $vaultToken, $model)
    {
        $vaultToken->setTokenDetails($this->convertDetailsToJSON([
            'type' => $model->getMethod(),
            'maskedCC' => $this->getLastFourNumbers($model->getCardNumber()),
            'expirationDate'=> $this->getExpirationMonthAndYear($model)
        ]));
        try {
            $this->paymentTokenRepository->save($vaultToken);
        } catch (Exception $e) {
            throw new GraphQlInputException(__($e->getMessage()), $e);
        }
    }
    /**
     * Get Expiration Month And Year
     *
     * @param SavedToken $token
     * @return string
     */
    public function getExpirationMonthAndYear($token)
    {
        return $token->getCardExpiryMonth().'/'.$token->getCardExpiryYear();
    }
    /**
     * Get Last Four Numbers
     *
     * @param SavedToken $number
     * @return int
     */
    public function getLastFourNumbers($number)
    {
        return substr($number, -4);
    }
    /**
     * Convert Details to JSON
     *
     * @param array $details
     * @return json
     */
    private function convertDetailsToJSON($details)
    {
        $json = \Zend_Json::encode($details);
        return $json ? $json : '{}';
    }
}
