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

class ProofageGateModuleFrontController extends ModuleFrontController
{
    /** @var string */
    private $back = '';

    public function init()
    {
        parent::init();
        $requested = (string) Tools::getValue('back');
        $this->back = $this->proofage()->gatekeeper()->safeBack($requested);
        if ($requested !== '' && $this->back !== $requested) {
            // PrestaShop echoes the request URL into the page (canonical, og:url, JS config): drop the off-site value.
            Tools::redirect($this->context->link->getModuleLink('proofage', 'gate', [], true));
        }
    }

    public function initContent()
    {
        parent::initContent();
        $gatekeeper = $this->proofage()->gatekeeper();
        if ($gatekeeper->isVerified()) {
            Tools::redirect($this->back);
        }
        http_response_code(403);
        header('X-Robots-Tag: noindex, nofollow');
        $this->context->smarty->assign(['proofage' => $gatekeeper->viewModel($this->back)]);
        $this->setTemplate('module:proofage/views/templates/front/gate.tpl');
    }

    public function setMedia()
    {
        $result = parent::setMedia();
        $this->proofage()->gatekeeper()->registerAssets($this, $this->back);

        return $result;
    }

    private function proofage(): Proofage
    {
        /** @var Proofage $module */
        $module = $this->module;

        return $module;
    }
}
