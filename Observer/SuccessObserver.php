<?php
/**
 * @package   Divante\CartSync
 * @author    Maciej Daniłowicz <mdaniłowicz@divante.pl>
 * @copyright 2018 Divante Sp. z o.o.
 * @license   See LICENSE_DIVANTE.txt for license details.
 */

namespace Divante\CartSync\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * Class SuccessObserver
 *
 * @package Divante\CartSync\Observer
 */
class SuccessObserver implements ObserverInterface
{
    /**
     * @var string
     */
    private $confPath = 'vuestorefront_externalcheckout/externalcheckout_general/externalcheckout_link';

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * SuccessObserver constructor.
     *
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        $url = $this->scopeConfig->getValue(
            $this->confPath,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        if ($url && $url !== '') {
            if (!(strpos($url, "http://") !== false || strpos($url, "https://") !== false)) {
                $url = 'https://' . $url;
            }
            header("Location: " . $url);
            die();
        }
    }
}
