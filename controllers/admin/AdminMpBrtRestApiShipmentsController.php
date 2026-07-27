<?php

/**
 * @author    Massimiliano Palermo <maxx.palermo@gmail.com>
 * @copyright Since 2016 Massimiliano Palermo
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

use MpSoft\MpBrtRestApiShipments\Api\Shipment\BrtShipmentClient;
use MpSoft\MpBrtRestApiShipments\Api\Shipment\BrtShipmentRequest;
use MpSoft\MpBrtRestApiShipments\Api\Tracking\BrtTrackingClient;
use MpSoft\MpBrtRestApiShipments\Api\Tracking\BrtTrackingRequest;
use MpSoft\MpBrtRestApiShipments\Helpers\BrtConfig;
use MpSoft\MpBrtRestApiShipments\Helpers\BrtStats;
use MpSoft\MpBrtRestApiShipments\Helpers\GetTwigEnvironment;
use MpSoft\MpBrtRestApiShipments\Helpers\MpConnector;

class AdminMpBrtRestApiShipmentsController extends ModuleAdminController
{
    protected $currentTab = 'settings';

    public function __construct()
    {
        $this->bootstrap = true;
        $this->module = Module::getInstanceByName('mpbrtrestapishipments');

        parent::__construct();

        $this->meta_title = $this->module->l('BRT REST API Shipments & Tracking');

        if (Tools::isSubmit('ajax') && Tools::isSubmit('action')) {
            $action = 'ajaxProcess' . Tools::ucfirst(Tools::getValue('action'));
            if (method_exists($this, $action)) {
                header('Content-Type: application/json');
                http_response_code(200);
                $this->ajaxRender(json_encode($this->$action()));
                exit();
            }
        }
    }

    public function setMedia($isNewTheme = false)
    {
        parent::setMedia($isNewTheme);
        $this->addJS(_PS_JS_DIR_ . 'jquery/plugins/chosen/jquery.chosen.js');
        $this->addCSS($this->module->getLocalPath() . 'views/css/mpbrtrestapishipments-admin.css', 'all', 999);
        $this->addCSS($this->module->getLocalPath() . 'views/css/style-override.css', 'all', 1001);
        $this->addJS($this->module->getLocalPath() . 'views/js/mpbrtrestapishipments-admin.js');
        $this->addCSS($this->module->getLocalPath() . 'views/css/chosen-bootstrap-theme.css', 'all', 1000);
    }

    public function initContent()
    {
        $this->content = $this->getPage();
        parent::initContent();
    }

    protected function getPage(): string
    {
        $currentTab = Tools::getValue('tab', 'settings');
        $adminUrl = $this->context->link->getAdminLink('AdminMpBrtRestApiShipments');
        $config = BrtConfig::getAll();
        $idLang = (int) $this->context->language->id;

        $paymentModules = PaymentModule::getInstalledPaymentModules();
        $pm = [];
        if ($paymentModules) {
            foreach ($paymentModules as $pmItem) {
                $mod = PaymentModule::getInstanceByName($pmItem['name']);
                if (Validate::isLoadedObject($mod)) {
                    $pm[] = [
                        'id' => (int) $pmItem['id_module'],
                        'name' => $pmItem['name'],
                        'display_name' => $mod->displayName,
                    ];
                }
            }
        }

        $pricingRulesInput = $config[BrtConfig::PRICING_RULES] ?? null;
        $pricingRules = \MpSoft\MpBrtRestApiShipments\Helpers\BrtPricingRuleParser::parseRulesInput($pricingRulesInput);
        if (empty($pricingRules)) {
            $pricingRules = \MpSoft\MpBrtRestApiShipments\Helpers\BrtPricingRuleParser::getDefaultRules();
        }

        $params = [
            'admin_url' => $adminUrl,
            'admin_url_orders' => $this->context->link->getAdminLink('AdminOrders'),
            'current_tab' => $currentTab,
            'config' => $config,
            'order_states' => OrderState::getOrderStates($idLang),
            'payment_modules' => $pm,
            'selected_order_states' => json_decode((string) $config[BrtConfig::ORDERSTATES_DISPLAY], true) ?: [],
            'selected_order_states_start' => json_decode((string) $config[BrtConfig::ORDERSTATES_START_DATE], true) ?: [],
            'selected_order_states_end' => json_decode((string) $config[BrtConfig::ORDERSTATES_END_DATE], true) ?: [],
            'selected_cod_modules' => json_decode((string) $config[BrtConfig::COD_PAYMENT_MODULES], true) ?: [],
            'network_options' => BrtConfig::getNetworkOptions(),
            'freight_type_options' => BrtConfig::getFreightTypeOptions(),
            'service_type_options' => BrtConfig::getServiceTypeOptions(),
            'label_output_options' => BrtConfig::getLabelOutputTypeOptions(),
            'label_format_options' => BrtConfig::getLabelFormatOptions(),
            'pricing_rules' => $pricingRules,
            'pricing_default_code' => $config[BrtConfig::PRICING_DEFAULT_CODE] ?: '020',
            'available_fields' => \MpSoft\MpBrtRestApiShipments\Helpers\BrtPricingRuleParser::getAvailableFields(),
            'tab_shipments' => ModelBrtRestApiShipmentResponse::getAll(50, 0),
            'tab_bordero' => ModelBrtRestApiBordero::getUnprinted(100),
            'tab_tracking' => ModelBrtRestApiTracking::getAll(50, 0),
            'connector_url' => $config[BrtConfig::CONNECTOR_URL] ?: '',
            'connector_token' => $config[BrtConfig::CONNECTOR_TOKEN] ?: '',
            'messages' => $this->getMessages(),
        ];

        if ($currentTab === 'stats') {
            $filters = $this->getStatsFilters();
            $limit = (int) Tools::getValue('stats_limit', 50);
            $offset = (int) Tools::getValue('stats_offset', 0);
            $history = BrtStats::getHistory($filters, $idLang, $limit, $offset);

            $params['stats'] = BrtStats::getDashboardStats();
            $params['delivery_days'] = BrtStats::getDeliveryDays();
            $params['delivered_by_month'] = BrtStats::getDeliveredByMonth();
            $params['history'] = $history['rows'];
            $params['history_total'] = $history['total'];
            $params['history_filters'] = $filters;
            $params['history_limit'] = $limit;
            $params['history_offset'] = $offset;
            $params['history_current_page'] = $limit > 0 ? (int) floor($offset / $limit) + 1 : 1;
            $params['history_total_pages'] = $limit > 0 ? (int) ceil($history['total'] / $limit) : 1;
        }

        $twig = new GetTwigEnvironment($this->module->name);
        $twig->load('@ModuleTwig/admin/layout');

        return $twig->render($params);
    }

    protected function getStatsFilters(): array
    {
        $filters = [];
        $fields = [
            'id_order',
            'parcel_id',
            'stato_spedizione',
            'order_state',
            'data_consegna_from',
            'data_consegna_to',
            'event_code',
            'event_name',
            'event_date',
            'filiale',
            'nome_filiale',
        ];
        foreach ($fields as $field) {
            $value = trim(Tools::getValue('filter_' . $field, ''));
            if ($value !== '') {
                $filters[$field] = $value;
            }
        }

        return $filters;
    }

    protected function getMessages(): array
    {
        $msgs = [];
        if ($msg = $this->context->cookie->__get('mpbrt_success')) {
            $msgs['success'] = $msg;
            $this->context->cookie->__unset('mpbrt_success');
        }
        if ($msg = $this->context->cookie->__get('mpbrt_error')) {
            $msgs['error'] = $msg;
            $this->context->cookie->__unset('mpbrt_error');
        }

        return $msgs;
    }

    public function postProcess()
    {
        if (Tools::getValue('action') === 'printBordero') {
            $this->processPrintBordero();
        }

        if (Tools::isSubmit('submitSettings')) {
            $this->processSettings();
        }

        parent::postProcess();
    }

    public function processPrintBordero()
    {
        $batchId = (int) Tools::getValue('batch_id', 0);

        if ($batchId > 0) {
            $shipments = ModelBrtRestApiBordero::getByBatchId($batchId);
        } else {
            $shipments = ModelBrtRestApiBordero::getUnprinted(500);
        }

        if (empty($shipments) && $batchId === 0) {
            $lastBatch = (int) \Db::getInstance()->getValue(
                'SELECT MAX(`id_bordero_batch`) FROM `' . _DB_PREFIX_ . 'brt_restapi_bordero`'
            );
            if ($lastBatch > 0) {
                $shipments = ModelBrtRestApiBordero::getByBatchId($lastBatch);
                $batchId = $lastBatch;
            }
        }

        if (!empty($shipments) && $batchId === 0) {
            $batchId = time();
            $ids = array_column($shipments, 'id_brt_restapi_bordero');
            ModelBrtRestApiBordero::markAsPrinted($ids, $batchId);
        }

        \MpSoft\MpBrtRestApiShipments\Helpers\BrdPdfGenerator::renderPdf($shipments, $batchId);
        exit();
    }

    protected function processSettings()
    {
        $data = $_POST;

        if (BrtConfig::saveFromPost($data)) {
            $this->context->cookie->__set('mpbrt_success', $this->module->l('Impostazioni salvate con successo.'));
        } else {
            $this->context->cookie->__set('mpbrt_error', $this->module->l('Errore nel salvataggio delle impostazioni.'));
        }

        Tools::redirectAdmin($this->context->link->getAdminLink('AdminMpBrtRestApiShipments') . '&tab=settings');
    }

    // ─── AJAX handlers — all return array (ajaxRender encodes to JSON) ────────

    // ─── AJAX: Test BRT API connection ──────────────────────────────────────────

    public function ajaxProcessTestBrtConnection()
    {
        $type = Tools::getValue('type', 'shipment');

        try {
            if ($type === 'tracking') {
                $client = BrtTrackingClient::fromConfig();
                $result = $client->getTrackingByParcelId('TEST');
            } else {
                $client = BrtShipmentClient::fromConfig();
                $sandbox = (bool) Configuration::get(BrtConfig::SANDBOX_ENABLED);
                $customerCode = (int) Configuration::get($sandbox ? BrtConfig::SANDBOX_CUSTOMER_CODE : BrtConfig::ACCOUNT_CUSTOMER_CODE);
                $departureDepot = (int) Configuration::get($sandbox ? BrtConfig::SANDBOX_DEPARTURE_DEPOT : BrtConfig::ACCOUNT_DEPARTURE_DEPOT);

                $result = $client->getRouting([
                    'network' => Configuration::get(BrtConfig::NETWORK) ?: '',
                    'departureDepot' => $departureDepot,
                    'senderCustomerCode' => $customerCode,
                    'deliveryFreightTypeCode' => 'DAP',
                    'consigneeCompanyName' => 'Test BRT',
                    'consigneeAddress' => 'Via Test 1',
                    'consigneeZIPCode' => '40138',
                    'consigneeCity' => 'BOLOGNA',
                    'consigneeProvinceAbbreviation' => 'BO',
                    'consigneeCountryAbbreviationISOAlpha2' => 'IT',
                    'serviceType' => '',
                    'numberOfParcels' => 1,
                    'weightKG' => 1.0,
                    'volumeM3' => 0.0,
                ]);
            }

            die(json_encode(['success' => true, 'message' => $this->module->l('Connessione BRT OK'), 'data' => $result]));
        } catch (\Exception $e) {
            die(json_encode(['success' => false, 'error' => $e->getMessage()]));
        }
    }

    // ─── AJAX: Get Order shipping data for Modal ────────────────────────────────
    public function ajaxProcessGetOrderData()
    {
        $idOrder = (int) Tools::getValue('id_order', 0);
        if (!$idOrder) {
            die(json_encode(['success' => false, 'error' => $this->module->l('ID ordine non fornito')]));
        }

        $data = BrtShipmentRequest::extractDataFromOrder($idOrder);
        if (empty($data)) {
            die(json_encode(['success' => false, 'error' => $this->module->l('Ordine non trovato o non valido')]));
        }

        $parcels = ModelBrtRestApiWeight::getParcelsByOrderId($idOrder);
        if (empty($parcels) && !empty($data['numericSenderReference'])) {
            $parcels = ModelBrtRestApiWeight::getParcelsByReference((string) $data['numericSenderReference']);
        }
        if (!empty($parcels)) {
            $totals = ModelBrtRestApiWeight::calculateTotalsByOrderId($idOrder);
            $data['parcels'] = $parcels;
            $data['numberOfParcels'] = $totals['numberOfParcels'];
            $data['weightKG'] = $totals['weightKG'];
            $data['volumeM3'] = $totals['volumeM3'];
        }

        die(json_encode(['success' => true, 'data' => $data]));
    }

    // ─── AJAX: Get Parcels / Weight records ──────────────────────────────────────
    public function ajaxProcessGetParcels()
    {
        $idOrder = (int) Tools::getValue('id_order', 0);
        $referenceNumber = trim((string) Tools::getValue('reference_number', ''));

        if (!$idOrder && empty($referenceNumber)) {
            die(json_encode(['success' => false, 'error' => $this->module->l('Riferimento o ID ordine mancante')]));
        }

        $parcels = $idOrder
            ? ModelBrtRestApiWeight::getParcelsByOrderId($idOrder)
            : ModelBrtRestApiWeight::getParcelsByReference($referenceNumber);

        $totals = $idOrder
            ? ModelBrtRestApiWeight::calculateTotalsByOrderId($idOrder)
            : ModelBrtRestApiWeight::calculateTotalsByReference($referenceNumber);

        die(json_encode([
            'success' => true,
            'parcels' => $parcels,
            'totals' => $totals,
        ]));
    }

    // ─── AJAX: Save Parcels list ─────────────────────────────────────────────────
    public function ajaxProcessSaveParcels()
    {
        $idOrder = (int) Tools::getValue('id_order', 0);
        $referenceNumber = trim((string) Tools::getValue('reference_number', ''));
        $parcels = Tools::getValue('parcels', []);

        if (!is_array($parcels)) {
            $parcels = json_decode($parcels, true) ?: [];
        }

        if (empty($referenceNumber) && $idOrder) {
            $referenceNumber = (string) $idOrder;
        }

        if (empty($referenceNumber)) {
            die(json_encode(['success' => false, 'error' => $this->module->l('Reference number mancante')]));
        }

        $savedParcels = [];
        foreach ($parcels as $idx => $p) {
            $prog = (int) ($p['progressivo'] ?? ($idx + 1));
            $weight = (float) ($p['weight'] ?? 0);
            $x = (float) ($p['x'] ?? 0);
            $y = (float) ($p['y'] ?? 0);
            $z = (float) ($p['z'] ?? 0);
            $vol = (float) ($p['volume'] ?? 0);
            $isEnv = (int) ($p['is_envelope'] ?? 0);

            $res = ModelBrtRestApiWeight::saveParcel(
                $referenceNumber,
                $prog,
                $weight,
                $x,
                $y,
                $z,
                $vol,
                $idOrder,
                $isEnv
            );
            if ($res['success']) {
                $savedParcels[] = $res['data'];
            }
        }

        $totals = $idOrder
            ? ModelBrtRestApiWeight::calculateTotalsByOrderId($idOrder)
            : ModelBrtRestApiWeight::calculateTotalsByReference($referenceNumber);

        die(json_encode([
            'success' => true,
            'parcels' => $savedParcels,
            'totals' => $totals,
        ]));
    }

    // ─── AJAX: Delete Parcel record ──────────────────────────────────────────────
    public function ajaxProcessDeleteParcel()
    {
        $idWeight = (int) Tools::getValue('id_weight', 0);
        if (!$idWeight) {
            die(json_encode(['success' => false, 'error' => $this->module->l('ID pacco mancante')]));
        }

        $deleted = ModelBrtRestApiWeight::deleteParcel($idWeight);
        die(json_encode(['success' => $deleted]));
    }

    // ─── AJAX: Create shipment ───────────────────────────────────────────────────

    public function ajaxProcessCreateShipment()
    {
        $idOrder = (int) Tools::getValue('id_order', 0);
        $createData = Tools::getValue('create_data', []);

        if (!is_array($createData)) {
            $createData = json_decode($createData, true) ?: [];
        }

        $numericRef = (int) ($createData['numericSenderReference'] ?? 0);
        if (!$numericRef) {
            $numericRef = $idOrder ?: (int) time();
            $createData['numericSenderReference'] = $numericRef;
        }
        $refNum = (string) $numericRef;

        // Save parcel list if provided in createData
        if (!empty($createData['parcels']) && is_array($createData['parcels'])) {
            foreach ($createData['parcels'] as $idx => $p) {
                $prog = (int) ($p['progressivo'] ?? ($idx + 1));
                $weight = (float) ($p['weight'] ?? 0);
                $x = (float) ($p['x'] ?? 0);
                $y = (float) ($p['y'] ?? 0);
                $z = (float) ($p['z'] ?? 0);
                $vol = (float) ($p['volume'] ?? 0);
                $isEnv = (int) ($p['is_envelope'] ?? 0);

                ModelBrtRestApiWeight::saveParcel(
                    $refNum,
                    $prog,
                    $weight,
                    $x,
                    $y,
                    $z,
                    $vol,
                    $idOrder,
                    $isEnv
                );
            }
        }

        try {
            $request = new BrtShipmentRequest($idOrder, $createData);
            $result = $request->send();

            die(json_encode([
                'success' => $result['success'],
                'data' => $result['data'],
                'error' => $result['error'],
            ]));
        } catch (\Exception $e) {
            die(json_encode(['success' => false, 'error' => $e->getMessage()]));
        }
    }


    // ─── AJAX: Confirm shipment ──────────────────────────────────────────────────

    public function ajaxProcessConfirmShipment()
    {
        $senderCustomerCode = (int) Tools::getValue('sender_customer_code', 0);
        $numericSenderReference = (int) Tools::getValue('numeric_sender_reference', 0);
        $alphanumericSenderReference = (string) Tools::getValue('alphanumeric_sender_reference', '');

        try {
            $client = BrtShipmentClient::fromConfig();
            $result = $client->confirmShipment($senderCustomerCode, $numericSenderReference, $alphanumericSenderReference);
            die(json_encode($result));
        } catch (\Exception $e) {
            die(json_encode(['success' => false, 'error' => $e->getMessage()]));
        }
    }

    // ─── AJAX: Delete shipment ───────────────────────────────────────────────────

    public function ajaxProcessDeleteShipment()
    {
        $senderCustomerCode = (int) Tools::getValue('sender_customer_code', 0);
        $numericSenderReference = (int) Tools::getValue('numeric_sender_reference', 0);
        $alphanumericSenderReference = (string) Tools::getValue('alphanumeric_sender_reference', '');

        try {
            $client = BrtShipmentClient::fromConfig();
            $result = $client->deleteShipment($senderCustomerCode, $numericSenderReference, $alphanumericSenderReference);
            die(json_encode($result));
        } catch (\Exception $e) {
            die(json_encode(['success' => false, 'error' => $e->getMessage()]));
        }
    }

    // ─── AJAX: Print Borderò PDF ──────────────────────────────────────────────────

    public function ajaxProcessPrintBordero()
    {
        $this->processPrintBordero();
    }

    // ─── AJAX: Routing ───────────────────────────────────────────────────────────

    public function ajaxProcessGetRouting()
    {
        $routingData = Tools::getValue('routing_data', []);
        if (!is_array($routingData)) {
            $routingData = json_decode($routingData, true) ?: [];
        }

        try {
            $client = BrtShipmentClient::fromConfig();
            $result = $client->getRouting($routingData);
            die(json_encode($result));
        } catch (\Exception $e) {
            die(json_encode(['success' => false, 'error' => $e->getMessage()]));
        }
    }

    // ─── AJAX: Tracking ──────────────────────────────────────────────────────────

    public function ajaxProcessGetTracking()
    {
        $parcelId = (string) Tools::getValue('parcel_id', '');
        $idOrder = (int) Tools::getValue('id_order', 0);
        $numericSenderReference = (int) Tools::getValue('numeric_sender_reference', 0);
        $alphanumericSenderReference = (string) Tools::getValue('alphanumeric_sender_reference', '');

        if (!$parcelId) {
            die(json_encode(['success' => false, 'error' => $this->module->l('Parcel ID mancante')]));
        }

        try {
            $request = new BrtTrackingRequest($idOrder);
            $result = $request->track($parcelId, $numericSenderReference, $alphanumericSenderReference);

            die(json_encode([
                'success' => $result['success'],
                'data' => $result['data'],
                'parsed' => $result['parsed'],
                'error' => $result['error'],
            ]));
        } catch (\Exception $e) {
            die(json_encode(['success' => false, 'error' => $e->getMessage()]));
        }
    }

    // ─── AJAX: BRT API Test ──────────────────────────────────────────────────

    public function ajaxProcessTestBrtApi()
    {
        try {
            $client = BrtShipmentClient::fromConfig();
            $sandbox = $client->isSandbox();
            $account = $client->getAccount();

            if (empty($account['userID']) || empty($account['password'])) {
                die(json_encode([
                    'success' => false,
                    'error' => $this->module->l('Credenziali BRT non configurate (userID o password vuoti).'),
                ]));
            }

            // Test API connection using getRouting (read-only endpoint)
            $routingData = [
                'departureDepot' => (int) ($sandbox
                    ? \Configuration::get('MPBRTRESTAPI_SANDBOX_DEPARTURE_DEPOT')
                    : \Configuration::get('MPBRTRESTAPI_ACCOUNT_DEPARTURE_DEPOT')),
                'senderCustomerCode' => (int) ($sandbox
                    ? \Configuration::get('MPBRTRESTAPI_SANDBOX_CUSTOMER_CODE')
                    : \Configuration::get('MPBRTRESTAPI_ACCOUNT_CUSTOMER_CODE')),
                'consigneeZIPCode' => '40138',
                'consigneeCountryAbbreviationISOAlpha2' => 'IT',
            ];

            $result = $client->getRouting($routingData);

            if ($result['success']) {
                die(json_encode([
                    'success' => true,
                    'message' => 'Connessione alle API BRT riuscita con successo! (' . ($sandbox ? 'Ambiente SANDBOX' : 'Ambiente PRODUZIONE') . ')',
                    'data' => $result['data'],
                ]));
            } else {
                die(json_encode([
                    'success' => false,
                    'error' => 'Connessione fallita: ' . ($result['error'] ?: 'Risposta non valida da BRT REST API'),
                    'httpCode' => $result['httpCode'] ?? 0,
                    'data' => $result['data'] ?? [],
                ]));
            }
        } catch (\Exception $e) {
            die(json_encode([
                'success' => false,
                'error' => 'Eccezione durante il test: ' . $e->getMessage(),
            ]));
        }
    }

    // ─── AJAX: Connector test ────────────────────────────────────────────────────

    public function ajaxProcessTestConnector()
    {
        $connector = MpConnector::fromConfig();
        $result = $connector->test();
        die(json_encode($result));
    }

    // ─── AJAX: Import from old database ─────────────────────────────────────────

    public function ajaxProcessImportFromV16Init()
    {
        $connector = MpConnector::fromConfig();
        $result = $connector->query('SELECT * FROM `ps_brtlabels_request` ORDER BY `id_brtlabels_request`');

        if (!$result['success']) {
            die(json_encode(['success' => false, 'error' => $result['error']]));
        }

        $data = $result['data'];
        $total = count($data);
        $hash = md5(session_id());
        file_put_contents('/tmp/mpbrtapi_import_' . $hash . '.json', json_encode($data));

        die(json_encode(['success' => true, 'total' => $total, 'hash' => $hash]));
    }

    public function ajaxProcessImportFromV16Chunk()
    {
        $hash = (string) Tools::getValue('hash', '');
        $file = '/tmp/mpbrtapi_import_' . preg_replace('/[^a-f0-9]/', '', $hash) . '.json';

        if (!file_exists($file)) {
            die(json_encode(['success' => false, 'error' => 'File temporaneo non trovato']));
        }

        $allData = json_decode(file_get_contents($file), true) ?: [];
        $total = count($allData);
        $chunk = array_splice($allData, 0, 100);
        $done = $total - count($allData);

        foreach ($chunk as $row) {
            $model = new ModelBrtRestApiShipmentRequest();
            $model->id_order = (int) ($row['id_order'] ?? 0);
            $model->numeric_sender_reference = (int) ($row['numericSenderReference'] ?? 0);
            $model->alphanumeric_sender_reference = (string) ($row['alphanumericSenderReference'] ?? '');
            $model->sandbox = 0;
            $model->setRequestData($row);
            $model->date_add = $row['date_add'] ?? date('Y-m-d H:i:s');
            $model->date_upd = $row['date_upd'] ?? date('Y-m-d H:i:s');

            try {
                $model->save();
            } catch (\Exception $e) {
            }
        }

        file_put_contents($file, json_encode($allData));

        $finished = empty($allData);
        if ($finished) {
            @unlink($file);
        }

        die(json_encode([
            'success' => true,
            'done' => $done,
            'total' => $total,
            'percent' => $total > 0 ? round($done / $total * 100) : 100,
            'finished' => $finished,
        ]));
    }

    // ─── AJAX: Get label PDF ─────────────────────────────────────────────────────

    public function ajaxProcessGetLabel()
    {
        $numericRef = (int) Tools::getValue('numeric_sender_reference', 0);
        $idOrder = (int) Tools::getValue('id_order', 0);

        if (!$numericRef && !$idOrder) {
            die(json_encode(['success' => false, 'error' => $this->module->l('Riferimento numerico o ID ordine mancante.')]));
        }

        $responseModel = false;
        if ($numericRef) {
            $responseModel = ModelBrtRestApiShipmentResponse::getByNumericSenderReference($numericRef);
        }
        if (!$responseModel && $idOrder) {
            $responseModel = ModelBrtRestApiShipmentResponse::getByIdOrder($idOrder);
        }

        if (!$responseModel) {
            die(json_encode(['success' => false, 'error' => $this->module->l('Nessuna risposta spedizione trovata nel sistema.')]));
        }

        $labels = $responseModel->getLabelsArray();
        $labelList = $labels['label'] ?? [];

        if (empty($labelList)) {
            die(json_encode(['success' => false, 'error' => $this->module->l('Elenco etichette PDF vuoto o non disponibile nella risposta BRT.')]));
        }

        $streams = [];
        foreach ($labelList as $label) {
            if (!empty($label['stream'])) {
                $streams[] = $label['stream'];
            }
        }

        if (empty($streams)) {
            die(json_encode(['success' => false, 'error' => $this->module->l('Impossibile trovare lo stream PDF nell\'etichetta segnacollo.')]));
        }

        try {
            $mergedPdf = \MpSoft\MpBrtRestApiShipments\Helpers\BrtPdfMerger::mergePdfs($streams);
            die(json_encode([
                'success' => true,
                'pdf_base64' => base64_encode($mergedPdf),
                'count' => count($streams),
            ]));
        } catch (\Exception $e) {
            die(json_encode(['success' => false, 'error' => 'Errore durante l\'unione delle etichette PDF: ' . $e->getMessage()]));
        }
    }
}
