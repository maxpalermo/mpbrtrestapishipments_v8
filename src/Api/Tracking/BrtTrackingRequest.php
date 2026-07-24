<?php

/**
 * @author    Massimiliano Palermo <maxx.palermo@gmail.com>
 * @copyright Since 2016 Massimiliano Palermo
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 *
 * High-level wrapper: executes tracking query and persists to ModelBrtRestApiTracking.
 */

namespace MpSoft\MpBrtRestApiShipments\Api\Tracking;

class BrtTrackingRequest
{
    protected BrtTrackingClient $client;
    protected int $idOrder;

    public function __construct(int $idOrder = 0)
    {
        $this->idOrder = $idOrder;
        $this->client = BrtTrackingClient::fromConfig();
    }

    /**
     * Fetch and persist tracking for a given parcel ID.
     *
     * @param string $parcelId
     * @param int    $numericSenderReference   (for DB cross-reference, optional)
     * @param string $alphanumericSenderReference
     * @return array ['success', 'data', 'error', 'parsed', 'model']
     */
    public function track(string $parcelId, int $numericSenderReference = 0, string $alphanumericSenderReference = ''): array
    {
        $result = $this->client->getTrackingByParcelId($parcelId);

        $model = \ModelBrtRestApiTracking::getByParcelId($parcelId);
        if (!$model) {
            $model = new \ModelBrtRestApiTracking();
            $model->date_add = date('Y-m-d H:i:s');
        }

        $model->id_order = $this->idOrder;
        $model->parcel_id = $parcelId;
        $model->numeric_sender_reference = $numericSenderReference;
        $model->alphanumeric_sender_reference = $alphanumericSenderReference;
        $model->date_upd = date('Y-m-d H:i:s');
        $model->setResponseData($result['data']);

        $parsed = [];

        if ($result['success']) {
            $parsed = BrtTrackingClient::parseTrackingResponse($result['data']);
            $model->esito = $parsed['esito'];
            $model->stato_spedizione = (string) ($parsed['dati_spedizione']['stato_sped_parte1'] ?? '');
            $firmatario = (string) ($parsed['dati_consegna']['firmatario_consegna'] ?? '');
            $model->firmatario = $firmatario;
            $dataConsegna = $parsed['dati_consegna']['data_consegna_merce'] ?? null;
            $model->data_consegna = $dataConsegna ?: null;
        } else {
            $model->esito = -1;
        }

        try {
            $model->save();
        } catch (\Exception $e) {
            $result['error'] .= ' | Errore salvataggio tracking: ' . $e->getMessage();
        }

        $result['parsed'] = $parsed;
        $result['model'] = $model;

        return $result;
    }
}
