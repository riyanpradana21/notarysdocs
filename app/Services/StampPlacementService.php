<?php

namespace App\Services;

class StampPlacementService
{
    /**
     * Compute stamp layout dimensions and exact coordinates for each target page.
     *
     * @param  array<string, mixed>  $stampConfig
     * @param  array<int, array{width: float, height: float, orientation: string, blocks?: array}>  $pagesData
     * @return array{
     *     config: array<string, mixed>,
     *     stamp_dimensions: array{width: float, height: float, notary_name: string, city_title: string, color: string},
     *     target_pages: list<int>,
     *     placements: array<int, array{
     *         page_number: int,
     *         width: float,
     *         height: float,
     *         x: float,
     *         y: float,
     *         x_percent: float,
     *         y_percent: float,
     *         is_active: bool,
     *         line_start_x: float,
     *         line_end_x: float,
     *         line_y: float,
     *         name_y: float,
     *         city_y: float
     *     }>
     * }
     */
    public function calculatePlacements(array $stampConfig, array $pagesData): array
    {
        $notaryName = trim($stampConfig['notary_name'] ?? 'ROBERT, S.H., M.Kn.');
        $cityTitle = trim($stampConfig['city_title'] ?? 'NOTARIS KOTA DENPASAR');
        $color = $stampConfig['color'] ?? '#DC2626'; // Red mandatory

        $fontSizeName = (float) ($stampConfig['font_size_name'] ?? 11.0);
        $fontSizeCity = (float) ($stampConfig['font_size_city'] ?? 9.0);
        $lineThickness = (float) ($stampConfig['line_thickness'] ?? 1.2);
        $elementSpacing = (float) ($stampConfig['element_spacing'] ?? 4.0);

        // Calculate text widths in points to match line length exactly to notary name
        $nameWidth = $this->estimateTextWidth($notaryName, $fontSizeName, 'B');
        $cityWidth = $this->estimateTextWidth($cityTitle, $fontSizeCity, 'B');

        // Line length matches the notary name length with slight padding on each end (like real stamp in Gambar 3)
        $autoLineLength = round(max($nameWidth + 6.0, $cityWidth + 4.0), 1);

        // If line_length is explicitly specified (different from default 180), respect it; otherwise match notary name
        if (! empty($stampConfig['line_length']) && (float) $stampConfig['line_length'] > 0 && (float) $stampConfig['line_length'] !== 180.0) {
            $stampWidth = (float) $stampConfig['line_length'];
        } else {
            $stampWidth = $autoLineLength;
        }

        // Total height: Name text height + spacing + line thickness + spacing + City text height
        $stampHeight = $fontSizeName + $elementSpacing + $lineThickness + $elementSpacing + $fontSizeCity + 2.0;

        $totalPages = count($pagesData);
        $targetPagesRule = $stampConfig['target_pages'] ?? 'first_page';
        $customPagesStr = $stampConfig['custom_pages'] ?? '';

        $activePages = (new DraftLineService)->resolveActivePages($targetPagesRule, $customPagesStr, $totalPages);

        $preset = $stampConfig['preset'] ?? 'top_left';
        $rawXPercent = (float) ($stampConfig['x_percent'] ?? 8.0);
        $rawYPercent = (float) ($stampConfig['y_percent'] ?? 8.0);

        $placements = [];

        foreach ($pagesData as $pageNo => $pData) {
            $pageWidth = (float) ($pData['width'] ?? 595.28);
            $pageHeight = (float) ($pData['height'] ?? 841.89);
            $isActive = in_array((int) $pageNo, $activePages, true);

            // Compute default or preset coordinates if preset chosen
            $coords = $this->resolveCoordinatesForPage(
                $preset,
                $rawXPercent,
                $rawYPercent,
                $pageWidth,
                $pageHeight,
                $stampWidth,
                $stampHeight,
                $pData['blocks'] ?? []
            );

            $xPt = $coords['x'];
            $yPt = $coords['y'];

            // Strict clamping to ensure stamp stays completely inside page boundaries
            $clampedX = round(max(10.0, min($pageWidth - $stampWidth - 10.0, $xPt)), 2);
            $clampedY = round(max(10.0, min($pageHeight - $stampHeight - 10.0, $yPt)), 2);

            // Element relative offsets
            // Line 1: Notary Name (top)
            $nameY = $clampedY;
            // Line 2: Horizontal red line
            $lineY = $nameY + $fontSizeName + $elementSpacing;
            // Line 3: City title below line
            $cityY = $lineY + $lineThickness + $elementSpacing;

            $placements[$pageNo] = [
                'page_number' => (int) $pageNo,
                'width' => round($pageWidth, 2),
                'height' => round($pageHeight, 2),
                'x' => round($clampedX, 2),
                'y' => round($clampedY, 2),
                'x_percent' => round(($clampedX / $pageWidth) * 100.0, 2),
                'y_percent' => round(($clampedY / $pageHeight) * 100.0, 2),
                'is_active' => $isActive,
                'line_start_x' => round($clampedX, 2),
                'line_end_x' => round($clampedX + $stampWidth, 2),
                'line_y' => round($lineY, 2),
                'name_y' => round($nameY, 2),
                'city_y' => round($cityY, 2),
            ];
        }

        return [
            'config' => $stampConfig,
            'stamp_dimensions' => [
                'width' => round($stampWidth, 2),
                'height' => round($stampHeight, 2),
                'notary_name' => $notaryName,
                'city_title' => $cityTitle,
                'color' => $color,
                'font_size_name' => $fontSizeName,
                'font_size_city' => $fontSizeCity,
                'line_thickness' => $lineThickness,
                'line_length' => round($stampWidth, 2),
                'element_spacing' => $elementSpacing,
            ],
            'target_pages' => $activePages,
            'placements' => $placements,
        ];
    }

    /**
     * Estimate text width in points using standard Helvetica-Bold typographic metrics.
     */
    public function estimateTextWidth(string $text, float $fontSize, string $style = 'B'): float
    {
        // Standard Adobe Helvetica-Bold glyph widths per 1000 units
        $widths = [
            ' ' => 278, '!' => 333, '"' => 474, '#' => 556, '$' => 556, '%' => 889, '&' => 722, '\'' => 278,
            '(' => 389, ')' => 389, '*' => 444, '+' => 610, ',' => 278, '-' => 333, '.' => 278, '/' => 278,
            '0' => 556, '1' => 556, '2' => 556, '3' => 556, '4' => 556, '5' => 556, '6' => 556, '7' => 556,
            '8' => 556, '9' => 556, ':' => 333, ';' => 333, '<' => 610, '=' => 610, '>' => 610, '?' => 556,
            '@' => 975, 'A' => 722, 'B' => 722, 'C' => 722, 'D' => 722, 'E' => 667, 'F' => 610, 'G' => 778,
            'H' => 722, 'I' => 278, 'J' => 556, 'K' => 722, 'L' => 610, 'M' => 833, 'N' => 722, 'O' => 778,
            'P' => 667, 'Q' => 778, 'R' => 722, 'S' => 667, 'T' => 610, 'U' => 722, 'V' => 667, 'W' => 944,
            'X' => 667, 'Y' => 667, 'Z' => 610, '[' => 389, '\\' => 278, ']' => 389, '^' => 556, '_' => 556,
            'a' => 556, 'b' => 610, 'c' => 556, 'd' => 610, 'e' => 556, 'f' => 333, 'g' => 610, 'h' => 610,
            'i' => 278, 'j' => 278, 'k' => 556, 'l' => 278, 'm' => 889, 'n' => 610, 'o' => 610, 'p' => 610,
            'q' => 610, 'r' => 389, 's' => 556, 't' => 333, 'u' => 610, 'v' => 556, 'w' => 778, 'x' => 556,
            'y' => 556, 'z' => 500,
        ];

        $totalUnits = 0;
        $len = strlen($text);
        for ($i = 0; $i < $len; $i++) {
            $char = $text[$i];
            $totalUnits += $widths[$char] ?? 600;
        }

        return ($totalUnits * $fontSize) / 1000.0;
    }

    /**
     * Resolve preset or coordinate percent into exact points on page.
     *
     * @param  list<array<string, mixed>>  $blocks
     * @return array{x: float, y: float}
     */
    protected function resolveCoordinatesForPage(
        string $preset,
        float $xPercent,
        float $yPercent,
        float $pageWidth,
        float $pageHeight,
        float $stampWidth,
        float $stampHeight,
        array $blocks = []
    ): array {
        switch ($preset) {
            case 'top_left':
                return [
                    'x' => 24.0,
                    'y' => 24.0,
                ];

            case 'bottom_left':
                return [
                    'x' => $pageWidth * 0.08,
                    'y' => $pageHeight - $stampHeight - ($pageHeight * 0.08),
                ];

            case 'bottom_center':
                return [
                    'x' => ($pageWidth - $stampWidth) / 2.0,
                    'y' => $pageHeight - $stampHeight - ($pageHeight * 0.08),
                ];

            case 'signature_area':
                // Try to detect signature keywords in text layout as starting point
                $sigCoords = $this->detectSignaturePosition($blocks, $pageWidth, $pageHeight, $stampWidth, $stampHeight);
                if ($sigCoords !== null) {
                    return $sigCoords;
                }

                // Default starting point for signature area: bottom right area
                return [
                    'x' => max(10.0, $pageWidth - $stampWidth - ($pageWidth * 0.10)),
                    'y' => max(10.0, $pageHeight - $stampHeight - ($pageHeight * 0.12)),
                ];

            case 'custom':
            default:
                return [
                    'x' => ($xPercent / 100.0) * $pageWidth,
                    'y' => ($yPercent / 100.0) * $pageHeight,
                ];
        }
    }

    /**
     * Attempt heuristic detection of signature area keywords.
     *
     * @param  list<array<string, mixed>>  $blocks
     * @return array{x: float, y: float}|null
     */
    protected function detectSignaturePosition(
        array $blocks,
        float $pageWidth,
        float $pageHeight,
        float $stampWidth,
        float $stampHeight
    ): ?array {
        $keywords = ['notaris', 'penghadap', 'tanda tangan', 'pihak pertama', 'pihak kedua', 'saksi'];

        foreach ($blocks as $block) {
            foreach ($block['lines'] ?? [] as $line) {
                $text = strtolower($line['text'] ?? '');
                foreach ($keywords as $kw) {
                    if (strpos($text, $kw) !== false) {
                        // Place stamp slightly below or aligned with keyword
                        $lineX = (float) $line['xMin'];
                        $lineY = (float) $line['yMax'];

                        // If keyword is in the lower 40% of the page, place near it
                        if ($lineY > ($pageHeight * 0.50)) {
                            $targetX = min($pageWidth - $stampWidth - 10.0, max(10.0, $lineX));
                            $targetY = min($pageHeight - $stampHeight - 10.0, $lineY + 15.0);

                            return ['x' => $targetX, 'y' => $targetY];
                        }
                    }
                }
            }
        }

        return null;
    }
}
