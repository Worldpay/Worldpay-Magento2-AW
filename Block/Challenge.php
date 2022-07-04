<?php


namespace Sapient\AccessWorldpay\Block;

use Sapient\AccessWorldpay\Helper\Data;

class Challenge extends \Magento\Framework\View\Element\Template
{
    /**
     * @var Sapient\AccessWorldpay\Helper\Data;
     */
    
    protected $helper;
    
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;
       
    /**
     * Jwt constructor
     *
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Checkout\Model\Session $checkoutSession
     * @param Data $helper
     * @param array $data
     */

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        Data $helper,
        array $data = []
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->_helper = $helper;
        parent::__construct($context, $data);
    }

    /**
     * Get Challenge Configs
     *
     * @return array
     */
    public function challengeConfigs()
    {
          $threeDsChallengeData = $this->checkoutSession->get3DschallengeData();
          $challengeurl = $threeDsChallengeData['challenge']['url'];
          $challengeJwt = $threeDsChallengeData['challenge']['jwt'];
          $challengeReference = $threeDsChallengeData['challenge']['reference'];
          $orderId = $this->checkoutSession->getAuthOrderId();
          $data['threeDsChallengeData'] = $threeDsChallengeData;
          $data['challengeurl'] = $challengeurl;
          $data['challengeJwt'] = $challengeJwt;
          $data['challengeReference'] = $challengeReference;
          $data['orderId'] = $this->checkoutSession->getAuthOrderId();
          $data['redirectUrl'] = $this->getUrl('worldpay/threedsecure/challengeredirectresponse', ['_secure' => true]);
        return $data;
    }
}
