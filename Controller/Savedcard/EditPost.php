<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Controller\Savedcard;

use Magento\Framework\App\Action\Context;
use \Sapient\AccessWorldpay\Model\SavedTokenFactory;
use \Magento\Customer\Model\Session;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Vault\Model\PaymentTokenManagement;
use Magento\Vault\Api\Data\PaymentTokenInterface;
use Exception;

/**
 * Controller for Updating Saved card
 */
class EditPost extends \Magento\Customer\Controller\AbstractAccount
{
    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * @var Magento\Framework\Data\Form\FormKey\Validator
     */
    protected $formKeyValidator;

    /**
     * Constructor
     *
     * @param Context $context
     * @param SavedTokenFactory $savecard
     * @param Session $customerSession
     * @param Validator $formKeyValidator
     * @param StoreManagerInterface $storeManager
     * @param \Sapient\AccessWorldpay\Model\Token\Service $tokenService
     * @param \Sapient\AccessWorldpay\Model\Token\WorldpayToken $worldpayToken
     * @param \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger
     * @param PaymentTokenRepositoryInterface $tokenRepository
     * @param PaymentTokenManagement $paymentTokenManagement
     * @param \Sapient\AccessWorldpay\Helper\Data $worldpayHelper
     */
    public function __construct(
        Context $context,
        SavedTokenFactory $savecard,
        Session $customerSession,
        Validator $formKeyValidator,
        StoreManagerInterface $storeManager,
        \Sapient\AccessWorldpay\Model\Token\Service $tokenService,
        \Sapient\AccessWorldpay\Model\Token\WorldpayToken $worldpayToken,
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger,
        PaymentTokenRepositoryInterface $tokenRepository,
        PaymentTokenManagement $paymentTokenManagement,
        \Sapient\AccessWorldpay\Helper\Data $worldpayHelper
    ) {
        parent::__construct($context);
        $this->_storeManager = $storeManager;
        $this->formKeyValidator = $formKeyValidator;
        $this->savecard = $savecard;
        $this->customerSession = $customerSession;
        $this->_tokenService = $tokenService;
        $this->_worldpayToken = $worldpayToken;
        $this->wplogger = $wplogger;
        $this->tokenRepository = $tokenRepository;
        $this->paymentTokenManagement = $paymentTokenManagement;
        $this->worldpayHelper = $worldpayHelper;
    }

    /**
     * Retrive store Id
     *
     * @return int
     */
    public function getStoreId()
    {
        return $this->_storeManager->getStore()->getId();
    }

    /**
     * Receive http post request to update saved card details
     */
    public function execute()
    {
        if (!$this->customerSession->isLoggedIn()) {
            $this->_redirect('customer/account/login');
            return;
        }
        $validFormKey = $this->formKeyValidator->validate($this->getRequest());
        if ($validFormKey && $this->getRequest()->isPost()) {
            try {
                $tokenInquiryResponse = $this->_tokenService->getTokenInquiry(
                    $this->_getTokenModel()
                );
                $tokenId = $this->_getTokenModel()->getTokenId();
                if (!empty($tokenInquiryResponse['tokenId']) && $tokenId == $tokenInquiryResponse['tokenId']) {
                    $cardHolderNameUrl = $tokenInquiryResponse['_links']['tokens:cardHolderName'];
                    $cardExpiryDateUrl = $tokenInquiryResponse['_links']['tokens:cardExpiryDate'];
                    $tokenExpiryResponse = $this->_tokenService->putTokenExpiry(
                        $this->_getTokenModel(),
                        $cardExpiryDateUrl['href']
                    );
                    $tokenNameResponse = $this->_tokenService->putTokenName(
                        $this->_getTokenModel(),
                        $cardHolderNameUrl['href']
                    );
                    if ($tokenNameResponse == 204 && $tokenExpiryResponse == 204) {
                        $this->_applyTokenUpdate();
                        $this->_applyVaultTokenUpdate();
                    } else {
                        $errorResponse = $tokenNameResponse!=204 ? $this->getErrorResponse($tokenNameResponse) :
                            $this->getExpiryErrorResponse($tokenExpiryResponse);
                        $this->messageManager->addError(__($errorResponse));
                        $this->_redirect('*/savedcard/edit', ['id' => $this->_getTokenModel()->getId()]);
                        return;
                    }
                } else {
                    $this->messageManager->addError(__($this->worldpayHelper->getMyAccountSpecificexception('MCAM1')));
                    $this->_redirect('*/savedcard/edit', ['id' => $this->_getTokenModel()->getId()]);
                    return;
                }
            } catch (Exception $e) {
                $this->wplogger->error($e->getMessage());
                $this->messageManager->addException($e, __('Error: ').$e->getMessage());
                $this->_redirect('*/savedcard/edit', ['id' => $this->_getTokenModel()->getId()]);
                return;
            }
            $this->messageManager->addSuccess($this->worldpayHelper->getMyAccountSpecificexception('MCAM2'));
            $this->_redirect('*/savedcard');
            return;
        }
    }

    /**
     * Update Saved Card Detail
     */
    protected function _applyTokenUpdate()
    {
        $this->_worldpayToken->updateTokenByCustomer(
            $this->_getTokenModel(),
            $this->customerSession->getCustomer()
        );
    }

    /**
     * GetTokenModel
     *
     * @return Sapient/AccessWorldPay/Model/Token
     */
    protected function _getTokenModel()
    {
        if (! $tokenId = $this->getRequest()->getParam('token_id')) {
            $tokenData = $this->getRequest()->getParam('token');
            $tokenId = $tokenData['id'];
        }
        $token = $this->savecard->create()->loadByTokenCode($tokenId);
        $tokenUpdateData = $this->getRequest()->getParam('token');
        if (!empty($tokenUpdateData)) {
            $token->setToken(trim($tokenUpdateData['tokenUrl']));
            $token->setTokenId(trim($tokenUpdateData['id']));
            $token->setCardholderName(trim($tokenUpdateData['cardholder_name']));
            //$token->setCardExpiryMonth(sprintf('%02d', $tokenUpdateData['card_expiry_month']));
            //$token->setCardExpiryYear(sprintf('%d', $tokenUpdateData['card_expiry_year']));
            $token->setCardExpiryMonth($tokenUpdateData['card_expiry_month']);
            $token->setCardExpiryYear($tokenUpdateData['card_expiry_year']);
        }
        return $token;
    }

    /**
     * Apply Vault TokenUpdate
     */
    protected function _applyVaultTokenUpdate()
    {
        $existingVaultPaymentToken = $this->paymentTokenManagement->getByGatewayToken(
            $this->_getTokenModel()->getTokenId(),
            'worldpay_cc',
            $this->customerSession->getCustomer()->getId()
        );
        $this->_saveVaultToken($existingVaultPaymentToken);
    }
    
    /**
     * Save Vault Token
     *
     * @param PaymentTokenInterface $vaultToken
     */
    protected function _saveVaultToken(PaymentTokenInterface $vaultToken)
    {
        $vaultToken->setTokenDetails($this->convertDetailsToJSON([
            'type' => $this->_getTokenModel()->getCardBrand().'-SSL',
            'maskedCC' => $this->getLastFourNumbers($this->_getTokenModel()->getCardNumber()),
            'expirationDate'=> $this->getExpirationMonthAndYear($this->_getTokenModel())
        ]));
        try {
            $this->tokenRepository->save($vaultToken);
        } catch (Exception $e) {
            $this->wplogger->error($e->getMessage());
            $this->messageManager->addException($e, __('Error: ').$e->getMessage());
        }
    }

    /**
     * Get Expiration MonthAndYear
     *
     * @param string $token
     */
    public function getExpirationMonthAndYear($token)
    {
        return $token->getCardExpiryMonth().'/'.$token->getCardExpiryYear();
    }

    /**
     * Get Last Four Numbers
     *
     * @param string $number
     */
    public function getLastFourNumbers($number)
    {
        return substr($number, -4);
    }

    /**
     * Convert Details To JSON
     *
     * @param string $details
     */
    private function convertDetailsToJSON($details)
    {
        $json = \Zend_Json::encode($details);
        return $json ? $json : '{}';
    }
    
    /**
     * Update Saved Card Detail
     *
     * @param string $tokenInquiryResponse
     */
    protected function _applyTokenInquiry($tokenInquiryResponse)
    {
        $this->_worldpayToken->updateTokenByCustomer(
            $this->_getTokenModelInquiry($tokenInquiryResponse),
            $this->customerSession->getCustomer()
        );
    }
    
    /**
     * Get Token Model Inquiry
     *
     * @param string $tokenInquiryResponse
     * @return Sapient/WorldPay/Model/Token
     */
    protected function _getTokenModelInquiry($tokenInquiryResponse)
    {
        if (! $tokenId = $this->getRequest()->getParam('token_id')) {
            $tokenData = $this->getRequest()->getParam('token');
            $tokenId = $tokenData['id'];
        }
        $token = $this->savecard->create()->loadByTokenCode($tokenId);
        $tokenUpdateData = $this->getRequest()->getParam('token');
        if (! empty($tokenUpdateData) && ! empty($tokenInquiryResponse->isSuccess())) {
            $token->setBinNumber(trim($tokenInquiryResponse->isSuccess()));
        }
        return $token;
    }

    /**
     * Get Expiry Error Response
     *
     * @param string $body
     */
    public function getExpiryErrorResponse($body)
    {
        $result = '';
        foreach ($body as $k => $v) {
            $body[$k] = $v;
            if (is_array($body[$k])) {
                $result = $this->getErrorArray($body[$k]);
            }
        }
        return $result;
    }

    /**
     * Get ErrorArray
     *
     * @param string $body
     */
    public function getErrorArray($body)
    {
        $result = '';
        foreach ($body as $k => $v) {
            $body[$k] = $v;
            if (is_array($body[$k])) {
                $result = $this->getErrorResponse($body[$k]);
            }
        }
        return $result;
    }

    /**
     * Get Error Response
     *
     * @param string $body
     */
    public function getErrorResponse($body)
    {
        $result = '';
        $i = 0;
        foreach ($body as $k => $v) {
            if ($i == 1) {
                $result = $v;
                break;
            }
            $i++;
        }
        return $result;
    }
}
