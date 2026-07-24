<?php

/**
 * @author    Massimiliano Palermo <maxx.palermo@gmail.com>
 * @copyright Since 2016 Massimiliano Palermo
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 *
 * BRT REST API Tracking client.
 *
 * Base URL (produzione): https://api.brt.it/rest/tracking
 *
 * Endpoints:
 *   GET /parcelID/{parcelID}  → getTrackingByParcelId()
 *     Headers: userID, password
 *
 * Response structure: parcelIDResult → ttParcelIdResponse → bolla (spedizione), lista_eventi, lista_note
 */

namespace MpSoft\MpBrtRestApiShipments\Api\Tracking;

class BrtTrackingClient
{
    const BASE_URL_PRODUCTION = 'https://api.brt.it/rest/tracking';
    const BASE_URL_SANDBOX    = 'https://api.brt.it/rest/tracking';

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

    public function isSandbox(): bool
    {
        return $this->sandbox;
    }

    /**
     * GET /parcelID/{parcelID} — Get tracking info by parcel ID.
     *
     * @param string $parcelId BRT parcel ID (up to 35 chars)
     * @return array ['success' => bool, 'data' => array, 'error' => string, 'httpCode' => int]
     */
    public function getTrackingByParcelId(string $parcelId): array
    {
        $url = rtrim($this->baseUrl, '/') . '/parcelID/' . urlencode($parcelId);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET        => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER     => [
                'userID: ' . $this->userId,
                'password: ' . $this->password,
                'Accept: application/json',
            ],
        ]);

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

        $trackingResponse = $decoded['parcelIDResult']['ttParcelIdResponse'] ?? $decoded['ttParcelIdResponse'] ?? [];
        $execMsg          = $trackingResponse['executionMessage'] ?? [];
        $esito            = (int) ($trackingResponse['esito'] ?? 0);

        if ($execMsg && isset($execMsg['code']) && (int) $execMsg['code'] !== 0) {
            return [
                'success'  => false,
                'data'     => $decoded,
                'error'    => 'BRT error [' . $execMsg['code'] . ']: ' . ($execMsg['message'] ?? $execMsg['codeDesc'] ?? ''),
                'httpCode' => $httpCode,
            ];
        }

        return [
            'success'  => true,
            'data'     => $decoded,
            'error'    => '',
            'httpCode' => $httpCode,
            'esito'    => $esito,
        ];
    }

    /**
     * Parse the full tracking response into a structured array.
     *
     * @param array $data Raw response data from getTrackingByParcelId()
     * @return array Parsed tracking data
     */
    public static function parseTrackingResponse(array $data): array
    {
        $trackingResponse = $data['parcelIDResult']['ttParcelIdResponse']
            ?? $data['ttParcelIdResponse']
            ?? [];

        $bolla       = $trackingResponse['bolla'] ?? [];
        $listaEventi = $trackingResponse['lista_eventi'] ?? [];
        $listaNote   = $trackingResponse['lista_note'] ?? [];
        $contEventi  = (int) ($trackingResponse['contatore_eventi'] ?? 0);
        $contNote    = (int) ($trackingResponse['contatore_note'] ?? 0);
        $esito       = (int) ($trackingResponse['esito'] ?? 0);
        $versione    = (int) ($trackingResponse['versione'] ?? 0);
        $timestamp   = $trackingResponse['risposta_timestamp'] ?? '';

        $datiSpedizione = $bolla['dati_spedizione'] ?? [];
        $datiConsegna   = $bolla['dati_consegna'] ?? [];
        $riferimenti    = $bolla['riferimenti'] ?? [];
        $mittente       = $bolla['mittente'] ?? [];
        $destinatario   = $bolla['destinatario'] ?? [];
        $merce          = $bolla['merce'] ?? [];
        $contrassegno   = $bolla['contrassegno'] ?? [];
        $assicurazione  = $bolla['assicurazione'] ?? [];

        return [
            'esito'            => $esito,
            'versione'         => $versione,
            'timestamp'        => $timestamp,
            'dati_spedizione'  => $datiSpedizione,
            'dati_consegna'    => $datiConsegna,
            'riferimenti'      => $riferimenti,
            'mittente'         => $mittente,
            'destinatario'     => $destinatario,
            'merce'            => $merce,
            'contrassegno'     => $contrassegno,
            'assicurazione'    => $assicurazione,
            'lista_eventi'     => $listaEventi,
            'contatore_eventi' => $contEventi,
            'lista_note'       => $listaNote,
            'contatore_note'   => $contNote,
        ];
    }
}
