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

require_once __DIR__ . '/vendor/autoload.php';

use ProofAge\PrestaShop\Infrastructure\Gatekeeper;
use ProofAge\PrestaShop\Infrastructure\Installer;
use ProofAge\PrestaShop\Infrastructure\OrderGuard;
use ProofAge\PrestaShop\Infrastructure\ServiceFactory;
use ProofAge\PrestaShop\Infrastructure\SettingsForm;

class Proofage extends Module
{
    /** @var array<int,array<string,mixed>> hidden back-office controller used for AJAX */
    public $tabs = [
        [
            'name' => 'ProofAge AJAX',
            'class_name' => 'AdminProofageAjax',
            'visible' => false,
            'parent_class_name' => 'AdminParentModulesSf',
        ],
    ];

    /** @var Gatekeeper|null */
    private $gatekeeper;

    public function __construct()
    {
        $this->name = 'proofage';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'ProofAge';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = ['min' => '8.1.0', 'max' => _PS_VERSION_];

        parent::__construct();

        $this->displayName = $this->trans('ProofAge Age Verification', [], 'Modules.Proofage.Admin');
        $this->description = $this->trans('Age and identity verification for products, categories and pages, powered by ProofAge.', [], 'Modules.Proofage.Admin');
    }

    public function isUsingNewTranslationSystem()
    {
        return true;
    }

    public function install()
    {
        return parent::install() && (new Installer())->install($this);
    }

    public function uninstall()
    {
        return (new Installer())->uninstall() && parent::uninstall();
    }

    public function getContent()
    {
        $form = new SettingsForm($this, $this->context, (int) $this->context->shop->id);
        $output = '';
        if (Tools::isSubmit('submitProofage')) {
            $errors = $form->save(Tools::getAllValues());
            $output .= $errors === [] ? $this->displayConfirmation($form->t('Settings saved.')) : $this->displayError($errors);
        }
        $warning = $form->inactiveWarning();
        if ($warning !== null) {
            $output .= $this->displayWarning($warning);
        }
        $this->context->controller->addJS($this->_path . 'views/js/admin.js');
        $this->context->controller->addCSS($this->_path . 'views/css/admin.css');

        return $output . $form->render() . $form->renderLogs();
    }

    public function gatekeeper(): Gatekeeper
    {
        if ($this->gatekeeper === null) {
            $this->gatekeeper = new Gatekeeper($this->context, ServiceFactory::forShop((int) $this->context->shop->id));
        }

        return $this->gatekeeper;
    }

    public function hookActionFrontControllerInitAfter(array $params)
    {
        $controller = isset($params['controller']) ? $params['controller'] : $this->context->controller;
        if ($controller instanceof FrontController) {
            $this->gatekeeper()->onFrontControllerInit($controller);
        }
    }

    public function hookActionFrontControllerSetMedia()
    {
        $controller = $this->context->controller;
        if ($controller instanceof FrontController && $this->gatekeeper()->needsAssets()) {
            $this->gatekeeper()->registerAssets($controller, $this->gatekeeper()->currentUrl());
        }
    }

    public function hookDisplayBeforeBodyClosingTag()
    {
        $gatekeeper = $this->gatekeeper();
        if (!$gatekeeper->overlayRequired()) {
            return '';
        }
        $this->context->smarty->assign(['proofage' => $gatekeeper->viewModel($gatekeeper->currentUrl())]);

        return $this->fetch('module:proofage/views/templates/hook/overlay.tpl');
    }

    public function orderGuard(): OrderGuard
    {
        return new OrderGuard($this->context, ServiceFactory::forShop((int) $this->context->shop->id));
    }

    public function hookActionObjectOrderAddBefore(array $params)
    {
        if (isset($params['object']) && $params['object'] instanceof Order) {
            $this->orderGuard()->assertOrderAllowed($params['object']);
        }
    }

    public function hookActionValidateOrder(array $params)
    {
        if (isset($params['order'], $params['cart']) && $params['order'] instanceof Order && $params['cart'] instanceof Cart) {
            $this->orderGuard()->snapshot($params['order'], $params['cart']);
        }
    }

    public function hookActionAuthentication(array $params)
    {
        if (isset($params['customer']) && $params['customer'] instanceof Customer) {
            $this->gatekeeper()->promote((int) $params['customer']->id);
        }
    }

    public function hookActionCustomerAccountAdd(array $params)
    {
        if (isset($params['newCustomer']) && $params['newCustomer'] instanceof Customer) {
            $this->gatekeeper()->promote((int) $params['newCustomer']->id);
        }
    }

    public function hookDisplayAdminOrderMain(array $params)
    {
        $idOrder = isset($params['id_order']) ? (int) $params['id_order'] : 0;
        $snapshot = ServiceFactory::forShop((int) $this->context->shop->id)->orders()->find($idOrder);
        $this->context->smarty->assign(['proofage_snapshot' => $snapshot]);

        return $this->display(__FILE__, 'views/templates/hook/admin_order.tpl');
    }

    public function hookDisplayAdminCustomers(array $params)
    {
        $idCustomer = isset($params['id_customer']) ? (int) $params['id_customer'] : 0;
        $idShop = (int) $this->context->shop->id;
        $verification = ServiceFactory::forShop($idShop)->customers()->find($idCustomer, $idShop);
        $this->context->smarty->assign([
            'proofage_customer_verification' => $verification,
            'proofage_customer_valid' => $verification !== null && $verification->isValidAt(time()),
            'proofage_reset_url' => $this->context->link->getAdminLink('AdminProofageAjax', true, [], ['proofage_action' => 'reset_customer', 'id_customer' => $idCustomer]),
        ]);

        return $this->display(__FILE__, 'views/templates/hook/admin_customer.tpl');
    }

    public function hookActionExportGDPRData(array $customer)
    {
        $idCustomer = isset($customer['id']) ? (int) $customer['id'] : 0;
        $rows = ServiceFactory::forShop((int) $this->context->shop->id)->verifications()->rowsForCustomer($idCustomer);

        return json_encode($rows);
    }

    public function hookActionDeleteGDPRCustomer(array $customer)
    {
        $idCustomer = isset($customer['id']) ? (int) $customer['id'] : 0;
        if ($idCustomer > 0) {
            $factory = ServiceFactory::forShop((int) $this->context->shop->id);
            $factory->customers()->deleteCustomerEverywhere($idCustomer);
            $factory->verifications()->anonymiseCustomer($idCustomer);
        }

        return json_encode(true);
    }
}
