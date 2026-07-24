<?php

/**
 * @author    Massimiliano Palermo <maxx.palermo@gmail.com>
 * @copyright Since 2016 Massimiliano Palermo
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace MpSoft\MpBrtRestApiShipments\Helpers;

use MpSoft\MpBrtRestApiShipments\Api\Tracking\BrtTrackingClient;

class BrtStats
{
    public static function getCategories(): array
    {
        return [
            'delivered' => ['label' => 'Consegnati', 'color' => 'green', 'icon' => 'check', 'keywords' => ['CONSEGNATA', 'CONSEGNATO']],
            'fermopoint' => ['label' => 'FermoPoint', 'color' => 'blue', 'icon' => 'place', 'keywords' => ['FERMOPOINT', 'DEPOSITO', 'PUNTO RITIRO']],
            'rejected' => ['label' => 'Rifiutati', 'color' => 'red', 'icon' => 'block', 'keywords' => ['RIFIUTATA', 'RIFIUTATO', 'NON CONSEGNATA']],
            'waiting' => ['label' => 'In attesa', 'color' => 'yellow', 'icon' => 'hourglass_empty', 'keywords' => ['IN ATTESA', 'ATTESA', 'PRESA IN CARICO']],
            'transit' => ['label' => 'In transito', 'color' => 'amber', 'icon' => 'local_shipping', 'keywords' => ['IN TRANSITO', 'PARTITA', 'IN CONSEGNA', 'ARRIVATA']],
            'alerts' => ['label' => 'Avvisi', 'color' => 'red', 'icon' => 'warning', 'keywords' => ['AVVISO', 'ANOMALIA', 'ALLERTA', 'ERRORE']],
        ];
    }

    public static function getDashboardStats(): array
    {
        $rows = \Db::getInstance()->executeS(
            'SELECT `stato_spedizione`, `esito` FROM `' . _DB_PREFIX_ . 'brt_restapi_tracking`'
        ) ?: [];

        $categories = self::getCategories();
        $stats = [];
        foreach ($categories as $key => $cat) {
            $stats[$key] = ['label' => $cat['label'], 'color' => $cat['color'], 'icon' => $cat['icon'], 'count' => 0, 'percent' => 0];
        }

        foreach ($rows as $row) {
            $status = strtoupper((string) ($row['stato_spedizione'] ?? ''));
            $esito = (int) ($row['esito'] ?? 0);
            $assigned = false;

            if ($esito === 1) {
                $stats['delivered']['count']++;
                $assigned = true;
            } else {
                foreach ($categories as $key => $cat) {
                    foreach ($cat['keywords'] as $kw) {
                        if (strpos($status, $kw) !== false) {
                            $stats[$key]['count']++;
                            $assigned = true;
                            break 2;
                        }
                    }
                }
            }

            if (!$assigned) {
                $stats['transit']['count']++;
            }
        }

        $total = array_sum(array_column($stats, 'count'));
        foreach ($stats as $key => $s) {
            $stats[$key]['percent'] = $total > 0 ? round($s['count'] / $total * 100, 1) : 0;
        }
        $stats['total'] = $total;

        return $stats;
    }

    public static function getDeliveryDays(): array
    {
        $rows = \Db::getInstance()->executeS(
            'SELECT `id_order`, `data_consegna`, `response_json` FROM `' . _DB_PREFIX_ . 'brt_restapi_tracking`
             WHERE `data_consegna` IS NOT NULL AND `data_consegna` != \'0000-00-00\''
        ) ?: [];

        $days = [];
        foreach ($rows as $row) {
            $shipDate = self::extractShipmentDate((string) $row['response_json'], (int) $row['id_order']);
            if ($shipDate) {
                $diff = self::calculateDays($shipDate, $row['data_consegna']);
                if ($diff >= 0) {
                    $days[] = $diff;
                }
            }
        }

        if (empty($days)) {
            return ['max' => 0, 'min' => 0, 'distribution' => []];
        }

        sort($days);
        $distribution = [];
        foreach ($days as $d) {
            $distribution[$d] = ($distribution[$d] ?? 0) + 1;
        }

        return ['max' => max($days), 'min' => min($days), 'distribution' => $distribution];
    }

    public static function getDeliveredByMonth(): array
    {
        $rows = \Db::getInstance()->executeS(
            'SELECT DATE_FORMAT(`data_consegna`, \'%Y-%m\') AS month, COUNT(*) AS cnt
             FROM `' . _DB_PREFIX_ . 'brt_restapi_tracking`
             WHERE `data_consegna` IS NOT NULL AND `data_consegna` != \'0000-00-00\'
             GROUP BY month
             ORDER BY month DESC
             LIMIT 12'
        ) ?: [];

        $labels = [];
        $values = [];
        foreach (array_reverse($rows) as $row) {
            $labels[] = $row['month'];
            $values[] = (int) $row['cnt'];
        }

        return ['labels' => $labels, 'values' => $values];
    }

    public static function getHistory(array $filters, int $idLang, int $limit, int $offset): array
    {
        $db = \Db::getInstance();
        $prefix = _DB_PREFIX_;
        $where = ['1=1'];

        if (!empty($filters['id_order'])) {
            $where[] = 't.id_order = ' . (int) $filters['id_order'];
        }
        if (!empty($filters['parcel_id'])) {
            $where[] = 't.parcel_id LIKE \'%' . pSQL($filters['parcel_id']) . '%\'';
        }
        if (!empty($filters['stato_spedizione'])) {
            $where[] = 't.stato_spedizione LIKE \'%' . pSQL($filters['stato_spedizione']) . '%\'';
        }
        if (!empty($filters['order_state'])) {
            $where[] = 'osl.name LIKE \'%' . pSQL($filters['order_state']) . '%\'';
        }
        if (!empty($filters['data_consegna_from'])) {
            $where[] = 't.data_consegna >= \'' . pSQL($filters['data_consegna_from']) . '\'';
        }
        if (!empty($filters['data_consegna_to'])) {
            $where[] = 't.data_consegna <= \'' . pSQL($filters['data_consegna_to']) . '\'';
        }
        if (!empty($filters['event_code'])) {
            $where[] = 't.response_json LIKE \'%' . pSQL($filters['event_code']) . '%\'';
        }
        if (!empty($filters['event_name'])) {
            $where[] = 't.response_json LIKE \'%' . pSQL($filters['event_name']) . '%\'';
        }
        if (!empty($filters['event_date'])) {
            $where[] = 't.response_json LIKE \'%' . pSQL($filters['event_date']) . '%\'';
        }
        if (!empty($filters['filiale'])) {
            $where[] = 't.response_json LIKE \'%' . pSQL($filters['filiale']) . '%\'';
        }
        if (!empty($filters['nome_filiale'])) {
            $where[] = 't.response_json LIKE \'%' . pSQL($filters['nome_filiale']) . '%\'';
        }

        $whereSql = 'WHERE ' . implode(' AND ', $where);
        $lang = (int) $idLang;

        $sql = "SELECT
            t.id_brt_restapi_tracking AS id,
            t.id_order,
            t.parcel_id,
            t.numeric_sender_reference,
            t.stato_spedizione,
            t.data_consegna,
            t.response_json,
            t.date_add,
            t.date_upd,
            o.current_state,
            osl.name AS order_state_name,
            s.date_add AS shipment_date
        FROM {$prefix}brt_restapi_tracking t
        LEFT JOIN {$prefix}orders o ON o.id_order = t.id_order
        LEFT JOIN {$prefix}order_state_lang osl ON osl.id_order_state = o.current_state AND osl.id_lang = {$lang}
        LEFT JOIN (
            SELECT id_order, MAX(date_add) AS max_date
            FROM {$prefix}brt_restapi_shipment_response
            GROUP BY id_order
        ) latest ON latest.id_order = t.id_order
        LEFT JOIN {$prefix}brt_restapi_shipment_response s ON s.id_order = latest.id_order AND s.date_add = latest.max_date
        {$whereSql}
        ORDER BY t.date_add DESC
        LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset;

        $rows = $db->executeS($sql) ?: [];
        $total = (int) $db->getValue(
            "SELECT COUNT(*) FROM {$prefix}brt_restapi_tracking t
             LEFT JOIN {$prefix}orders o ON o.id_order = t.id_order
             LEFT JOIN {$prefix}order_state_lang osl ON osl.id_order_state = o.current_state AND osl.id_lang = {$lang}
             {$whereSql}"
        );

        foreach ($rows as &$row) {
            $data = json_decode($row['response_json'], true);
            $parsed = is_array($data) ? BrtTrackingClient::parseTrackingResponse($data) : [];
            $row['shipment_date'] = !empty($row['shipment_date']) && $row['shipment_date'] != '0000-00-00 00:00:00'
                ? date('Y-m-d', strtotime($row['shipment_date']))
                : self::extractShipmentDate((string) $row['response_json'], (int) $row['id_order']);
            $row['giorni'] = self::calculateDays($row['shipment_date'], $row['data_consegna']);
            $row['last_event'] = self::extractLastEvent($parsed);
            $row['stato_spedizione'] = (string) $row['stato_spedizione'];
            $row['order_state_name'] = (string) $row['order_state_name'];
        }

        return ['rows' => $rows, 'total' => $total];
    }

    public static function extractShipmentDate(string $responseJson, int $idOrder): ?string
    {
        $data = json_decode($responseJson, true);
        if (is_array($data)) {
            $parsed = BrtTrackingClient::parseTrackingResponse($data);
            $date = $parsed['dati_spedizione']['spedizione_data'] ?? null;
            if ($date) {
                return $date;
            }
        }

        if ($idOrder) {
            $responseModel = \ModelBrtRestApiShipmentResponse::getByIdOrder($idOrder);
            if ($responseModel && $responseModel->date_add && $responseModel->date_add != '0000-00-00 00:00:00') {
                return date('Y-m-d', strtotime($responseModel->date_add));
            }
        }

        return null;
    }

    public static function calculateDays(?string $shipDate, ?string $delDate): int
    {
        if (!$shipDate || !$delDate || $delDate == '0000-00-00') {
            return 0;
        }
        $d1 = strtotime($shipDate);
        $d2 = strtotime($delDate);
        if (!$d1 || !$d2) {
            return 0;
        }

        return max(0, (int) round(($d2 - $d1) / 86400));
    }

    public static function extractLastEvent(array $parsed): array
    {
        $events = $parsed['lista_eventi'] ?? [];
        if (empty($events) || !is_array($events)) {
            return self::emptyEvent();
        }

        if (isset($events[0])) {
            $event = end($events);
            $ev = $event['evento'] ?? $event;
        } else {
            $ev = $events['evento'] ?? $events;
        }

        if (!is_array($ev)) {
            return self::emptyEvent();
        }

        return [
            'event_code' => self::firstNotEmpty([$ev['codice'] ?? null, $ev['idEvento'] ?? null, $ev['id'] ?? null, $ev['evento'] ?? null]) ?: '—',
            'event_name' => self::firstNotEmpty([$ev['descrizione'] ?? null, $ev['nomeEvento'] ?? null, $ev['descrizione_evento'] ?? null, $ev['nome'] ?? null]) ?: '—',
            'event_date' => self::firstNotEmpty([$ev['data'] ?? null, $ev['dataEvento'] ?? null]) ?: '—',
            'filiale' => self::firstNotEmpty([$ev['filiale'] ?? null, $ev['filiale_arrivo'] ?? null, $ev['idFiliale'] ?? null]) ?: '—',
            'nome_filiale' => self::firstNotEmpty([$ev['nome_filiale'] ?? null, $ev['nomeFiliale'] ?? null, $ev['filiale_nome'] ?? null]) ?: '—',
            'rma' => self::firstNotEmpty([$ev['rma'] ?? null, $ev['RMA'] ?? null, $ev['codice_rma'] ?? null]) ?: '—',
        ];
    }

    private static function emptyEvent(): array
    {
        return ['event_code' => '—', 'event_name' => '—', 'event_date' => '—', 'filiale' => '—', 'nome_filiale' => '—', 'rma' => '—'];
    }

    private static function firstNotEmpty(array $values): string
    {
        foreach ($values as $v) {
            if ($v !== null && $v !== '' && $v !== '—') {
                return (string) $v;
            }
        }

        return '';
    }
}
