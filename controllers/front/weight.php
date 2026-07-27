<?php

/**
 * @author    Massimiliano Palermo <maxx.palermo@gmail.com>
 * @copyright Since 2016 Massimiliano Palermo
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class MpBrtRestApiShipmentsWeightModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();

        header('Content-Type: application/json; charset=utf-8');

        $action = trim((string) Tools::getValue('action', 'save'));
        $code = trim((string) Tools::getValue('code', Tools::getValue('barcode', Tools::getValue('referenceNumber', Tools::getValue('ref', '')))));
        $progressivoInput = (int) Tools::getValue('progressivo', Tools::getValue('prog', 0));

        $referenceNumber = '';
        $progressivo = 1;

        if (!empty($code)) {
            if (strpos($code, '-') !== false) {
                $lastHyphenPos = strrpos($code, '-');
                $refPart = substr($code, 0, $lastHyphenPos);
                $progPart = substr($code, $lastHyphenPos + 1);

                if (is_numeric($progPart) && (int) $progPart > 0) {
                    $referenceNumber = $refPart;
                    $progressivo = (int) $progPart;
                } else {
                    $referenceNumber = $code;
                }
            } else {
                $referenceNumber = $code;
            }
        }

        if ($progressivoInput > 0) {
            $progressivo = $progressivoInput;
        }

        $idOrder = (int) Tools::getValue('id_order', 0);

        if (!$idOrder && !empty($referenceNumber) && is_numeric($referenceNumber)) {
            $testOrder = new Order((int) $referenceNumber);
            if (\Validate::isLoadedObject($testOrder)) {
                $idOrder = (int) $referenceNumber;
            }
        }

        if ($action === 'get' || $action === 'list') {
            if (empty($referenceNumber) && !$idOrder) {
                http_response_code(400);
                die(json_encode([
                    'success' => false,
                    'error' => 'Parametro referenceNumber o id_order mancante',
                ]));
            }

            $parcels = $idOrder
                ? ModelBrtRestApiWeight::getParcelsByOrderId($idOrder)
                : ModelBrtRestApiWeight::getParcelsByReference($referenceNumber);

            $totals = $idOrder
                ? ModelBrtRestApiWeight::calculateTotalsByOrderId($idOrder)
                : ModelBrtRestApiWeight::calculateTotalsByReference($referenceNumber);

            die(json_encode([
                'success' => true,
                'data' => $parcels,
                'totals' => $totals,
            ]));
        }

        // Default action: SAVE / UPDATE parcel
        if (empty($referenceNumber)) {
            http_response_code(400);
            die(json_encode([
                'success' => false,
                'error' => 'Parametro referenceNumber o code mancante. Formati validi: code=10299-1 oppure referenceNumber=10299&progressivo=1',
            ]));
        }

        $weight = (float) Tools::getValue('weight', Tools::getValue('peso', 0.0));
        $x = (float) Tools::getValue('x', 0.0);
        $y = (float) Tools::getValue('y', 0.0);
        $z = (float) Tools::getValue('z', 0.0);
        $volume = (float) Tools::getValue('volume', Tools::getValue('vol', 0.0));
        $isEnvelope = (int) Tools::getValue('is_envelope', Tools::getValue('busta', 0));
        $idRead = (int) Tools::getValue('id_read', 0);
        $isRead = (int) Tools::getValue('is_read', 0);

        $result = ModelBrtRestApiWeight::saveParcel(
            $referenceNumber,
            $progressivo,
            $weight,
            $x,
            $y,
            $z,
            $volume,
            $idOrder,
            $isEnvelope,
            $idRead,
            $isRead
        );

        if (!$result['success']) {
            http_response_code(500);
        } else {
            http_response_code(200);
        }

        die(json_encode($result));
    }
}
