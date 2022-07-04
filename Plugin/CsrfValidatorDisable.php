<?php
/**
 * @copyright 2020 Sapient
 */

namespace Sapient\AccessWorldpay\Plugin;

class CsrfValidatorDisable
{
    /**
     * Around Validate
     *
     * @param string $subject
     * @param \Closure $proceed
     * @param string $request
     * @param string $action
     */
    public function aroundValidate(
        $subject,
        \Closure $proceed,
        $request,
        $action
    ) {
        if ($request->getModuleName() == 'worldpay') {
            return; // Disable CSRF check
        }
        $proceed($request, $action); // Proceed Magento 2 core functionalities
    }
}
