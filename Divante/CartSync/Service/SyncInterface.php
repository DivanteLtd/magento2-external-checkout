<?php
/**
 * @package   Divante\CartSync
 * @author    Mateusz Bukowski <mbukowski@divante.pl>
 * @copyright 2018 Divante Sp. z o.o.
 * @license   See LICENSE_DIVANTE.txt for license details.
 */

namespace Divante\CartSync\Service;

/**
 * Interface SyncInterface
 */
interface SyncInterface
{

    /**
     * @param int $customerId
     * @param int $cartId
     *
     * @return SyncInterface
     */
    public function synchronizeCustomerCart($customerId, $cartId): SyncInterface;

    /**
     * @param string $cartId
     *
     * @return SyncInterface
     */
    public function synchronizeGuestCart(string $cartId): SyncInterface;
}
