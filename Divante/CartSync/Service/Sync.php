<?php
/**
 * @package   Divante\CartSync
 * @author    Mateusz Bukowski <mbukowski@divante.pl>
 * @copyright 2018 Divante Sp. z o.o.
 * @license   See LICENSE_DIVANTE.txt for license details.
 */

namespace Divante\CartSync\Service;

use Magento\Checkout\Model\Session;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\CustomerFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteIdMask;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Quote\Model\QuoteRepository;
use Monolog\Logger;

/**
 * Class Sync
 */
class Sync implements SyncInterface
{

    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;
    /**
     * @var Session
     */
    private $checkoutSession;
    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;
    /**
     * @var Logger
     */
    private $logger;
    /**
     * @var QuoteRepository
     */
    private $quoteRepository;
    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;

    /**
     * Sync constructor.
     *
     * @param CartRepositoryInterface     $cartRepository
     * @param CustomerRepositoryInterface $customerRepository
     * @param SyncLoggerFactory           $syncLoggerFactory
     * @param Session                     $checkoutSession
     * @param QuoteIdMaskFactory          $quoteIdMaskFactory
     * @param QuoteRepository             $quoteRepository
     *
     * @throws \Exception
     */
    public function __construct(
        CartRepositoryInterface $cartRepository,
        CustomerRepositoryInterface $customerRepository,
        SyncLoggerFactory $syncLoggerFactory,
        Session $checkoutSession,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        QuoteRepository $quoteRepository
    )
    {
        $this->cartRepository     = $cartRepository;
        $this->checkoutSession    = $checkoutSession;
        $this->customerRepository = $customerRepository;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->quoteRepository    = $quoteRepository;
        $this->logger             = $syncLoggerFactory->create();
    }

    /**
     * @param int $customerId
     * @param int $cartId
     *
     * @return SyncInterface|false
     */
    public function synchronizeCustomerCart($customerId, $cartId): SyncInterface
    {
        try {
            $customer = $this->customerRepository->getById($customerId);
            $cart     = $this->cartRepository->getForCustomer($customer->getId(), [$customer->getStoreId()]);
        } catch (NoSuchEntityException $e) {
            $this->logger->addError($e->getMessage());

            return false;
        } catch (LocalizedException $e) {
            $this->logger->addError($e->getMessage());

            return false;
        }

        if ($cartId !== $cart->getId()) {
            try {
                $currentQuote = $this->quoteRepository->get($cart->getId());
                $newQuote     = $this->quoteRepository->getActive($cartId);
            } catch (NoSuchEntityException $e) {
                $this->logger->addError($e->getMessage());

                return false;
            }

            $newQuote->setCustomer($customer);
            $this->cartRepository->save($newQuote->merge($currentQuote)->collectTotals());

            $this->checkoutSession->resetCheckout();
            $this->checkoutSession->replaceQuote($newQuote);
            $this->quoteRepository->delete($currentQuote);
        }

        $this->checkoutSession->regenerateId();

        return $this;
    }

    /**
     * @param string $cartId
     *
     * @return SyncInterface|false
     */
    public function synchronizeGuestCart(string $cartId): SyncInterface
    {
        /** @var QuoteIdMask $quoteIdMask */
        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');

        if (null === $quoteIdMask->getId()) {
            return false;
        }

        try {
            $quote = $this->quoteRepository->getActive($quoteIdMask->getQuoteId());
        } catch (NoSuchEntityException $e) {
            $this->logger->addError($e->getMessage());

            return false;
        }

        $this->cartRepository->save($quote);
        $this->checkoutSession->replaceQuote($quote);
        $this->checkoutSession->regenerateId();

        return $this;
    }
}
