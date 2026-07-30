<?php

/**
 * @author    Massimiliano Palermo <maxx.palermo@gmail.com>
 * @copyright Since 2016 Massimiliano Palermo
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 *
 * Builds and sends a Create Shipment request to BRT, persisting request and response to DB.
 *
 * network values:       '' = Standard, 'D' = DPD, 'E' = EuroExpress, 'S' = FED
 * deliveryFreightTypeCode: 'DAP' = Franco, 'EXW' = Assegnato
 * serviceType:          '' = Standard, 'E' = Express, 'H' = 10:30
 * codPaymentType:       '' = Contanti, 'BM' = Ass. Bancario Mitt., 'CM' = Ass. Circolare Mitt.,
 *                       'BB' = Ass. Bancario Corriere Manleva, 'OM' = Ass. Mitt. Originale,
 *                       'OC' = Ass. Circolare Mitt. Originale
 */

namespace MpSoft\MpBrtRestApiShipments\Api\Shipment;

class BrtShipmentRequest
{
    protected int $idOrder;
    protected array $createData;
    protected array $labelParameters;
    protected bool $isLabelRequired;
    protected BrtShipmentClient $client;

    public function __construct(int $idOrder, array $createData, array $labelParameters = [], bool $isLabelRequired = true)
    {
        $this->idOrder = $idOrder;
        $this->createData = $createData;
        $this->labelParameters = $labelParameters ?: $this->getDefaultLabelParameters();
        $this->isLabelRequired = $isLabelRequired;
        $this->client = BrtShipmentClient::fromConfig();
    }

    /**
     * Build default label parameters from PS configuration.
     */
    protected function getDefaultLabelParameters(): array
    {
        return [
            'outputType' => \Configuration::get('MPBRTRESTAPI_LABEL_OUTPUT_TYPE') ?: 'PDF',
            'offsetX' => (string) (\Configuration::get('MPBRTRESTAPI_LABEL_OFFSET_X') ?: '0'),
            'offsetY' => (string) (\Configuration::get('MPBRTRESTAPI_LABEL_OFFSET_Y') ?: '0'),
            'isBorderRequired' => \Configuration::get('MPBRTRESTAPI_LABEL_BORDER') ? '1' : '0',
            'isLogoRequired' => \Configuration::get('MPBRTRESTAPI_LABEL_LOGO') ? '1' : '0',
            'isBarcodeControlRowRequired' => \Configuration::get('MPBRTRESTAPI_LABEL_BARCODE') ? '1' : '0',
        ];
    }

    /**
     * Build the full createData array enriched with config values.
     */
    protected function buildCreateData(): array
    {
        $sandbox = $this->client->isSandbox();

        $defaults = [
            'network' => \Configuration::get('MPBRTRESTAPI_NETWORK') ?: '',
            'departureDepot' => (int) ($sandbox
                ? \Configuration::get('MPBRTRESTAPI_SANDBOX_DEPARTURE_DEPOT')
                : \Configuration::get('MPBRTRESTAPI_ACCOUNT_DEPARTURE_DEPOT')),
            'senderCustomerCode' => (int) ($sandbox
                ? \Configuration::get('MPBRTRESTAPI_SANDBOX_CUSTOMER_CODE')
                : \Configuration::get('MPBRTRESTAPI_ACCOUNT_CUSTOMER_CODE')),
            'deliveryFreightTypeCode' => \Configuration::get('MPBRTRESTAPI_FREIGHT_TYPE') ?: 'DAP',
            'serviceType' => \Configuration::get('MPBRTRESTAPI_SERVICE_TYPE') ?: '',
            'isAlertRequired' => '1',
            'senderParcelType' => mb_substr((string) (\Configuration::get('MPBRTRESTAPI_SENDER_PARCEL_TYPE') ?: 'ABBIGLIAMENTO'), 0, 15),
        ];

        $merged = array_merge($defaults, $this->createData);

        // Always enforce numericSenderReference and alphanumericSenderReference as non-empty values
        $numRef = (string) ($merged['numericSenderReference'] ?? '');
        if (trim($numRef) === '' || trim($numRef) === '0') {
            $numRef = (string) $this->idOrder;
        }
        $merged['numericSenderReference'] = $numRef;

        $alphaRef = (string) ($merged['alphanumericSenderReference'] ?? '');
        if (trim($alphaRef) === '' && $this->idOrder) {
            $order = new \Order($this->idOrder);
            if (\Validate::isLoadedObject($order)) {
                $alphaRef = (string) $order->reference;
            }
        }
        $merged['alphanumericSenderReference'] = $alphaRef;

        // Always enforce isAlertRequired = 1 and senderParcelType from config
        $merged['isAlertRequired'] = '1';
        $merged['senderParcelType'] = mb_substr((string) (\Configuration::get('MPBRTRESTAPI_SENDER_PARCEL_TYPE') ?: 'ABBIGLIAMENTO'), 0, 15);

        // Dynamic Pricing Condition Code evaluation via BrtPricingRuleParser
        if (!isset($merged['pricingConditionCode']) || $merged['pricingConditionCode'] === '') {
            $merged['pricingConditionCode'] = \MpSoft\MpBrtRestApiShipments\Helpers\BrtPricingRuleParser::evaluate($merged);
        }

        // Automatic COD parameters handling: isCODMandatory MUST always be present ('1' if COD > 0, else '0')
        $codAmount = isset($merged['cashOnDelivery']) ? (float) $merged['cashOnDelivery'] : 0.0;
        if ($codAmount > 0) {
            $merged['cashOnDelivery'] = number_format($codAmount, 2, '.', '');
            $merged['isCODMandatory'] = '1';
            $paymentType = isset($merged['codPaymentType']) ? (string) $merged['codPaymentType'] : '';
            $merged['codPaymentType'] = ($paymentType === 'CA') ? '' : $paymentType;
            $merged['codCurrency'] = 'EUR';
        } else {
            $merged['isCODMandatory'] = '0';
            unset($merged['cashOnDelivery']);
            unset($merged['codPaymentType']);
            unset($merged['codCurrency']);
        }

        // Remove internal helper fields not recognized by BRT REST API
        unset($merged['parcels']);

        return $merged;
    }

    /**
     * Get preview payload structure built with exact backend logic (password masked).
     *
     * @return array
     */
    public function getPreviewPayload(): array
    {
        $createData = $this->buildCreateData();
        $account = $this->client->getAccount();
        $account['password'] = '****************';

        return [
            'account' => $account,
            'createData' => $createData,
            'isLabelRequired' => $this->isLabelRequired ? '1' : '0',
            'labelParameters' => $this->labelParameters,
        ];
    }

    /**
     * Send the request to BRT, persist to DB, and return the full result.
     *
     * @return array ['success', 'data', 'error', 'requestModel', 'responseModel']
     */
    public function send(): array
    {
        $createData = $this->buildCreateData();
        $labelParameters = $this->labelParameters;
        $sandbox = $this->client->isSandbox();

        $numericRef = (int) ($createData['numericSenderReference'] ?? 0);
        $alphanumericRef = (string) ($createData['alphanumericSenderReference'] ?? '');

        $fullPayload = [
            'account' => $this->client->getAccount(),
            'createData' => $createData,
            'isLabelRequired' => $this->isLabelRequired ? '1' : '0',
            'labelParameters' => $labelParameters,
        ];

        $requestModel = \ModelBrtRestApiShipmentRequest::getByNumericSenderReference($numericRef);
        if (!$requestModel) {
            $requestModel = new \ModelBrtRestApiShipmentRequest();
        }
        $requestModel->id_order = $this->idOrder;
        $requestModel->numeric_sender_reference = $numericRef;
        $requestModel->alphanumeric_sender_reference = $alphanumericRef;
        $requestModel->sandbox = $sandbox ? 1 : 0;
        $requestModel->setRequestData($fullPayload);
        $requestModel->date_add = date('Y-m-d H:i:s');
        $requestModel->date_upd = date('Y-m-d H:i:s');

        try {
            $requestModel->save();
        } catch (\Exception $e) {
            return [
                'success' => false,
                'data' => [],
                'error' => 'Errore salvataggio richiesta: ' . $e->getMessage(),
                'requestModel' => null,
                'responseModel' => null,
            ];
        }

        $result = $this->client->createShipment($createData, $this->isLabelRequired, $labelParameters);

        $responseModel = \ModelBrtRestApiShipmentResponse::getByNumericSenderReference($numericRef);
        if (!$responseModel) {
            $responseModel = new \ModelBrtRestApiShipmentResponse();
        }
        $responseModel->id_order = $this->idOrder;
        $responseModel->numeric_sender_reference = $numericRef;
        $responseModel->alphanumeric_sender_reference = $alphanumericRef;
        $responseModel->sandbox = $sandbox ? 1 : 0;
        $responseModel->setResponseData($result['data']);
        $responseModel->date_add = date('Y-m-d H:i:s');
        $responseModel->date_upd = date('Y-m-d H:i:s');

        if ($result['success']) {
            $createResponse = $result['data']['createResult']['createResponse'] ?? $result['data']['createResponse'] ?? [];
            $execMsg = $createResponse['executionMessage'] ?? [];
            $labels = $createResponse['labels'] ?? [];

            $responseModel->execution_code = (int) ($execMsg['code'] ?? 0);
            $responseModel->execution_severity = (string) ($execMsg['severity'] ?? '');
            $responseModel->execution_message = mb_substr((string) ($execMsg['message'] ?? ''), 0, 500);
            $responseModel->parcel_number_from = (string) ($createResponse['parcelNumberFrom'] ?? '');
            $responseModel->parcel_number_to = (string) ($createResponse['parcelNumberTo'] ?? '');
            $responseModel->arrival_depot = (string) ($createResponse['arrivalDepot'] ?? '');
            $responseModel->arrival_terminal = (string) ($createResponse['arrivalTerminal'] ?? '');
            $responseModel->delivery_zone = (string) ($createResponse['deliveryZone'] ?? '');
            $responseModel->setLabelsData($labels);

            // Register in Borderò table
            \ModelBrtRestApiBordero::registerShipment([
                'id_order' => $this->idOrder,
                'numeric_sender_reference' => $numericRef,
                'alphanumeric_sender_reference' => $alphanumericRef,
                'parcel_number_from' => $responseModel->parcel_number_from,
                'parcel_number_to' => $responseModel->parcel_number_to,
                'number_of_parcels' => (int) ($createData['numberOfParcels'] ?? 1),
                'weight_kg' => (float) ($createData['weightKG'] ?? 0),
                'cash_on_delivery' => (float) ($createData['cashOnDelivery'] ?? 0),
                'consignee_company_name' => (string) ($createData['consigneeCompanyName'] ?? ''),
                'consignee_city' => (string) ($createData['consigneeCity'] ?? ''),
            ]);
        } else {
            $responseModel->execution_code = -1;
            $responseModel->execution_message = mb_substr((string) ($result['error'] ?? ''), 0, 500);
            $responseModel->setLabelsData([]);
        }

        try {
            $responseModel->save();
        } catch (\Exception $e) {
            $result['error'] .= ' | Errore salvataggio risposta: ' . $e->getMessage();
        }

        $result['requestModel'] = $requestModel;
        $result['responseModel'] = $responseModel;

        return $result;
    }

    /**
     * Extract pre-filled shipment data from a PrestaShop order.
     */
    public static function extractDataFromOrder(int $idOrder): array
    {
        $order = new \Order($idOrder);
        if (!\Validate::isLoadedObject($order)) {
            return [];
        }

        $address = new \Address($order->id_address_delivery);
        $customer = new \Customer($order->id_customer);
        $country = new \Country($address->id_country);
        $state = $address->id_state ? new \State($address->id_state) : null;

        $company = trim($address->company);
        if (!$company) {
            $company = trim($address->firstname . ' ' . $address->lastname);
        }

        $weight = (float) $order->getTotalWeight();
        if ($weight <= 0) {
            $weight = 1.0;
        }

        $codAmount = 0.0;
        $moduleName = strtolower($order->module);
        $codModules = json_decode((string) \Configuration::get('MPBRTRESTAPI_COD_PAYMENT_MODULES'), true) ?: ['ps_cashondelivery', 'mpcodfee'];
        if (in_array($moduleName, $codModules) || strpos($moduleName, 'cashondelivery') !== false || strpos($moduleName, 'cod') !== false) {
            $codAmount = (float) $order->total_paid_tax_incl;
        }

        $data = [
            'id_order' => $idOrder,
            'numericSenderReference' => $idOrder,
            'alphanumericSenderReference' => (string) $order->reference,
            'consigneeCompanyName' => mb_substr($company, 0, 70),
            'consigneeAddress' => mb_substr(trim($address->address1 . ' ' . $address->address2), 0, 35),
            'consigneeZIPCode' => mb_substr(trim($address->postcode), 0, 9),
            'consigneeCity' => mb_substr(trim($address->city), 0, 35),
            'consigneeProvinceAbbreviation' => $state ? mb_substr($state->iso_code, 0, 2) : '',
            'consigneeCountryAbbreviationISOAlpha2' => $country->iso_code ?: 'IT',
            'consigneeContactName' => mb_substr(trim($address->firstname . ' ' . $address->lastname), 0, 35),
            'consigneeTelephone' => mb_substr(trim($address->phone ?: $address->phone_mobile), 0, 35),
            'consigneeMobilePhoneNumber' => mb_substr(trim($address->phone_mobile ?: $address->phone), 0, 16),
            'consigneeEMail' => mb_substr(trim($customer->email), 0, 70),
            'consigneeVATNumber' => mb_substr(trim($address->vat_number), 0, 14),
            'consigneeItalianFiscalCode' => mb_substr(trim($address->dni), 0, 35),
            'numberOfParcels' => 1,
            'weightKG' => round($weight, 2),
            'volumeM3' => 0.001,
            'cashOnDelivery' => round($codAmount, 2),
            'isCODMandatory' => $codAmount > 0 ? '1' : '0',
            'codPaymentType' => '',
            'codCurrency' => '',
            'network' => \Configuration::get('MPBRTRESTAPI_NETWORK') ?: '',
        ];

        $data['pricingConditionCode'] = \MpSoft\MpBrtRestApiShipments\Helpers\BrtPricingRuleParser::evaluate($data);

        return $data;
    }
}
