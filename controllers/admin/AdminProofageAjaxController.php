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

class AdminProofageAjaxController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
    }

    /**
     * Whether a profile holds the given legacy right ("view", "edit") on this hidden tab.
     * The token alone is not enough: Controller::run() dispatches actions even without view access.
     *
     * @param int $idProfile
     * @param string $legacyRight
     */
    public static function profileCan($idProfile, $legacyRight)
    {
        $slugs = [];
        foreach ((array) Access::getAuthorizationFromLegacy($legacyRight) as $suffix) {
            $slugs[] = 'ROLE_MOD_TAB_ADMINPROOFAGEAJAX_' . $suffix;
        }

        return $slugs !== [] && Access::isGranted($slugs, (int) $idProfile);
    }

    public function postProcess()
    {
        $isReset = Tools::getValue('proofage_action') === 'reset_customer';
        if ($isReset && !self::profileCan($this->context->employee->id_profile, 'edit')) {
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminDashboard'));
        }
        if ($this->ajax && !self::profileCan($this->context->employee->id_profile, 'view')) {
            http_response_code(403);
            $this->json(Tools::getValue('action') === 'SearchProducts' ? [] : ['ok' => false, 'error' => 'Access denied']);
        }
        if ($isReset) {
            $idCustomer = (int) Tools::getValue('id_customer');
            ServiceFactory::forShop((int) $this->context->shop->id)->visitorState()->resetCustomer($idCustomer, (int) $this->context->shop->id);
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminCustomers', true, ['route' => 'admin_customers_view', 'customerId' => $idCustomer], ['id_customer' => $idCustomer, 'viewcustomer' => 1]));
        }
        if (!$this->ajax) {
            Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', true, [], ['configure' => 'proofage']));
        }

        return parent::postProcess();
    }

    public function displayAjaxSearchProducts()
    {
        $query = trim((string) Tools::getValue('q'));
        $results = [];
        if (Tools::strlen($query) >= 2) {
            $rows = Product::searchByName((int) $this->context->language->id, $query);
            foreach (array_slice(is_array($rows) ? $rows : [], 0, 20) as $row) {
                $results[] = ['id' => (int) $row['id_product'], 'name' => (string) $row['name'], 'reference' => (string) $row['reference']];
            }
        }
        $this->json($results);
    }

    public function displayAjaxTestConnection()
    {
        $factory = ServiceFactory::forShop((int) $this->context->shop->id);
        if (!$factory->settings()->isConfigured()) {
            $this->json(['ok' => false, 'error' => $this->trans('Save your public and secret keys first.', [], 'Modules.Proofage.Admin')]);
        }
        try {
            $workspace = $factory->api()->getWorkspace();
        } catch (ApiException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage() . ($e->getHttpStatus() ? ' (HTTP ' . $e->getHttpStatus() . ')' : '')]);
        }
        $fields = ['name', 'mode', 'flow_type', 'age_mode', 'age_threshold', 'wallet_first'];
        $this->json(['ok' => true, 'workspace' => array_intersect_key($workspace, array_flip($fields))]);
    }

    /**
     * @param mixed $data
     */
    private function json($data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
