<?php
/**
 * @package   Divante\CartSync
 * @author    Mateusz Bukowski <mbukowski@divante.pl>
 * @copyright 2018 Divante Sp. z o.o.
 * @license   See LICENSE_DIVANTE.txt for license details.
 */

namespace Divante\CartSync\Service;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

/**
 * Class SyncLoggerFactory
 */
class SyncLoggerFactory
{

    /**
     * @var string
     */
    private static $path = BP . '/var/log/cart-sync.log';

    /**
     * @param string $channelName
     *
     * @return Logger
     *
     * @throws \Exception
     */
    public function create(string $channelName = 'cart-sync'): Logger
    {
        $logger = new Logger($channelName);
        $logger->pushHandler(new StreamHandler(static::$path));

        return $logger;
    }
}
