<?php

namespace Sapient\AccessWorldpay\Block;

use Magento\Framework\View\Element\Template;
use Sapient\AccessWorldpay\Helper\Data;
use Magento\Framework\Serialize\SerializerInterface;

class Webpayment extends Template
{
    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * Constructor
     *
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Sapient\AccessWorldpay\Helper\Data $helper
     * @param \Magento\Framework\Serialize\SerializerInterface $serializer
     * @param \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger
     * @param array $data
     */
    public function __construct(
        Template\Context $context,
        Data $helper,
        SerializerInterface $serializer,
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger,
        array $data = []
    ) {

        $this->_helper = $helper;
        $this->serializer = $serializer;
        $this->wplogger = $wplogger;
        parent::__construct(
            $context,
            $data
        );
    }
    /**
     * Is 3D Secure Enable
     *
     * @return bool
     */
    public function is3DSecureEnabled()
    {
        return $this->_helper->is3DSecureEnabled();
    }

    /**
     * Get Websdk Js Path
     *
     * @return string
     */
    public function getWebSdkJsPath()
    {
        return $this->_helper->getWebSdkJsPath();
    }

    /**
     * Get Environment Mode
     *
     * @return string
     */
    public function getEnvironmentMode()
    {
        return $this->_helper->getEnvironmentMode();
    }
    
    /**
     * Get Credit Card Exception
     *
     * @return array
     */
    public function getCreditCardException()
    {
        $generaldata=$this->serializer->unserialize($this->_helper->getCreditCardException());
        $result=[];
        $data=[];
        if (is_array($generaldata) || is_object($generaldata)) {
            foreach ($generaldata as $key => $value) {

                $result['exception_code']=$key;
                $result['exception_messages'] = $value['exception_messages'];
                $result['exception_module_messages'] = $value['exception_module_messages'];
                array_push($data, $result);
            
            }
        }
        //$output=implode(',', $data);
        return json_encode($data);
    }
    /**
     * Get Account Exceptions
     *
     * @return array
     */
    public function myAccountExceptions()
    {
        $generaldata=$this->serializer->unserialize($this->_helper->getMyAccountException());
        $result=[];
        $data=[];
        if (is_array($generaldata) || is_object($generaldata)) {
            foreach ($generaldata as $key => $value) {
                $result['exception_code']=$key;
                $result['exception_messages'] = $value['exception_messages'];
                $result['exception_module_messages'] = $value['exception_module_messages'];
                array_push($data, $result);
            
            }
        }
        return json_encode($data);
    }
     /**
      * Get MyAccount Specific Exception
      *
      * @param string $exceptioncode
      * @return array
      */
    public function getMyAccountSpecificException($exceptioncode)
    {
        $data=json_decode($this->myAccountExceptions(), true);
        if (is_array($data) || is_object($data)) {
            foreach ($data as $key => $valuepair) {
                if ($valuepair['exception_code'] == $exceptioncode) {
                    return $valuepair['exception_module_messages']?
                            $valuepair['exception_module_messages']:$valuepair['exception_messages'];
                }
            }
        }
    }

     /**
      * Get Credit Card Specific Exception
      *
      * @param string $exceptioncode
      * @return string
      */
    public function getCreditCardSpecificException($exceptioncode)
    {
        return $this->_helper->getCreditCardSpecificexception($exceptioncode);
    }
}
