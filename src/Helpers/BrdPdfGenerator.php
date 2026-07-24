<?php

/**
 * @author    Massimiliano Palermo <maxx.palermo@gmail.com>
 * @copyright Since 2016 Massimiliano Palermo
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 *
 * Daily Borderò PDF Manifest Generator.
 */

namespace MpSoft\MpBrtRestApiShipments\Helpers;

class BrdPdfGenerator
{
    /**
     * Generate HTML for the Borderò Manifest.
     */
    public static function generateHtml(array $shipments, int $batchId = 0): string
    {
        $sandbox = (bool) \Configuration::get(BrtConfig::SANDBOX_ENABLED);
        $customerCode = \Configuration::get($sandbox ? BrtConfig::SANDBOX_CUSTOMER_CODE : BrtConfig::ACCOUNT_CUSTOMER_CODE);
        $departureDepot = \Configuration::get($sandbox ? BrtConfig::SANDBOX_DEPARTURE_DEPOT : BrtConfig::ACCOUNT_DEPARTURE_DEPOT);
        $shopName = \Configuration::get('PS_SHOP_NAME');
        $date = date('d/m/Y H:i');

        $totalParcels = 0;
        $totalWeight = 0.0;
        $totalCod = 0.0;

        $rowsHtml = '';
        foreach ($shipments as $index => $row) {
            $numParcels = (int) ($row['number_of_parcels'] ?? 1);
            $weight = (float) ($row['weight_kg'] ?? 0);
            $cod = (float) ($row['cash_on_delivery'] ?? 0);

            $totalParcels += $numParcels;
            $totalWeight += $weight;
            $totalCod += $cod;

            $rowsHtml .= '
                <tr style="font-size: 11px; border-bottom: 1px solid #ddd;">
                    <td style="padding: 6px; text-align: center;">' . ($index + 1) . '</td>
                    <td style="padding: 6px;">' . htmlspecialchars($row['alphanumeric_sender_reference'] ?: ('Ord. #' . $row['id_order'])) . '</td>
                    <td style="padding: 6px;">' . htmlspecialchars($row['consignee_company_name']) . '</td>
                    <td style="padding: 6px;">' . htmlspecialchars($row['consignee_city']) . '</td>
                    <td style="padding: 6px; text-align: center;">' . htmlspecialchars($row['parcel_number_from'] ? ($row['parcel_number_from'] . ' - ' . $row['parcel_number_to']) : '-') . '</td>
                    <td style="padding: 6px; text-align: center;">' . $numParcels . '</td>
                    <td style="padding: 6px; text-align: right;">' . number_format($weight, 2, ',', '.') . ' kg</td>
                    <td style="padding: 6px; text-align: right;">' . ($cod > 0 ? ('€ ' . number_format($cod, 2, ',', '.')) : '-') . '</td>
                </tr>
            ';
        }

        return '
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="utf-8">
                <title>Borderò Spedizioni BRT</title>
                <style>
                    body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #333; margin: 20px; }
                    .header { width: 100%; border-bottom: 2px solid #d0021b; padding-bottom: 10px; margin-bottom: 20px; }
                    .header table { width: 100%; }
                    .title { font-size: 20px; font-weight: bold; color: #d0021b; }
                    .info { font-size: 11px; color: #555; text-align: right; }
                    table.data { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                    table.data th { background: #f4f5f7; padding: 8px; font-size: 11px; border-bottom: 2px solid #ccc; text-align: left; }
                    .summary { width: 100%; background: #f8f9fa; border: 1px solid #e9ecef; padding: 12px; margin-bottom: 30px; border-radius: 4px; }
                    .summary td { padding: 4px 15px; font-size: 13px; font-weight: bold; }
                    .signatures { width: 100%; margin-top: 40px; }
                    .signatures td { width: 50%; vertical-align: top; font-size: 11px; }
                    .sig-box { border-top: 1px solid #999; margin-top: 50px; padding-top: 5px; width: 80%; }
                </style>
            </head>
            <body>
                <div class="header">
                    <table>
                        <tr>
                            <td>
                                <div class="title">BRT Bartolini — Borderò Spedizioni</div>
                                <div style="font-size: 13px; font-weight: bold; margin-top: 4px;">' . htmlspecialchars($shopName) . '</div>
                            </td>
                            <td class="info">
                                <div><strong>Codice Cliente BRT:</strong> ' . htmlspecialchars($customerCode) . '</div>
                                <div><strong>Punto Operativo:</strong> ' . htmlspecialchars($departureDepot) . '</div>
                                <div><strong>Data Stampa:</strong> ' . $date . '</div>
                                <div><strong>Lotto Manifest:</strong> #' . ($batchId ?: time()) . '</div>
                            </td>
                        </tr>
                    </table>
                </div>

                <table class="data">
                    <thead>
                        <tr>
                            <th style="width: 30px; text-align: center;">#</th>
                            <th>Rif. Ordine</th>
                            <th>Destinatario</th>
                            <th>Città</th>
                            <th style="text-align: center;">Range Pacchi</th>
                            <th style="text-align: center;">Colli</th>
                            <th style="text-align: right;">Peso</th>
                            <th style="text-align: right;">Contrassegno</th>
                        </tr>
                    </thead>
                    <tbody>
                        ' . ($rowsHtml ?: '<tr><td colspan="8" style="text-align:center; padding: 20px;">Nessuna spedizione trovata</td></tr>') . '
                    </tbody>
                </table>

                <table class="summary">
                    <tr>
                        <td>Spedizioni Totali: <span style="color:#d0021b;">' . count($shipments) . '</span></td>
                        <td>Colli Complessivi: <span style="color:#d0021b;">' . $totalParcels . '</span></td>
                        <td>Peso Totale: <span style="color:#d0021b;">' . number_format($totalWeight, 2, ',', '.') . ' kg</span></td>
                        <td>Totale Contrassegni: <span style="color:#d0021b;">€ ' . number_format($totalCod, 2, ',', '.') . '</span></td>
                    </tr>
                </table>

                <table class="signatures">
                    <tr>
                        <td>
                            <div>Firma del Mittente:</div>
                            <div class="sig-box"></div>
                        </td>
                        <td>
                            <div>Firma dell\'Autista BRT per Ricevuta:</div>
                            <div class="sig-box"></div>
                        </td>
                    </tr>
                </table>
            </body>
            </html>
        ';
    }

    /**
     * Render PDF to browser output.
     */
    public static function renderPdf(array $shipments, int $batchId = 0)
    {
        $html = self::generateHtml($shipments, $batchId);

        if (class_class_exists('PDFGenerator')) {
            $pdfGenerator = new \PDFGenerator(false, 'P');
            $pdfGenerator->setFontForLang('it');
            $pdfGenerator->writeHTML($html);
            return $pdfGenerator->render('Bordero_BRT_' . date('Ymd_His') . '.pdf', 'I');
        }

        // Fallback: output printable HTML page with auto-print script
        header('Content-Type: text/html; charset=utf-8');
        echo $html . '<script>window.onload = function() { window.print(); };</script>';
        exit();
    }
}
