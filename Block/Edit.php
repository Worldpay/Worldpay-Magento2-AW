<?php
/**
 * @copyright 2017 Sapient
 */
namespace Sapient\AccessWorldpay\Block;

use Magento\Framework\Serialize\SerializerInterface;

class Edit extends \Magento\Framework\View\Element\Template
{
    /**
     * @var \Sapient\AccessWorldpay\Model\SavedTokenFactory
     */
    protected $_savecard;
    /**
     * @var SerializerInterface
     */
    private $serializer;
     /**
      * @var \Magento\Customer\Model\Session
      */
    protected $_customerSession;
     /**
      * @var array
      */
    protected static $_months;
     /**
      * @var array
      */
    protected static $_expiryYears;
    
    /**
     * Constructor
     *
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Sapient\AccessWorldpay\Model\SavedTokenFactory $savecard
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Sapient\AccessWorldpay\Helper\Data $worldpayHelper
     * @param \Magento\Framework\Serialize\SerializerInterface $serializer
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Sapient\AccessWorldpay\Model\SavedTokenFactory $savecard,
        \Magento\Customer\Model\Session $customerSession,
        \Sapient\AccessWorldpay\Helper\Data $worldpayHelper,
        SerializerInterface $serializer,
        array $data = []
    ) {
        $this->_savecard = $savecard;
        $this->_customerSession = $customerSession;
        $this->worldpayHelper = $worldpayHelper;
        parent::__construct($context, $data);
        $this->serializer = $serializer;
    }

    /**
     * Retrive savecard Deatil
     *
     * @return object
     */
    public function getTokenData()
    {
        if (!($customerId = $this->_customerSession->getCustomerId())) {
            return false;
        }
        $id = $this->getRequest()->getParam('id');
        if ($id) {
            return $this->_savecard->create()->load($id);
        }
    }

    /**
     * Helps to build year html dropdown
     *
     * @return array
     */
    public function getMonths()
    {
        if (!self::$_months) {
            self::$_months = ['' => __('Month')];
            for ($i = 1; $i < 13; $i++) {
                $month = str_pad($i, 2, '0', STR_PAD_LEFT);
                self::$_months[$month] = date("$i - F", mktime(0, 0, 0, $i, 1));
            }
        }

        return self::$_months;
    }

    /**
     * Helps to build year html dropdown
     *
     * @return array
     */
    public function getExpiryYears()
    {
        if (!self::$_expiryYears) {
            self::$_expiryYears = ['' => __('Year')];
            $year = date('Y');
            $endYear = ($year + 20);
            while ($year < $endYear) {
                self::$_expiryYears[$year] = $year;
                $year++;
            }
        }
        return self::$_expiryYears;
    }
    /**
     * Account Label Code
     *
     * @param string $labelCode
     * @return array
     */

    public function getMyAccountLabels($labelCode)
    {
        $accdata = $this->serializer->unserialize($this->worldpayHelper->getMyAccountLabels());
        if (is_array($accdata) || is_object($accdata)) {
            foreach ($accdata as $key => $valuepair) {
                if ($key == $labelCode) {
                    return $valuepair['wpay_custom_label']?$valuepair['wpay_custom_label']:
                        $valuepair['wpay_label_desc'];
                }
            }
        }
    }
}
