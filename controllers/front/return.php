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

class ProofageReturnModuleFrontController extends ModuleFrontController
{
    /** @var string */
    private $back = '';
    /** @var bool */
    private $cookieMissing = false;
    /** @var bool */
    private $found = false;

    public function init()
    {
        parent::init();
        $factory = ServiceFactory::forShop((int) $this->context->shop->id);
        $gatekeeper = $this->proofage()->gatekeeper();
        $ref = (string) Tools::getValue('ref');
        $record = preg_match('/^[0-9a-f]{32}$/', $ref) ? $factory->verifications()->findByReturnRef($ref) : null;

        $this->found = $record !== null;
        $this->back = $gatekeeper->safeBack($record !== null ? $record->originUrl : '');
        if ($record !== null) {
            if ($record->matchesToken($gatekeeper->cookie()->token())) {
                $factory->verificationService()->sync($record);
            } else {
                $this->cookieMissing = true;
            }
        }
    }

    public function initContent()
    {
        parent::initContent();
        header('X-Robots-Tag: noindex, nofollow');
        $this->context->smarty->assign(['proofage' => [
            'back' => $this->back,
            'found' => $this->found,
            'cookieMissing' => $this->cookieMissing,
        ]]);
        $this->setTemplate('module:proofage/views/templates/front/return.tpl');
    }

    public function setMedia()
    {
        $result = parent::setMedia();
        $this->proofage()->gatekeeper()->registerAssets($this, $this->back, [
            'returnMode' => $this->found && !$this->cookieMissing,
            'cookieMissing' => $this->cookieMissing,
        ]);

        return $result;
    }

    private function proofage(): Proofage
    {
        /** @var Proofage $module */
        $module = $this->module;

        return $module;
    }
}
