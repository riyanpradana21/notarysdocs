<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use App\Services\PdfRenderingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use Tests\TestCase;

class DocumentRenderingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Document $document;

    protected string $workingPdfAbsPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();

        // Create a real valid sample PDF using FPDI/FPDF
        $fpdf = new Fpdi('P', 'pt', 'A4');
        $fpdf->AddPage();
        $fpdf->SetFont('Helvetica', 'B', 14);
        $fpdf->Cell(0, 20, 'AKTA NOTARIS CONTOH PENGUJIAN', 0, 1, 'C');
        $fpdf->SetFont('Helvetica', '', 11);
        $fpdf->MultiCell(0, 16, "Pada hari ini, Senin tanggal 18 Oktober 2026, hadir dihadapan saya, ROBERT, S.H., M.Kn., Notaris di Kota Denpasar.\n1. Tuan A, KTP 51710101;\n2. Nyonya B, KTP 51710102;");

        $uuid = 'test-render-sample-uuid';
        $relativePdfPath = "documents/working/{$uuid}.pdf";
        $this->workingPdfAbsPath = Storage::disk('local')->path($relativePdfPath);

        if (! file_exists(dirname($this->workingPdfAbsPath))) {
            mkdir(dirname($this->workingPdfAbsPath), 0777, true);
        }
        $fpdf->Output('F', $this->workingPdfAbsPath);

        $this->document = Document::create([
            'uuid' => $uuid,
            'user_id' => $this->user->id,
            'title' => 'Akta Pengujian Render',
            'original_filename' => 'sample_akta.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => filesize($this->workingPdfAbsPath),
            'source_type' => 'pdf',
            'status' => 'ready',
            'page_count' => 1,
            'original_path' => $relativePdfPath,
            'pdf_path' => $relativePdfPath,
            'layout_data' => [
                'page_count' => 1,
                'is_scanned' => false,
                'pages' => [
                    1 => [
                        'page_number' => 1,
                        'width' => 595.28,
                        'height' => 841.89,
                        'orientation' => 'P',
                        'blocks' => [
                            [
                                'lines' => [
                                    ['xMin' => 50.0, 'yMin' => 100.0, 'xMax' => 450.0, 'yMax' => 114.0, 'text' => 'Pada hari ini, hadir...', 'is_list_item' => false, 'is_header' => false, 'is_footer' => false],
                                    ['xMin' => 50.0, 'yMin' => 130.0, 'xMax' => 400.0, 'yMax' => 144.0, 'text' => '1. Tuan A', 'is_list_item' => true, 'is_header' => false, 'is_footer' => false],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'draft_line_config' => config('notary.default_draft_lines'),
            'stamp_config' => config('notary.default_stamp'),
        ]);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->workingPdfAbsPath)) {
            @unlink($this->workingPdfAbsPath);
        }
        $finalPdf = $this->document->getFinalPdfAbsolutePath();
        if ($finalPdf && file_exists($finalPdf)) {
            @unlink($finalPdf);
        }
        parent::tearDown();
    }

    public function test_updates_configuration_and_returns_recalculated_preview(): void
    {
        $response = $this->actingAs($this->user)->patchJson(route('documents.config', $this->document), [
            'draft_lines' => [
                'offset' => 14.0,
                'thickness' => 2.0,
            ],
            'stamp' => [
                'notary_name' => 'ROBERT, S.H., M.Kn.',
                'city_title' => 'NOTARIS KOTA DENPASAR',
                'preset' => 'bottom_center',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['draft_lines', 'stamp']);

        $this->document->refresh();
        $this->assertEquals(14.0, $this->document->draft_line_config['offset']);
        $this->assertEquals('bottom_center', $this->document->stamp_config['preset']);
    }

    public function test_generates_final_pdf_and_allows_download(): void
    {
        $response = $this->actingAs($this->user)->postJson(route('documents.generate', $this->document), [
            'draft_lines' => ['enabled' => true],
            'stamp' => ['preset' => 'signature_area'],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['download_url']);

        $this->document->refresh();
        $this->assertTrue($this->document->hasFinalPdf(), 'PDF final harus berhasil dibuat di penyimpanan privat.');

        // Test download
        $downloadResponse = $this->actingAs($this->user)->get(route('documents.download', $this->document));
        $downloadResponse->assertOk();
        $downloadResponse->assertHeader('content-type', 'application/pdf');
    }

    public function test_preview_config_matches_rendered_output_coordinates(): void
    {
        $renderingService = app(PdfRenderingService::class);
        $finalPdfRelative = "documents/final/{$this->document->uuid}_final.pdf";
        $finalPdfAbs = Storage::disk('local')->path($finalPdfRelative);

        if (! file_exists(dirname($finalPdfAbs))) {
            mkdir(dirname($finalPdfAbs), 0777, true);
        }

        $resultPath = $renderingService->renderFinalPdf(
            $this->workingPdfAbsPath,
            $finalPdfAbs,
            $this->document->layout_data,
            $this->document->draft_line_config,
            $this->document->stamp_config
        );

        $this->assertFileExists($resultPath);
        $this->assertGreaterThan(500, filesize($resultPath), 'File PDF final harus memiliki ukuran valid dan tidak kosong.');
    }
}
