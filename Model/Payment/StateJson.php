<?php
/**
 * @copyright 2020 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Payment;

/**
 * Reading xml
 */
class StateJson implements \Sapient\AccessWorldpay\Model\Payment\StateInterface
{
    /**
     * @var string $_xml
     */
    private $_xml;
    /**
     * Constructor
     *
     * @param string $xml
     */
    public function __construct($xml)
    {
        $this->_xml = $xml;
    }

    /**
     * Retrive ordercode from xml
     *
     * @return string
     */
    public function getOrderCode()
    {
        if (isset($this->_xml->orderCode)) {
            return (string) $this->_xml->orderCode;
        } elseif (isset($this->_xml->eventDetails->transactionReference)) {
            return (string) $this->_xml->eventDetails->transactionReference;
        }
    }

    /**
     * Retrive ordercode from xml
     *
     * @return string
     */
    public function getPaymentStatus()
    {
        $statusNode = $this->_getStatusNode();
        $statusNode = $this->formattedStatusFromEvents($statusNode);
        return (string) $statusNode;
    }

    /**
     * Retrive status node from xml
     *
     * @return xml
     */
    private function _getStatusNode()
    {
        if (isset($this->_xml->outcome)) {
            return strtoupper($this->_xml->outcome);
        } elseif (isset($this->_xml->lastEvent)) {
            return strtoupper($this->_xml->lastEvent);
        } elseif (isset($this->_xml->eventDetails->type)) {
            return strtoupper($this->_xml->eventDetails->type);
        }
        
        return $this->_xml->outcome;
    }
    
    /**
     * Retrive journal reference from xml
     *
     * @param string $state
     * @return string
     */
    public function getJournalReference($state)
    {
        $statusNode = $this->_getStatusNode();
        if (isset($this->_xml->reference)) {
            $reference = $this->_xml->reference;
        } elseif (isset($this->_xml->eventDetails->reference)) {
            $reference = $this->_xml->eventDetails->reference;
            return $reference->__toString();
        }
        if ($statusNode == $state) {
            $reference = $reference;
            if ($reference) {
                return $reference->__toString();
            }
        }
        return false;
    }

    /**
     * Retrive payment links from xml
     *
     * @return string
     */
        
    public function getLinks()
    {
        if (isset($this->_xml->_links)) {
            $links = [
                'cancel' => $this->getOmsUrls($this->_xml->_links->cancel),
                'settle' => $this->getOmsUrls($this->_xml->_links->settle),
                'partialSettle' => $this->getOmsUrls($this->_xml->_links->partialSettle),
                'events' => $this->getOmsUrls($this->_xml->_links->events)
                ];
            return $links;
        }
        return false;
    }
    
    /**
     * Get Oms links
     *
     * @param string $link
     * @return string $link
     */
    public function getOmsUrls($link)
    {
        return $link->href;
    }
    
    /**
     * Formate and return events Status
     *
     * @param string $statusNode
     * @return string $statusNode
     */
    public function formattedStatusFromEvents($statusNode)
    {
        switch ($statusNode) {
            case "SENTFORAUTHORIZATION":
                return "SENT_FOR_AUTHORIZATION";
            case "SENTFORSETTLEMENT":
                return "SENT_FOR_SETTLEMENT";
            case "SENTFORREFUND":
                return "SENT_FOR_REFUND";
            case "REFUNDFAILED":
                return "REFUND_FAILED";
            default:
                return $statusNode;
        }
    }

    /**
     * Retrive amount from xml
     * @return string
     */
//    public function getAmount()
//    {
//        $statusNode = $this->_getStatusNode();
//        return (string) $statusNode->payment->amount['value'];
//    }

    /**
     * Retrive merchant code from xml
     * @return string
     */
//    public function getMerchantCode()
//    {
//        return (string) $this->_xml['merchantCode'];
//    }

    /**
     * Retrive Risk Score from xml
     * @return string
     */
//    public function getRiskScore()
//    {
//        $statusNode = $this->_getStatusNode();
//        return (string) $statusNode->payment->riskScore['value'];
//    }

    /**
     * Retrive payment method from xml
     * @return string
     */
//    public function getPaymentMethod()
//    {
//        $statusNode = $this->_getStatusNode();
//        return (string) $statusNode->payment->paymentMethod;
//    }

    /**
     * Retrive card number from xml
     * @return string
     */
//    public function getCardNumber()
//    {
//        /** @var SimpleXMLElement $statusNode */
//        $statusNode = $this->_getStatusNode();
//        if (isset($statusNode->payment->cardNumber)) {
//            return (string) $statusNode->payment->cardNumber;
//        }
//
//        return (string) $statusNode->payment->paymentMethodDetail->card['number'];
//    }

    /**
     * Retrive avs result code from xml
     * @return string
     */
//    public function getAvsResultCode()
//    {
//        $statusNode = $this->_getStatusNode();
//        return (string) $statusNode->payment->AVSResultCode['description'];
//    }

    /**
     * Retrive cvc result code from xml
     * @return string
     */
//    public function getCvcResultCode()
//    {
//        $statusNode = $this->_getStatusNode();
//        return (string) $statusNode->payment->CVCResultCode['description'];
//    }

    /**
     * Retrive advance risk provider from xml
     * @return string
     */
//    public function getAdvancedRiskProvider()
//    {
//        $statusNode = $this->_getStatusNode();
//        return (string) $statusNode->payment->riskScore['Provider'];
//    }

    /**
     * Retrive advance risk provider id from xml
     * @return string
     */
//    public function getAdvancedRiskProviderId()
//    {
//        $statusNode = $this->_getStatusNode();
//        return (string) $statusNode->payment->riskScore['RGID'];
//    }

    /**
     * Retrive advance risk provider Threshold from xml
     * @return string
     */
//    public function getAdvancedRiskProviderThreshold()
//    {
//        $statusNode = $this->_getStatusNode();
//        return (string) $statusNode->payment->riskScore['tRisk'];
//    }

    /**
     * Retrive advance risk provider Score from xml
     * @return string
     */
//    public function getAdvancedRiskProviderScore()
//    {
//        $statusNode = $this->_getStatusNode();
//        return (string) $statusNode->payment->riskScore['tScore'];
//    }

    /**
     * Retrive advance risk provider final score from xml
     * @return string
     */
//    public function getAdvancedRiskProviderFinalScore()
//    {
//        $statusNode = $this->_getStatusNode();
//        return (string) $statusNode->payment->riskScore['finalScore'];
//    }

    /**
     * Retrive Payment refusal code from xml
     * @return string
     */
//    public function getPaymentRefusalCode()
//    {
//        $statusNode = $this->_getStatusNode();
//        return $statusNode->payment->issueResponseCode['code'] ? : $statusNode->payment->ISO8583ReturnCode['code'];
//    }

    /**
     * Retrive Payment refusal Description from xml
     * @return string
     */
//    public function getPaymentRefusalDescription()
//    {
//        $statusNode = $this->_getStatusNode();
//        return $statusNode->payment->issueResponseCode['description'] ?
//        : $statusNode->payment->ISO8583ReturnCode['description'];
//    }

    

   

    /**
     * Retrive Asynchronus Notification from xml
     * @return string
     */
//    public function isAsyncNotification()
//    {
//        return isset($this->_xml->notify);
//    }

    /**
     * Tells if this response is a direct reply xml sent from WP server
     * @return bool
     */
//    public function isDirectReply()
//    {
//        return ! $this->isAsyncNotification();
//    }

    /**
     * Retrive AAV Addewss Result Code from xml
     * @return string
     */
//    public function getAAVAddressResultCode()
//    {
//        $statusNode = $this->_getStatusNode();
//        return (string) $statusNode->payment->AAVAddressResultCode['description'];
//    }

    /**
     * Retrive AAV Postcode Result Code from xml
     * @return string
     */
//    public function getAAVPostcodeResultCode()
//    {
//        $statusNode = $this->_getStatusNode();
//        return (string) $statusNode->payment->AAVPostcodeResultCode['description'];
//    }

    /**
     * Retrive AAV card holder Name Result Code from xml
     * @return string
     */
//    public function getAAVCardholderNameResultCode()
//    {
//        $statusNode = $this->_getStatusNode();
//        return (string) $statusNode->payment->AAVCardholderNameResultCode['description'];
//    }

    /**
     * Retrive AAV Telephone Result Code from xml
     * @return string
     */
//    public function getAAVTelephoneResultCode()
//    {
//        $statusNode = $this->_getStatusNode();
//        return (string) $statusNode->payment->AAVTelephoneResultCode['description'];
//    }

    /**
     * Retrive AAV Email Result Code from xml
     * @return string
     */
//    public function getAAVEmailResultCode()
//    {
//        $statusNode = $this->_getStatusNode();
//        return (string) $statusNode->payment->AAVEmailResultCode['description'];
//    }

    /*
    *Retrieve currency code from xml
    */
//    public function getCurrency()
//    {
//        $statusNode = $this->_getStatusNode();
//        return (string) $statusNode->payment->amount['currencyCode'];
//    }
}
