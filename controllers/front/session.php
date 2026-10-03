<?php

/**
 * ProofAge Age Verification for PrestaShop
 *
 * @author    Denis <denis@proofage.net>
 * @copyright Since 2026 ProofAge
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

use ProofAge\PrestaShop\Api\ApiException;
use ProofAge\PrestaShop\Infrastructure\ServiceFactory;
use ProofAge\PrestaShop\Support\ModuleInfo;
use ProofAge\PrestaShop\Verification\RateLimitedException;
use ProofAge\PrestaShop\Verification\StartRequest;

class ProofageSessionModuleFrontController extends ModuleFrontController
{
    public $ajax = true;

    public function postProcess()
    {
        if ((isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET') !== 'POST') {
            $this->respond(405, ['error' => 'method_not_allowed']);
        }
        if ((string) Tools::getValue('token') !== Tools::getToken(false)) {
            $this->respond(403, ['error' => 'invalid_token']);
        }

        $factory = ServiceFactory::forShop((int) $this->context->shop->id);
        if (!$factory->settings()->isConfigured()) {
            $this->respond(503, ['error' => 'not_configured']);
        }

        $gatekeeper = $this->module->gatekeeper();
        $cookie = $gatekeeper->cookie();
        $idCustomer = $gatekeeper->customerId();
        $link = $this->context->link;

        $request = new StartRequest();
        $request->idShop = $factory->idShop();
        $request->idCustomer = $idCustomer > 0 ? $idCustomer : null;
        $request->guestKey = $cookie->guestKey();
        $request->cookieVerificationId = $cookie->verificationId();
        $request->cookieToken = $cookie->token();
        $request->ipHash = hash('sha256', Tools::getRemoteAddr() . _COOKIE_KEY_);
        $request->originUrl = $gatekeeper->safeBack((string) Tools::getValue('back'));
        $request->language = (string) $this->context->language->iso_code;
        $request->metadata = [
            'integration' => 'prestashop-module',
            'module_version' => ModuleInfo::VERSION,
            'ps_version' => _PS_VERSION_,
            'shop_id' => $factory->idShop(),
        ];
        $request->callbackUrlFactory = function ($returnRef) use ($link) {
            return $link->getModuleLink('proofage', 'return', ['ref' => $returnRef], true);
        };

        try {
            $result = $factory->verificationService()->start($request);
        } catch (RateLimitedException $e) {
            $this->respond(429, ['error' => 'rate_limited']);
        } catch (ApiException $e) {
            $factory->logger()->warning('Could not create verification: HTTP ' . $e->getHttpStatus() . ' ' . $e->getMessage());
            $this->respond(502, ['error' => 'api_error', 'code' => $e->getErrorCode()]);
        }

        if ($result->newToken !== null) {
            $cookie->remember($result->verificationId, $result->newToken);
        }

        $this->respond(200, [
            'verification_id' => $result->verificationId,
            'url' => $result->url,
            'launch_mode' => $factory->settings()->launchMode(),
        ]);
    }

    /**
     * @param array<string,mixed> $body
     */
    private function respond(int $status, array $body)
    {
        http_response_code($status);
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        echo json_encode($body);
        exit;
    }
}
