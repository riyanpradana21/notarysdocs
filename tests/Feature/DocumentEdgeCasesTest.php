<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DocumentConversionService;
use App\Services\DraftLineService;
use App\Services\PdfRenderingService;
use App\Services\StampPlacementService;
use App\Services\TextLayoutDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use setasign\Fpdi\Fpdi;
use Tests\TestCase;

class DocumentEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_rejects_corrupted_pdf_file(): void
    {
        $conversionService = app(DocumentConversionService::class);
        $tempInput = storage_path('app/private/corrupt_'.uniqid().'.pdf');
        $tempOutput = storage_path('app/private/out_'.uniqid().'.pdf');

        file_put_contents($tempInput, 'THIS IS NOT A VALID PDF FILE HEADER');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('File yang diunggah bukan file PDF yang valid');

        try {
            $conversionService->convertToPdf($tempInput, 'pdf', $tempOutput);
        } finally {
            if (file_exists($tempInput)) {
                @unlink($tempInput);
            }
            if (file_exists($tempOutput)) {
                @unlink($tempOutput);
            }
        }
    }

    public function test_detects_scanned_pdf_with_no_text_layer(): void
    {
        // Create a blank 1-page PDF without text
        $fpdf = new Fpdi('P', 'pt', 'A4');
        $fpdf->AddPage();
        // Do not add any text -> pure blank / scanned equivalent

        $tempPdf = storage_path('app/private/blank_'.uniqid().'.pdf');
        $fpdf->Output('F', $tempPdf);

        try {
            $layoutService = app(TextLayoutDetectionService::class);
            $layoutData = $layoutService->extractLayout($tempPdf);

            $this->assertTrue($layoutData['is_scanned'], 'Dokumen tanpa layer teks harus ditandai is_scanned = true.');
            $this->assertEquals(0, $layoutData['pages'][1]['word_count']);
        } finally {
            if (file_exists($tempPdf)) {
                @unlink($tempPdf);
            }
        }
    }

    public function test_exact_coordinate_equality_between_preview_and_final_rendering(): void
    {
        // 1. Setup multi-line test document
        $fpdf = new Fpdi('P', 'pt', 'A4');
        $fpdf->AddPage();
        $fpdf->SetFont('Helvetica', '', 12);
        $fpdf->SetXY(60, 120);
        $fpdf->MultiCell(480, 18, "Akta Notaris Nomor 10\nPada hari ini hadir Tuan Robert di Denpasar.\n1. Penghadap Pertama;\n2. Penghadap Kedua;");

        $tempPdf = storage_path('app/private/coord_test_'.uniqid().'.pdf');
        $finalPdf = storage_path('app/private/coord_final_'.uniqid().'.pdf');
        $fpdf->Output('F', $tempPdf);

        try {
            $layoutService = app(TextLayoutDetectionService::class);
            $layoutData = $layoutService->extractLayout($tempPdf);

            $draftConfig = [
                'enabled' => true,
                'color' => '#DC2626',
                'thickness' => 1.5,
                'offset' => 10.0,
                'paragraph_threshold' => 1.6,
                'ignore_headers_footers' => true,
                'target_pages' => 'all_pages',
            ];

            $stampConfig = [
                'notary_name' => 'ROBERT, S.H., M.Kn.',
                'city_title' => 'NOTARIS KOTA DENPASAR',
                'color' => '#DC2626',
                'preset' => 'bottom_center',
                'line_length' => 200.0,
                'target_pages' => 'first_page',
            ];

            // Calculate preview coordinates
            $draftService = app(DraftLineService::class);
            $previewDraftLines = $draftService->calculateDraftLines($layoutData, $draftConfig);

            $stampService = app(StampPlacementService::class);
            $previewStamp = $stampService->calculatePlacements($stampConfig, $layoutData['pages']);

            // Validate preview coordinates
            $this->assertNotEmpty($previewDraftLines[1]['segments']);
            $firstSeg = $previewDraftLines[1]['segments'][0];
            $this->assertGreaterThan(0, $firstSeg['x']);
            $this->assertGreaterThan(0, $firstSeg['y_start']);
            $this->assertGreaterThan($firstSeg['y_start'], $firstSeg['y_end']);

            $firstStamp = $previewStamp['placements'][1];
            $this->assertTrue($firstStamp['is_active']);
            $this->assertEquals(200.0, $previewStamp['stamp_dimensions']['width']);

            // Render final PDF using exactly the same services
            $renderingService = app(PdfRenderingService::class);
            $renderedPath = $renderingService->renderFinalPdf($tempPdf, $finalPdf, $layoutData, $draftConfig, $stampConfig);

            $this->assertFileExists($renderedPath);
            $this->assertGreaterThan(filesize($tempPdf), filesize($renderedPath), 'PDF final yang telah diberi garis draft dan stempel harus bertambah ukurannya.');
        } finally {
            if (file_exists($tempPdf)) {
                @unlink($tempPdf);
            }
            if (file_exists($finalPdf)) {
                @unlink($finalPdf);
            }
        }
    }
}
