<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Plugin;

use Magento\Checkout\Model\GuestPaymentInformationManagement as CheckoutGuestPaymentInformationManagement;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\GuestCartManagementInterface;
use \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger;
use Sapient\AccessWorldpay\Model\MethodList;
use \Sapient\AccessWorldpay\Helper\Data;

/**
 * Class GuestPaymentInformationManagement helps to manage WP payment actions
 */
class GuestPaymentInformationManagement
{
    /**
     * @var GuestCartManagementInterface
     */
    private $cartManagement;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var MethodList
     */
    private $methodList;
    /**
     * @var bool
     */
    private $checkMethods;
    /**
     * GuestPaymentInformationManagement constructor.
     * @param GuestCartManagementInterface $cartManagement
     * @param AccessWorldpayLogger $logger
     * @param MethodList $methodList
     * @param \Sapient\AccessWorldpay\Helper\Data $worldpayHelper
     * @param bool $checkMethods
     */
    public function __construct(
        GuestCartManagementInterface $cartManagement,
        AccessWorldpayLogger $logger,
        MethodList $methodList,
        \Sapient\AccessWorldpay\Helper\Data $worldpayHelper,
        $checkMethods = true
    ) {
        $this->cartManagement = $cartManagement;
        $this->logger = $logger;
        $this->methodList = $methodList;
        $this->checkMethods = $checkMethods;
        $this->worldpayHelper = $worldpayHelper;
    }
    /**
     * Around Save Payment Information And Place Order
     *
     * @param CheckoutGuestPaymentInformationManagement $subject
     * @param \Closure $proceed
     * @param string $cartId
     * @param string $email
     * @param \Magento\Quote\Api\Data\PaymentInterface $paymentMethod
     * @param \Magento\Quote\Api\Data\AddressInterface|null $billingAddress
     * @return int
     * @throws CouldNotSaveException
     */
    public function aroundSavePaymentInformationAndPlaceOrder(
        CheckoutGuestPaymentInformationManagement $subject,
        \Closure $proceed,
        $cartId,
        $email,
        \Magento\Quote\Api\Data\PaymentInterface $paymentMethod,
        \Magento\Quote\Api\Data\AddressInterface $billingAddress = null
    ) {
        if ($this->checkMethods && !in_array($paymentMethod->getMethod(), $this->methodList->get())) {
            return $proceed($cartId, $email, $paymentMethod, $billingAddress);
        }
        $subject->savePaymentInformation($cartId, $email, $paymentMethod, $billingAddress);
        try {
            $orderId = $this->cartManagement->placeOrder($cartId);
        } catch (LocalizedException $exception) {
            $this->logger->error($exception->getMessage());
            throw new CouldNotSaveException(__($exception->getMessage()));
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());
            throw new CouldNotSaveException(
                __($this->worldpayHelper->getCreditCardSpecificException('CCAM13')),
                $exception
            );
        }
        return $orderId;
    }
}
