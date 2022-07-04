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
use Sapient\AccessWorldpay\Helper\Data;

class DeleteToken implements ResolverInterface
{
    /**
     * Constructor
     *
     * @param PaymentTokenManagementInterface $paymentTokenManagement
     * @param PaymentTokenRepositoryInterface $paymentTokenRepository
     * @param Service $tokenservice
     * @param SavedTokenFactory $savedtoken
     * @param WorldpayToken $worldpayToken
     * @param Data $worldpayHelper
     */
    public function __construct(
        PaymentTokenManagementInterface $paymentTokenManagement,
        PaymentTokenRepositoryInterface $paymentTokenRepository,
        Service $tokenservice,
        SavedTokenFactory $savedtoken,
        WorldpayToken $worldpayToken,
        Data $worldpayHelper
    ) {
        $this->paymentTokenManagement = $paymentTokenManagement;
        $this->paymentTokenRepository = $paymentTokenRepository;
        $this->tokenservice = $tokenservice;
        $this->savedtoken = $savedtoken;
        $this->worldpayToken = $worldpayToken;
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
            throw new GraphQlAuthorizationException(__($this->worldpayHelper->getMyAccountSpecificexception('GMCAM0')));
        }
        
        if (!isset($args['id'])) {
            throw new GraphQlInputException(__($this->worldpayHelper->getMyAccountSpecificexception('GMCAM1')));
        }
        
        if (!isset($args['tokenid'])) {
            throw new GraphQlInputException(__($this->worldpayHelper->getMyAccountSpecificexception('GMCAM2')));
        }
        
        $id = $args['id'];
        $tokenid = $args['tokenid'];
        $customerId = $context->getUserId();
        $model = $this->savedtoken->create();
        $model->load($id);
        $result = false;
        if ($customerId == $model->getCustomerId()) {
            if ($model->getTokenId() == $tokenid) {
                $result = $this->deleteToken($model, $customerId);
            } else {
                throw new GraphQlInputException(__($this->worldpayHelper->getMyAccountSpecificexception('GMCAM3')));
            }
            
        } else {
             throw new GraphQlInputException(__($this->worldpayHelper->getMyAccountSpecificexception('GMCAM4')));
        }
        
        return ['result' => $result];
    }
    /**
     * Vault Token Delete
     *
     * @param SavedTokenFactory $tokenModel
     * @param int $customerId
     * @return $this
     */
    protected function _applyVaultTokenDelete($tokenModel, $customerId)
    {
        $paymentToken = $this->paymentTokenManagement->getByGatewayToken($tokenModel, 'worldpay_cc', $customerId);
        if ($paymentToken === null) {
            return;
        }
        try {
            $this->paymentTokenRepository->delete($paymentToken);
        } catch (\Exception $e) {
            throw new GraphQlInputException(__($this->worldpayHelper->getMyAccountSpecificexception('GMCAM5')));
        }
    }
    /**
     * Delete Token
     *
     * @param SavedTokenFactory $model
     * @param int $customerId
     * @return bool
     */
    protected function deleteToken($model, $customerId)
    {
        
        $tokendeleteresponse = $this->tokenservice->getTokenDelete($model->getToken());
        if ($tokendeleteresponse == 204) {
            $model->delete();
            $this->_applyVaultTokenDelete($model->getTokenId(), $customerId);
            return true;
        }
        return false;
    }
}
