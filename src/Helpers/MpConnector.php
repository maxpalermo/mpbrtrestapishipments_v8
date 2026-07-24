<?php

/**
 * @author    Massimiliano Palermo <maxx.palermo@gmail.com>
 * @copyright Since 2016 Massimiliano Palermo
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 *
 * Client per il modulo mpconnector — importa dati dal vecchio database tramite query remote.
 *
 * Protocollo:
 *   POST {url}
 *   Headers: Content-Type: application/json, Accept: application/json,
 *            Authorization: Bearer {token}, X-Auth-Token: {token}
 *   Body JSON: { "action": "setQuery", "query": "{SQL}", "token": "{token}" }
 *
 * Risposta attesa: { "success": true/false, "data": [...], "error": "..." }
 */

namespace MpSoft\MpBrtRestApiShipments\Helpers;

class MpConnector
{
    protected string $url;
    protected string $token;
    protected int $timeout;

    const CONFIG_URL   = 'MPBRTRESTAPI_CONNECTOR_URL';
    const CONFIG_TOKEN = 'MPBRTRESTAPI_CONNECTOR_TOKEN';

    public function __construct(string $url, string $token, int $timeout = 10)
    {
        $this->url     = rtrim($url, '/');
        $this->token   = $token;
        $this->timeout = $timeout;
    }

    public static function fromConfig(): self
    {
        $url   = (string) \Configuration::get(self::CONFIG_URL);
        $token = (string) \Configuration::get(self::CONFIG_TOKEN);

        return new self($url, $token);
    }

    public static function saveConfig(string $url, string $token): bool
    {
        return \Configuration::updateValue(self::CONFIG_URL, $url)
            && \Configuration::updateValue(self::CONFIG_TOKEN, $token);
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * Execute a remote SQL query.
     *
     * @param string $sql
     * @return array ['success' => bool, 'data' => array, 'error' => string]
     */
    public function query(string $sql): array
    {
        if (!$this->url || !$this->token) {
            return ['success' => false, 'data' => [], 'error' => 'URL o token non configurati.'];
        }

        $payload = json_encode([
            'action' => 'setQuery',
            'query'  => $sql,
            'token'  => $this->token,
        ]);

        $ch = curl_init($this->url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $this->token,
                'X-Auth-Token: ' . $this->token,
            ],
        ]);

        $response  = curl_exec($ch);
        $httpCode  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            return ['success' => false, 'data' => [], 'error' => 'cURL error: ' . $curlError];
        }

        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            return [
                'success' => false,
                'data'    => [],
                'error'   => 'Risposta non valida (HTTP ' . $httpCode . '): ' . substr((string) $response, 0, 300),
            ];
        }

        if (isset($decoded['success']) && !$decoded['success']) {
            return [
                'success' => false,
                'data'    => [],
                'error'   => 'HTTP ' . $httpCode . ' — ' . ($decoded['error'] ?? $decoded['message'] ?? json_encode($decoded)),
            ];
        }

        $data = $decoded['data'] ?? $decoded;
        if (!is_array($data)) {
            $data = [];
        }

        return ['success' => true, 'data' => $data, 'error' => ''];
    }

    public function test(): array
    {
        return $this->query('SELECT 1');
    }
}
