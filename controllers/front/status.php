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

use ProofAge\PrestaShop\Infrastructure\ServiceFactory;
use ProofAge\PrestaShop\Verification\StatusMapper;

class ProofageStatusModuleFrontController extends ModuleFrontController
{
    public $ajax = true;

    public function postProcess()
    {
        $factory = ServiceFactory::forShop((int) $this->context->shop->id);
        $gatekeeper = $this->module->gatekeeper();
        $cookie = $gatekeeper->cookie();

        $vid = $cookie->verificationId();
        $record = $vid !== null ? $factory->verifications()->findByVerificationId($vid) : null;
        if ($record !== null && $record->matchesToken($cookie->token())) {
            $record = $factory->verificationService()->sync($record);
        } else {
            $record = null;
        }

        if ($factory->visitorState()->isVerified($gatekeeper->customerId(), $factory->idShop(), $vid, $cookie->token())) {
            $redirect = $record !== null ? $gatekeeper->safeBack($record->originUrl) : $gatekeeper->safeBack((string) Tools::getValue('back'));
            $this->respond(['state' => StatusMapper::STATE_APPROVED, 'redirect' => $redirect]);
        }
        if ($record === null) {
            $this->respond(['state' => 'none']);
        }

        $state = StatusMapper::toState($record->status);
        if ($state === StatusMapper::STATE_APPROVED) {
            // Approved but no longer valid (expired guest approval).
            $state = StatusMapper::STATE_RESET;
        }
        $this->respond(['state' => $state, 'status' => $record->status]);
    }

    /**
     * @param array<string,mixed> $body
     */
    private function respond(array $body)
    {
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        echo json_encode($body);
        exit;
    }
}
