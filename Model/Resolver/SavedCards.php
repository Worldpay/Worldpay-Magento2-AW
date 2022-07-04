<?php

declare(strict_types=1);

namespace Sapient\AccessWorldpay\Model\Resolver;

use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\ContextInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Sapient\AccessWorldpay\Model\SavedTokenFactory;
use Sapient\AccessWorldpay\Helper\Data;

class SavedCards implements ResolverInterface
{
    /**
     * Constructor
     *
     * @param SavedTokenFactory $tokenfactory
     * @param Data $worldpayHelper
     */
    public function __construct(
        SavedTokenFactory $tokenfactory,
        Data $worldpayHelper
    ) {
        $this->tokenfactory = $tokenfactory;
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
            throw new GraphQlAuthorizationException(__($this->worldpayHelper->getMyAccountSpecificexception('GMCAM6')));
        }
        
        $customerId = $context->getUserId();
        
        //$customer = $this->getCustomer->execute($context);
        $savedCardCollection = $this->tokenfactory->create()->getCollection()
                                    ->addFieldToSelect(['id',
                                        'token_id',
                                        'card_number',
                                        'cardholder_name','card_expiry_month','card_expiry_year',
                                        'method'])
                                    ->addFieldToFilter('customer_id', ['eq' => $customerId]);
        
        $savecard = [];
        foreach ($savedCardCollection as $_savecard) {
            $savecard[] =[
                'id' => $_savecard->getId(),
                'tokenid' => $_savecard->getTokenId(),
                'cardnumber' => $_savecard->getCardNumber(),
                'cardholdername' => $_savecard->getCardholderName(),
                'cardexpirymonth' => $_savecard->getCardExpiryMonth(),
                'cardexpiryyear' => $_savecard->getCardExpiryYear(),
                'method' => $_savecard->getMethod()
            ];
            
        }
        return ['cards' => $savecard];
    }
}
