<?php
namespace Sapient\AccessWorldpay\Block;

use Sapient\AccessWorldpay\Helper\Data;
use Sapient\AccessWorldpay\Model\Request\PaymentServiceRequest;

class Jwt extends \Magento\Framework\View\Element\Template
{
    /**
     * @var \Sapient\AccessWorldpay\Helper\Data
     */
    protected $helper;
    
    /**
     * Constructor
     *
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
     * @param \Sapient\AccessWorldpay\Helper\Data $helper
     * @param \Sapient\AccessWorldpay\Model\Request\PaymentServiceRequest $paymentservice
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        Data $helper,
        PaymentServiceRequest $paymentservice
    ) {
        $this->_helper = $helper;
        $this->paymentservice = $paymentservice;
        $this->checkoutSession = $checkoutSession;
        $this->quoteRepository = $quoteRepository;
        parent::__construct($context);
    }
    /**
     * Get DDC Url
     *
     * @return string
     */
    public function getDdcUrl()
    {
        $mode = $this->_helper->getEnvironmentMode();
        $ddcurl =  $this->checkoutSession->getDdcUrl();
        return $ddcurl;
    }
    /**
     * Get Jwt
     *
     * @return string
     */
    public function getJWT()
    {
        $jwt = $this->checkoutSession->getDdcJwt();
       
        return $jwt;
    }
    /**
     * Get Cookie
     *
     * @return string
     */
    public function getCookie()
    {
        return $cookie = $this->_helper->getWorldpayAuthCookie();
    }
}
