<?php

namespace Sapient\AccessWorldpay\Model\System\Config\Backend;

/**
 * Retrieve current plugin version details
 *
 */
class CurrentPluginVersion extends \Magento\Framework\App\Config\Value
{
    
    /**
     * Constructor
     *
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
     * @param \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
     * @param \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger
     * @param \Magento\Framework\Composer\ComposerInformation $composerInformation
     * @param \Magento\Framework\App\Config\Storage\WriterInterface $configWriter
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\Filesystem\Driver\File $fileDriver
     * @param \Magento\Framework\Filesystem\DirectoryList $dir
     * @param \Magento\Framework\App\Cache\Manager $cacheManager
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger,
        \Magento\Framework\Composer\ComposerInformation $composerInformation,
        \Magento\Framework\App\Config\Storage\WriterInterface $configWriter,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Filesystem\Driver\File $fileDriver,
        \Magento\Framework\Filesystem\DirectoryList $dir,
        \Magento\Framework\App\Cache\Manager $cacheManager,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->wplogger = $wplogger;
        $this->scopeConfig = $scopeConfig;
        $this->composerInformation = $composerInformation;
        $this->configWriter = $configWriter;
        $this->cacheManager = $cacheManager;
        $this->fileDriver = $fileDriver;
        $this->dir = $dir;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }
    
    /**
     * Process data after load
     *
     * @return void
     */
    protected function _afterLoad()
    {

        $value = $this->getValue();
        $value = $this->getPluinVersionDetails();
        if (isset($value['newVersion'])) {
            $this->setValue($value['newVersion']);
            $this->configWriter->save(
                'worldpay/general_config/plugin_tracker/current_wopay_plugin_version',
                $value['newVersion']
            );
            $this->cacheManager->flush($this->cacheManager->getAvailableTypes());
        }
    }
    
    /**
     * Prepare data before save
     *
     * @return void
     */
    public function beforeSave()
    {
        $value = $this->getValue();
        $value = $this->getPluinVersionDetails();
         
        if (isset($value['newVersion'])) {
            $this->setValue($value['newVersion']);
            $this->configWriter->save(
                'worldpay/general_config/plugin_tracker/current_wopay_plugin_version',
                $value['newVersion']
            );
        }
    }
    /**
     * Get Plugin Version Details
     *
     * @return void
     */
    public function getPluinVersionDetails()
    {
        $value=[];
        $fileName = 'composer.json';
        $filenameDir = $this->dir->getRoot()."/".$fileName;
        $oldData = $this->scopeConfig->getValue(
            'worldpay/general_config/plugin_tracker/current_wopay_plugin_version',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        if (isset($oldData) && !empty($oldData)) {
            $this->wplogger->info("Inside oldvalue");
            $value['oldVersion'] = $oldData;
        }
        
        $packageDetails = $this->composerInformation->getInstalledMagentoPackages();
        if (array_key_exists('sapient/module-access-worldpay', $packageDetails)) {
            $value['newVersion'] = $packageDetails['sapient/module-access-worldpay']['version'];
            if (isset($value['oldVersion']) && ($value['oldVersion']==$value['newVersion'])) {
                $value['oldVersion'] = "";
            }
            return $value;
        
        } elseif ($this->fileDriver->isExists($fileName) || $this->fileDriver->isExists($filenameDir)) {
            $file = $this->fileDriver->isExists($filenameDir)?$filenameDir:$fileName;
            $content = $this->fileDriver->fileGetContents($file);
            $content = json_decode($content, true);
            $this->wplogger->info(
                array_key_exists('sapient/module-access-worldpay', $content['require'])
            );
            if (array_key_exists('sapient/module-access-worldpay', $content['require'])) {
                $value['newVersion'] = $content['require']['sapient/module-access-worldpay'];
                if (isset($value['oldVersion']) && ($value['oldVersion']==$value['newVersion'])) {
                    $value['oldVersion'] = "";
                }
            }
            return $value;
        }
        return $value;
    }
}
