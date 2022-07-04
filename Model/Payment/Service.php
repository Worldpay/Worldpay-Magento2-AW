<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Payment;

class Service
{

    /**
     * @var \Sapient\AccessWorldpay\Model\Request\PaymentServiceRequest
     */
    protected $_paymentServiceRequest;

   /**
    * @var _paymentUpdateFactory
    */
    protected $_paymentUpdateFactory;

    /**
     * @var R_redirectResponse
     */
    protected $_redirectResponse;

    /**
     * @var $_paymentModel
     */
    protected $_paymentModel;

    /**
     * @var $_helper
     */
    protected $_helper;

    /**
     * Constructor
     *
     * @param \Sapient\AccessWorldpay\Model\Payment\Update\Factory $paymentupdatefactory
     * @param \Sapient\AccessWorldpay\Model\Request\PaymentServiceRequest $paymentservicerequest
     * @param \Sapient\AccessWorldpay\Model\Response\DirectResponse $directResponse
     * @param \Sapient\AccessWorldpay\Model\AccessWorldpayment $worldpayPayment
     */
    public function __construct(
        \Sapient\AccessWorldpay\Model\Payment\Update\Factory $paymentupdatefactory,
        \Sapient\AccessWorldpay\Model\Request\PaymentServiceRequest $paymentservicerequest,
        \Sapient\AccessWorldpay\Model\Response\DirectResponse $directResponse,
        \Sapient\AccessWorldpay\Model\AccessWorldpayment $worldpayPayment
    ) {
        $this->paymentupdatefactory = $paymentupdatefactory;
        $this->paymentservicerequest = $paymentservicerequest;
        $this->worldpayPayment = $worldpayPayment;
        $this->directResponse = $directResponse;
    }

    /**
     * Create Payment Update From WorldPay Xml
     *
     * @param string $xml
     * @return array
     */
    public function createPaymentUpdateFromWorldPayXml($xml)
    {
        if (isset($xml->errorName) && $xml->errorName=='entityIsNotConfigured') {
            throw new \Magento\Framework\Exception\LocalizedException(__($xml->message));
        }
        return $this->_getPaymentUpdateFactory()
            ->create(new \Sapient\AccessWorldpay\Model\Payment\StateJson($xml));
    }

    /**
     * Get Payment Update Factory
     *
     * @return array
     */
    protected function _getPaymentUpdateFactory()
    {
        if ($this->_paymentUpdateFactory === null) {
            $this->_paymentUpdateFactory = $this->paymentupdatefactory;
        }

        return $this->_paymentUpdateFactory;
    }

    /**
     * Create Payment Update From WorldPay Response
     *
     * @param Sapient\Worldpay\Model\Payment\StateInterface $state
     * @return array
     */
    public function createPaymentUpdateFromWorldPayResponse(\Sapient\AccessWorldpay\Model\Payment\StateInterface $state)
    {
        return $this->_getPaymentUpdateFactory()
            ->create($state);
    }

    /**
     * Set Payment Global Payment By Payment Update
     *
     * @param string $paymentUpdate
     * @return array
     */
    public function setGlobalPaymentByPaymentUpdate($paymentUpdate)
    {
        $this->worldpayPayment->loadByAccessWorldpayOrderId($paymentUpdate->getTargetOrderCode());
    }
 
    /**
     * Get Payment Update Xml For Order
     *
     * @param Sapient\Worldpay\Model\Order $order
     * @return array
     */
    public function getPaymentUpdateXmlForOrder(\Sapient\AccessWorldpay\Model\Order $order)
    {
        $worldPayPayment = $order->getWorldPayPayment();

        if (!$worldPayPayment) {
            return false;
        }
        $orderid = $order->getOrder()->getIncrementId();
        $xml = $this->paymentservicerequest->eventInquiry($orderid, null);
        $response = $this->directResponse->setResponse($xml);
        return $response->getXml();
    }
}
