<?php

namespace Sapient\AccessWorldpay\Helper;

use \Magento\Framework\App\Helper\Context;
use \Sapient\AccessWorldpay\Model\Mail\Template\EmailTransportBuilder as TransportBuilder;
use \Magento\Framework\Translate\Inline\StateInterface;
use \Magento\Store\Model\StoreManagerInterface;

class SendErrorReport extends \Magento\Framework\App\Helper\AbstractHelper
{
    private const WORLDPAY_SUPPORT_EMAIL = "adobe@fisglobal.com";
    private const WORLDPAY_ERROR_ALERT_EMAIL_TEMPLATE = "worldpay_error_alert_email";
    private const EMAIL_ATTACHMENT_WP_REQUEST_FILE_NAME = 'request.txt';
    private const EMAIL_ATTACHMENT_WP_RESPONSE_FILE_NAME = 'response.txt';
    
    /**
     * @var $scopeConfig
     */
    protected $_scopeConfig;

    /**
     * @var $wplogger
     */
    protected $wplogger;
    
    /**
     * @param \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param TransportBuilder $transportBuilder
     * @param StoreManagerInterface $storeManager
     * @param StateInterface $state
     */
    public function __construct(
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        TransportBuilder $transportBuilder,
        StoreManagerInterface $storeManager,
        StateInterface $state
    ) {
        $this->_scopeConfig = $scopeConfig;
        $this->wplogger = $wplogger;
        $this->storeManager = $storeManager;
        $this->transportBuilder = $transportBuilder;
        $this->inlineTranslation = $state;
    }

    /**
     *  Check if send error report email is enabled
     */
    public function isEnableSendEmail()
    {
        return $this->_scopeConfig->getValue(
            'worldpay/wp_error_alerts/enable_error_alerts',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
    /**
     * Check if send to worlpay configuration is enabled or not
     */
    public function getRecipientEmails()
    {
        $configuredEmails = $this->_scopeConfig->getValue(
            'worldpay/wp_error_alerts/error_alert_emails',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        if (!empty($configuredEmails)) {
            $allEmails = explode(',', $configuredEmails);
            if (!empty($allEmails)) {
                return $allEmails;
            }
        }
        return [];
    }

    /**
     * Check if send to worlpay configuration is enabled or not
     */
    public function isSendToWorldpayEnabled()
    {
        return $this->_scopeConfig->getValue(
            'worldpay/wp_error_alerts/send_email_to_worldpay',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Send Email
     *
     * @param string $params
     * @param string $toEmail
     */
    public function sendEmail($params, $toEmail)
    {
        $senderDetails = $this->getAdminContactEmail();
        $templateId = self::WORLDPAY_ERROR_ALERT_EMAIL_TEMPLATE;

        try {
            // template variables pass here
            $templateVars = $params;
            $templateVars['mail_subject'] = __("WORLDPAY ERROR: ").$templateVars['error_message'];
            $storeId = $this->storeManager->getStore()->getId();
            $from = ['email' => $senderDetails['email'], 'name' => $senderDetails['name']];
            $this->inlineTranslation->suspend();
  
            $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
            $templateOptions = [
                'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                'store' => $storeId
            ];
            $transport = $this->transportBuilder->setTemplateIdentifier($templateId, $storeScope)
                ->setTemplateOptions($templateOptions)
                ->setTemplateVars($templateVars)
                ->setFrom($from)
                ->addTo($toEmail)
                ->addAttachment($params['request'], self::EMAIL_ATTACHMENT_WP_REQUEST_FILE_NAME, 'text/html')
                ->addAttachment($params['response'], self::EMAIL_ATTACHMENT_WP_RESPONSE_FILE_NAME, 'text/html')
                ->getTransport();
            $transport->sendMessage();
            $this->inlineTranslation->resume();
        } catch (\Exception $e) {
            $this->wplogger->info(__('Unable to send email'). $e->getMessage());
        }
    }

    /**
     * Get store admin email and name
     */
    public function getAdminContactEmail()
    {
        return [
            'name' =>   $this->_scopeConfig->getValue(
                'trans_email/ident_support/name',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            ),
            'email' =>   $this->_scopeConfig->getValue(
                'trans_email/ident_support/email',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            ),
        ];
    }
    /**
     * Send email to error report
     *
     * @param string $params
     */
    public function sendErrorReport($params)
    {
        $this->wplogger->info("Sending error report: ");
        try {
            if ($this->isEnableSendEmail()) {
                $recipientEmails = $this->getRecipientEmails();
                
                if (!empty($recipientEmails)) {
                    foreach ($recipientEmails as $recipientEmail) {
                        $this->sendEmail($params, $recipientEmail);
                        $this->wplogger->info("sent error alert email to ".$recipientEmail);
                    }
                }
                if ($this->isSendToWorldpayEnabled()) {
                    $this->sendEmail($params, self::WORLDPAY_SUPPORT_EMAIL);
                }
            }
        } catch (\Exception $e) {
            $this->wplogger->info(__("Failed to send error report:").$e->getMessage());
        }
    }
}
