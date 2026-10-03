<?php
/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace ProofAge\PrestaShop\Infrastructure;

if (!defined('_PS_VERSION_')) {
    exit;
}

use ProofAge\PrestaShop\Rules\PageContext;
use ProofAge\PrestaShop\Support\Urls;

/**
 * Storefront enforcement: decides per request whether to redirect to the gate,
 * show the overlay, or let the visitor through.
 */
final class Gatekeeper
{
    /** @var \Context */
    private $context;
    /** @var ServiceFactory */
    private $factory;
    /** @var PageContextResolver */
    private $resolver;
    /** @var bool */
    private $overlay = false;
    /** @var bool|null */
    private $verified;
    /** @var CookieState|null */
    private $cookie;

    public function __construct(\Context $context, ServiceFactory $factory)
    {
        $this->context = $context;
        $this->factory = $factory;
        $this->resolver = new PageContextResolver();
    }

    public function isActive(): bool
    {
        $settings = $this->factory->settings();

        return $settings->isConfigured() && !$settings->ruleSet()->isEmpty();
    }

    public function onFrontControllerInit(\FrontController $controller): void
    {
        if (!$this->isActive()) {
            return;
        }
        if ($this->isAddToCart($controller)) {
            $this->guardAddToCart();

            return;
        }
        if ($controller->ajax && PageContextResolver::controllerName($controller) === 'product') {
            $this->guardProductAjax();

            return;
        }

        $page = $this->resolver->resolve($controller, $this->context);
        if (!$this->factory->ruleEngine()->pageRequires($page)) {
            return;
        }
        if ($this->isVerified()) {
            $this->bindCart($page);

            return;
        }
        if ($page->type === PageContext::TYPE_CART
            || $page->type === PageContext::TYPE_CHECKOUT
            || $this->factory->settings()->displayMode() === 'gate') {
            \Tools::redirect($this->gateUrl($this->currentUrl()));
        }
        $this->overlay = true;
    }

    private function isAddToCart(\FrontController $controller): bool
    {
        return PageContextResolver::controllerName($controller) === 'cart'
            && (\Tools::getIsset('add') || \Tools::getIsset('update'))
            && \Tools::getValue('op', 'up') !== 'down'
            && (int) \Tools::getValue('id_product') > 0;
    }

    private function guardAddToCart(): void
    {
        $idProduct = (int) \Tools::getValue('id_product');
        if (!$this->factory->ruleEngine()->productRequires($idProduct) || $this->isVerified()) {
            return;
        }
        $gateUrl = $this->gateUrl($this->context->link->getProductLink($idProduct));
        if (\Tools::getValue('ajax')) {
            header('Content-Type: application/json');
            echo json_encode([
                'hasError' => true,
                'errors' => [$this->trans('Age verification is required to add this product to your cart.')],
                'quantity' => 0,
                'proofage' => ['required' => true, 'gateUrl' => $gateUrl],
            ]);
            exit;
        }
        \Tools::redirect($gateUrl);
    }

    /**
     * Product AJAX actions (quickview, refresh, ...) render product content without the page itself.
     */
    private function guardProductAjax(): void
    {
        $idProduct = (int) \Tools::getValue('id_product');
        if (!$this->factory->ruleEngine()->productRequires($idProduct) || $this->isVerified()) {
            return;
        }
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'hasError' => true,
            'errors' => [$this->trans('Age verification is required to view this product.')],
            'proofage' => ['required' => true, 'gateUrl' => $this->gateUrl($this->context->link->getProductLink($idProduct))],
        ]);
        exit;
    }

    public function overlayRequired(): bool
    {
        return $this->overlay;
    }

    public function needsAssets(): bool
    {
        return $this->isActive() && !$this->isVerified();
    }

    public function isVerified(): bool
    {
        if ($this->verified === null) {
            $cookie = $this->cookie();
            $this->verified = $this->factory->visitorState()->isVerified(
                $this->customerId(),
                $this->factory->idShop(),
                $cookie->verificationId(),
                $cookie->token()
            );
        }

        return $this->verified;
    }

    public function cookie(): CookieState
    {
        if ($this->cookie === null) {
            $this->cookie = new CookieState($this->context->cookie instanceof \Cookie ? $this->context->cookie : null);
        }

        return $this->cookie;
    }

    public function customerId(): int
    {
        $customer = $this->context->customer;

        return $customer instanceof \Customer && $customer->isLogged() ? (int) $customer->id : 0;
    }

    public function currentUrl(): string
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/';

        return \Tools::getShopProtocol() . \Tools::getHttpHost(false) . $uri;
    }

    public function gateUrl(string $back): string
    {
        return $this->context->link->getModuleLink('proofage', 'gate', ['back' => $back], true);
    }

    public function safeBack(string $back): string
    {
        if (Urls::isSafeRedirect($back, (string) \Tools::getHttpHost(false, false, true))) {
            return $back;
        }

        return $this->context->link->getPageLink('index', true);
    }

    /**
     * @return array<string,mixed>
     */
    public function viewModel(string $back): array
    {
        return [
            'texts' => $this->factory->settings()->gateTexts((int) $this->context->language->id),
            'back' => $back,
        ];
    }

    /**
     * @param array<string,mixed> $extra merged into the JS config (e.g. returnMode, cookieMissing)
     */
    public function registerAssets(\FrontController $controller, string $back, array $extra = []): void
    {
        $settings = $this->factory->settings();
        $controller->registerStylesheet('proofage-gate', 'modules/proofage/views/css/gate.css', ['media' => 'all', 'priority' => 200]);
        $controller->registerJavascript('proofage-gate', 'modules/proofage/views/js/gate.js', ['position' => 'bottom', 'priority' => 200]);
        \Media::addJsDef(['proofageGate' => array_merge([
            'sessionUrl' => $this->context->link->getModuleLink('proofage', 'session', [], true),
            'statusUrl' => $this->context->link->getModuleLink('proofage', 'status', [], true),
            'token' => \Tools::getToken(false),
            'launchMode' => $settings->launchMode(),
            'sdkUrl' => Urls::sdkLoaderUrl($settings->apiUrl()),
            'publicKey' => $settings->publicKey(),
            'language' => (string) $this->context->language->iso_code,
            'backUrl' => $back,
            'returnMode' => false,
            'cookieMissing' => false,
            'texts' => [
                'starting' => $this->trans('Starting verification…'),
                'inProgress' => $this->trans('Complete the verification in the window that opened.'),
                'checking' => $this->trans('Checking your verification…'),
                'review' => $this->trans('Your verification is being reviewed. You can close this page: the result will be applied on your next visit.'),
                'stillPending' => $this->trans('Your verification is still in progress. Click the button to continue it.'),
                'approved' => $this->trans('Verification approved. Redirecting…'),
                'declined' => $this->trans('Your verification was declined. You can try again.'),
                'retry' => $this->trans('We need you to retake a photo or document. Click the button to continue.'),
                'reset' => $this->trans('Your previous verification has expired. Please start again.'),
                'rateLimited' => $this->trans('Too many attempts. Please try again later.'),
                'error' => $this->trans('The verification could not be started. Please try again later.'),
                'insecure' => $this->trans('Verification requires a secure (HTTPS) connection.'),
            ],
        ], $extra)]);
    }

    public function promote(int $idCustomer): void
    {
        $cookie = $this->cookie();
        if ($this->factory->visitorState()->promoteGuest($idCustomer, $this->factory->idShop(), $cookie->verificationId(), $cookie->token(), $this->factory->settings()->ttlPolicy())) {
            $this->verified = null;
        }
    }

    public function trans(string $text): string
    {
        return $this->context->getTranslator()->trans($text, [], 'Modules.Proofage.Shop');
    }

    /**
     * Remembers the cart of a verified guest so cookieless payment callbacks can still place the order.
     */
    private function bindCart(PageContext $page): void
    {
        if ($page->type !== PageContext::TYPE_CART && $page->type !== PageContext::TYPE_CHECKOUT) {
            return;
        }
        $cart = $this->context->cart;
        if (!$cart instanceof \Cart || !$cart->id) {
            return;
        }
        $cookie = $this->cookie();
        $record = $this->factory->visitorState()->approvedGuestRecord($cookie->verificationId(), $cookie->token());
        if ($record !== null && $record->idCart !== (int) $cart->id) {
            $record->idCart = (int) $cart->id;
            $record->updatedAt = time();
            $this->factory->verifications()->update($record);
        }
    }
}
