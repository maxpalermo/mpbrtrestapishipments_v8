<?php

/**
 * @author    Massimiliano Palermo <maxx.palermo@gmail.com>
 * @copyright Since 2016 Massimiliano Palermo
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 *
 * Daily Borderò PDF Manifest Generator using native TCPDF methods (A4 Landscape).
 */

namespace MpSoft\MpBrtRestApiShipments\Helpers;

class BrtBorderoTcpdf extends \TCPDF
{
    protected string $headerTitle = '';
    protected string $printDate = '';

    public function setHeaderTitle(string $title): void
    {
        $this->headerTitle = $title;
    }

    public function setPrintDate(string $date): void
    {
        $this->printDate = $date;
    }

    public function Header(): void
    {
        $this->SetFont('helvetica', 'B', 9);
        $this->SetTextColor(0, 0, 0);
        $this->SetXY(15, 10);
        $this->Cell(267, 6, $this->headerTitle, 0, 1, 'C');

        $this->SetLineWidth(0.3);
        $this->Line(15, 17, 282, 17);
    }

    public function Footer(): void
    {
        $this->SetLineWidth(0.3);
        $this->Line(15, 196, 282, 196);

        $this->SetFont('helvetica', '', 8);
        $this->SetTextColor(0, 0, 0);

        // Left side: print date & time
        $this->SetXY(15, 197);
        $this->Cell(100, 5, $this->printDate ?: date('d/m/Y H:i:s'), 0, 0, 'L');

        // Right side: page number (X/Y)
        $this->SetXY(182, 197);
        $pageStr = $this->getAliasNumPage() . '/' . $this->getAliasNbPages();
        $this->Cell(100, 5, $pageStr, 0, 0, 'R');
    }
}

class BrdPdfGenerator
{
    /**
     * Render and output the Borderò PDF using native TCPDF calls.
     */
    public static function renderPdf(array $shipments, int $batchId = 0)
    {
        $sandbox = (bool) \Configuration::get(BrtConfig::SANDBOX_ENABLED);
        $customerCode = \Configuration::get($sandbox ? BrtConfig::SANDBOX_CUSTOMER_CODE : BrtConfig::ACCOUNT_CUSTOMER_CODE) ?: '';
        $shopName = \Configuration::get('PS_SHOP_NAME') ?: 'SHOP';
        $shopAddr = \Configuration::get('PS_SHOP_ADDR1') ?: '';
        $shopZip = \Configuration::get('PS_SHOP_CODE') ?: '';
        $shopCity = \Configuration::get('PS_SHOP_CITY') ?: '';
        $shopState = \Configuration::get('PS_SHOP_STATE') ?: '';

        $headerParts = [];
        if ($shopAddr) {
            $headerParts[] = $shopAddr;
        }
        $cityState = array_filter([$shopZip, mb_strtoupper($shopCity)]);
        if (!empty($cityState)) {
            $cityStr = implode(' ', $cityState);
            if ($shopState) {
                $cityStr .= ' (' . mb_strtoupper($shopState) . ')';
            }
            $headerParts[] = $cityStr;
        }

        $shopFullAddr = implode(' - ', $headerParts);
        $headerTitle = '[' . $customerCode . '] ' . mb_strtoupper($shopName) . ($shopFullAddr ? ' - ' . $shopFullAddr : '');

        $pdf = new BrtBorderoTcpdf('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setHeaderTitle($headerTitle);
        $pdf->setPrintDate(date('d/m/Y H:i:s'));

        // Document Metadata
        $pdf->SetCreator('PrestaShop BRT REST API Module');
        $pdf->SetAuthor($shopName);
        $pdf->SetTitle('Borderò Spedizioni BRT ' . date('d/m/Y'));
        $pdf->SetMargins(15, 20, 15);
        $pdf->SetAutoPageBreak(false);

        $pdf->AddPage('L', 'A4');

        $currentY = self::drawTableHeader($pdf, 20);

        $totSpedizioni = count($shipments);
        $totColli = 0;
        $totPeso = 0.0;
        $totVolume = 0.0;
        $numCod = 0;
        $importoCod = 0.0;

        foreach ($shipments as $row) {
            $idOrder = (int) ($row['id_order'] ?? 0);
            $numRef = (string) ($row['numeric_sender_reference'] ?? '');
            $alphaRef = (string) ($row['alphanumeric_sender_reference'] ?? '');
            $parcelFrom = (string) ($row['parcel_number_from'] ?? '');
            $parcelTo = (string) ($row['parcel_number_to'] ?? '');
            $numParcels = (int) ($row['number_of_parcels'] ?? 1);
            $weight = (float) ($row['weight_kg'] ?? 0);
            $cod = (float) ($row['cash_on_delivery'] ?? 0);
            $destName = (string) ($row['consignee_company_name'] ?? '');
            $city = (string) ($row['consignee_city'] ?? '');

            $totColli += $numParcels;
            $totPeso += $weight;

            if ($cod > 0) {
                $numCod++;
                $importoCod += $cod;
            }

            // Extract additional fields from Request JSON or Order Address
            $reqModel = false;
            if ($numRef) {
                $reqModel = \ModelBrtRestApiShipmentRequest::getByNumericSenderReference((int) $numRef);
            }
            if (!$reqModel && $idOrder) {
                $reqModel = \ModelBrtRestApiShipmentRequest::getByIdOrder($idOrder);
            }

            $address = '';
            $zip = '';
            $prov = '';
            $serviceType = '';
            $volume = 0.0;

            if ($reqModel) {
                $reqData = $reqModel->getRequestDataArray();
                $createData = $reqData['createData'] ?? $reqData;
                $address = (string) ($createData['consigneeAddress'] ?? '');
                $zip = (string) ($createData['consigneeZIPCode'] ?? '');
                if (empty($city)) {
                    $city = (string) ($createData['consigneeCity'] ?? '');
                }
                $prov = (string) ($createData['consigneeProvinceAbbreviation'] ?? '');
                $serviceType = (string) ($createData['serviceType'] ?? '');
                if (isset($createData['volumeM3'])) {
                    $volume = (float) $createData['volumeM3'];
                }
            }

            if (empty($address) && $idOrder > 0) {
                $orderObj = new \Order($idOrder);
                if (\Validate::isLoadedObject($orderObj)) {
                    $addrObj = new \Address($orderObj->id_address_delivery);
                    if (\Validate::isLoadedObject($addrObj)) {
                        $address = trim($addrObj->address1 . ' ' . $addrObj->address2);
                        $zip = $addrObj->postcode;
                        if (empty($city)) {
                            $city = $addrObj->city;
                        }
                        if ($addrObj->id_state) {
                            $stObj = new \State($addrObj->id_state);
                            $prov = $stObj->iso_code;
                        }
                    }
                }
            }

            $totVolume += $volume;

            $capCityProvParts = array_filter([$zip, mb_strtoupper($city), mb_strtoupper($prov)]);
            $capCityProv = implode(' - ', $capCityProvParts);
            $serviceTypeStr = 'TIPO DI SERVIZIO: ' . ($serviceType ? mb_strtoupper($serviceType) : 'C');

            $codFormatted = number_format($cod, 2, ',', '.') . ' €';
            $weightFormatted = number_format($weight, 3, ',', '.');
            $volFormatted = number_format($volume, 3, ',', '.');

            // Page Break Control: each row needs 9.0mm height
            if ($currentY + 9.0 > 185) {
                $pdf->AddPage('L', 'A4');
                $currentY = self::drawTableHeader($pdf, 20);
            }

            // Line 1 of Shipment Row
            $pdf->SetXY(15, $currentY);
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell(65, 4, mb_substr($destName, 0, 38), 0, 0, 'L');

            $pdf->SetFont('helvetica', '', 8);
            $pdf->Cell(65, 4, mb_substr($address, 0, 38), 0, 0, 'L');
            $pdf->Cell(35, 4, $numRef, 0, 0, 'C');
            $pdf->Cell(18, 4, '', 0, 0, 'C');
            $pdf->Cell(22, 4, $codFormatted, 0, 0, 'R');
            $pdf->Cell(12, 4, $numParcels, 0, 0, 'R');
            $pdf->Cell(16, 4, $weightFormatted, 0, 0, 'R');
            $pdf->Cell(16, 4, $volFormatted, 0, 0, 'R');

            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell(18, 4, $parcelFrom, 0, 1, 'R');

            // Line 2 of Shipment Row
            $pdf->SetXY(15, $currentY + 4.2);
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell(65, 4, $serviceTypeStr, 0, 0, 'L');
            $pdf->Cell(65, 4, mb_substr($capCityProv, 0, 38), 0, 0, 'L');
            $pdf->Cell(35, 4, $alphaRef, 0, 0, 'C');
            $pdf->Cell(18, 4, '', 0, 0, 'C');
            $pdf->Cell(22, 4, '', 0, 0, 'R');
            $pdf->Cell(12, 4, '', 0, 0, 'R');
            $pdf->Cell(16, 4, '', 0, 0, 'R');
            $pdf->Cell(16, 4, '', 0, 0, 'R');
            $pdf->Cell(18, 4, $parcelTo, 0, 1, 'R');

            $currentY += 9.0;
        }

        // Draw Summary Section & Signature block
        $totals = [
            'totSpedizioni' => $totSpedizioni,
            'totColli' => $totColli,
            'numCod' => $numCod,
            'importoCod' => $importoCod,
            'totPeso' => $totPeso,
            'totVolume' => $totVolume,
        ];

        self::drawSummarySection($pdf, $currentY, $totals);

        // Clear output buffer if any to avoid PDF stream corruption
        if (ob_get_length()) {
            ob_end_clean();
        }

        $pdf->Output('Bordero_BRT_' . date('Ymd_His') . '.pdf', 'I');
        exit();
    }

    protected static function drawTableHeader(BrtBorderoTcpdf $pdf, float $y): float
    {
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(0, 0, 0);

        // Line 1 of Header
        $pdf->SetXY(15, $y);
        $pdf->Cell(65, 4, 'Destinatario', 0, 0, 'L');
        $pdf->Cell(65, 4, 'Indirizzo', 0, 0, 'L');
        $pdf->Cell(35, 4, 'Rif. numerico', 0, 0, 'C');
        $pdf->Cell(18, 4, 'Cod', 0, 0, 'C');
        $pdf->Cell(22, 4, 'Importo', 0, 0, 'R');
        $pdf->Cell(12, 4, 'Colli', 0, 0, 'R');
        $pdf->Cell(16, 4, 'Peso', 0, 0, 'R');
        $pdf->Cell(16, 4, 'Volume', 0, 0, 'R');
        $pdf->Cell(18, 4, 'Segnacolli', 0, 1, 'R');

        // Line 2 of Header
        $pdf->SetXY(15, $y + 4);
        $pdf->Cell(65, 4, '', 0, 0, 'L');
        $pdf->Cell(65, 4, 'Cap Città Prov', 0, 0, 'L');
        $pdf->Cell(35, 4, 'Riferimento', 0, 0, 'C');
        $pdf->Cell(18, 4, 'Bolla', 0, 0, 'C');
        $pdf->Cell(22, 4, 'C/ass', 0, 0, 'R');
        $pdf->Cell(12, 4, '', 0, 0, 'R');
        $pdf->Cell(16, 4, '', 0, 0, 'R');
        $pdf->Cell(16, 4, '', 0, 0, 'R');
        $pdf->Cell(18, 4, 'Dal - Al', 0, 1, 'R');

        $lineY = $y + 8.5;
        $pdf->SetLineWidth(0.3);
        $pdf->Line(15, $lineY, 282, $lineY);

        return $lineY + 2;
    }

    protected static function drawSummarySection(BrtBorderoTcpdf $pdf, float $currentY, array $totals): void
    {
        // Fixed bottom position for summary box (bottom of box at Y = 188mm, above footer line at 196mm)
        $boxY = 143;

        // If current Y exceeds 138mm, add a new page for the summary section
        if ($currentY > 138) {
            $pdf->AddPage('L', 'A4');
        }

        $boxX = 15;
        $boxW = 110;
        $boxH = 45;

        // Outer box rectangle
        $pdf->SetLineWidth(0.3);
        $pdf->Rect($boxX, $boxY, $boxW, $boxH);

        // Header of box: RIEPILOGO
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetXY($boxX + 3, $boxY + 2);
        $pdf->Cell($boxW - 6, 5, 'RIEPILOGO', 0, 1, 'L');
        $pdf->Line($boxX, $boxY + 8, $boxX + $boxW, $boxY + 8);

        // Content rows
        $rowY = $boxY + 10;
        $pdf->SetFont('helvetica', '', 8);

        $labelW = 60;
        $valW = 44;

        // 1. Totale Spedizioni
        $pdf->SetXY($boxX + 3, $rowY);
        $pdf->Cell($labelW, 5, 'TOTALE SPEDIZIONI:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell($valW, 5, $totals['totSpedizioni'] . '  SPED', 0, 1, 'R');
        $rowY += 5;

        // 2. Totale Colli
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetXY($boxX + 3, $rowY);
        $pdf->Cell($labelW, 5, 'TOTALE COLLI:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell($valW, 5, $totals['totColli'] . '  COLLI', 0, 1, 'R');
        $rowY += 5;

        // 3. Totale Contrassegni
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetXY($boxX + 3, $rowY);
        $pdf->Cell($labelW, 5, 'TOTALE CONTRASSEGNI:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell($valW, 5, $totals['numCod'] . '  ORDINI', 0, 1, 'R');
        $rowY += 5;

        // 4. Importo Contrassegni
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetXY($boxX + 3, $rowY);
        $pdf->Cell($labelW, 5, 'IMPORTO CONTRASSEGNI:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell($valW, 5, number_format($totals['importoCod'], 2, ',', '.') . '  EUR', 0, 1, 'R');
        $rowY += 5;

        // 5. Totale Peso
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetXY($boxX + 3, $rowY);
        $pdf->Cell($labelW, 5, 'TOTALE PESO:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell($valW, 5, number_format($totals['totPeso'], 2, ',', '.') . '  Kg', 0, 1, 'R');
        $rowY += 5;

        // 6. Totale Volume
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetXY($boxX + 3, $rowY);
        $pdf->Cell($labelW, 5, 'TOTALE VOLUME:', 0, 0, 'L');
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell($valW, 5, number_format($totals['totVolume'], 3, ',', '.') . '  M³', 0, 1, 'R');

        // FIRMA block on the right (aligned vertically with summary box)
        $firmaX = 180;
        $firmaY = $boxY + 25;

        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetXY($firmaX, $firmaY);
        $pdf->Cell(80, 5, 'FIRMA', 0, 1, 'C');

        $pdf->SetXY($firmaX, $firmaY + 12);
        $pdf->Cell(80, 5, '--------------------------------------------------', 0, 1, 'C');
    }
}
