<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use setasign\Fpdi\Fpdi;

class PdfRenderingService
{
    public function __construct(
        protected DraftLineService $draftLineService,
        protected StampPlacementService $stampPlacementService
    ) {}

    /**
     * Render the final PDF by applying dynamic draft lines and the 3-element red notary stamp.
     *
     * @param  string  $sourcePdfPath  Absolute path to input working PDF
     * @param  string  $outputPdfPath  Absolute path for final PDF output
     * @param  array{pages: array<int, array>}  $layoutData  Extracted layout bounding boxes
     * @param  array<string, mixed>  $draftConfig  Draft line settings
     * @param  array<string, mixed>  $stampConfig  Stamp settings
     * @return string Final PDF absolute path
     *
     * @throws RuntimeException
     */
    public function renderFinalPdf(
        string $sourcePdfPath,
        string $outputPdfPath,
        array $layoutData,
        array $draftConfig,
        array $stampConfig
    ): string {
        if (! file_exists($sourcePdfPath)) {
            throw new RuntimeException("File PDF sumber tidak ditemukan: {$sourcePdfPath}");
        }

        File::ensureDirectoryExists(dirname($outputPdfPath));

        // 1. Calculate draft lines
        $calculatedDraftLines = $this->draftLineService->calculateDraftLines($layoutData, $draftConfig);

        // 2. Calculate stamp placements
        $pagesInfo = $layoutData['pages'] ?? [];
        $calculatedStamp = $this->stampPlacementService->calculatePlacements($stampConfig, $pagesInfo);

        // Parse colors
        $draftRgb = $this->hexToRgb($draftConfig['color'] ?? '#DC2626');
        $draftThickness = (float) ($draftConfig['thickness'] ?? 1.2);
        $draftEnabled = (bool) ($draftConfig['enabled'] ?? true);

        $stampRgb = $this->hexToRgb($stampConfig['color'] ?? '#DC2626');
        $notaryName = $calculatedStamp['stamp_dimensions']['notary_name'];
        $cityTitle = $calculatedStamp['stamp_dimensions']['city_title'];
        $fontSizeName = (float) $calculatedStamp['stamp_dimensions']['font_size_name'];
        $fontSizeCity = (float) $calculatedStamp['stamp_dimensions']['font_size_city'];
        $stampLineThickness = (float) $calculatedStamp['stamp_dimensions']['line_thickness'];
        $stampWidth = (float) $calculatedStamp['stamp_dimensions']['width'];

        try {
            $pdf = new Fpdi('P', 'pt');
            $pdf->SetAutoPageBreak(false);

            $pageCount = $pdf->setSourceFile($sourcePdfPath);

            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $tplId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($tplId);

                $orientation = $size['orientation'];
                $pageWidth = $size['width'];
                $pageHeight = $size['height'];

                $pdf->AddPage($orientation, [$pageWidth, $pageHeight]);
                $pdf->useTemplate($tplId, 0, 0, $pageWidth, $pageHeight);

                // --- 1. Draw Draft Lines if active on this page ---
                if ($draftEnabled && isset($calculatedDraftLines[$pageNo]) && $calculatedDraftLines[$pageNo]['is_enabled']) {
                    $pdf->SetDrawColor($draftRgb['r'], $draftRgb['g'], $draftRgb['b']);
                    $pdf->SetLineWidth($draftThickness);

                    foreach ($calculatedDraftLines[$pageNo]['segments'] as $seg) {
                        $x = (float) $seg['x'];
                        $y1 = (float) $seg['y_start'];
                        $y2 = (float) $seg['y_end'];

                        // Clamp to page
                        $y1 = max(0.0, min($pageHeight, $y1));
                        $y2 = max(0.0, min($pageHeight, $y2));

                        if ($y2 > $y1) {
                            $pdf->Line($x, $y1, $x, $y2);
                        }
                    }
                }

                // --- 2. Draw Notary Stamp if active on this page ---
                if (isset($calculatedStamp['placements'][$pageNo])) {
                    $placement = $calculatedStamp['placements'][$pageNo];

                    if (! empty($placement['is_active'])) {
                        $pdf->SetTextColor($stampRgb['r'], $stampRgb['g'], $stampRgb['b']);
                        $pdf->SetDrawColor($stampRgb['r'], $stampRgb['g'], $stampRgb['b']);

                        $x = (float) $placement['x'];
                        $nameY = (float) $placement['name_y'];
                        $lineY = (float) $placement['line_y'];
                        $cityY = (float) $placement['city_y'];

                        // Element 1: Notary Name (Bold Red Text)
                        $pdf->SetFont('Helvetica', 'B', $fontSizeName);
                        $pdf->SetXY($x, $nameY);
                        $pdf->Cell($stampWidth, $fontSizeName, $notaryName, 0, 0, 'L');

                        // Element 2: Single Horizontal Red Line from Left to Right
                        $pdf->SetLineWidth($stampLineThickness);
                        $pdf->Line($x, $lineY, $x + $stampWidth, $lineY);

                        // Element 3: NOTARIS KOTA DENPASAR (Bold Red Text below line)
                        $pdf->SetFont('Helvetica', 'B', $fontSizeCity);
                        $pdf->SetXY($x, $cityY);
                        $pdf->Cell($stampWidth, $fontSizeCity, $cityTitle, 0, 0, 'L');
                    }
                }
            }

            // Save final PDF
            $pdf->Output('F', $outputPdfPath);

            if (! file_exists($outputPdfPath) || filesize($outputPdfPath) === 0) {
                throw new RuntimeException('File PDF final gagal disimpan atau kosong.');
            }

            return $outputPdfPath;
        } catch (Exception $e) {
            Log::error('PDF Rendering error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            throw new RuntimeException('Gagal menghasilkan PDF final: '.$e->getMessage());
        }
    }

    /**
     * Convert Hex color (e.g. #DC2626) to RGB array.
     *
     * @return array{r: int, g: int, b: int}
     */
    public function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (strlen($hex) !== 6) {
            return ['r' => 220, 'g' => 38, 'b' => 38]; // Default Red
        }

        return [
            'r' => (int) hexdec(substr($hex, 0, 2)),
            'g' => (int) hexdec(substr($hex, 2, 2)),
            'b' => (int) hexdec(substr($hex, 4, 2)),
        ];
    }
}
