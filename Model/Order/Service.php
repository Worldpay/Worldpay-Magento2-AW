<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Order;

class Service
{

    /**
     * Constructor
     * @param \Magento\Sales\Model\Order $mageOrder
     * @param \Magento\Checkout\Model\Session $checkoutsession
     * @param \Magento\Sales\Model\Order\Email\Sender\OrderSender $emailsender
     * @param \Magento\Sales\Model\Service\InvoiceService $invoiceService
     * @param \Magento\Framework\DB\Transaction $transaction
     * @param \Sapient\AccessWorldpay\Model\AccessWorldpayment $worldpaypaymentmodel
     * @param \Magento\Sales\Model\Order\CreditmemoFactory $creditmemoFactory
     * @param \Magento\Sales\Model\Order\Invoice $Invoice
     * @param \Magento\Sales\Model\Service\CreditmemoService $CreditmemoService
     * @param \Magento\Sales\Model\Order\Creditmemo $creditmemo
     * @param \Magento\Sales\Api\CreditmemoRepositoryInterface $creditmemoRepository
     */
    public function __construct(
        \Magento\Sales\Model\Order $mageOrder,
        \Magento\Checkout\Model\Session $checkoutsession,
        \Magento\Sales\Model\Order\Email\Sender\OrderSender $emailsender,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\Transaction $transaction,
        \Sapient\AccessWorldpay\Model\AccessWorldpayment $worldpaypaymentmodel,
        \Magento\Sales\Model\Order\CreditmemoFactory $creditmemoFactory,
        \Magento\Sales\Model\Order\Invoice $Invoice,
        \Magento\Sales\Model\Service\CreditmemoService $CreditmemoService,
        \Magento\Sales\Model\Order\Creditmemo $creditmemo,
        \Magento\Sales\Api\CreditmemoRepositoryInterface $creditmemoRepository
    ) {

        $this->checkoutsession = $checkoutsession;
        $this->mageorder = $mageOrder;
        $this->emailsender = $emailsender;
        $this->_invoiceService = $invoiceService;
        $this->_transaction = $transaction;
        $this->worldpaypaymentmodel = $worldpaypaymentmodel;
        $this->creditmemoFactory = $creditmemoFactory;
        $this->Invoice = $Invoice;
        $this->CreditmemoService = $CreditmemoService;
        $this->ordercreditmemo = $creditmemo;
        $this->creditmemoRepository = $creditmemoRepository;
    }

    /**
     * Get Order By Id
     *
     * @param int $orderId
     * @return \Sapient\AccessWorldpay\Model\Order
     */
    public function getById($orderId)
    {

         return new \Sapient\AccessWorldpay\Model\Order(
             ['order' => $this->mageorder->load($orderId)],
             $this->_invoiceService,
             $this->_transaction,
             $this->worldpaypaymentmodel,
             $this->creditmemoFactory,
             $this->Invoice,
             $this->CreditmemoService,
             $this->ordercreditmemo,
             $this->creditmemoRepository
         );
    }

    /**
     * Get Order by IncrementId
     *
     * @param string $incrementId
     * @return \Sapient\AccessWorldpay\Model\Order
     */
    public function getByIncrementId($incrementId)
    {

        return new \Sapient\AccessWorldpay\Model\Order(
            ['order' => $this->mageorder->loadByIncrementId($incrementId)],
            $this->_invoiceService,
            $this->_transaction,
            $this->worldpaypaymentmodel,
            $this->creditmemoFactory,
            $this->Invoice,
            $this->CreditmemoService,
            $this->ordercreditmemo,
            $this->creditmemoRepository
        );
    }

    /**
     * If order is success send email and mark order as processing
     */
    public function redirectOrderSuccess()
    {
        $order = $this->getAuthorisedOrder();
        $magentoorder = $order->getOrder();
        $this->emailsender->send($magentoorder);
    }

    /**
     * Get Authorised Order
     *
     * @return Increament Id
     */
    public function getAuthorisedOrder()
    {
        if ($this->checkoutsession->getauthenticatedOrderId()) {
            return $this->getByIncrementId($this->checkoutsession->getauthenticatedOrderId());
        }
        return false;
    }

    /**
     * Delete currently authorised order from session
     */
    public function removeAuthorisedOrder()
    {
        $this->checkoutsession->unsauthenticatedOrderId();
    }
}
