<?php
declare(strict_types=1);

namespace Divante\CartSync\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    const CHECKOUT_PATH = 'vuestorefront_externalcheckout/externalcheckout_general/checkout_path';
    const VUESTOREFRONT_SUCCES_PATH = 'vuestorefront_externalcheckout/externalcheckout_general/externalcheckout_link';

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    public function getCheckoutPath()
    {
        $value = $this->getConfigValue(self::CHECKOUT_PATH);
        if (!$value) {
            return 'checkout/cart';
        }
        return trim($value);
    }

    public function getVueStorefrontSuccessUrl()
    {
        return $this->getConfigValue(self::VUESTOREFRONT_SUCCES_PATH);
    }

    protected function getConfigValue($key)
    {
        return $this->scopeConfig->getValue($key, ScopeInterface::SCOPE_STORE);
    }
}
