<?php
/**
 * @copyright 2017 Sapient
 */
namespace Sapient\AccessWorldpay\Model\Config\Source;

class ChallengePreference extends \Magento\Framework\App\Config\Value
{
    /**
     * ToOptionArray
     *
     * @return array
     */
    public function toOptionArray()
    {

        return [
            ['value' => 'noPreference', 'label' => __('No Preference')],
            ['value' => 'noChallengeRequested', 'label' => __('No Challenge Requested')],
            ['value' => 'challengeRequested', 'label' => __('Challenge Requested')],
            ['value' => 'challengeMandated', 'label' => __('Challenge Mandated')]
        ];
    }
}
