<?php

/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ProofAge\PrestaShop\Infrastructure;

use ProofAge\PrestaShop\Verification\OrderSnapshot;

/**
 * Last line of defence: refuses to create an order for a cart that needs a verification
 * the customer does not have, whichever payment module or checkout created it.
 */
final class OrderGuard
{
    /** @var \Context */
    private $context;
    /** @var ServiceFactory */
    private $factory;

    public function __construct(\Context $context, ServiceFactory $factory)
    {
        $this->context = $context;
        $this->factory = $factory;
    }

    /**
     * @throws OrderBlockedError
     */
    public function assertOrderAllowed(\Order $order): void
    {
        if (defined('_PS_ADMIN_DIR_')) {
            return; // orders created by staff in the back office
        }
        try {
            $this->check($order);
        } catch (OrderBlockedError $e) {
            throw $e;
        } catch (\Throwable $e) {
            // Fail closed: an unexpected failure must never let an unverified order through.
            try {
                $this->factory->logger()->warning('Order guard failed for cart ' . (int) $order->id_cart . ': ' . $e->getMessage());
            } catch (\Throwable $ignored) {
                // logging must not change the outcome
            }

            throw new OrderBlockedError($this->message(), 0, $e);
        }
    }

    /**
     * @throws OrderBlockedError
     */
    private function check(\Order $order): void
    {
        $settings = $this->factory->settings();
        if (!$settings->isConfigured() || $settings->ruleSet()->isEmpty()) {
            return;
        }
        $cart = new \Cart((int) $order->id_cart);
        if (!$this->factory->ruleEngine()->cartRequires(PageContextResolver::cartProductIds($cart))) {
            return;
        }
        if ($this->approval($order) !== null) {
            return;
        }
        $this->factory->logger()->warning('Order blocked for cart ' . (int) $order->id_cart . ': verification missing');

        throw new OrderBlockedError($this->message());
    }

    private function message(): string
    {
        $text = 'Age verification is required to order the products in your cart.';
        try {
            return $this->context->getTranslator()->trans($text, [], 'Modules.Proofage.Shop');
        } catch (\Throwable $e) {
            return $text;
        }
    }

    public function snapshot(\Order $order, \Cart $cart): void
    {
        try {
            $required = $this->factory->settings()->isConfigured()
                && $this->factory->ruleEngine()->cartRequires(PageContextResolver::cartProductIds($cart));
            $approval = $this->approval($order);
            if ($approval !== null) {
                $snapshot = new OrderSnapshot((int) $order->id, $required, $approval->verificationId, OrderSnapshot::STATUS_APPROVED, $approval->method, $approval->verifiedAt);
            } else {
                $snapshot = new OrderSnapshot((int) $order->id, $required, null, $required ? OrderSnapshot::STATUS_NOT_VERIFIED : OrderSnapshot::STATUS_NOT_REQUIRED, null, null);
            }
            $this->factory->orders()->save($snapshot);
        } catch (\Throwable $e) {
            try {
                $this->factory->logger()->warning('Could not save the verification snapshot of order ' . (int) $order->id . ': ' . $e->getMessage());
            } catch (\Throwable $ignored) {
                // never break the order confirmation
            }
        }
    }

    /**
     * @return \ProofAge\PrestaShop\Verification\Approval|null
     */
    private function approval(\Order $order)
    {
        $cookie = new CookieState($this->context->cookie instanceof \Cookie ? $this->context->cookie : null);

        return $this->factory->visitorState()->resolveApproval(
            (int) $order->id_customer,
            (int) $order->id_shop,
            (int) $order->id_cart,
            $cookie->verificationId(),
            $cookie->token()
        );
    }
}
