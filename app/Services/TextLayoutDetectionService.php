<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use SimpleXMLElement;
use Symfony\Component\Process\Process;

class TextLayoutDetectionService
{
    /**
     * Extract layout data and bounding boxes for all pages of a PDF.
     *
     * @return array{
     *     page_count: int,
     *     is_scanned: bool,
     *     pages: array<int, array{
     *         page_number: int,
     *         width: float,
     *         height: float,
     *         orientation: string,
     *         word_count: int,
     *         line_count: int,
     *         is_scanned: bool,
     *         blocks: array<int, array{
     *             xMin: float,
     *             yMin: float,
     *             xMax: float,
     *             yMax: float,
     *             lines: array<int, array{
     *                 xMin: float,
     *                 yMin: float,
     *                 xMax: float,
     *                 yMax: float,
     *                 text: string,
     *                 is_list_item: bool,
     *                 list_prefix: string|null,
     *                 is_header: bool,
     *                 is_footer: bool
     *             }>
     *         }>
     *     }>
     * }
     */
    public function extractLayout(string $pdfPath): array
    {
        if (! file_exists($pdfPath)) {
            throw new RuntimeException("File PDF tidak ditemukan di: {$pdfPath}");
        }

        $pdftotextBinary = config('notary.poppler_pdftotext');
        if (! file_exists($pdftotextBinary)) {
            throw new RuntimeException("Poppler pdftotext tidak ditemukan pada: {$pdftotextBinary}");
        }

        $tempHtmlPath = dirname($pdfPath).DIRECTORY_SEPARATOR.'layout_'.uniqid().'.html';

        $process = new Process([
            $pdftotextBinary,
            '-bbox-layout',
            $pdfPath,
            $tempHtmlPath,
        ]);

        $timeout = (int) config('notary.process_timeout_seconds', 60);
        $process->setTimeout($timeout);
        $process->run();

        if (! $process->isSuccessful() || ! file_exists($tempHtmlPath)) {
            $err = trim($process->getErrorOutput() ?: $process->getOutput());
            if (file_exists($tempHtmlPath)) {
                @unlink($tempHtmlPath);
            }
            Log::error("pdftotext execution failed: {$err}");
            throw new RuntimeException("Gagal mengekstrak layout koordinat dokumen PDF. {$err}");
        }

        $xmlContent = file_get_contents($tempHtmlPath);
        @unlink($tempHtmlPath);

        return $this->parseBboxLayoutXml($xmlContent);
    }

    /**
     * Parse the XHTML/XML output from pdftotext -bbox-layout.
     */
    protected function parseBboxLayoutXml(string $xmlContent): array
    {
        // Suppress libxml errors and parse
        libxml_use_internal_errors(true);

        // Remove DOCTYPE and xmlns namespace declarations to ensure xpath queries succeed across XML parsers
        $cleanXml = preg_replace('/<!DOCTYPE[^>]*>/i', '', $xmlContent);
        $cleanXml = preg_replace('/xmlns="[^"]*"/i', '', $cleanXml);

        try {
            $xml = new SimpleXMLElement($cleanXml);
        } catch (Exception $e) {
            libxml_clear_errors();
            Log::error('XML Parse Error in pdftotext output: '.$e->getMessage());
            throw new RuntimeException('Gagal memproses data layout XML dari PDF.');
        }

        libxml_clear_errors();

        $pages = [];
        $pageNumber = 1;
        $totalWordCount = 0;

        $headerMarginPercent = (float) config('notary.default_draft_lines.header_margin_percent', 7.0);
        $footerMarginPercent = (float) config('notary.default_draft_lines.footer_margin_percent', 7.0);

        // The XML structure has <page> elements inside <doc> or <body><doc>
        $pageNodes = $xml->xpath('//page');

        if (empty($pageNodes)) {
            return [
                'page_count' => 0,
                'is_scanned' => true,
                'pages' => [],
            ];
        }

        foreach ($pageNodes as $pageNode) {
            $pageWidth = (float) ($pageNode['width'] ?? 595.28);
            $pageHeight = (float) ($pageNode['height'] ?? 841.89);
            $orientation = ($pageWidth > $pageHeight) ? 'L' : 'P';

            $headerThreshold = ($pageHeight * ($headerMarginPercent / 100.0));
            $footerThreshold = ($pageHeight * (1.0 - ($footerMarginPercent / 100.0)));

            $blocks = [];
            $pageWordCount = 0;
            $pageLineCount = 0;

            // Find all blocks within this page
            $blockNodes = $pageNode->xpath('.//block');

            foreach ($blockNodes as $blockNode) {
                $blockXMin = (float) ($blockNode['xMin'] ?? 0);
                $blockYMin = (float) ($blockNode['yMin'] ?? 0);
                $blockXMax = (float) ($blockNode['xMax'] ?? 0);
                $blockYMax = (float) ($blockNode['yMax'] ?? 0);

                $lines = [];
                $lineNodes = $blockNode->xpath('.//line');

                foreach ($lineNodes as $lineNode) {
                    $lineXMin = (float) ($lineNode['xMin'] ?? 0);
                    $lineYMin = (float) ($lineNode['yMin'] ?? 0);
                    $lineXMax = (float) ($lineNode['xMax'] ?? 0);
                    $lineYMax = (float) ($lineNode['yMax'] ?? 0);

                    // Collect text from words
                    $words = [];
                    $wordNodes = $lineNode->xpath('.//word');
                    foreach ($wordNodes as $wordNode) {
                        $wordText = trim((string) $wordNode);
                        if ($wordText !== '') {
                            $words[] = $wordText;
                            $pageWordCount++;
                            $totalWordCount++;
                        }
                    }

                    $lineText = implode(' ', $words);
                    if ($lineText === '') {
                        continue;
                    }

                    $pageLineCount++;

                    // Determine if list item / bullet
                    $listInfo = $this->detectListPattern($lineText);

                    // Determine if header or footer based on vertical position
                    $isHeader = ($lineYMax <= $headerThreshold);
                    $isFooter = ($lineYMin >= $footerThreshold);

                    $lines[] = [
                        'xMin' => round($lineXMin, 2),
                        'yMin' => round($lineYMin, 2),
                        'xMax' => round($lineXMax, 2),
                        'yMax' => round($lineYMax, 2),
                        'text' => $lineText,
                        'is_list_item' => $listInfo['is_list'],
                        'list_prefix' => $listInfo['prefix'],
                        'is_header' => $isHeader,
                        'is_footer' => $isFooter,
                    ];
                }

                if (! empty($lines)) {
                    $blocks[] = [
                        'xMin' => round($blockXMin, 2),
                        'yMin' => round($blockYMin, 2),
                        'xMax' => round($blockXMax, 2),
                        'yMax' => round($blockYMax, 2),
                        'lines' => $lines,
                    ];
                }
            }

            $isPageScanned = ($pageWordCount < 5);

            $pages[$pageNumber] = [
                'page_number' => $pageNumber,
                'width' => round($pageWidth, 2),
                'height' => round($pageHeight, 2),
                'orientation' => $orientation,
                'word_count' => $pageWordCount,
                'line_count' => $pageLineCount,
                'is_scanned' => $isPageScanned,
                'blocks' => $blocks,
            ];

            $pageNumber++;
        }

        $isDocScanned = ($totalWordCount < (count($pages) * 5));

        return [
            'page_count' => count($pages),
            'is_scanned' => $isDocScanned,
            'pages' => $pages,
        ];
    }

    /**
     * Detect list items (numbers, letters, roman numerals, bullets).
     *
     * @return array{is_list: bool, prefix: string|null}
     */
    protected function detectListPattern(string $text): array
    {
        $text = trim($text);

        // Numbered list: "1.", "1)", "(1)", "1.1.", "1.1"
        if (preg_match('/^(\d+(\.\d+)*[\.\)]|\(\d+\))\s+/u', $text, $matches)) {
            return ['is_list' => true, 'prefix' => trim($matches[1])];
        }

        // Letter list: "a.", "A.", "a)", "A)", "(a)", "(A)"
        if (preg_match('/^([a-zA-Z][\.\)]|\([a-zA-Z]\))\s+/u', $text, $matches)) {
            return ['is_list' => true, 'prefix' => trim($matches[1])];
        }

        // Roman numerals: "i.", "ii.", "III.", "(iv)"
        if (preg_match('/^([ivxIVX]+[\.\)]|\([ivxIVX]+\))\s+/u', $text, $matches)) {
            return ['is_list' => true, 'prefix' => trim($matches[1])];
        }

        // Bullets: "-", "•", "*", "–", "—"
        if (preg_match('/^([•\-\*–—]|(\xC2\x95))\s+/u', $text, $matches)) {
            return ['is_list' => true, 'prefix' => trim($matches[1])];
        }

        // Indonesian legal keywords that start sub-items: "Pertama:", "Kedua:", "Ketiga:"
        if (preg_match('/^(Pertama|Kedua|Ketiga|Keempat|Kelima|Keenam|Ketujuh|Kedelapan|Kesembilan|Kesepuluh)\s*:/ui', $text, $matches)) {
            return ['is_list' => true, 'prefix' => trim($matches[0])];
        }

        return ['is_list' => false, 'prefix' => null];
    }
}
