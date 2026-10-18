<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;

class DocxConversionAndRenderingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected string $sampleDocxPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();

        // Generate a valid minimal DOCX file with legal text
        $this->sampleDocxPath = storage_path('app/private/sample_legal_'.uniqid().'.docx');
        $this->createMinimalDocx($this->sampleDocxPath);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->sampleDocxPath)) {
            @unlink($this->sampleDocxPath);
        }
        parent::tearDown();
    }

    public function test_docx_conversion_and_end_to_end_pipeline(): void
    {
        $this->assertFileExists($this->sampleDocxPath);

        $uploadedFile = new UploadedFile(
            $this->sampleDocxPath,
            'Akta_Pendirian_PT.docx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            null,
            true
        );

        $response = $this->actingAs($this->user)->post(route('documents.store'), [
            'document' => $uploadedFile,
            'title' => 'Akta Pendirian PT Mandiri',
        ]);

        $response->assertSessionHasNoErrors();

        $document = $this->user->documents()->latest()->first();
        $this->assertNotNull($document);
        $this->assertEquals('ready', $document->status, 'Dokumen DOCX harus berhasil dikonversi dan status ready.');
        $this->assertEquals('docx', $document->source_type);
        $this->assertGreaterThan(0, $document->page_count);

        $workingPdfPath = $document->getPdfAbsolutePath();
        $this->assertFileExists($workingPdfPath, 'Working PDF hasil konversi LibreOffice harus ada.');

        // Verify layout extraction
        $layout = $document->layout_data;
        $this->assertNotEmpty($layout['pages']);
        $this->assertFalse($layout['is_scanned'], 'Dokumen DOCX yang dikonversi harus memiliki layer teks digital.');

        // Test generation of final PDF
        $generateResponse = $this->actingAs($this->user)->postJson(route('documents.generate', $document), [
            'draft_lines' => [
                'enabled' => true,
                'color' => '#DC2626',
                'offset' => 8.0,
            ],
            'stamp' => [
                'notary_name' => 'ROBERT, S.H., M.Kn.',
                'city_title' => 'NOTARIS KOTA DENPASAR',
                'preset' => 'signature_area',
            ],
        ]);

        $generateResponse->assertOk()->assertJsonPath('success', true);

        $document->refresh();
        $this->assertTrue($document->hasFinalPdf());

        $finalPdfPath = $document->getFinalPdfAbsolutePath();
        $this->assertFileExists($finalPdfPath);
        $this->assertGreaterThan(1000, filesize($finalPdfPath));

        // Clean up generated files
        if (file_exists($document->getOriginalAbsolutePath())) {
            @unlink($document->getOriginalAbsolutePath());
        }
        if (file_exists($workingPdfPath)) {
            @unlink($workingPdfPath);
        }
        if (file_exists($finalPdfPath)) {
            @unlink($finalPdfPath);
        }
    }

    /**
     * Helper to build a valid minimal OpenXML DOCX archive.
     */
    protected function createMinimalDocx(string $path): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Cannot create test docx zip');
        }

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>';

        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>';

        $documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p>
      <w:r>
        <w:rPr><w:b/><w:sz w:val="28"/></w:rPr>
        <w:t>AKTA PENDIRIAN PERSEROAN TERBATAS</w:t>
      </w:r>
    </w:p>
    <w:p>
      <w:r>
        <w:t>Nomor: 01.-</w:t>
      </w:r>
    </w:p>
    <w:p>
      <w:r>
        <w:t>Pada hari ini, Senin, tanggal 18 Oktober 2026, berhadapan dengan saya, ROBERT, Sarjana Hukum, Magister Kenotariatan, Notaris di Kota Denpasar, dengan dihadiri para saksi:</w:t>
      </w:r>
    </w:p>
    <w:p>
      <w:r>
        <w:t>1. Tuan ANDI, bertempat tinggal di Denpasar, pemegang KTP Nomor 517101001;</w:t>
      </w:r>
    </w:p>
    <w:p>
      <w:r>
        <w:t>2. Nyonya DEWI, bertempat tinggal di Denpasar, pemegang KTP Nomor 517101002;</w:t>
      </w:r>
    </w:p>
    <w:p>
      <w:r>
        <w:t>Para penghadap menyatakan dengan ini mendirikan perseroan terbatas dengan ketentuan anggaran dasar.</w:t>
      </w:r>
    </w:p>
    <w:p>
      <w:r>
        <w:t>DEMIKIANLAH AKTA INI</w:t>
      </w:r>
    </w:p>
    <w:p>
      <w:r>
        <w:t>Dibuat dan diresmikan di Kota Denpasar pada tanggal tersebut di atas.</w:t>
      </w:r>
    </w:p>
    <w:p>
      <w:r>
        <w:t>Tanda tangan Notaris:</w:t>
      </w:r>
    </w:p>
  </w:body>
</w:document>';

        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $rels);
        $zip->addFromString('word/document.xml', $documentXml);
        $zip->close();
    }
}
