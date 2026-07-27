<?php

namespace MpSoft\MpBrtRestApiShipments\Helpers;

use setasign\Fpdi\Tcpdf\Fpdi;

class BrtPdfMerger
{
    /**
     * Merge multiple Base64 encoded PDF strings (or raw PDF strings) into a single PDF.
     * Returns binary PDF content.
     *
     * @param array $pdfList Array of Base64 strings or raw PDF strings
     * @return string Binary PDF string
     * @throws \Exception
     */
    public static function mergePdfs(array $pdfList): string
    {
        if (empty($pdfList)) {
            throw new \InvalidArgumentException('Nessun segnacollo PDF fornito per l\'unione.');
        }

        // Clean & decode all PDF strings to raw binary
        $validBins = [];
        foreach ($pdfList as $item) {
            if (empty($item)) {
                continue;
            }
            $bin = base64_decode($item, true);
            if ($bin === false || strpos($bin, '%PDF') !== 0) {
                if (strpos($item, '%PDF') === 0) {
                    $bin = $item;
                } else {
                    continue;
                }
            }
            $validBins[] = $bin;
        }

        if (empty($validBins)) {
            throw new \RuntimeException('Nessun contenuto PDF valido trovato nell\'elenco segnacolli.');
        }

        // If only 1 single PDF, return it directly
        if (count($validBins) === 1) {
            return $validBins[0];
        }

        // Multiple PDFs: merge using FPDI + TCPDF
        $tmpFiles = [];
        try {
            if (!class_exists('TCPDF')) {
                $root = defined('_PS_ROOT_DIR_') ? _PS_ROOT_DIR_ : dirname(__DIR__, 4);
                $tcpdfPath = $root . '/vendor/tecnickcom/tcpdf/tcpdf.php';
                if (file_exists($tcpdfPath)) {
                    require_once $tcpdfPath;
                }
            }

            $pdf = new Fpdi();
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetAutoPageBreak(false);

            foreach ($validBins as $index => $binary) {
                $tmpFile = tempnam(sys_get_temp_dir(), 'brt_pdf_' . $index . '_') . '.pdf';
                file_put_contents($tmpFile, $binary);
                $tmpFiles[] = $tmpFile;

                $pageCount = $pdf->setSourceFile($tmpFile);
                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                    $templateId = $pdf->importPage($pageNo);
                    $size = $pdf->getTemplateSize($templateId);
                    $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';
                    $pdf->AddPage($orientation, [$size['width'], $size['height']]);
                    $pdf->useTemplate($templateId);
                }
            }

            return $pdf->Output('brt_merged_labels.pdf', 'S');
        } finally {
            foreach ($tmpFiles as $f) {
                if (file_exists($f)) {
                    @unlink($f);
                }
            }
        }
    }
}
