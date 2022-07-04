<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Controller\Savedcard;

use Magento\Framework\App\Action\Context;
use \Magento\Framework\View\Result\PageFactory;
use \Sapient\AccessWorldpay\Model\SavedTokenFactory;
use \Magento\Customer\Model\Session;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Vault\Api\PaymentTokenRepositoryInterface;
use Magento\Vault\Model\PaymentTokenManagement;

/**
 * Perform delete card
 */
class Delete extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $_resultPageFactory;

    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * Constructor
     *
     * @param StoreManagerInterface $storeManager
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param SavedTokenFactory $savecard
     * @param Session $customerSession
     * @param \Sapient\AccessWorldpay\Model\Token\Service $tokenService
     * @param \Sapient\AccessWorldpay\Model\Token\WorldpayToken $worldpayToken
     * @param \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger
     * @param PaymentTokenRepositoryInterface $tokenRepository
     * @param PaymentTokenManagement $paymentTokenManagement
     * @param \Sapient\AccessWorldpay\Helper\Data $worldpayHelper
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        Context $context,
        PageFactory $resultPageFactory,
        SavedTokenFactory $savecard,
        Session $customerSession,
        \Sapient\AccessWorldpay\Model\Token\Service $tokenService,
        \Sapient\AccessWorldpay\Model\Token\WorldpayToken $worldpayToken,
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger,
        PaymentTokenRepositoryInterface $tokenRepository,
        PaymentTokenManagement $paymentTokenManagement,
        \Sapient\AccessWorldpay\Helper\Data $worldpayHelper
    ) {
        parent::__construct($context);
        $this->_storeManager = $storeManager;
        $this->_resultPageFactory = $resultPageFactory;
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
     * Perform card deletion
     */
    public function execute()
    {
        $id = $this->getRequest()->getParam('id');
        if ($id) {
            try {
                $model = $this->savecard->create();
                $model->load($id);
                if ($this->customerSession->getId() == $model->getCustomerId()) {
                    if ($model->getTokenId()) {
                        $tokenDeleteResponse = $this->_tokenService->getTokenDelete(
                            $model->getToken()
                        );
                        if ($tokenDeleteResponse) {
                            // Delete Vault Token.
                            $this->_applyVaultTokenDelete(
                                $model->getTokenId(),
                                $this->customerSession->getCustomer()
                            );
                            // Delete Worldpay Token.
                            $this->_applyTokenDelete($model, $this->customerSession->getCustomer());
                        }
                    } else {
                        // Delete Vault Token.
                        $this->_applyVaultTokenDelete(
                            $model->getTokenId(),
                            $this->customerSession->getCustomer()
                        );
                        $model->delete($id);
                    }
                    $this->messageManager->addSuccess(
                        __($this->worldpayHelper->getMyAccountSpecificexception('MCAM6'))
                    );
                } else {
                    $this->messageManager->addErrorMessage(
                        __($this->worldpayHelper->getCreditCardSpecificException('CCAM8'))
                    );
                }
            } catch (\Exception $e) {
                $this->wplogger->error($e->getMessage());
                if ($this->_tokenNotExistOnWorldpay($e->getMessage())) {
                    $this->_applyTokenDelete($model, $this->customerSession->getCustomer());
                    $this->_applyVaultTokenDelete($model->getTokenId(), $this->customerSession->getCustomer());

                    $this->messageManager->addSuccess(
                        __($this->worldpayHelper->getMyAccountSpecificexception('MCAM6'))
                    );
                } else {
                    $this->messageManager->addException($e, __('Error: ').$e->getMessage());
                }
            }
        }
        $this->_redirect('worldpay/savedcard/index');
    }

    /**
     * TokenNotExistOnWorldpay
     *
     * @param string $error
     * @return bool
     */
    protected function _tokenNotExistOnWorldpay($error)
    {
        $message = $this->worldpayHelper->getCreditCardSpecificException('CCAM9');
        if ($error == $message) {
            return true;
        }
        return false;
    }

    /**
     * Delete card of customer
     *
     * @param string $tokenModel
     * @param string $customer
     */
    protected function _applyTokenDelete($tokenModel, $customer)
    {
        $this->_worldpayToken->deleteTokenByCustomer(
            $tokenModel,
            $customer
        );
    }

    /**
     * Delete vault card of customer
     *
     * @param string $tokenModel
     * @param string $customer
     */
    protected function _applyVaultTokenDelete($tokenModel, $customer)
    {
        $paymentToken = $this->paymentTokenManagement->getByGatewayToken(
            $tokenModel,
            'worldpay_cc',
            $customer->getId()
        );
        if ($paymentToken === null) {
            return;
        }
        try {
            $this->tokenRepository->delete($paymentToken);
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage(
                __($this->worldpayHelper->getCreditCardSpecificException('CCAM8'))
            );
        }
    }
}
