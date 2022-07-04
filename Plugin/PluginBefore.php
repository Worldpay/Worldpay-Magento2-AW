<?php

namespace Sapient\AccessWorldpay\Plugin;

use Magento\Framework\App\RequestInterface;
use Magento\Backend\Block\Widget\Button\Toolbar as ToolbarContext;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Backend\Block\Widget\Button\ButtonList;
use Sapient\AccessWorldpay\Helper\Data;

class PluginBefore
{
    /**
     * Constructor function
     *
     * @param \Magento\Framework\UrlInterface $urlBuilder
     * @param \Magento\Sales\Model\Order $order
     * @param RequestInterface $request
     * @param Data $worldpayHelper
     * @param \Sapient\AccessWorldpay\Model\AccessWorldpaymentFactory $worldpaymodel
     */

    public function __construct(
        \Magento\Framework\UrlInterface $urlBuilder,
        \Magento\Sales\Model\Order $order,
        RequestInterface $request,
        Data $worldpayHelper,
        \Sapient\AccessWorldpay\Model\AccessWorldpaymentFactory $worldpaymodel
    ) {
        $this->_urlBuilder = $urlBuilder;
        $this->order = $order;
        $this->request = $request;
        $this->worldpayHelper = $worldpayHelper;
        $this->worldpaymodel = $worldpaymodel;
    }

    /**
     * Before Push Buttons
     *
     * @param ToolbarContext $toolbar
     * @param \Magento\Framework\View\Element\AbstractBlock $context
     * @param \Magento\Backend\Block\Widget\Button\ButtonList $buttonList
     */
    
    public function beforePushButtons(
        ToolbarContext $toolbar,
        \Magento\Framework\View\Element\AbstractBlock $context,
        \Magento\Backend\Block\Widget\Button\ButtonList $buttonList
    ) {
        $this->_request = $context->getRequest();
        if ($this->_request->getFullActionName() == 'sales_order_view' && $this->worldpayHelper->isWorldPayEnable()) {
            $requestdata = $this->request->getParams();
            $orderId = $requestdata['order_id'];
            $syncurl = $this->_urlBuilder->getUrl("worldpay/syncstatus/index", ['order_id' => $orderId]);
            $order = $this->order->load($orderId);
            if ($order->getPayment()->getMethod()=='worldpay_cc'
                || $order->getPayment()->getMethod()=='worldpay_apm'
                || $order->getPayment()->getMethod()=='worldpay_moto'
                || $order->getPayment()->getMethod()=='worldpay_wallets'
                || $order->getPayment()->getMethod()=='worldpay_cc_vault') {
                $buttonList->add(
                    'sync_status',
                    ['label' => __('Sync Status'), 'onclick' => 'setLocation("'.$syncurl.'")', 'class' => 'reset'],
                    -1
                );
                
                //Payment Reversal changes
                if ($this->checkEligibilityForPaymentReversal($order)) {
                    $reversalurl = $this->_urlBuilder->getUrl(
                        "worldpay/paymentreversal/index",
                        ['order_id' => $orderId]
                    );
                    $buttonList->add(
                        'payment_reversal',
                        ['label' => __('Payment Reversal'),
                            'onclick' => 'setLocation("' . $reversalurl . '")',
                            'class' => 'void'],
                        -1
                    );
                }
            }
        }
        
        return [$context, $buttonList];
    }
    
    /**
     * Check Eligibility For Payament Reversal
     *
     * @param string $order
     */
    private function checkEligibilityForPaymentReversal($order)
    {
        $data = $order->getData();
        $paymenttype = $this->getPaymentType($data['increment_id']);
        $orderStatus = $order->getStatus();
        if ((strtoupper($orderStatus)==='PENDING' || strtoupper($orderStatus)==='PROCESSING')
           && ($order->getPayment()->getMethod() == 'worldpay_apm'
           && $paymenttype === 'ACH_DIRECT_DEBIT-SSL')) {
                return true;
        }
    }
    
    /**
     * Get Payment Type
     *
     * @param string $orderid
     */
    public function getPaymentType($orderid)
    {
        $worldpaydata= $this->worldpaymodel->create()->loadByPaymentId($orderid);
        return $worldpaydata->getPaymentType();
    }
}
