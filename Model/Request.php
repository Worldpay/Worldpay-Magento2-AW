<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model;

use Exception;
use Magento\Framework\Exception\LocalizedException;

/**
 * Used for processing the Request
 */
class Request
{
    /**
     * @var \Magento\Framework\HTTP\Client\Curl
     */
    protected $_request;

    /**
     * @var \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger
     */
    protected $_logger;

    /**
     * @var CURL_POST
     */
    public const CURL_POST = true;

    /**
     * @var CURL_RETURNTRANSFER
     */
    public const CURL_RETURNTRANSFER = true;
 
    /**
     * @var CURL_NOPROGRESS
     */
    public const CURL_NOPROGRESS = false;

    /**
     * @var CURL_TIMEOUT
     */
    public const CURL_TIMEOUT = 60;
    
    /**
     * @var CURL_VERBOSE
     */
    public const CURL_VERBOSE = true;
    
    /**
     * @var SUCCESS
     */
    public const SUCCESS = 200;

    /**
     * Constructor
     *
     * @param \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger
     * @param \Magento\Framework\HTTP\Client\Curl $curl
     * @param \Sapient\AccessWorldpay\Helper\Data $helper
     * @param \Sapient\AccessWorldpay\Helper\SendErrorReport $emailErrorReportHelper
     */
    public function __construct(
        \Sapient\AccessWorldpay\Logger\AccessWorldpayLogger $wplogger,
        \Magento\Framework\HTTP\Client\Curl $curl,
        \Sapient\AccessWorldpay\Helper\Data $helper,
        \Sapient\AccessWorldpay\Helper\SendErrorReport $emailErrorReportHelper
    ) {
        $this->_wplogger = $wplogger;
        $this->curlrequest = $curl;
        $this->helper = $helper;

        $this->emailErrorReportHelper = $emailErrorReportHelper;
    }
     /**
      * Process the request
      *
      * @param object $orderCode
      * @param string $username
      * @param string $password
      * @param string $url
      * @param int $quote
      * @return SimpleXMLElement body
      * @throws Exception
      */
    public function sendRequest($orderCode, $username, $password, $url, $quote = null)
    {
        $pluginTrackerDetails = $this->helper->getPluginTrackerdetails();
        $request = $this->_getRequest();
        $logger = $this->_wplogger;
        if (!$url) {
            $url = $this->_getUrl();
        }
        $logger->info('Setting destination URL: ' . $url);
        $logger->info('Initialising request');
        $request->setOption(CURLOPT_POST, self::CURL_POST);
        $request->setOption(CURLOPT_RETURNTRANSFER, self::CURL_RETURNTRANSFER);
        $request->setOption(CURLOPT_NOPROGRESS, self::CURL_NOPROGRESS);
        $request->setOption(CURLOPT_TIMEOUT, self::CURL_TIMEOUT);
        $request->setOption(CURLOPT_VERBOSE, self::CURL_VERBOSE);
        /*SSL verification false*/
        $request->setOption(CURLOPT_SSL_VERIFYHOST, false);
        $request->setOption(CURLOPT_SSL_VERIFYPEER, false);
        if ($quote) {
            $request->setOption(CURLOPT_POSTFIELDS, $quote);
        }
        $request->setOption(CURLOPT_USERPWD, $username.':'.$password);

        $request->setOption(CURLOPT_HEADER, true);
        $request->setOption(
            CURLOPT_HTTPHEADER,
            ["Content-Type: application/vnd.worldpay.payments-v6+json",
             "MERCHANT_ENTITY_REF"=>$pluginTrackerDetails['MERCHANT_ENTITY_REF'],
             "MERCHANT_ID" => $pluginTrackerDetails['MERCHANT_ID'],
                "MAGENTO_EDITION"=>$pluginTrackerDetails['MAGENTO_EDITION'],
                "MAGENTO_VERSION"=>$pluginTrackerDetails['MAGENTO_VERSION'],
                "PHP_VERSION"=> $pluginTrackerDetails['PHP_VERSION'],
                "CURRENT_WORLDPAY_PLUGIN_VERSION"=>isset($pluginTrackerDetails['CURRENT_WORLDPAY_PLUGIN_VERSION'])?
                $pluginTrackerDetails['CURRENT_WORLDPAY_PLUGIN_VERSION']:"",
                "WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE" =>
                isset($pluginTrackerDetails['WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE'])?
                $pluginTrackerDetails['WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE']:"",
                "UPGRADE_DATES" => isset($pluginTrackerDetails['UPGRADE_DATES'])
                ?$pluginTrackerDetails['UPGRADE_DATES']:""
            ]
        );
        $logger->info('Sending additional headers as: ' . json_encode(["MERCHANT_ENTITY_REF"
            =>$pluginTrackerDetails['MERCHANT_ENTITY_REF'],
             "MERCHANT_ID" => $pluginTrackerDetails['MERCHANT_ID'],
                "MAGENTO_EDITION"=>$pluginTrackerDetails['MAGENTO_EDITION'],
                "MAGENTO_VERSION"=>$pluginTrackerDetails['MAGENTO_VERSION'],
                "PHP_VERSION"=> $pluginTrackerDetails['PHP_VERSION'],
                "CURRENT_WORLDPAY_PLUGIN_VERSION"
                =>isset($pluginTrackerDetails['CURRENT_WORLDPAY_PLUGIN_VERSION'])?
                $pluginTrackerDetails['CURRENT_WORLDPAY_PLUGIN_VERSION']:"",
                "WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE"
                =>isset($pluginTrackerDetails['WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE'])?
                $pluginTrackerDetails['WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE']:"",
                "UPGRADE_DATES" =>
                isset($pluginTrackerDetails['UPGRADE_DATES'])?$pluginTrackerDetails['UPGRADE_DATES']:""
            ], true));

        $request->setOption(CURLINFO_HEADER_OUT, true);

        $request->post($url, $quote);
        $result = $request->getBody();
        //$result = $request->execute();
    
        // logging Headder for 3DS request to check Cookie.
        if ($this->helper->isThreeDSRequest()) {
            //$information = $request->getInfo(CURLINFO_HEADER_OUT);
            $information = $request->getHeaders();
            $logger->info("**REQUEST HEADER START**");
            $logger->info(json_encode($information));
            $logger->info("**REQUEST HEADER ENDS**");
        }

        if (!$result) {
            $logger->info('Request could not be sent.');
            $logger->info($result);
            $logger->info(
                '########### END OF REQUEST - FAILURE WHILST TRYING TO SEND REQUEST ###########'
            );
            throw new \Magento\Framework\Exception\LocalizedException(
                __('AccessWorldpay api service not available')
            );
        }
        $logger->info('Request successfully sent');
        $logger->info($result);

        // extract headers
        $bits = explode("\r\n\r\n", $result);
        $body = array_pop($bits);
        $headers = implode("\r\n\r\n", $bits);
        $jsonResponse = json_decode($body, true);
        
        /* Check Error */
        $this->_checkErrorForEmailSend($request, $jsonResponse);

        // Extracting Cookie from Response Header.
        if (preg_match("/set-cookie: (.+?)([\r\n]|$)/", $headers, $match)) {
            // Keep a hold of the cookie returned incase we need to send a
            // second order message after 3dSecure check
            $logger->info('Cookie Get: ' . $match[1]);

            $this->helper->setWorldpayAuthCookie($match[1]);

        }
        if (preg_match("/Set-Cookie: (.+?)([\r\n]|$)/", $headers, $match)) {
            // Keep a hold of the cookie returned incase we need to send a
            // second order message after 3dSecure check
                $logger->info('Cookie Get: ' . $match[1]);
                $this->helper->setAccessWorldpayAuthCookie($match[1]);
        }
        //return $body;
        $jsonData = json_decode($body, true);
        //$logger->info('Body'.$jsonData);
        try {
            $xml = $this->_array2xml($jsonData, false, $orderCode);
        } catch (Exception $e) {
            $logger->error($e->getMessage());
            throw new \Magento\Framework\Exception\LocalizedException(
                __($this->helper->getCreditCardSpecificException('CCAM18'))
            );
        }
        return $xml;
    }

    /**
     * Send apple pay request
     *
     * @param object $orderCode
     * @param string $username
     * @param string $password
     * @param string $url
     * @param int $quote
     * @return array
     */
    public function sendApplePayRequest($orderCode, $username, $password, $url, $quote = null)
    {
       /*
        $logger = $this->_wplogger;

        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => 'https://try.access.worldpay.com/payments/authorizations',
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => 'POST',
          CURLOPT_POSTFIELDS =>'{
            "transactionReference": "AWPUAT00007819",
            "merchant": {
                "entity": "default"
            },
            "instruction": {
                "narrative": {
                    "line1": "AWPUAT00007804"
                },
                "value": {
                    "currency": "USD",
                    "amount": 5371
                },
                "paymentInstrument": {
                    "type": "card/wallet+applepay",
                    "walletToken": "{\\"signature\\":\\"MIAGCSqGSIb3DQEHAqCAMIACAQExDzANBglghkgBZQMEAgEFADCABgkqhkiG9w0BBwEAAKCAMIID5jCCA4ugAwIBAgIIaGD2mdnMpw8wCgYIKoZIzj0EAwIwejEuMCwGA1UEAwwlQXBwbGUgQXBwbGljYXRpb24gSW50ZWdyYXRpb24gQ0EgLSBHMzEmMCQGA1UECwwdQXBwbGUgQ2VydGlmaWNhdGlvbiBBdXRob3JpdHkxEzARBgNVBAoMCkFwcGxlIEluYy4xCzAJBgNVBAYTAlVTMB4XDTE2MDYwMzE4MTY0MFoXDTIxMDYwMjE4MTY0MFowYjEoMCYGA1UEAwwfZWNjLXNtcC1icm9rZXItc2lnbl9VQzQtU0FOREJPWDEUMBIGA1UECwwLaU9TIFN5c3RlbXMxEzARBgNVBAoMCkFwcGxlIEluYy4xCzAJBgNVBAYTAlVTMFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEgjD9q8Oc914gLFDZm0US5jfiqQHdbLPgsc1LUmeY+M9OvegaJajCHkwz3c6OKpbC9q+hkwNFxOh6RCbOlRsSlaOCAhEwggINMEUGCCsGAQUFBwEBBDkwNzA1BggrBgEFBQcwAYYpaHR0cDovL29jc3AuYXBwbGUuY29tL29jc3AwNC1hcHBsZWFpY2EzMDIwHQYDVR0OBBYEFAIkMAua7u1GMZekplopnkJxghxFMAwGA1UdEwEB/wQCMAAwHwYDVR0jBBgwFoAUI/JJxE+T5O8n5sT2KGw/orv9LkswggEdBgNVHSAEggEUMIIBEDCCAQwGCSqGSIb3Y2QFATCB/jCBwwYIKwYBBQUHAgIwgbYMgbNSZWxpYW5jZSBvbiB0aGlzIGNlcnRpZmljYXRlIGJ5IGFueSBwYXJ0eSBhc3N1bWVzIGFjY2VwdGFuY2Ugb2YgdGhlIHRoZW4gYXBwbGljYWJsZSBzdGFuZGFyZCB0ZXJtcyBhbmQgY29uZGl0aW9ucyBvZiB1c2UsIGNlcnRpZmljYXRlIHBvbGljeSBhbmQgY2VydGlmaWNhdGlvbiBwcmFjdGljZSBzdGF0ZW1lbnRzLjA2BggrBgEFBQcCARYqaHR0cDovL3d3dy5hcHBsZS5jb20vY2VydGlmaWNhdGVhdXRob3JpdHkvMDQGA1UdHwQtMCswKaAnoCWGI2h0dHA6Ly9jcmwuYXBwbGUuY29tL2FwcGxlYWljYTMuY3JsMA4GA1UdDwEB/wQEAwIHgDAPBgkqhkiG92NkBh0EAgUAMAoGCCqGSM49BAMCA0kAMEYCIQDaHGOui+X2T44R6GVpN7m2nEcr6T6sMjOhZ5NuSo1egwIhAL1a+/hp88DKJ0sv3eT3FxWcs71xmbLKD/QJ3mWagrJNMIIC7jCCAnWgAwIBAgIISW0vvzqY2pcwCgYIKoZIzj0EAwIwZzEbMBkGA1UEAwwSQXBwbGUgUm9vdCBDQSAtIEczMSYwJAYDVQQLDB1BcHBsZSBDZXJ0aWZpY2F0aW9uIEF1dGhvcml0eTETMBEGA1UECgwKQXBwbGUgSW5jLjELMAkGA1UEBhMCVVMwHhcNMTQwNTA2MjM0NjMwWhcNMjkwNTA2MjM0NjMwWjB6MS4wLAYDVQQDDCVBcHBsZSBBcHBsaWNhdGlvbiBJbnRlZ3JhdGlvbiBDQSAtIEczMSYwJAYDVQQLDB1BcHBsZSBDZXJ0aWZpY2F0aW9uIEF1dGhvcml0eTETMBEGA1UECgwKQXBwbGUgSW5jLjELMAkGA1UEBhMCVVMwWTATBgcqhkjOPQIBBggqhkjOPQMBBwNCAATwFxGEGddkhdUaXiWBB3bogKLv3nuuTeCN/EuT4TNW1WZbNa4i0Jd2DSJOe7oI/XYXzojLdrtmcL7I6CmE/1RFo4H3MIH0MEYGCCsGAQUFBwEBBDowODA2BggrBgEFBQcwAYYqaHR0cDovL29jc3AuYXBwbGUuY29tL29jc3AwNC1hcHBsZXJvb3RjYWczMB0GA1UdDgQWBBQj8knET5Pk7yfmxPYobD+iu/0uSzAPBgNVHRMBAf8EBTADAQH/MB8GA1UdIwQYMBaAFLuw3qFYM4iapIqZ3r6966/ayySrMDcGA1UdHwQwMC4wLKAqoCiGJmh0dHA6Ly9jcmwuYXBwbGUuY29tL2FwcGxlcm9vdGNhZzMuY3JsMA4GA1UdDwEB/wQEAwIBBjAQBgoqhkiG92NkBgIOBAIFADAKBggqhkjOPQQDAgNnADBkAjA6z3KDURaZsYb7NcNWymK/9Bft2Q91TaKOvvGcgV5Ct4n4mPebWZ+Y1UENj53pwv4CMDIt1UQhsKMFd2xd8zg7kGf9F3wsIW2WT8ZyaYISb1T4en0bmcubCYkhYQaZDwmSHQAAMYIBizCCAYcCAQEwgYYwejEuMCwGA1UEAwwlQXBwbGUgQXBwbGljYXRpb24gSW50ZWdyYXRpb24gQ0EgLSBHMzEmMCQGA1UECwwdQXBwbGUgQ2VydGlmaWNhdGlvbiBBdXRob3JpdHkxEzARBgNVBAoMCkFwcGxlIEluYy4xCzAJBgNVBAYTAlVTAghoYPaZ2cynDzANBglghkgBZQMEAgEFAKCBlTAYBgkqhkiG9w0BCQMxCwYJKoZIhvcNAQcBMBwGCSqGSIb3DQEJBTEPFw0yMTAyMDEwNjQwMzRaMCoGCSqGSIb3DQEJNDEdMBswDQYJYIZIAWUDBAIBBQChCgYIKoZIzj0EAwIwLwYJKoZIhvcNAQkEMSIEICkrcfQNgJwC+ObwK2PDu2WvF+Itf+NH4k9YQpnZS8+WMAoGCCqGSM49BAMCBEYwRAIgJiP5KbLUmKjCpcKrcOLHqmDe4WTnxOLpX09MtdASlz0CIEyiKhZ1i+WRRwYH3fAnd+U21dAlj+FaeodSJQ/s5cJPAAAAAAAA\\",\\"version\\":\\"EC_v1\\",\\"data\\":\\"ifGVeXI0UNe7VWqvU2x09FkNu/ibyRybghrZs1rIxfRKlflwcm0E61u7H6WtY6OZQVxWGO6WVsspGxLGMlCNwnLJvOb/Z9HkEIrR2bKmMGjzkUqL7xwIeTt7JRvrFN54sTqBKeMftLH8KexWA2NQ7TlnfBwtP6ra20JhWkNGAIPDnDKZ0O7p7kaTJfTQp+Mr+LXye+xjMvfoWOXc88tJWthYup55nh8MFdGHHI5lW06N6fiG//jfk+cqc4h2PJ4pOJuuQDwjRPOeCFDyxml3Xo6OYQhC//iFNyMAVUSnjtTx9uPhJDwPSpPALfrRjMyk5vsJRkMa5dC0nDK9TIEUgbxgEVVcZniHz6/qMnacT0xyrUsiW2CVFhZ1uPTKRt2Gu5mIg4gHLGlP+zRjspFNSIoGirC39K8Jq7NZRrvTjUI=\\",\\"header\\":{\\"ephemeralPublicKey\\":\\"MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEp7OKFIRTiBv8aGErDvJC6F2fUwh8VuD5AGe7GMLoY8LFsB7qFSpLcAhw/k2KgQbbpudj1axSmVagn7VbHqcmJg==\\",\\"publicKeyHash\\":\\"vjtnw1HBhuEUhYWDobbbWf6qxhj8kGxh0dLh045enV4=\\",\\"transactionId\\":\\"29b13103be6794bcc2b64bc9f53d9d3032e3d7718b03a61e4723d3d50f855b8f\\"}}"
                }
            }
        }',
          CURLOPT_HTTPHEADER => array(
            'Content-Type: application/vnd.worldpay.payments-v6+json',
            'Authorization: Basic dG5lYnY4NmJjMHlwNG9uMTpsbDAxYTcwZDdhc2xubzYy'
          ),
        ));

        $result = curl_exec($curl);

        curl_close($curl);

         if (!$result) {
            $logger->info('Request could not be sent.');
            $logger->info($result);
            $logger->info('########### END OF REQUEST - FAILURE WHILST TRYING TO SEND REQUEST ###########');
            throw new Exception('AccessWorldpay api service not available');
        }

        $logger->info('Request successfully sent');
        $logger->info($result);
        *
        */

        // @codingStandardsIgnoreStart
        $pluginTrackerDetails = $this->helper->getPluginTrackerdetails();
        $request = $this->_getRequest();
        $logger = $this->_wplogger;
        if (!$url) {
            $url = $this->_getUrl();
        }

        $logger->info('Setting destination URL: ' . $url);
        $logger->info('Initialising request');
        $request->setOption(CURLOPT_POST, self::CURL_POST);
        $request->setOption(CURLOPT_RETURNTRANSFER, self::CURL_RETURNTRANSFER);
        $request->setOption(CURLOPT_NOPROGRESS, self::CURL_NOPROGRESS);
        $request->setOption(CURLOPT_TIMEOUT, self::CURL_TIMEOUT);
        $request->setOption(CURLOPT_VERBOSE, self::CURL_VERBOSE);
        /*SSL verification false*/
        $request->setOption(CURLOPT_SSL_VERIFYHOST, false);
        $request->setOption(CURLOPT_SSL_VERIFYPEER, false);
        if ($quote) {
            //$quote = json_decode($quote, true);
            $requestBody = '{
                "transactionReference": "'.$quote['transactionReference'].'",
                "merchant": {
                    "entity": "'.$this->helper->getMerchantEntityReference().'"
                },
                "instruction": {
                    "narrative": {
                        "line1": "'.$this->helper->getNarrative().'"
                    },
                    "value": {
                        "currency": "'.$quote['instruction']['value']['currency'].'",
                        "amount": '.$quote['instruction']['value']['amount'].'
                    },
                    "paymentInstrument": {
                        "type": "card/wallet+applepay",
                        "walletToken":'.json_encode(json_encode($quote['instruction']['paymentInstrument']['walletToken'])).'
                    }
                }
            }';
            $logger->info(print_r($requestBody, true));
            $request->setOption(CURLOPT_POSTFIELDS, $requestBody);
        }
        $request->setOption(CURLOPT_USERPWD, $username.':'.$password);
        $request->setOption(CURLOPT_HEADER, true);
        $request->setOption(
            CURLOPT_HTTPHEADER,
            ["Content-Type: application/vnd.worldpay.payments-v6+json",
                "MERCHANT_ENTITY_REF"=>$pluginTrackerDetails['MERCHANT_ENTITY_REF'],
                "MERCHANT_ID" => $pluginTrackerDetails['MERCHANT_ID'],
                "MAGENTO_EDITION"=>$pluginTrackerDetails['MAGENTO_EDITION'],
                "MAGENTO_VERSION"=>$pluginTrackerDetails['MAGENTO_VERSION'],
                "PHP_VERSION"=> $pluginTrackerDetails['PHP_VERSION'],
                "CURRENT_WORLDPAY_PLUGIN_VERSION"=>isset($pluginTrackerDetails['CURRENT_WORLDPAY_PLUGIN_VERSION'])?
                $pluginTrackerDetails['CURRENT_WORLDPAY_PLUGIN_VERSION']:"",
                "WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE" =>isset($pluginTrackerDetails['WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE'])?
                $pluginTrackerDetails['WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE']:"",
                "UPGRADE_DATES" => isset($pluginTrackerDetails['UPGRADE_DATES'])?$pluginTrackerDetails['UPGRADE_DATES']:""
            ]
        );
        //$logger->info('Sending Json as: ' . $this->_getObfuscatedXmlLog($quote));
        $request->setOption(CURLINFO_HEADER_OUT, true);

        $request->post($url, $quote);
        $result = $request->getBody();

        //$result = $request->execute();

        //$logger->info(print_r($result,true));
        // logging Headder for 3DS request to check Cookie.
        if ($this->helper->isThreeDSRequest()) {
            //$information = $request->getInfo(CURLINFO_HEADER_OUT);
            $information = $request->getHeaders();
            $logger->info("**REQUEST HEADER START**");
            $logger->info($information);
            $logger->info("**REQUEST HEADER ENDS**");
        }

        if (!$result) {
            $logger->info('Request could not be sent.');
            $logger->info($result);
            $logger->info(
                '########### END OF REQUEST - FAILURE WHILST TRYING TO SEND REQUEST ###########'
            );
            throw new \Magento\Framework\Exception\LocalizedException(
                __('AccessWorldpay api service not available')
            );
        }
        
        $logger->info('Request successfully sent');
        $logger->info($result);

        // extract headers
        $bits = explode("\r\n\r\n", $result);
        $body = array_pop($bits);
        $headers = implode("\r\n\r\n", $bits); 

        // Extracting Cookie from Response Header.
        if (preg_match("/set-cookie: (.+?)([\r\n]|$)/", $headers, $match)) {
            // Keep a hold of the cookie returned incase we need to send a
            // second order message after 3dSecure check
            $logger->info('Cookie Get: ' . $match[1]);
            $this->helper->setWorldpayAuthCookie($match[1]);
        }
        if (preg_match("/Set-Cookie: (.+?)([\r\n]|$)/", $headers, $match)) {
            // Keep a hold of the cookie returned incase we need to send a
            // second order message after 3dSecure check
                $logger->info('Cookie Get: ' . $match[1]);
                $this->helper->setAccessWorldpayAuthCookie($match[1]);
        }
        //return $body;
        $jsonData = json_decode($body, true);
        //$logger->info('Body'.$jsonData);
        try {
            $xml = $this->_array2xml($jsonData, false, $orderCode);
        } catch (Exception $e) {
            $logger->error($e->getMessage());
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Something happened, please refresh the page and try again.')
            );
        }
        return $xml;
        // @codingStandardsIgnoreEnd
    }

    /**
     * Send DdcRequest
     *
     * @param object $orderCode
     * @param string $username
     * @param string $password
     * @param string $url
     * @param int $quote
     * @return json
     */
    public function sendDdcRequest($orderCode, $username, $password, $url, $quote = null)
    {
        $pluginTrackerDetails = $this->helper->getPluginTrackerdetails();
        $request = $this->_getRequest();
        $logger = $this->_wplogger;
        if (!$url) {
            $url = $this->_getUrl();
        }

        $logger->info('Setting destination URL: ' . $url);
        $logger->info('Initialising request');
        $request->setOption(CURLOPT_POST, self::CURL_POST);
        $request->setOption(CURLOPT_RETURNTRANSFER, self::CURL_RETURNTRANSFER);
        $request->setOption(CURLOPT_NOPROGRESS, self::CURL_NOPROGRESS);
        $request->setOption(CURLOPT_TIMEOUT, self::CURL_TIMEOUT);
        $request->setOption(CURLOPT_VERBOSE, self::CURL_VERBOSE);
        /*SSL verification false*/
        $request->setOption(CURLOPT_SSL_VERIFYHOST, false);
        $request->setOption(CURLOPT_SSL_VERIFYPEER, false);
        if ($quote) {
            $request->setOption(CURLOPT_POSTFIELDS, $quote);
        }
        $request->setOption(CURLOPT_USERPWD, $username.':'.$password);

        $request->setOption(CURLOPT_HEADER, true);
        $request->setOption(
            CURLOPT_HTTPHEADER,
            ["Content-Type: application/vnd.worldpay.verifications.customers-v2.hal+json",
                "MERCHANT_ENTITY_REF"=>$pluginTrackerDetails['MERCHANT_ENTITY_REF'],
                "MERCHANT_ID" => $pluginTrackerDetails['MERCHANT_ID'],
                "MAGENTO_EDITION"=>$pluginTrackerDetails['MAGENTO_EDITION'],
                "MAGENTO_VERSION"=>$pluginTrackerDetails['MAGENTO_VERSION'],
                "PHP_VERSION"=> $pluginTrackerDetails['PHP_VERSION'],
                "CURRENT_WORLDPAY_PLUGIN_VERSION"
                =>isset($pluginTrackerDetails['CURRENT_WORLDPAY_PLUGIN_VERSION'])?
                $pluginTrackerDetails['CURRENT_WORLDPAY_PLUGIN_VERSION']:"",
                "WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE"
                =>isset($pluginTrackerDetails['WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE'])?
                $pluginTrackerDetails['WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE']:"",
                "UPGRADE_DATES" => isset($pluginTrackerDetails['UPGRADE_DATES'])
                ?$pluginTrackerDetails['UPGRADE_DATES']:""
            ]
        );
        //$logger->info('Sending Json as: ' . $this->_getObfuscatedXmlLog($quote));

        $request->setOption(CURLINFO_HEADER_OUT, true);
        $request->post($url, $quote);
        $result = $request->getBody();
        if (!$result) {
            $logger->info('Request could not be sent.');
            $logger->info($result);
            $logger->info(
                '########### END OF REQUEST - FAILURE WHILST TRYING TO SEND REQUEST ###########'
            );
            throw new \Magento\Framework\Exception\LocalizedException(
                __('AccessWorldpay api service not available')
            );
        }
        $logger->info('Request successfully sent');
        $logger->info($result);

        // extract headers
        $bits = explode("\r\n\r\n", $result);
        $body = array_pop($bits);
        $headers = implode("\r\n\r\n", $bits);

        // Extracting Cookie from Response Header.
        if (preg_match("/set-cookie: (.+?)([\r\n]|$)/", $headers, $match)) {
            // Keep a hold of the cookie returned incase we need to send a
            // second order message after 3dSecure check
            $logger->info('Cookie Get: ' . $match[1]);

            $this->helper->setWorldpayAuthCookie($match[1]);

        }
        if (preg_match("/Set-Cookie: (.+?)([\r\n]|$)/", $headers, $match)) {
            // Keep a hold of the cookie returned incase we need to send a
            // second order message after 3dSecure check
                $logger->info('Cookie Get: ' . $match[1]);
                $this->helper->setAccessWorldpayAuthCookie($match[1]);
        }
        //return $body;
        $jsonData = json_decode($body, true);
        //$xml = $this->_array2xml($jsonData, false, $orderCode);
        return $jsonData;
    }

    /**
     * Get verified token request
     *
     * @param array $verifiedTokenRequest
     * @param string $username
     * @param string $password
     * @return json
     */
    public function getVerifiedToken($verifiedTokenRequest, $username, $password)
    {
        $quote = null;
        $pluginTrackerDetails = $this->helper->getPluginTrackerdetails();
        $request = $this->_getRequest();
        $logger = $this->_wplogger;
        //$url = $this->_getUrl();
        $url = str_replace('/payments/authorizations', '/verifiedTokens/cardOnFile', $this->_getUrl());
        $logger->info('Setting destination URL: ' . $url);
        $logger->info('Initialising verified token request');
        $request->setOption(CURLOPT_POST, self::CURL_POST);
        $request->setOption(CURLOPT_RETURNTRANSFER, self::CURL_RETURNTRANSFER);
        $request->setOption(CURLOPT_NOPROGRESS, self::CURL_NOPROGRESS);
        $request->setOption(CURLOPT_TIMEOUT, self::CURL_TIMEOUT);
        $request->setOption(CURLOPT_VERBOSE, self::CURL_VERBOSE);
        /*SSL verification false*/
        $request->setOption(CURLOPT_SSL_VERIFYHOST, false);
        $request->setOption(CURLOPT_SSL_VERIFYPEER, false);
        $request->setOption(CURLOPT_POSTFIELDS, $verifiedTokenRequest);
        $request->setOption(CURLOPT_USERPWD, $username.':'.$password);

        $request->setOption(CURLOPT_HEADER, true);
        $request->setOption(
            CURLOPT_HTTPHEADER,
            ["Content-Type: application/vnd.worldpay.verified-tokens-v2.hal+json",
                "MERCHANT_ENTITY_REF"=>$pluginTrackerDetails['MERCHANT_ENTITY_REF'],
                "MERCHANT_ID" => $pluginTrackerDetails['MERCHANT_ID'],
                "MAGENTO_EDITION"=>$pluginTrackerDetails['MAGENTO_EDITION'],
                "MAGENTO_VERSION"=>$pluginTrackerDetails['MAGENTO_VERSION'],
                "PHP_VERSION"=> $pluginTrackerDetails['PHP_VERSION'],
                "CURRENT_WORLDPAY_PLUGIN_VERSION"
                =>isset($pluginTrackerDetails['CURRENT_WORLDPAY_PLUGIN_VERSION'])?
                $pluginTrackerDetails['CURRENT_WORLDPAY_PLUGIN_VERSION']:"",
                "WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE"
                =>isset($pluginTrackerDetails['WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE'])?
                $pluginTrackerDetails['WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE']:"",
                "UPGRADE_DATES" => isset($pluginTrackerDetails['UPGRADE_DATES'])
                ?$pluginTrackerDetails['UPGRADE_DATES']:""
            ]
        );
        $logger->info('Sending Json as: ' . $verifiedTokenRequest);

        $request->setOption(CURLINFO_HEADER_OUT, true);
        $request->post($url, $verifiedTokenRequest);
        $result = $request->getBody();

        //$result = $request->execute();

        // logging Headder for 3DS request to check Cookie.
        if ($this->helper->isThreeDSRequest()) {
            //$information = $request->getInfo(CURLINFO_HEADER_OUT);
            $information = $request->getHeaders();
            $logger->info("**REQUEST HEADER START**");
            $logger->info($information);
            $logger->info("**REQUEST HEADER ENDS**");
        }

        if (!$result) {
            $logger->info('Request could not be sent.');
            $logger->info($result);
            $logger->info(
                '########### END OF REQUEST - FAILURE WHILST TRYING TO SEND REQUEST ###########'
            );
            throw new \Magento\Framework\Exception\LocalizedException(
                'AccessWorldpay api service not available'
            );
        }
        $httpCode = $request->getStatus();
        //$httpCode = $request->getInfo(CURLINFO_HTTP_CODE);
       
        $logger->info('Request successfully sent for tokenisation ........................');
        $logger->info($result);

        // extract headers
        $bits = explode("\r\n\r\n", $result);
        $body = array_pop($bits);
        $headers = implode("\r\n\r\n", $bits);
        $jsonResponse = json_decode($body, true);
        
        /* Check Error */
        $this->_checkErrorForEmailSend($verifiedTokenRequest, $jsonResponse);
        
        // Extracting Cookie from Response Header.
        if (preg_match("/set-cookie: (.+?)([\r\n]|$)/", $headers, $match)) {
            // Keep a hold of the cookie returned incase we need to send a
            // second order message after 3dSecure check
            $logger->info('Cookie Get: ' . $match[1]);
            $this->helper->setWorldpayAuthCookie($match[1]);
        }
        if (preg_match("/Set-Cookie: (.+?)([\r\n]|$)/", $headers, $match)) {
            // Keep a hold of the cookie returned incase we need to send a
            // second order message after 3dSecure check
                $logger->info('Cookie Get: ' . $match[1]);
                $this->helper->setAccessWorldpayAuthCookie($match[1]);
        }
        /*Conflict Resolution*/
        $verifiedTokenResponseToArray = json_decode($body, true);
        $verifiedTokenResponseToArray['response_code']=$httpCode;
        $body = json_encode($verifiedTokenResponseToArray, true);
        return $body;
    }

    /**
     * Get Session href for direct
     *
     * @param string $url
     * @param string $params
     * @param string $username
     * @param string $password
     * @return json
     */
    public function getSessionHrefForDirect($url, $params, $username, $password)
    {
        $quote = null;
        $pluginTrackerDetails = $this->helper->getPluginTrackerdetails();
        $request = $this->_getRequest();
        $logger = $this->_wplogger;
        $logger->info('Setting destination URL: ' . $url);
        $logger->info('Initialising session href request');
        $request->setOption(CURLOPT_POST, self::CURL_POST);
        $request->setOption(CURLOPT_RETURNTRANSFER, self::CURL_RETURNTRANSFER);
        $request->setOption(CURLOPT_NOPROGRESS, self::CURL_NOPROGRESS);
        $request->setOption(CURLOPT_TIMEOUT, self::CURL_TIMEOUT);
        $request->setOption(CURLOPT_VERBOSE, self::CURL_VERBOSE);
        /*SSL verification false*/
        $request->setOption(CURLOPT_SSL_VERIFYHOST, false);
        $request->setOption(CURLOPT_SSL_VERIFYPEER, false);
        $request->setOption(CURLOPT_POSTFIELDS, $params);
        $request->setOption(CURLOPT_USERPWD, $username.':'.$password);

        $request->setOption(CURLOPT_HEADER, true);
        $request->setOption(
            CURLOPT_HTTPHEADER,
            ["Content-Type: application/vnd.worldpay.verified-tokens-v2.hal+json",
                "MERCHANT_ENTITY_REF"=>$pluginTrackerDetails['MERCHANT_ENTITY_REF'],
                "MERCHANT_ID" => $pluginTrackerDetails['MERCHANT_ID'],
                "MAGENTO_EDITION"=>$pluginTrackerDetails['MAGENTO_EDITION'],
                "MAGENTO_VERSION"=>$pluginTrackerDetails['MAGENTO_VERSION'],
                "PHP_VERSION"=> $pluginTrackerDetails['PHP_VERSION'],
                "CURRENT_WORLDPAY_PLUGIN_VERSION"
                =>isset($pluginTrackerDetails['CURRENT_WORLDPAY_PLUGIN_VERSION'])?
                $pluginTrackerDetails['CURRENT_WORLDPAY_PLUGIN_VERSION']:"",
                "WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE"
                =>isset($pluginTrackerDetails['WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE'])?
                $pluginTrackerDetails['WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE']:"",
                "UPGRADE_DATES" => isset($pluginTrackerDetails['UPGRADE_DATES'])
                ?$pluginTrackerDetails['UPGRADE_DATES']:""
            ]
        );
        //$logger->info('Sending Json as: ' . $params);

        $request->setOption(CURLINFO_HEADER_OUT, true);
        $request->post($url, $quote);
        $result = $request->getBody();
        if (!$result) {
            $logger->info('Request could not be sent.');
            $logger->info($result);
            $logger->info(
                '########### END OF REQUEST - FAILURE WHILST TRYING TO SEND REQUEST ###########'
            );
            throw new \Magento\Framework\Exception\LocalizedException(
                __('AccessWorldpay api service not available')
            );
        }
        $httpCode = $request->getStatus();
       // $httpCode = $request->getInfo(CURLINFO_HTTP_CODE);
        $logger->info($result);

        // extract headers
        $bits = explode("\r\n\r\n", $result);
        $body = array_pop($bits);
        $headers = implode("\r\n\r\n", $bits);

        // Extracting Cookie from Response Header.
        if (preg_match("/set-cookie: (.+?)([\r\n]|$)/", $headers, $match)) {
            // Keep a hold of the cookie returned incase we need to send a
            // second order message after 3dSecure check
            $logger->info('Cookie Get: ' . $match[1]);
            $this->helper->setWorldpayAuthCookie($match[1]);
        }
        if (preg_match("/Set-Cookie: (.+?)([\r\n]|$)/", $headers, $match)) {
            // Keep a hold of the cookie returned incase we need to send a
            // second order message after 3dSecure check
                $logger->info('Cookie Get: ' . $match[1]);
                $this->helper->setAccessWorldpayAuthCookie($match[1]);
        }
        /*Conflict Resolution*/
        $sessionHrefToArray = json_decode($body, true);
        $sessionHrefToArray['response_code']=$httpCode;
        $body = json_encode($sessionHrefToArray, true);
        return $body;
    }

    /**
     * Get detailed verified token
     *
     * @param string $verifiedToken
     * @param string $username
     * @param string $password
     * @return json
     */
    public function getDetailedVerifiedToken($verifiedToken, $username, $password)
    {
        // @codingStandardsIgnoreStart
        $pluginTrackerDetails = $this->helper->getPluginTrackerdetails();
        $logger = $this->_wplogger;
        $curl = curl_init();

        curl_setopt_array($curl, [
          CURLOPT_URL => $verifiedToken,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "GET",
          CURLOPT_USERPWD => $username.':'.$password,
          CURLOPT_HTTPHEADER => [
            "Content-Type: application/vnd.worldpay.verified-tokens-v2.hal+json",
                "MERCHANT_ENTITY_REF"=>$pluginTrackerDetails['MERCHANT_ENTITY_REF'],
                "MERCHANT_ID" => $pluginTrackerDetails['MERCHANT_ID'],
                "MAGENTO_EDITION"=>$pluginTrackerDetails['MAGENTO_EDITION'],
                "MAGENTO_VERSION"=>$pluginTrackerDetails['MAGENTO_VERSION'],
                "PHP_VERSION"=> $pluginTrackerDetails['PHP_VERSION'],
                "CURRENT_WORLDPAY_PLUGIN_VERSION"=>isset($pluginTrackerDetails['CURRENT_WORLDPAY_PLUGIN_VERSION'])?
                $pluginTrackerDetails['CURRENT_WORLDPAY_PLUGIN_VERSION']:"",
                "WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE"
                =>isset($pluginTrackerDetails['WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE'])?
                $pluginTrackerDetails['WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE']:"",
                "UPGRADE_DATES" => isset($pluginTrackerDetails['UPGRADE_DATES'])
                ?$pluginTrackerDetails['UPGRADE_DATES']:""
          ],
        ]);
        $response = curl_exec($curl);

        curl_close($curl);

        if (!$response) {
            $logger->info('Request could not be sent.');
            $logger->info(
                '########### END OF REQUEST - FAILURE WHILST TRYING TO SEND REQUEST ###########'
            );
            throw new \Magento\Framework\Exception\LocalizedException(
                __('AccessWorldpay api service not available')
            );
        }
        $logger->info('Request successfully sent for Detailed Verified Tokenisation ..........');
        $logger->info($response);

        return $response;
        // @codingStandardsIgnoreEnd
    }

    /**
     * Get detailed token for brand
     *
     * @param string $verifiedToken
     * @param string $username
     * @param string $password
     * @return json
     */
    public function getDetailedTokenForBrand($verifiedToken, $username, $password)
    {
        // @codingStandardsIgnoreStart
        $pluginTrackerDetails = $this->helper->getPluginTrackerdetails();
        $logger = $this->_wplogger;
        $curl = curl_init();

        curl_setopt_array($curl, [
          CURLOPT_URL => $verifiedToken,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "GET",
          CURLOPT_USERPWD => $username.':'.$password,
          CURLOPT_HTTPHEADER => [
            "Content-Type: application/vnd.worldpay.tokens-v2.hal+json",
               "MERCHANT_ENTITY_REF"=>$pluginTrackerDetails['MERCHANT_ENTITY_REF'],
                "MERCHANT_ID" => $pluginTrackerDetails['MERCHANT_ID'],
                "MAGENTO_EDITION"=>$pluginTrackerDetails['MAGENTO_EDITION'],
                "MAGENTO_VERSION"=>$pluginTrackerDetails['MAGENTO_VERSION'],
                "PHP_VERSION"=> $pluginTrackerDetails['PHP_VERSION'],
                "CURRENT_WORLDPAY_PLUGIN_VERSION"
                =>isset($pluginTrackerDetails['CURRENT_WORLDPAY_PLUGIN_VERSION'])?
                $pluginTrackerDetails['CURRENT_WORLDPAY_PLUGIN_VERSION']:"",
                "WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE"
                =>isset($pluginTrackerDetails['WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE'])?
                $pluginTrackerDetails['WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE']:"",
                "UPGRADE_DATES" => isset($pluginTrackerDetails['UPGRADE_DATES'])
                ?$pluginTrackerDetails['UPGRADE_DATES']:""
          ],
        ]);
        $response = curl_exec($curl);
        curl_close($curl);
        if (!$response) {
            $logger->info('Request could not be sent.');
            $logger->info(
                '########### END OF REQUEST - FAILURE WHILST TRYING TO SEND REQUEST ###########'
            );
            throw new \Magento\Framework\Exception\LocalizedException(
                __('AccessWorldpay api service not available')
            );
        }
        $logger->info('Request successfully sent for Detailed Token ........................');
        $logger->info($response);

        return $response;
        // @codingStandardsIgnoreEnd
    }

    /**
     *  Get token enquery
     *
     * @param string $verifiedToken
     * @param string $username
     * @param string $password
     */

    public function getTokenInquiry($verifiedToken, $username, $password)
    {
        // @codingStandardsIgnoreStart
        $pluginTrackerDetails = $this->helper->getPluginTrackerdetails();
        $logger = $this->_wplogger;
        $curl = curl_init();
        curl_setopt_array($curl, [
          CURLOPT_URL => $verifiedToken,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => "",
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 0,
          CURLOPT_FOLLOWLOCATION => true,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => "GET",
          CURLOPT_USERPWD => $username.':'.$password,
          CURLOPT_HTTPHEADER => [
            "Content-Type: application/vnd.worldpay.verified-tokens-v2.hal+json",
                "MERCHANT_ENTITY_REF"=>$pluginTrackerDetails['MERCHANT_ENTITY_REF'],
                "MERCHANT_ID" => $pluginTrackerDetails['MERCHANT_ID'],
                "MAGENTO_EDITION"=>$pluginTrackerDetails['MAGENTO_EDITION'],
                "MAGENTO_VERSION"=>$pluginTrackerDetails['MAGENTO_VERSION'],
                "PHP_VERSION"=> $pluginTrackerDetails['PHP_VERSION'],
                "CURRENT_WORLDPAY_PLUGIN_VERSION"
                    =>isset($pluginTrackerDetails['CURRENT_WORLDPAY_PLUGIN_VERSION'])?
                $pluginTrackerDetails['CURRENT_WORLDPAY_PLUGIN_VERSION']:"",
                "WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE"
                    =>isset($pluginTrackerDetails['WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE'])?
                $pluginTrackerDetails['WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE']:"",
                "UPGRADE_DATES" => isset($pluginTrackerDetails['UPGRADE_DATES'])
                    ?$pluginTrackerDetails['UPGRADE_DATES']:""
          ],
        ]);

        $response = curl_exec($curl);

        curl_close($curl);
        if (!$response) {
            $logger->info('Request could not be sent.');
            $logger->info(
                '########### END OF REQUEST - FAILURE WHILST TRYING TO SEND REQUEST ###########'
            );
            throw new \Magento\Framework\Exception\LocalizedException(
                __('AccessWorldpay api service not available')
            );
        }

        $logger->info('Request successfully sent for getTokenInquiry from My Account................');
        $bits = explode("\r\n\r\n", $response);
        $body = array_pop($bits);
        $jsonResponse = json_decode($body, true);

        /* Check Error */
        $this->_checkErrorForEmailSend($verifiedToken, $jsonResponse);

        $logger->info($response);
        return $response;
        // @codingStandardsIgnoreEnd
    }

    /**
     * Get token delete
     *
     * @param string $deleteTokenUrl
     * @param string $username
     * @param string $password
     */
    public function getTokenDelete($deleteTokenUrl, $username, $password)
    {
        // @codingStandardsIgnoreStart
        $pluginTrackerDetails = $this->helper->getPluginTrackerdetails();
        $logger = $this->_wplogger;
        $logger->info($deleteTokenUrl);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $deleteTokenUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            ["Content-Type: application/vnd.worldpay.verified-tokens-v2.hal+json",
                "MERCHANT_ENTITY_REF"=>$pluginTrackerDetails['MERCHANT_ENTITY_REF'],
                "MERCHANT_ID" => $pluginTrackerDetails['MERCHANT_ID'],
                "MAGENTO_EDITION"=>$pluginTrackerDetails['MAGENTO_EDITION'],
                "MAGENTO_VERSION"=>$pluginTrackerDetails['MAGENTO_VERSION'],
                "PHP_VERSION"=> $pluginTrackerDetails['PHP_VERSION'],
                "CURRENT_WORLDPAY_PLUGIN_VERSION"
                    =>isset($pluginTrackerDetails['CURRENT_WORLDPAY_PLUGIN_VERSION'])?
                $pluginTrackerDetails['CURRENT_WORLDPAY_PLUGIN_VERSION']:"",
                "WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE"
                    =>isset($pluginTrackerDetails['WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE'])?
                $pluginTrackerDetails['WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE']:"",
                "UPGRADE_DATES"=> isset($pluginTrackerDetails['UPGRADE_DATES'])
                    ?$pluginTrackerDetails['UPGRADE_DATES']:""
            ]
        );
        //curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_USERPWD, $username.':'.$password);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);    // we want headers
        curl_setopt($ch, CURLOPT_NOBODY, true);    // we don't need body
        $result = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $logger->info('Request successfully sent for delete token ........................');
        curl_close($ch);
        return $httpcode;
        // @codingStandardsIgnoreEnd
    }

    /**
     * Censors sensitive data before outputting to the log file
     *
     * @param int $quote
     * @return json
     */
    protected function _getObfuscatedXmlLog($quote)
    {
        $result = json_decode($quote);
        $cardNumber = str_repeat('X', strlen($result->instruction->paymentInstrument->cardNumber));
        $result->instruction->paymentInstrument->cardNumber = $cardNumber;
        return json_encode($result);
    }

    /**
     * Get URL of merchant site based on environment mode
     *
     * @return string
     */
    private function _getUrl()
    {
        if ($this->helper->getEnvironmentMode()=='Live Mode') {
            return $this->helper->getLiveUrl();
        }
        return $this->helper->getTestUrl();
    }

    /**
     * Get Request
     *
     * @return object
     */
    private function _getRequest()
    {
        if ($this->_request === null) {
            $this->_request = $this->curlrequest;
        }
        return $this->_request;
    }

     /**
      * Process the request
      *
      * @param string $username
      * @param string $password
      * @param string $url
      * @param INT $quote
      * @return SimpleXMLElement body
      * @throws Exception
      */
    public function putRequest($username, $password, $url, $quote = null)
    {
        // @codingStandardsIgnoreStart
        $pluginTrackerDetails = $this->helper->getPluginTrackerdetails();
        $logger = $this->_wplogger;
        $logger->info('Setting destination URL: ' . $url);
        $logger->info('Initialising request');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            ["Content-Type: application/vnd.worldpay.tokens-v2.hal+json",
                "MERCHANT_ENTITY_REF"=>$pluginTrackerDetails['MERCHANT_ENTITY_REF'],
                "MERCHANT_ID" => $pluginTrackerDetails['MERCHANT_ID'],
                "MAGENTO_EDITION"=>$pluginTrackerDetails['MAGENTO_EDITION'],
                "MAGENTO_VERSION"=>$pluginTrackerDetails['MAGENTO_VERSION'],
                "PHP_VERSION"=> $pluginTrackerDetails['PHP_VERSION'],
                "CURRENT_WORLDPAY_PLUGIN_VERSION"
                    =>isset($pluginTrackerDetails['CURRENT_WORLDPAY_PLUGIN_VERSION'])?
                $pluginTrackerDetails['CURRENT_WORLDPAY_PLUGIN_VERSION']:"",
                "WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE"
                    =>isset($pluginTrackerDetails['WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE'])?
                $pluginTrackerDetails['WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE']:"",
                "UPGRADE_DATES" => isset($pluginTrackerDetails['UPGRADE_DATES'])
                    ?$pluginTrackerDetails['UPGRADE_DATES']:""
            ]
        );
        curl_setopt($ch, CURLOPT_USERPWD, $username.':'.$password);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $quote);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_NOPROGRESS, self::CURL_NOPROGRESS);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::CURL_TIMEOUT);
        curl_setopt($ch, CURLOPT_VERBOSE, self::CURL_VERBOSE);
        /*SSL verification false*/
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, true);    // we want headers
        $result = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $logger->info('Request successfully sent for update token from My Account ..............');
        $resultArray = explode("\r\n\r\n", $result);
        $body = array_pop($resultArray);
        $logger->info(print_r($body, true));
        $bodyArray = json_decode($body, true);
        
        /* Check Error */
        $this->_checkErrorForEmailSend($result, $bodyArray);
        curl_close($ch);
        if ($httpcode == 204) {
            return $httpcode;
        } else {
            return $bodyArray;
        }

        // logging Headder for 3DS request to check Cookie.
        if ($this->helper->isThreeDSRequest()) {
            $information = $request->getInfo(CURLINFO_HEADER_OUT);
            $logger->info("**REQUEST HEADER START**");
            $logger->info($information);
            $logger->info("**REQUEST HEADER ENDS**");
        }

        if (!$result) {
            $logger->info('Request could not be sent.');
            $logger->info($result);
            $logger->info(
                '########### END OF REQUEST - FAILURE WHILST TRYING TO PUT REQUEST ###########'
            );
            throw new \Magento\Framework\Exception\LocalizedException(
                __('AccessWorldpay api service not available for put request')
            );
        }
        
        $logger->info('Request successfully sent');
        $logger->info($result);

        // extract headers
        $bits = explode("\r\n\r\n", $result);
        $body = array_pop($bits);
        $headers = implode("\r\n\r\n", $bits);

        // Extracting Cookie from Response Header.
        if (preg_match("/set-cookie: (.+?)([\r\n]|$)/", $headers, $match)) {
            // Keep a hold of the cookie returned incase we need to send a
            // second order message after 3dSecure check
            $logger->info('Cookie Get: ' . $match[1]);

            $this->helper->setWorldpayAuthCookie($match[1]);

        }
        if (preg_match("/Set-Cookie: (.+?)([\r\n]|$)/", $headers, $match)) {
            // Keep a hold of the cookie returned incase we need to send a
            // second order message after 3dSecure check
                $logger->info('Cookie Get: ' . $match[1]);
                $this->helper->setAccessWorldpayAuthCookie($match[1]);
        }
        //return $body;
        $jsonData = json_decode($body, true);
        $xml = $this->_array2xml($jsonData, false);
        return $xml;
        // @codingStandardsIgnoreEnd
    }

     /**
      * Array to xml
      *
      * @param array $array
      * @param string $xml
      * @param string $orderCode
      * @return SimpleXMLElement body
      * @throws Exception
      */
    public function _array2xml($array, $xml = false, $orderCode = null)
    {
        if ($xml === false) {
            $xml = new \SimpleXmlElement('<result/>');
        }
        foreach ($array as $key => $value) {
            if ($key !== 'riskFactors' && $key !== 'curies') {
                if (is_array($value)) {
                    $this->_array2xml($value, $xml->addChild($key));
                } else {
                    $xml->addChild($key, $value);
                }
            }
        }
        if ($orderCode) {
            $xml->addChild('orderCode', $orderCode);
        }
        return $xml->asXML();
    }

    /**
     * Resolve Token Conflict
     *
     * @param string $username
     * @param string $password
     * @param string $url
     */
    public function resolveConflict($username, $password, $url)
    {
        $this->_wplogger->info('Resolve Conflict URL: '.$url);
        $responseArray=[];
        $responseData = $this->getConflictDetails($username, $password, $url);
        $responseDataArray = json_decode($responseData, true);
        if ($responseDataArray['response_code']!=404) {
            $this->_wplogger->info('Conflict Detail Response: '.$responseData);
            if (isset($responseDataArray['conflicts']['paymentInstrument']['cardHolderName'])) {
                $resolveCardHolderNameResponse = $this->resolveConflictData(
                    $username,
                    $password,
                    $responseDataArray['_links']['tokens:cardHolderName']['href'],
                    json_encode($responseDataArray['conflicts']['paymentInstrument']['cardHolderName'])
                );
                $responseArray['nameConflict']=$resolveCardHolderNameResponse;
                $this->_wplogger->info('Resolve Name Conflict Response: '
                        .$resolveCardHolderNameResponse);
            }
            if (isset($responseDataArray['conflicts']['paymentInstrument']['cardExpiryDate'])) {
                $resolveCardExpiryDateResponse = $this->resolveConflictData(
                    $username,
                    $password,
                    $responseDataArray['_links']['tokens:cardExpiryDate']['href'],
                    json_encode($responseDataArray['conflicts']['paymentInstrument']['cardExpiryDate'])
                );
                $responseArray['dateConflict']=$resolveCardExpiryDateResponse;
                $this->_wplogger->info('Resolve ExpiryDate Conflict Response: '
                        .$resolveCardExpiryDateResponse);
            }
        }
        return $responseArray;
    }

    /**
     * Get Conflict Detail
     *
     * @param string $username
     * @param string $password
     * @param string $url
     */
    public function getConflictDetails($username, $password, $url)
    {
        // @codingStandardsIgnoreStart
        $pluginTrackerDetails = $this->helper->getPluginTrackerdetails();
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_USERPWD, $username.':'.$password);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/vnd.worldpay.tokens-v2.hal+json",
                "Accept: application/vnd.worldpay.tokens-v2.hal+json",
                "MERCHANT_ENTITY_REF"=>$pluginTrackerDetails['MERCHANT_ENTITY_REF'],
                "MERCHANT_ID" => $pluginTrackerDetails['MERCHANT_ID'],
                "MAGENTO_EDITION"=>$pluginTrackerDetails['MAGENTO_EDITION'],
                "MAGENTO_VERSION"=>$pluginTrackerDetails['MAGENTO_VERSION'],
                "PHP_VERSION"=> $pluginTrackerDetails['PHP_VERSION'],
                "CURRENT_WORLDPAY_PLUGIN_VERSION"
                    =>isset($pluginTrackerDetails['CURRENT_WORLDPAY_PLUGIN_VERSION'])?
                $pluginTrackerDetails['CURRENT_WORLDPAY_PLUGIN_VERSION']:"",
                "WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE"
                    =>isset($pluginTrackerDetails['WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE'])?
                $pluginTrackerDetails['WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE']:"",
                "UPGRADE_DATES" => isset($pluginTrackerDetails['UPGRADE_DATES'])
                    ?$pluginTrackerDetails['UPGRADE_DATES']:""
              ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        /*SSL verification false*/
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, true);    // we want headers
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $bits = explode("\r\n\r\n", $result);
        $body = array_pop($bits);
        curl_close($ch);
        $verifiedTokenResponseToArray = json_decode($body, true);
        $verifiedTokenResponseToArray['response_code']=$httpCode;
        $body = json_encode($verifiedTokenResponseToArray, true);
        return $body;
        // @codingStandardsIgnoreEnd
    }

    /**
     * Resolve Cardholder name Conflict
     *
     * @param string $username
     * @param string $password
     * @param string $url
     * @param array $data
     */

    public function resolveConflictData($username, $password, $url, $data)
    {
        // @codingStandardsIgnoreStart
        $pluginTrackerDetails = $this->helper->getPluginTrackerdetails();
        $this->_wplogger->info('Conflict Data'.$data);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_USERPWD, $username.':'.$password);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/vnd.worldpay.tokens-v2.hal+json",
                "Accept: application/vnd.worldpay.tokens-v2.hal+json",
                "MERCHANT_ENTITY_REF"=>$pluginTrackerDetails['MERCHANT_ENTITY_REF'],
                "MERCHANT_ID" => $pluginTrackerDetails['MERCHANT_ID'],
                "MAGENTO_EDITION"=>$pluginTrackerDetails['MAGENTO_EDITION'],
                "MAGENTO_VERSION"=>$pluginTrackerDetails['MAGENTO_VERSION'],
                "PHP_VERSION"=> $pluginTrackerDetails['PHP_VERSION'],
                "CURRENT_WORLDPAY_PLUGIN_VERSION"
                    =>isset($pluginTrackerDetails['CURRENT_WORLDPAY_PLUGIN_VERSION'])?
                $pluginTrackerDetails['CURRENT_WORLDPAY_PLUGIN_VERSION']:"",
                "WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE"
                    =>isset($pluginTrackerDetails['WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE'])?
                $pluginTrackerDetails['WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE']:"",
                "UPGRADE_DATES" => isset($pluginTrackerDetails['UPGRADE_DATES'])
                    ?$pluginTrackerDetails['UPGRADE_DATES']:""
              ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        /*SSL verification false*/
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HEADER, true);    // we want headers
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        return $httpCode;
        // @codingStandardsIgnoreEnd
    }

    /**
     * Send saved card cardOn file verification request
     *
     * @param object $orderCode
     * @param string $username
     * @param string $password
     * @param string $url
     * @param int $quote
     */
    public function sendSavedCardCardOnFileVerificationRequest(
        $orderCode,
        $username,
        $password,
        $url,
        $quote = null
    ) {
        // @codingStandardsIgnoreStart
        $pluginTrackerDetails = $this->helper->getPluginTrackerdetails();
        $request = $this->_getRequest();
        $logger = $this->_wplogger;
        if (!$url) {
            $url = $this->_getUrl();
        }

        $logger->info('Setting destination URL: ' . $url);
        $logger->info('Initialising request');
        $request->setOption(CURLOPT_POST, self::CURL_POST);
        $request->setOption(CURLOPT_RETURNTRANSFER, self::CURL_RETURNTRANSFER);
        $request->setOption(CURLOPT_NOPROGRESS, self::CURL_NOPROGRESS);
        $request->setOption(CURLOPT_TIMEOUT, self::CURL_TIMEOUT);
        $request->setOption(CURLOPT_VERBOSE, self::CURL_VERBOSE);
        /*SSL verification false*/
        $request->setOption(CURLOPT_SSL_VERIFYHOST, false);
        $request->setOption(CURLOPT_SSL_VERIFYPEER, false);
        if ($quote) {
            $request->setOption(CURLOPT_POSTFIELDS, $quote);
        }
        $request->setOption(CURLOPT_USERPWD, $username.':'.$password);

        $request->setOption(CURLOPT_HEADER, true);
        $request->setOption(
            CURLOPT_HTTPHEADER,
            ["Content-Type: application/vnd.worldpay.verifications.accounts-v5+json",
            "Accept: application/vnd.worldpay.verifications.accounts-v5+json",
                "MERCHANT_ENTITY_REF"=>$pluginTrackerDetails['MERCHANT_ENTITY_REF'],
                "MERCHANT_ID" => $pluginTrackerDetails['MERCHANT_ID'],
                "MAGENTO_EDITION"=>$pluginTrackerDetails['MAGENTO_EDITION'],
                "MAGENTO_VERSION"=>$pluginTrackerDetails['MAGENTO_VERSION'],
                "PHP_VERSION"=> $pluginTrackerDetails['PHP_VERSION'],
                "CURRENT_WORLDPAY_PLUGIN_VERSION"
                    =>isset($pluginTrackerDetails['CURRENT_WORLDPAY_PLUGIN_VERSION'])?
                $pluginTrackerDetails['CURRENT_WORLDPAY_PLUGIN_VERSION']:"",
                "WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE"
                    =>isset($pluginTrackerDetails['WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE'])?
                $pluginTrackerDetails['WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE']:"",
                "UPGRADE_DATES" => isset($pluginTrackerDetails['UPGRADE_DATES'])
                    ?$pluginTrackerDetails['UPGRADE_DATES']:""
            ]
        );
        //$logger->info('Sending Json as: ' . $this->_getObfuscatedXmlLog($quote));

        $request->setOption(CURLINFO_HEADER_OUT, true);
        $request->post($url, $quote);
        $result = $request->getBody();
        
        //$result = $request->execute();

        if (!$result) {
            $logger->info('Request could not be sent.');
            $logger->info($result);
            $logger->info(
                '########### END OF REQUEST - FAILURE WHILST TRYING TO SEND REQUEST ###########'
            );
            throw new \Magento\Framework\Exception\LocalizedException(
                __('AccessWorldpay api service not available')
            );
        }
       
        $logger->info('Request successfully sent');
        $logger->info($result);

        // extract headers
        $bits = explode("\r\n\r\n", $result);
        $body = array_pop($bits);
        $headers = implode("\r\n\r\n", $bits);

        // Extracting Cookie from Response Header.
        if (preg_match("/set-cookie: (.+?)([\r\n]|$)/", $headers, $match)) {
            // Keep a hold of the cookie returned incase we need to send a
            // second order message after 3dSecure check
            $logger->info('Cookie Get: ' . $match[1]);
            $this->helper->setWorldpayAuthCookie($match[1]);
        }
        if (preg_match("/Set-Cookie: (.+?)([\r\n]|$)/", $headers, $match)) {
            // Keep a hold of the cookie returned incase we need to send a
            // second order message after 3dSecure check
                $logger->info('Cookie Get: ' . $match[1]);
                $this->helper->setAccessWorldpayAuthCookie($match[1]);
        }
        return $body;
        // @codingStandardsIgnoreEnd
    }

    /**
     * Process the request
     *
     * @param object $orderCode
     * @param string $username
     * @param string $password
     * @param string $url
     * @param int $quote
     * @return SimpleXMLElement body
     * @throws Exception
     */
    public function savedCardSendRequest($orderCode, $username, $password, $url, $quote = null)
    {
        // @codingStandardsIgnoreStart
        $pluginTrackerDetails = $this->helper->getPluginTrackerdetails();
        $request = $this->_getRequest();
        $logger = $this->_wplogger;
        if (!$url) {
            $url = $this->_getUrl();
        }

        $logger->info('Setting destination URL: ' . $url);
        $logger->info('Initialising request');
        $request->setOption(CURLOPT_POST, self::CURL_POST);
        $request->setOption(CURLOPT_RETURNTRANSFER, self::CURL_RETURNTRANSFER);
        $request->setOption(CURLOPT_NOPROGRESS, self::CURL_NOPROGRESS);
        $request->setOption(CURLOPT_TIMEOUT, self::CURL_TIMEOUT);
        $request->setOption(CURLOPT_VERBOSE, self::CURL_VERBOSE);
        /*SSL verification false*/
        $request->setOption(CURLOPT_SSL_VERIFYHOST, false);
        $request->setOption(CURLOPT_SSL_VERIFYPEER, false);
        if ($quote) {
            $request->setOption(CURLOPT_POSTFIELDS, $quote);
        }
        $request->setOption(CURLOPT_USERPWD, $username.':'.$password);

        $request->setOption(CURLOPT_HEADER, true);
        $request->setOption(
            CURLOPT_HTTPHEADER,
            ["Content-Type: application/vnd.worldpay.payments-v6+json",
                "MERCHANT_ENTITY_REF"=>$pluginTrackerDetails['MERCHANT_ENTITY_REF'],
                "MERCHANT_ID" => $pluginTrackerDetails['MERCHANT_ID'],
                "MAGENTO_EDITION"=>$pluginTrackerDetails['MAGENTO_EDITION'],
                "MAGENTO_VERSION"=>$pluginTrackerDetails['MAGENTO_VERSION'],
                "PHP_VERSION"=> $pluginTrackerDetails['PHP_VERSION'],
                "CURRENT_WORLDPAY_PLUGIN_VERSION"
                    =>isset($pluginTrackerDetails['CURRENT_WORLDPAY_PLUGIN_VERSION'])?
                $pluginTrackerDetails['CURRENT_WORLDPAY_PLUGIN_VERSION']:"",
                "WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE" 
                    =>isset($pluginTrackerDetails['WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE'])?
                $pluginTrackerDetails['WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE']:"",
                "UPGRADE_DATES" => isset($pluginTrackerDetails['UPGRADE_DATES'])
                    ?$pluginTrackerDetails['UPGRADE_DATES']:""
            ]
        );
        //$logger->info('Sending Json as: ' . $this->_getObfuscatedXmlLog($quote));

        $request->setOption(CURLINFO_HEADER_OUT, true);
        $request->post($url, $quote);
        $result = $request->getBody();
        
        //$result = $request->execute();

        // logging Headder for 3DS request to check Cookie.
        if ($this->helper->isThreeDSRequest()) {
            //$information = $request->getInfo(CURLINFO_HEADER_OUT);
            $information = $request->getHeaders();
            $logger->info("**REQUEST HEADER START**");
            $logger->info($information);
            $logger->info("**REQUEST HEADER ENDS**");
        }

        if (!$result) {
            $logger->info('Request could not be sent.');
            $logger->info($result);
            $logger->info(
                '########### END OF REQUEST - FAILURE WHILST TRYING TO SEND REQUEST ###########'
            );
            throw new \Magento\Framework\Exception\LocalizedException(
                __('AccessWorldpay api service not available')
            );
        }
        
        $logger->info('Request successfully sent');
        $logger->info($result);

        // extract headers
        $bits = explode("\r\n\r\n", $result);
        $body = array_pop($bits);
        $headers = implode("\r\n\r\n", $bits);
        $jsonResponse = json_decode($body, true);
        
        /* Check Error */
        $this->_checkErrorForEmailSend($request, $jsonResponse);

        // Extracting Cookie from Response Header.
        if (preg_match("/set-cookie: (.+?)([\r\n]|$)/", $headers, $match)) {
            // Keep a hold of the cookie returned incase we need to send a
            // second order message after 3dSecure check
            $logger->info('Cookie Get: ' . $match[1]);

            $this->helper->setWorldpayAuthCookie($match[1]);

        }
        if (preg_match("/Set-Cookie: (.+?)([\r\n]|$)/", $headers, $match)) {
            // Keep a hold of the cookie returned incase we need to send a
            // second order message after 3dSecure check
                $logger->info('Cookie Get: ' . $match[1]);
                $this->helper->setAccessWorldpayAuthCookie($match[1]);
        }

        $jsonData = json_decode($body, true);
        return $jsonData;
        // @codingStandardsIgnoreEnd
    }

    /**
     * Send GooglePay Request
     *
     * @param object $orderCode
     * @param string $username
     * @param string $password
     * @param string $url
     * @param string $quote
     * @return json
     */
    public function sendGooglePayRequest($orderCode, $username, $password, $url, $quote = null)
    {
    // @codingStandardsIgnoreStart
        $pluginTrackerDetails = $this->helper->getPluginTrackerdetails();
        $request = $this->_getRequest();
        $logger = $this->_wplogger;
        if (!$url) {
            $url = $this->_getUrl();
        }
        $logger->info('Setting destination URL: ' . $url);
        $logger->info('Initialising request');
        $request->setOption(CURLOPT_POST, self::CURL_POST);
        $request->setOption(CURLOPT_RETURNTRANSFER, self::CURL_RETURNTRANSFER);
        $request->setOption(CURLOPT_NOPROGRESS, self::CURL_NOPROGRESS);
        $request->setOption(CURLOPT_TIMEOUT, self::CURL_TIMEOUT);
        $request->setOption(CURLOPT_VERBOSE, self::CURL_VERBOSE);
        /*SSL verification false*/
        $request->setOption(CURLOPT_SSL_VERIFYHOST, false);
        $request->setOption(CURLOPT_SSL_VERIFYPEER, false);
        if ($quote) {
            $quote = json_decode($quote, true);
            $requestBody = '{
                "transactionReference": "'.$quote['transactionReference'].'",
                "merchant": {
                    "entity": "'.$quote['merchant']['entity'].'"
                },
                "instruction": {
                    "narrative": {
                        "line1": "'.$quote['instruction']['narrative']['line1'].'"
                    },
                    "value": {
                        "currency": "'.$quote['instruction']['value']['currency'].'",
                        "amount": '.$quote['instruction']['value']['amount'].'
                    },
                    "paymentInstrument": {
                        "type": "card/wallet+googlepay",
                        "walletToken":'.json_encode($quote['instruction']['paymentInstrument']['walletToken']).'
                    }
                }
            }';
            $logger->info(print_r($requestBody, true));
            $request->setOption(CURLOPT_POSTFIELDS, $requestBody);
        }
        $request->setOption(CURLOPT_USERPWD, $username.':'.$password);
        $request->setOption(CURLOPT_HEADER, true);
        $request->setOption(
            CURLOPT_HTTPHEADER,
            ["Content-Type: application/vnd.worldpay.payments-v6+json",
                "MERCHANT_ENTITY_REF"=>$pluginTrackerDetails['MERCHANT_ENTITY_REF'],
                "MERCHANT_ID" => $pluginTrackerDetails['MERCHANT_ID'],
                "MAGENTO_EDITION"=>$pluginTrackerDetails['MAGENTO_EDITION'],
                "MAGENTO_VERSION"=>$pluginTrackerDetails['MAGENTO_VERSION'],
                "PHP_VERSION"=> $pluginTrackerDetails['PHP_VERSION'],
                "CURRENT_WORLDPAY_PLUGIN_VERSION"
                    =>isset($pluginTrackerDetails['CURRENT_WORLDPAY_PLUGIN_VERSION'])?
                $pluginTrackerDetails['CURRENT_WORLDPAY_PLUGIN_VERSION']:"",
                "WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE"
                    =>isset($pluginTrackerDetails['WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE'])?
                $pluginTrackerDetails['WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE']:"",
                "UPGRADE_DATES" => isset($pluginTrackerDetails['UPGRADE_DATES'])
                    ?$pluginTrackerDetails['UPGRADE_DATES']:""
            ]
        );
        //$logger->info('Sending Json as: ' . $this->_getObfuscatedXmlLog($quote));
        $request->setOption(CURLINFO_HEADER_OUT, true);
        $request->post($url, $quote);
        $result = $request->getBody();

        //$logger->info(print_r($result,true));
        // logging Headder for 3DS request to check Cookie.
        if ($this->helper->isThreeDSRequest()) {
            $information = $request->getInfo(CURLINFO_HEADER_OUT);
            $logger->info("**REQUEST HEADER START**");
            $logger->info($information);
            $logger->info("**REQUEST HEADER ENDS**");
        }

        if (!$result) {
            $logger->info('Request could not be sent.');
            $logger->info($result);
            $logger->info(
                '########### END OF REQUEST - FAILURE WHILST TRYING TO SEND REQUEST ###########'
            );
            throw new \Magento\Framework\Exception\LocalizedException(
                __('AccessWorldpay api service not available')
            );
        }
        $logger->info('Request successfully sent');
        $logger->info($result);

        // extract headers
        $bits = explode("\r\n\r\n", $result);
        $body = array_pop($bits);
        $headers = implode("\r\n\r\n", $bits);

        // Extracting Cookie from Response Header.
        if (preg_match("/set-cookie: (.+?)([\r\n]|$)/", $headers, $match)) {
            // Keep a hold of the cookie returned incase we need to send a
            // second order message after 3dSecure check
            $logger->info('Cookie Get: ' . $match[1]);

            $this->helper->setWorldpayAuthCookie($match[1]);

        }
        if (preg_match("/Set-Cookie: (.+?)([\r\n]|$)/", $headers, $match)) {
            // Keep a hold of the cookie returned incase we need to send a
            // second order message after 3dSecure check
                $logger->info('Cookie Get: ' . $match[1]);
                $this->helper->setAccessWorldpayAuthCookie($match[1]);
        }
        //return $body;
        $jsonData = json_decode($body, true);
        //$logger->info('Response Body>>'.print_r($body,true));
        try {
            $xml = $this->_array2xml($jsonData, false, $orderCode);
        } catch (Exception $e) {
            $logger->error($e->getMessage());
            throw new \Magento\Framework\Exception\LocalizedException(
                __('Something happened, please refresh the page and try again.')
            );
        }
        return $xml;
        // @codingStandardsIgnoreEnd
    }

    /**
     * Process the request
     *
     * @param object $orderCode
     * @param string $username
     * @param string $password
     * @param string $url
     * @param string $quote
     * @return json
     */
    public function sendEventRequest($orderCode, $username, $password, $url, $quote = null)
    {
       // @codingStandardsIgnoreStart
        $pluginTrackerDetails = $this->helper->getPluginTrackerdetails();
        $logger = $this->_wplogger;
        $logger->info('Setting destination URL: ' . $url);
        $logger->info('Initialising request');

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($curl, CURLOPT_ENCODING, "");
        curl_setopt($curl, CURLOPT_MAXREDIRS, 10);
        curl_setopt($curl, CURLOPT_TIMEOUT, 0);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($curl, CURLOPT_USERPWD, $username.':'.$password);
        if($quote == 'ACH_DIRECT_DEBIT-SSL'){
            curl_setopt($curl, CURLOPT_HTTPHEADER, [
                "Content-Type: application/vnd.worldpay.pay-direct-v1+json",
                "Accept: application/vnd.worldpay.pay-direct-v1+json",
                "MERCHANT_ENTITY_REF"=>$pluginTrackerDetails['MERCHANT_ENTITY_REF'],
                "MERCHANT_ID" => $pluginTrackerDetails['MERCHANT_ID'],
                "MAGENTO_EDITION"=>$pluginTrackerDetails['MAGENTO_EDITION'],
                "MAGENTO_VERSION"=>$pluginTrackerDetails['MAGENTO_VERSION'],
                "PHP_VERSION"=> $pluginTrackerDetails['PHP_VERSION'],
                "CURRENT_WORLDPAY_PLUGIN_VERSION"
                    =>isset($pluginTrackerDetails['CURRENT_WORLDPAY_PLUGIN_VERSION'])?
                $pluginTrackerDetails['CURRENT_WORLDPAY_PLUGIN_VERSION']:"",
                "WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE"
                    =>isset($pluginTrackerDetails['WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE'])?
                $pluginTrackerDetails['WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE']:"",
                "UPGRADE_DATES" => isset($pluginTrackerDetails['UPGRADE_DATES'])
                    ?$pluginTrackerDetails['UPGRADE_DATES']:""
              ]);
        }else{
            curl_setopt($curl, CURLOPT_HTTPHEADER, [
                "Content-Type: application/vnd.worldpay.payments-v6+json",
                "MERCHANT_ENTITY_REF"=>$pluginTrackerDetails['MERCHANT_ENTITY_REF'],
                "MERCHANT_ID" => $pluginTrackerDetails['MERCHANT_ID'],
                "MAGENTO_EDITION"=>$pluginTrackerDetails['MAGENTO_EDITION'],
                "MAGENTO_VERSION"=>$pluginTrackerDetails['MAGENTO_VERSION'],
                "PHP_VERSION"=> $pluginTrackerDetails['PHP_VERSION'],
                "CURRENT_WORLDPAY_PLUGIN_VERSION"
                    =>isset($pluginTrackerDetails['CURRENT_WORLDPAY_PLUGIN_VERSION'])?
                $pluginTrackerDetails['CURRENT_WORLDPAY_PLUGIN_VERSION']:"",
                "WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE"
                    =>isset($pluginTrackerDetails['WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE'])?
                $pluginTrackerDetails['WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE']:"",
                "UPGRADE_DATES" => isset($pluginTrackerDetails['UPGRADE_DATES'])
                    ?$pluginTrackerDetails['UPGRADE_DATES']:""
              ]);
        }

        //$logger->info(print_r(headers_list(),true));
        $result = curl_exec($curl);
        curl_close($curl);

        if (!$result) {
            $logger->info('Request could not be sent.');
            $logger->info($result);
            $logger->info(
                '########### END OF REQUEST - FAILURE WHILST TRYING TO SEND REQUEST ###########'
            );
            throw new \Magento\Framework\Exception\LocalizedException(
                __('AccessWorldpay api service not available')
            );
        }
        $logger->info('Request successfully sent');
        $logger->info($result);

        // extract headers
        $bits = explode("\r\n\r\n", $result);
        $body = array_pop($bits);
        $headers = implode("\r\n\r\n", $bits);

        // Extracting Cookie from Response Header.
        if (preg_match("/set-cookie: (.+?)([\r\n]|$)/", $headers, $match)) {
            // Keep a hold of the cookie returned incase we need to send a
            // second order message after 3dSecure check
            $logger->info('Cookie Get: ' . $match[1]);

            $this->helper->setWorldpayAuthCookie($match[1]);

        }
        if (preg_match("/Set-Cookie: (.+?)([\r\n]|$)/", $headers, $match)) {
            // Keep a hold of the cookie returned incase we need to send a
            // second order message after 3dSecure check
                $logger->info('Cookie Get: ' . $match[1]);
        }
        //return $body;
        $jsonData = json_decode($body, true);
        //$logger->info('Body'.$jsonData);
        try {
            if(isset($jsonData['errorName'])){
                $logger->info('inside try block in Request.php : if block  +++++++++++++');
                throw new \Magento\Framework\Exception\LocalizedException(
                __($jsonData['message']));
            }else{
                $xml = $this->_array2xml($jsonData, false, $orderCode);
                return $xml;
            }
        } catch (Exception $e) {
            $logger->error($e->getMessage());
            if($e->getMessage() == 'An error has occurred.'){
                throw new \Magento\Framework\Exception\LocalizedException(
                __($e->getMessage())
            );
            }else{
            throw new \Magento\Framework\Exception\LocalizedException(
                __($this->helper->getCreditCardSpecificException('CCAM18'))
            );}
        }

        // @codingStandardsIgnoreEnd
    }

    /**
     * Send exemption request
     *
     * @param object $orderCode
     * @param string $username
     * @param string $password
     * @param string $url
     * @param string $quote
     * @return json
     */
    public function sendExemptionRequest($orderCode, $username, $password, $url, $quote = null)
    {
        // @codingStandardsIgnoreStart
        $pluginTrackerDetails = $this->helper->getPluginTrackerdetails();
        $request = $this->_getRequest();
        $logger = $this->_wplogger;
        if (!$url) {
            $url = $this->_getUrl();
        }

        $logger->info('Setting destination URL: ' . $url);

        $logger->info('Initialising request');
        $request->setOption(CURLOPT_POST, self::CURL_POST);
        $request->setOption(CURLOPT_RETURNTRANSFER, self::CURL_RETURNTRANSFER);
        $request->setOption(CURLOPT_NOPROGRESS, self::CURL_NOPROGRESS);
        $request->setOption(CURLOPT_TIMEOUT, self::CURL_TIMEOUT);
        $request->setOption(CURLOPT_VERBOSE, self::CURL_VERBOSE);
        /*SSL verification false*/
        $request->setOption(CURLOPT_SSL_VERIFYHOST, false);
        $request->setOption(CURLOPT_SSL_VERIFYPEER, false);
        if ($quote) {
            $request->setOption(CURLOPT_POSTFIELDS, $quote);
        }
        $request->setOption(CURLOPT_USERPWD, $username.':'.$password);

        $request->setOption(CURLOPT_HEADER, true);
        $request->setOption(
            CURLOPT_HTTPHEADER,
            ["Content-Type: application/vnd.worldpay.exemptions-v1.hal+json",
             "Accept: application/vnd.worldpay.exemptions-v1.hal+json",
                "MERCHANT_ENTITY_REF"=>$pluginTrackerDetails['MERCHANT_ENTITY_REF'],
                "MERCHANT_ID" => $pluginTrackerDetails['MERCHANT_ID'],
                "MAGENTO_EDITION"=>$pluginTrackerDetails['MAGENTO_EDITION'],
                "MAGENTO_VERSION"=>$pluginTrackerDetails['MAGENTO_VERSION'],
                "PHP_VERSION"=> $pluginTrackerDetails['PHP_VERSION'],
                "CURRENT_WORLDPAY_PLUGIN_VERSION"
                    =>isset($pluginTrackerDetails['CURRENT_WORLDPAY_PLUGIN_VERSION'])?
                $pluginTrackerDetails['CURRENT_WORLDPAY_PLUGIN_VERSION']:"",
                "WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE"
                    =>isset($pluginTrackerDetails['WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE'])?
                $pluginTrackerDetails['WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE']:"",
                "UPGRADE_DATES" => isset($pluginTrackerDetails['UPGRADE_DATES'])
                    ?$pluginTrackerDetails['UPGRADE_DATES']:""
            ]
        );

        $request->setOption(CURLINFO_HEADER_OUT, true);
        $request->post($url, $quote);
        $result = $request->getBody();

        if (!$result) {
            $logger->info('Request could not be sent.');
            $logger->info($result);
            $logger->info(
                '########### END OF REQUEST - FAILURE WHILST TRYING TO SEND REQUEST ###########'
            );
            throw new \Magento\Framework\Exception\LocalizedException(
                'AccessWorldpay api service not available'
            );
        }

        $logger->info('Request successfully sent');
        $logger->info($result);

        // extract headers
        $bits = explode("\r\n\r\n", $result);
        $body = array_pop($bits);
        $headers = implode("\r\n\r\n", $bits);

        $jsonData = json_decode($body, true);
        return $jsonData;
    // @codingStandardsIgnoreEnd
    }

    /**
     * Send ACH order request
     *
     * @param object $orderCode
     * @param string $username
     * @param string $password
     * @param string $url
     * @param string $quote
     * @return json
     */
    public function sendACHOrderRequest($orderCode, $username, $password, $url, $quote = null)
    {
        $pluginTrackerDetails = $this->helper->getPluginTrackerdetails();
        $request = $this->_getRequest();
        $logger = $this->_wplogger;
        if (!$url) {
            $url = $this->_getUrl();
        }

        $logger->info('Setting destination URL: ' . $url);

        $logger->info('Initialising request');
        $request->setOption(CURLOPT_POST, self::CURL_POST);
        $request->setOption(CURLOPT_RETURNTRANSFER, self::CURL_RETURNTRANSFER);
        $request->setOption(CURLOPT_NOPROGRESS, self::CURL_NOPROGRESS);
        $request->setOption(CURLOPT_TIMEOUT, self::CURL_TIMEOUT);
        $request->setOption(CURLOPT_VERBOSE, self::CURL_VERBOSE);
        /*SSL verification false*/
        $request->setOption(CURLOPT_SSL_VERIFYHOST, false);
        $request->setOption(CURLOPT_SSL_VERIFYPEER, false);
        if ($quote) {
            $request->setOption(CURLOPT_POSTFIELDS, $quote);
        }
        $request->setOption(CURLOPT_USERPWD, $username.':'.$password);

        $request->setOption(CURLOPT_HEADER, true);
        $request->setOption(
            CURLOPT_HTTPHEADER,
            ["Content-Type: application/vnd.worldpay.pay-direct-v1+json",
             "Accept: application/vnd.worldpay.pay-direct-v1+json",
                "MERCHANT_ENTITY_REF"=>$pluginTrackerDetails['MERCHANT_ENTITY_REF'],
                "MERCHANT_ID" => $pluginTrackerDetails['MERCHANT_ID'],
                "MAGENTO_EDITION"=>$pluginTrackerDetails['MAGENTO_EDITION'],
                "MAGENTO_VERSION"=>$pluginTrackerDetails['MAGENTO_VERSION'],
                "PHP_VERSION"=> $pluginTrackerDetails['PHP_VERSION'],
                "CURRENT_WORLDPAY_PLUGIN_VERSION"
                    =>isset($pluginTrackerDetails['CURRENT_WORLDPAY_PLUGIN_VERSION'])?
                $pluginTrackerDetails['CURRENT_WORLDPAY_PLUGIN_VERSION']:"",
                "WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE"
                    =>isset($pluginTrackerDetails['WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE'])?
                $pluginTrackerDetails['WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE']:"",
                "UPGRADE_DATES" => isset($pluginTrackerDetails['UPGRADE_DATES'])
                    ?$pluginTrackerDetails['UPGRADE_DATES']:""
            ]
        );
        //$logger->info('Sending Json as: ' . $this->_getObfuscatedXmlLog($quote));

        $request->setOption(CURLINFO_HEADER_OUT, true);
        $request->post($url, $quote);
        $result = $request->getBody();

        if (!$result) {
            $logger->info('Request could not be sent.');
            $logger->info($result);
            $logger->info(
                '########### END OF REQUEST - FAILURE WHILST TRYING TO SEND REQUEST ###########'
            );
            throw new \Magento\Framework\Exception\LocalizedException(
                __('AccessWorldpay api service not available')
            );
        }
 
        $logger->info('Request successfully sent');
        $logger->info($result);

        // extract headers
        $bits = explode("\r\n\r\n", $result);
        $body = array_pop($bits);
        $headers = implode("\r\n\r\n", $bits);

        // Extracting Cookie from Response Header.
        if (preg_match("/set-cookie: (.+?)([\r\n]|$)/", $headers, $match)) {
            // Keep a hold of the cookie returned incase we need to send a
            // second order message after 3dSecure check
            $logger->info('Cookie Get: ' . $match[1]);

            $this->helper->setWorldpayAuthCookie($match[1]);

        }
        if (preg_match("/Set-Cookie: (.+?)([\r\n]|$)/", $headers, $match)) {
            // Keep a hold of the cookie returned incase we need to send a
            // second order message after 3dSecure check
                $logger->info('Cookie Get: ' . $match[1]);
                $this->helper->setAccessWorldpayAuthCookie($match[1]);
        }
        //return $body;
        $jsonData = json_decode($body, true);
        //$logger->info('Body'.$jsonData);
        try {
            $xml = $this->_array2xml($jsonData, false, $orderCode);
        } catch (Exception $e) {
            $logger->error($e->getMessage());
            throw new \Magento\Framework\Exception\LocalizedException(
                __($this->helper->getCreditCardSpecificException('CCAM18'))
            );
        }
        return $xml;
    }

        /**
         * Process the request
         *
         * @param object $orderCode
         * @param string $username
         * @param string $password
         * @param string $url
         * @param string $quote
         * @return json
         */
    public function sendReversalRequest($orderCode, $username, $password, $url, $quote = null)
    {
       // @codingStandardsIgnoreStart
        $pluginTrackerDetails = $this->helper->getPluginTrackerdetails();
        $logger = $this->_wplogger;
        $logger->info('Setting destination URL: ' . $url);
        $logger->info('Initialising request');

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($curl, CURLOPT_ENCODING, "");
        curl_setopt($curl, CURLOPT_MAXREDIRS, 10);
        curl_setopt($curl, CURLOPT_TIMEOUT, 0);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($curl, CURLOPT_USERPWD, $username.':'.$password);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            "Content-Type: application/vnd.worldpay.pay-direct-v1+json",
            "Accept: application/vnd.worldpay.pay-direct-v1+json",
                "MERCHANT_ENTITY_REF"=>$pluginTrackerDetails['MERCHANT_ENTITY_REF'],
                "MERCHANT_ID" => $pluginTrackerDetails['MERCHANT_ID'],
                "MAGENTO_EDITION"=>$pluginTrackerDetails['MAGENTO_EDITION'],
                "MAGENTO_VERSION"=>$pluginTrackerDetails['MAGENTO_VERSION'],
                "PHP_VERSION"=> $pluginTrackerDetails['PHP_VERSION'],
                "CURRENT_WORLDPAY_PLUGIN_VERSION"
                    =>isset($pluginTrackerDetails['CURRENT_WORLDPAY_PLUGIN_VERSION'])?
                $pluginTrackerDetails['CURRENT_WORLDPAY_PLUGIN_VERSION']:"",
                "WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE"
                    =>isset($pluginTrackerDetails['WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE'])?
                $pluginTrackerDetails['WORLDPAY_PLUGIN_VERSION_USED_TILL_DATE']:"",
                "UPGRADE_DATES" => isset($pluginTrackerDetails['UPGRADE_DATES'])
                    ?$pluginTrackerDetails['UPGRADE_DATES']:""
        ]);

        $result = curl_exec($curl);
        curl_close($curl);

        if (!$result) {
            $logger->info('Request could not be sent.');
            $logger->info($result);
            $logger->info(
                '########### END OF REQUEST - FAILURE WHILST TRYING TO SEND REQUEST ###########'
            );
            throw new \Magento\Framework\Exception\LocalizedException(
                __('AccessWorldpay api service not available ')
            );
        }
        $logger->info('Request successfully sent');
        $logger->info($result);

        // extract headers
        $bits = explode("\r\n\r\n", $result);
        $body = array_pop($bits);
        $headers = implode("\r\n\r\n", $bits);

        // Extracting Cookie from Response Header.
        if (preg_match("/set-cookie: (.+?)([\r\n]|$)/", $headers, $match)) {
            // Keep a hold of the cookie returned incase we need to send a
            // second order message after 3dSecure check
            $logger->info('Cookie Get: ' . $match[1]);

            $this->helper->setWorldpayAuthCookie($match[1]);

        }
        if (preg_match("/Set-Cookie: (.+?)([\r\n]|$)/", $headers, $match)) {
            // Keep a hold of the cookie returned incase we need to send a
            // second order message after 3dSecure check
                $logger->info('Cookie Get: ' . $match[1]);
        }
        //return $body;
        $jsonData = json_decode($body, true);
        //$logger->info('Body'.$jsonData);
        try {
            if(isset($jsonData['errorName'])){
                throw new \Magento\Framework\Exception\LocalizedException(
                __($jsonData['message']));
            }else{
                $xml = $this->_array2xml($jsonData, false, $orderCode);
                return $xml;
            }
        } catch (Exception $e) {
            $logger->error($e->getMessage());
            if($e->getMessage() == 'An error has occurred.'){
                throw new \Magento\Framework\Exception\LocalizedException(
                __($e->getMessage())
            );
            }else{
            throw new \Magento\Framework\Exception\LocalizedException(
                __($this->helper->getCreditCardSpecificException('CCAM18'))
            );}
        }
        // @codingStandardsIgnoreEnd
    }

    /**
     * Check error for send email
     *
     * @param string $request
     * @param array $response
     * @return void
     */
    public function _checkErrorForEmailSend($request, $response)
    {
        $this->_wplogger->info('checking Error for email send..');
        if (isset($response['errorName'])) {
            $this->emailErrorReportHelper->sendErrorReport([
                'request'=>json_encode($request),
                'response'=>json_encode($response),
                'error_code'=>$response['errorName'],
                'error_message'=>$response['message']
            ]);
        }
        if (isset($response['description']) && isset($response['outcome'])) {
            if ($response['outcome'] == 'not verified') {
                $this->emailErrorReportHelper->sendErrorReport([
                    'request'=>json_encode($request),
                    'response'=>json_encode($response),
                    'error_code'=>$response['code'],
                    'error_message'=>$response['description']
                ]);
            }
        }
        return true;
    }
}
