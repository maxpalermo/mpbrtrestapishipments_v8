<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 *
 * @author    Massimiliano Palermo <maxx.palermo@gmail.com>
 * @copyright Since 2016 Massimiliano Palermo
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/vendor/autoload.php';
require_once dirname(__FILE__) . '/src/Models/autoload.php';

use PrestaShop\PrestaShop\Adapter\SymfonyContainer;
use PrestaShop\PrestaShop\Core\Action\ActionsBarButton;

class MpBrtRestApiShipments extends Module
{
    protected static $adminControllerName = 'AdminMpBrtRestApiShipments';

    public function __construct()
    {
        $this->name = 'mpbrtrestapishipments';
        $this->tab = 'shipping_logistics';
        $this->version = '1.5.31';
        $this->author = 'Massimiliano Palermo';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '8.0', 'max' => '8.99'];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('BRT Rest API Shipments & Tracking');
        $this->description = $this->l('Gestione spedizioni e tracking BRT tramite REST API ufficiali.');
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook([
                'displayAdminOrderTop',
                'displayAdminEndContent',
                'displayAdminOrderMainBottom',
                'displayAdminOrderSide',
                'actionGetAdminToolbarButtons',
                'actionAdminControllerSetMedia',
            ])
            && $this->installTab()
            && ModelBrtRestApiShipmentRequest::install()
            && ModelBrtRestApiShipmentResponse::install()
            && ModelBrtRestApiTracking::install()
            && ModelBrtRestApiBordero::install()
            && ModelBrtRestApiWeight::install();
    }

    public function uninstall()
    {
        return parent::uninstall()
            && $this->uninstallTab()
            && ModelBrtRestApiBordero::uninstall()
            && ModelBrtRestApiWeight::uninstall();
    }

    public function installTab()
    {
        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = self::$adminControllerName;
        $tab->name = [];
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = 'BRT REST API';
        }

        $tabRes = SymfonyContainer::getInstance()->get('prestashop.core.admin.tab.repository');
        $tab->id_parent = (int) $tabRes->findOneIdByClassName('AdminParentShipping');
        $tab->module = $this->name;
        $tab->icon = 'local_shipping';
        $tab->position = 1;
        $tab->enabled = 1;
        $tab->active = 1;

        return $tab->add();
    }

    public function uninstallTab()
    {
        $tabRes = SymfonyContainer::getInstance()->get('prestashop.core.admin.tab.repository');
        $tabId = $tabRes->findOneIdByClassName(self::$adminControllerName);
        if ($tabId) {
            $tab = new Tab((int) $tabId);
            if (Validate::isLoadedObject($tab)) {
                return $tab->delete();
            }
        }

        return true;
    }

    public function getContent()
    {
        Tools::redirectAdmin(
            Context::getContext()->link->getAdminLink(self::$adminControllerName)
        );
    }

    public function hookActionGetAdminToolbarButtons(array $params)
    {
        $controller = $params['controller'] ?? null;
        $buttons = $params['toolbar_extra_buttons_collection'] ?? null;
        if (!$controller || !$buttons) {
            return 0;
        }

        $controller_name = $controller->controller_name ?? '';
        if (Tools::strtolower($controller_name) !== 'adminorders') {
            return 0;
        }

        $id_order = (int) Tools::getValue('id_order');
        if (!$id_order && isset($params['request'])) {
            $id_order = (int) ($params['request']->attributes->get('orderId') ?: $params['request']->get('orderId'));
        }

        if (!$id_order) {
            return 0;
        }

        $order = new Order($id_order);
        if (!Validate::isLoadedObject($order)) {
            return 0;
        }

        $currentState = (int) $order->getCurrentState();
        $showOnStates = json_decode((string) Configuration::get('MPBRTRESTAPI_ORDERSTATES_DISPLAY'), true) ?: [];

        if (!in_array($currentState, $showOnStates) && !$this->context->employee->isSuperAdmin()) {
            return 0;
        }

        // 1. Pulsante "Crea Segnacollo BRT"
        $buttonCreate = new ActionsBarButton(
            'btn-primary',
            [
                'href' => 'javascript:brtRestApiOpenDialog(' . (int) $order->id . ');',
                'icon' => 'qr_code',
                'id' => 'btnBrtCreateShipmentOrderToolbar',
                'class' => 'btnBrtCreateShipmentOrderToolbar',
                'data' => [
                    'action' => 'create_brt_shipment',
                    'id_order' => (int) $order->id,
                ],
            ],
            ' ' . $this->l('Crea Segnacollo BRT')
        );
        $buttons->add($buttonCreate);

        // 2. Pulsante "Tracking BRT"
        $trackingUrl = Context::getContext()->link->getAdminLink(self::$adminControllerName) . '&tab=tracking&filter_id_order=' . (int) $order->id;
        $buttonTracking = new ActionsBarButton(
            'btn-info',
            [
                'href' => $trackingUrl,
                'icon' => 'local_shipping',
                'id' => 'btnBrtTrackingOrderToolbar',
                'class' => 'btnBrtTrackingOrderToolbar',
                'target' => '_blank',
                'data' => [
                    'action' => 'brt_tracking',
                    'id_order' => (int) $order->id,
                ],
            ],
            ' ' . $this->l('Tracking BRT')
        );
        $buttons->add($buttonTracking);

        return 0;
    }

    public function hookActionAdminControllerSetMedia($params)
    {
        if ($this->isAdminOrderPageController()) {
            $this->context->controller->addJS($this->_path . 'views/js/mpbrtrestapishipments-admin.js');
            $this->context->controller->addCSS($this->_path . 'views/css/mpbrtrestapishipments-admin.css');
            $this->context->controller->addCSS($this->_path . 'views/css/style-override.css');

            Media::addJsDef([
                'brtAdminUrl' => Context::getContext()->link->getAdminLink(self::$adminControllerName),
            ]);
        }
    }

    public function hookDisplayAdminOrderTop($params)
    {
        return '';
    }

    public function hookDisplayAdminOrderMainBottom($params)
    {
        return $this->renderShipmentModal();
    }

    public function hookDisplayAdminOrderSide($params)
    {
        return $this->renderShipmentModal();
    }

    public function hookDisplayAdminEndContent($params)
    {
        return $this->renderShipmentModal();
    }

    protected function renderShipmentModal()
    {
        if (!$this->isAdminOrderPageController()) {
            return '';
        }

        static $alreadyRendered = false;
        if ($alreadyRendered) {
            return '';
        }

        $id_order = (int) Tools::getValue('id_order');
        if (!$id_order) {
            try {
                $request = SymfonyContainer::getInstance()->get('request_stack')->getCurrentRequest();
                if ($request) {
                    $id_order = (int) ($request->attributes->get('orderId') ?: $request->get('orderId'));
                }
            } catch (\Exception $e) {
            }
        }

        if (!$id_order) {
            return '';
        }

        $alreadyRendered = true;

        try {
            $sfContainer = SymfonyContainer::getInstance();
            $twig = $sfContainer->get('twig');

            return $twig->render('@Modules/mpbrtrestapishipments/views/twig/admin/modal-shipment.html.twig', [
                'admin_url' => Context::getContext()->link->getAdminLink(self::$adminControllerName),
                'id_order' => $id_order,
            ]);
        } catch (\Exception $e) {
            return '';
        }
    }

    protected function isAdminOrderPageController()
    {
        $controller = (string) Tools::getValue('controller');

        $isOrderController = preg_match('/(AdminOrders|AdminMpCustomerInvoice)/i', $controller)
            || (isset($this->context->controller) && $this->context->controller instanceof \PrestaShopBundle\Controller\Admin\Sell\Order\OrderController)
            || (isset($_SERVER['REQUEST_URI']) && (strpos($_SERVER['REQUEST_URI'], '/orders') !== false || strpos($_SERVER['REQUEST_URI'], 'AdminMpCustomerInvoice') !== false));

        return $isOrderController;
    }

    public static function getAdminControllerUrl()
    {
        return Context::getContext()->link->getAdminLink(self::$adminControllerName);
    }
}
