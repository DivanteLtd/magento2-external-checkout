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
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteFactory;
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
     * @var ManagerInterface
     */
    private $messageManager;
    /**
     * @var QuoteRepository
     */
    private $quoteRepository;
    /**
     * @var QuoteIdMaskFactory
     */
    private $quoteIdMaskFactory;
    /**
     * @var QuoteFactory
     */
    private $quoteFactory;
    /**
     * @var CartRepositoryInterface
     */
    private $cartRepository;

    /**
     * Sync constructor.
     *
     * @param CartRepositoryInterface     $cartRepository
     * @param CustomerRepositoryInterface $customerRepository
     * @param ManagerInterface            $messageManager
     * @param SyncLoggerFactory           $syncLoggerFactory
     * @param Session                     $checkoutSession
     * @param QuoteIdMaskFactory          $quoteIdMaskFactory
     * @param QuoteFactory                $quoteFactory
     * @param QuoteRepository             $quoteRepository
     *
     * @throws \Exception
     */
    public function __construct(
        CartRepositoryInterface $cartRepository,
        CustomerRepositoryInterface $customerRepository,
        ManagerInterface $messageManager,
        SyncLoggerFactory $syncLoggerFactory,
        Session $checkoutSession,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        QuoteFactory $quoteFactory,
        QuoteRepository $quoteRepository
    )
    {
        $this->cartRepository     = $cartRepository;
        $this->checkoutSession    = $checkoutSession;
        $this->customerRepository = $customerRepository;
        $this->messageManager     = $messageManager;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->quoteFactory       = $quoteFactory;
        $this->quoteRepository    = $quoteRepository;
        $this->logger             = $syncLoggerFactory->create();
    }

    /**
     * @param int $customerId
     * @param int $cartId
     *
     * @return SyncInterface|false
     */
    public function synchronizeCustomerCart($customerId, $cartId)
    {
        if (!is_numeric($cartId)) {
            $cartId = $this->getGuestQuoteId($cartId);

            if (null === $cartId) {
                $this->messageManager->addErrorMessage(__('Guest quote doen\'t exists'));

                return false;
            }
        }

        try {
            $customer = $this->customerRepository->getById($customerId);
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(__('This customer doen\'t exists'));

            return false;
        } catch (LocalizedException $e) {
            $this->logger->addError($e->getMessage());

            return false;
        }

        try {
            $customerQuote = $this->quoteRepository->getForCustomer($customer->getId());
        } catch (NoSuchEntityException $e) {
            $customerQuote = $this->quoteFactory->create();
        }

        $customerQuote->setStoreId($customer->getStoreId());

        try {
            $vueQuote = $this->quoteRepository->getActive($cartId);
        } catch (NoSuchEntityException $e) {
            $this->logger->addError($e->getMessage());

            return false;
        }

        if ($customerQuote->getId() && $vueQuote->getId() !== $customerQuote->getId()) {
            $vueQuote->assignCustomerWithAddressChange(
                $customer,
                $vueQuote->getBillingAddress(),
                $vueQuote->getShippingAddress()
            );
            $this->quoteRepository->save($vueQuote->merge($customerQuote)->collectTotals());
            $this->checkoutSession->replaceQuote($vueQuote);
            $this->quoteRepository->delete($customerQuote);
            $this->checkoutSession->regenerateId();
        } else {
            $customerQuote->assignCustomerWithAddressChange(
                $customer,
                $customerQuote->getBillingAddress(),
                $customerQuote->getShippingAddress()
            );
            $customerQuote->collectTotals();
            $this->quoteRepository->save($customerQuote);
            $this->checkoutSession->replaceQuote($customerQuote);
        }

        return $this;
    }

    /**
     * @param string $cartId
     *
     * @return SyncInterface|false
     */
    public function synchronizeGuestCart(string $cartId)
    {
        $quoteIdMask = $this->getGuestQuoteId($cartId);

        if (null === $quoteIdMask) {
            $this->messageManager->addErrorMessage(__('Guest quote doen\'t exists'));

            return false;
        }

        try {
            $quote = $this->quoteRepository->getActive($quoteIdMask);
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(__('Guest quote doen\'t exists'));

            return false;
        }

        $this->cartRepository->save($quote->collectTotals());
        $this->checkoutSession->replaceQuote($quote);
        $this->checkoutSession->regenerateId();

        return $this;
    }

    /**
     * @param string $cartId
     *
     * @return int|null
     */
    private function getGuestQuoteId(string $cartId)
    {
        /** @var QuoteIdMask $quoteIdMask */
        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($cartId, 'masked_id');

        return $quoteIdMask->getQuoteId();
    }
}
