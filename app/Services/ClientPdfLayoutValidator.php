<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Validation\ValidationException;
use setasign\Fpdi\Fpdi;
use Throwable;

final class ClientPdfLayoutValidator
{
    private const MAX_BLOCKS_PER_PAGE = 300;
    private const MAX_LINES_PER_PAGE = 1800;
    private const MAX_TOTAL_LINES = 25000;

    public function validate(array $layout, Document $document): array
    {
        $expected = (int) $document->page_count;
        $maxPages = (int) config('notary.max_page_limit', 100);
        $pages = $layout['pages'] ?? null;

        if (
            $expected < 1 ||
            $expected > $maxPages ||
            ! is_int($layout['page_count'] ?? null) ||
            $layout['page_count'] !== $expected ||
            ! is_array($pages) ||
            count($pages) !== $expected
        ) {
            $this->reject('Jumlah halaman layout tidak sesuai dengan dokumen.');
        }

        $sourcePath = $document->getPdfAbsolutePath();

        if (! is_file($sourcePath)) {
            $this->reject('File PDF untuk validasi tidak ditemukan.');
        }

        try {
            $pdf = new Fpdi('P', 'pt');
            $actualCount = $pdf->setSourceFile($sourcePath);
        } catch (Throwable $e) {
            $this->reject('Struktur halaman PDF tidak dapat diverifikasi.');
        }

        if ($actualCount !== $expected) {
            $this->reject('Jumlah halaman PDF berbeda dari metadata dokumen.');
        }

        $normalizedPages = [];
        $totalLines = 0;

        for ($pageNo = 1; $pageNo <= $expected; $pageNo++) {
            $page = $pages[$pageNo] ?? null;

            if (
                ! is_array($page) ||
                ! is_int($page['page_number'] ?? null) ||
                $page['page_number'] !== $pageNo
            ) {
                $this->reject("Halaman {$pageNo} tidak valid.");
            }

            try {
                $template = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($template);
            } catch (Throwable $e) {
                $this->reject("Ukuran halaman {$pageNo} tidak dapat diverifikasi.");
            }

            $width = (float) $size['width'];
            $height = (float) $size['height'];

            $submittedWidth = $this->number($page['width'] ?? null);
            $submittedHeight = $this->number($page['height'] ?? null);

            if (
                $submittedWidth === null ||
                $submittedHeight === null ||
                abs($submittedWidth - $width) > 2.0 ||
                abs($submittedHeight - $height) > 2.0
            ) {
                $this->reject("Ukuran halaman {$pageNo} tidak sesuai PDF.");
            }

            $blocks = $page['blocks'] ?? null;

            if (
                ! is_array($blocks) ||
                ! array_is_list($blocks) ||
                count($blocks) > self::MAX_BLOCKS_PER_PAGE
            ) {
                $this->reject("Struktur blok halaman {$pageNo} tidak valid.");
            }

            $cleanBlocks = [];
            $pageLineCount = 0;

            foreach ($blocks as $block) {
                if (
                    ! is_array($block) ||
                    ! is_array($block['lines'] ?? null) ||
                    ! array_is_list($block['lines'])
                ) {
                    $this->reject("Struktur baris halaman {$pageNo} tidak valid.");
                }

                $cleanLines = [];
                $bounds = [INF, INF, -INF, -INF];

                foreach ($block['lines'] as $line) {
                    $pageLineCount++;
                    $totalLines++;

                    if (
                        $pageLineCount > self::MAX_LINES_PER_PAGE ||
                        $totalLines > self::MAX_TOTAL_LINES
                    ) {
                        $this->reject('Terlalu banyak baris teks dalam layout.');
                    }

                    if (
                        ! is_array($line) ||
                        ! is_string($line['text'] ?? null)
                    ) {
                        $this->reject("Teks pada halaman {$pageNo} tidak valid.");
                    }

                    $text = trim($line['text']);

                    if ($text === '' || mb_strlen($text) > 2048) {
                        $this->reject("Panjang teks halaman {$pageNo} tidak valid.");
                    }

                    $rect = $this->rectangle(
                        $line,
                        $width,
                        $height,
                        $pageNo
                    );

                    $bounds[0] = min($bounds[0], $rect['xMin']);
                    $bounds[1] = min($bounds[1], $rect['yMin']);
                    $bounds[2] = max($bounds[2], $rect['xMax']);
                    $bounds[3] = max($bounds[3], $rect['yMax']);

                    $cleanLines[] = $rect + [
                        'text' => $text,
                        'is_list_item' => ($line['is_list_item'] ?? false) === true,
                        'list_prefix' => null,
                        'is_header' => ($line['is_header'] ?? false) === true,
                        'is_footer' => ($line['is_footer'] ?? false) === true,
                    ];
                }

                if ($cleanLines !== []) {
                    $cleanBlocks[] = [
                        'xMin' => $bounds[0],
                        'yMin' => $bounds[1],
                        'xMax' => $bounds[2],
                        'yMax' => $bounds[3],
                        'lines' => $cleanLines,
                    ];
                }
            }

            $normalizedPages[$pageNo] = [
                'page_number' => $pageNo,
                'width' => round($width, 2),
                'height' => round($height, 2),
                'orientation' => $width > $height ? 'L' : 'P',
                'word_count' => $pageLineCount,
                'line_count' => $pageLineCount,
                'is_scanned' => $pageLineCount === 0,
                'blocks' => $cleanBlocks,
            ];
        }

        if ($totalLines === 0) {
            $this->reject('PDF tidak memiliki lapisan teks untuk garis otomatis.');
        }

        return [
            'page_count' => $expected,
            'is_scanned' => false,
            'fallback_mode' => false,
            'pages' => $normalizedPages,
        ];
    }

    private function rectangle(
        array $line,
        float $width,
        float $height,
        int $pageNo
    ): array {
        $xs = $this->number($line['xMin'] ?? null);
        $xe = $this->number($line['xMax'] ?? null);
        $ys = $this->number($line['yMin'] ?? null);
        $ye = $this->number($line['yMax'] ?? null);

        if (
            $xs === null || $xe === null ||
            $ys === null || $ye === null ||
            $xs < -3 || $ys < -3 ||
            $xe > $width + 3 ||
            $ye > $height + 3 ||
            $xe <= $xs || $ye <= $ys
        ) {
            $this->reject(
                "Koordinat teks halaman {$pageNo} berada di luar batas."
            );
        }

        $xs = max(0.0, $xs);
        $ys = max(0.0, $ys);
        $xe = min($width, $xe);
        $ye = min($height, $ye);

        if ($xe <= $xs || $ye <= $ys) {
            $this->reject(
                "Koordinat teks halaman {$pageNo} tidak valid."
            );
        }

        return [
            'xMin' => round($xs, 2),
            'yMin' => round($ys, 2),
            'xMax' => round($xe, 2),
            'yMax' => round($ye, 2),
        ];
    }

    private function number(mixed $value): ?float
    {
        if (! is_int($value) && ! is_float($value)) {
            return null;
        }

        $number = (float) $value;

        return is_finite($number) ? $number : null;
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages([
            'layout_data' => $message,
        ]);
    }
}
