<?php
/**
 * @package   Divante\CartSync
 * @author    Mateusz Bukowski <mbukowski@divante.pl>
 * @copyright 2018 Divante Sp. z o.o.
 * @license   See LICENSE_DIVANTE.txt for license details.
 */

namespace Divante\CartSync\Controller\Cart;

use Divante\CartSync\Service\SyncInterface;
use Divante\CartSync\Service\SyncLoggerFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Integration\Model\Oauth\Token;
use Magento\Integration\Model\Oauth\TokenFactory;
use Monolog\Logger;

/**
 * Class Sync
 */
class Sync extends Action
{

    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;
    /**
     * @var CustomerSession
     */
    private $customerSession;
    /**
     * @var Logger
     */
    private $logger;
    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;
    /**
     * @var TokenFactory
     */
    private $tokenFactory;
    /**
     * @var SyncInterface
     */
    private $sync;

    /**
     * Sync constructor.
     *
     * @param Context                     $context
     * @param CustomerRepositoryInterface $customerRepository
     * @param CustomerSession             $customerSession
     * @param JsonFactory                 $resultJsonFactory
     * @param SyncInterface               $sync
     * @param SyncLoggerFactory           $syncLoggerFactory
     * @param TokenFactory                $tokenFactory
     *
     * @throws \Exception
     */
    public function __construct(
        Context $context,
        CustomerRepositoryInterface $customerRepository,
        CustomerSession $customerSession,
        JsonFactory $resultJsonFactory,
        SyncInterface $sync,
        SyncLoggerFactory $syncLoggerFactory,
        TokenFactory $tokenFactory
    )
    {
        parent::__construct($context);

        $this->customerRepository = $customerRepository;
        $this->customerSession    = $customerSession;
        $this->tokenFactory       = $tokenFactory;
        $this->sync               = $sync;

        $this->logger            = $syncLoggerFactory->create();
        $this->resultJsonFactory = $resultJsonFactory;
    }

    /**
     * @return ResponseInterface|Json|ResultInterface
     */
    public function execute()
    {
        if (!$this->hasRequestAllRequiredParams()) {
            return $this->resultRedirectFactory->create()->setPath('checkout/cart');
        }

        $customerToken = $this->getRequest()->getParam('token');
        $cartId        = $this->getRequest()->getParam('cart');

        if ($this->isGuestCart($cartId)) {
            $result = $this->sync->synchronizeGuestCart($cartId);

            if (!$result) {
                return $this->resultRedirectFactory->create()->setPath('checkout/cart');
            }
        } else {
            /** @var Token $token */
            $token = $this->tokenFactory->create()->loadByToken($customerToken);

            if (!$this->isTokenValid($token)) {
                return $this->resultRedirectFactory->create()->setPath('checkout/cart');
            }

            $isCustomerLogged = false;

            if ($this->customerSession->isLoggedIn()) {
                $isCustomerLogged = true;

                if ($token->getCustomerId() !== $this->customerSession->getCustomerId()) {
                    $this->customerSession->logout();

                    return $this->_redirect(
                        'vue/cart/sync',
                        [
                            'token' => $customerToken,
                            'cart'  => $cartId,
                        ]
                    );
                }
            }

            if (!$isCustomerLogged) {
                try {
                    $customer = $this->customerRepository->getById($token->getCustomerId());
                } catch (NoSuchEntityException $e) {
                    $this->logger->addError($e->getMessage());

                    return $this->resultRedirectFactory->create()->setPath('checkout/cart');
                } catch (LocalizedException $e) {
                    $this->logger->addError($e->getMessage());

                    return $this->resultRedirectFactory->create()->setPath('checkout/cart');
                }

                $this->customerSession->loginById($customer->getId());
            }

            $result = $this->sync->synchronizeCustomerCart($this->customerSession->getCustomerId(), $cartId);

            if (!$result) {
                return $this->resultRedirectFactory->create()->setPath('checkout/cart');
            }
        }

        return $this->resultRedirectFactory->create()->setPath('checkout/cart');
    }

    /**
     * @param Token $token
     *
     * @return bool
     */
    private function isTokenValid(Token $token): bool
    {
        return $token->getId() || !$token->getRevoked() || $token->getCustomerId();
    }

    /**
     * @return bool
     */
    private function hasRequestAllRequiredParams(): bool
    {
        return null !== $this->getRequest()->getParam('token')
               && null !== $this->getRequest()->getParam('cart');
    }

    /**
     * @param string $cartId
     *
     * @return bool
     */
    private function isCustomerCart(string $cartId): bool
    {
        return is_numeric($cartId);
    }

    /**
     * @param string $cartId
     *
     * @return bool
     */
    private function isGuestCart(string $cartId): bool
    {
        return !$this->isCustomerCart($cartId);
    }
}
