<?php

/**
 * @author    Massimiliano Palermo <maxx.palermo@gmail.com>
 * @copyright Since 2016 Massimiliano Palermo
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 *
 * BRT REST API Shipment client.
 *
 * Base URL (produzione): https://api.brt.it/rest/v1/shipments
 * Base URL (sandbox):    https://api.brt.it/rest/v1/shipments  (stessa URL, credenziali sandbox)
 *
 * Endpoints:
 *   POST /shipment  → createShipment()
 *   PUT  /shipment  → confirmShipment()
 *   PUT  /delete    → deleteShipment()
 *   PUT  /routing   → getRouting()
 */

namespace MpSoft\MpBrtRestApiShipments\Api\Shipment;

class BrtShipmentClient
{
    const BASE_URL_PRODUCTION = 'https://api.brt.it/rest/v1/shipments';
    const BASE_URL_SANDBOX    = 'https://api.brt.it/rest/v1/shipments';

    protected string $userId;
    protected string $password;
    protected bool $sandbox;
    protected int $timeout;
    protected string $baseUrl;

    public function __construct(string $userId, string $password, bool $sandbox = false, int $timeout = 30)
    {
        $this->userId   = $userId;
        $this->password = $password;
        $this->sandbox  = $sandbox;
        $this->timeout  = $timeout;
        $this->baseUrl  = $sandbox ? self::BASE_URL_SANDBOX : self::BASE_URL_PRODUCTION;
    }

    /**
     * Build from PrestaShop configuration.
     */
    public static function fromConfig(): self
    {
        $sandbox = (bool) \Configuration::get('MPBRTRESTAPI_SANDBOX_ENABLED');

        if ($sandbox) {
            $userId   = (string) \Configuration::get('MPBRTRESTAPI_SANDBOX_USER_ID');
            $password = (string) \Configuration::get('MPBRTRESTAPI_SANDBOX_PASSWORD');
        } else {
            $userId   = (string) \Configuration::get('MPBRTRESTAPI_ACCOUNT_USER_ID');
            $password = (string) \Configuration::get('MPBRTRESTAPI_ACCOUNT_PASSWORD');
        }

        return new self($userId, $password, $sandbox);
    }

    /**
     * Returns the account array for API requests.
     */
    public function getAccount(): array
    {
        return [
            'userID'   => $this->userId,
            'password' => $this->password,
        ];
    }

    public function isSandbox(): bool
    {
        return $this->sandbox;
    }

    /**
     * POST /shipment — Create a new shipment and get label.
     *
     * @param array $createData   Fields from createData schema
     * @param bool  $isLabelRequired
     * @param array $labelParameters
     * @return array ['success' => bool, 'data' => array, 'error' => string, 'httpCode' => int]
     */
    public function createShipment(array $createData, bool $isLabelRequired = true, array $labelParameters = []): array
    {
        $payload = [
            'account'         => $this->getAccount(),
            'createData'      => $createData,
            'isLabelRequired' => $isLabelRequired ? '1' : '0',
        ];

        if ($labelParameters) {
            $payload['labelParameters'] = $labelParameters;
        }

        return $this->doRequest('POST', '/shipment', $payload);
    }

    /**
     * PUT /shipment — Confirm an existing shipment.
     *
     * @param int    $senderCustomerCode
     * @param int    $numericSenderReference
     * @param string $alphanumericSenderReference
     * @return array
     */
    public function confirmShipment(int $senderCustomerCode, int $numericSenderReference, string $alphanumericSenderReference = ''): array
    {
        $payload = [
            'account'     => $this->getAccount(),
            'confirmData' => [
                'senderCustomerCode'          => $senderCustomerCode,
                'numericSenderReference'      => $numericSenderReference,
                'alphanumericSenderReference' => $alphanumericSenderReference,
            ],
        ];

        return $this->doRequest('PUT', '/shipment', $payload);
    }

    /**
     * PUT /delete — Delete an existing shipment.
     *
     * @param int    $senderCustomerCode
     * @param int    $numericSenderReference
     * @param string $alphanumericSenderReference
     * @return array
     */
    public function deleteShipment(int $senderCustomerCode, int $numericSenderReference, string $alphanumericSenderReference = ''): array
    {
        $payload = [
            'account'    => $this->getAccount(),
            'deleteData' => [
                'senderCustomerCode'          => $senderCustomerCode,
                'numericSenderReference'      => $numericSenderReference,
                'alphanumericSenderReference' => $alphanumericSenderReference,
            ],
        ];

        return $this->doRequest('PUT', '/delete', $payload);
    }

    /**
     * PUT /routing — Calculate routing for a shipment.
     *
     * @param array $routingData  Fields from routingData schema
     * @return array
     */
    public function getRouting(array $routingData): array
    {
        $payload = [
            'account'     => $this->getAccount(),
            'routingData' => $routingData,
        ];

        return $this->doRequest('PUT', '/routing', $payload);
    }

    /**
     * Execute an HTTP request to the BRT API.
     *
     * @param string $method  GET|POST|PUT
     * @param string $path    URL path (e.g. /shipment)
     * @param array  $payload Body payload (JSON-encoded)
     * @return array ['success' => bool, 'data' => array, 'error' => string, 'httpCode' => int]
     */
    protected function doRequest(string $method, string $path, array $payload = []): array
    {
        $url  = rtrim($this->baseUrl, '/') . $path;
        $body = json_encode($payload);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);

        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                break;
            case 'GET':
            default:
                break;
        }

        $response  = curl_exec($ch);
        $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return [
                'success'  => false,
                'data'     => [],
                'error'    => 'cURL error: ' . $curlError,
                'httpCode' => 0,
            ];
        }

        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            return [
                'success'  => false,
                'data'     => [],
                'error'    => 'Risposta non valida (HTTP ' . $httpCode . '): ' . substr((string) $response, 0, 300),
                'httpCode' => $httpCode,
            ];
        }

        $execMsg = $this->extractExecutionMessage($decoded);
        $code = (int) ($execMsg['code'] ?? 0);
        $severity = strtoupper((string) ($execMsg['severity'] ?? ''));
        $codeDesc = trim((string) ($execMsg['codeDesc'] ?? ''));
        $msg = trim((string) ($execMsg['message'] ?? ''));

        $msgText = '';
        if ($code === -67) {
            $msgText = 'Spedizione non ancora annullabile. L\'invocazione dell\'annullamento deve essere eseguita dopo almeno 5 minuti dalla creazione della spedizione.';
        } elseif ($codeDesc !== '' && $msg !== '' && strcasecmp($codeDesc, $msg) !== 0) {
            $msgText = $codeDesc . ' - ' . $msg;
        } elseif ($codeDesc !== '') {
            $msgText = $codeDesc;
        } elseif ($msg !== '') {
            $msgText = $msg;
        } else {
            $msgText = 'Errore sconosciuto BRT';
        }

        if ($severity === 'ERROR' || $code < 0) {
            return [
                'success'  => false,
                'data'     => $decoded,
                'error'    => 'BRT error [' . $code . '] (' . ($severity ?: 'ERROR') . '): ' . $msgText,
                'httpCode' => $httpCode,
            ];
        }

        $warning = '';
        if ($severity === 'WARNING' || $code > 0) {
            $warning = $msgText;
        }

        return [
            'success'  => true,
            'data'     => $decoded,
            'error'    => '',
            'warning'  => $warning,
            'httpCode' => $httpCode,
        ];
    }

    /**
     * Extract executionMessage from nested BRT response structures.
     */
    protected function extractExecutionMessage(array $decoded): array
    {
        $responseKeys = ['createResponse', 'confirmResponse', 'deleteResponse', 'routingResponse'];

        // 1. Direct top-level response keys
        foreach ($responseKeys as $rk) {
            if (isset($decoded[$rk]['executionMessage']) && is_array($decoded[$rk]['executionMessage'])) {
                return $decoded[$rk]['executionMessage'];
            }
        }

        // 2. Nested result containers
        $containers = ['createResult', 'confirmResult', 'deleteResult', 'routingResult'];
        foreach ($containers as $container) {
            if (isset($decoded[$container]) && is_array($decoded[$container])) {
                $inner = $decoded[$container];
                foreach ($responseKeys as $rk) {
                    if (isset($inner[$rk]['executionMessage']) && is_array($inner[$rk]['executionMessage'])) {
                        return $inner[$rk]['executionMessage'];
                    }
                }
            }
        }

        return [];
    }

    /**
     * Decode a base64-encoded label stream.
     *
     * @param string $stream Base64 encoded label
     * @return string|false Binary PDF/ZPL data
     */
    public static function decodeLabel(string $stream)
    {
        return base64_decode($stream);
    }
}
