<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use setasign\Fpdi\Fpdi;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class DocumentConversionService
{
    /**
     * Convert input document (DOCX or PDF) to working PDF and return metadata.
     *
     * @param  string  $inputPath  Absolute path to original uploaded file
     * @param  string  $sourceType  'pdf' or 'docx'
     * @param  string  $outputPdfPath  Absolute path where normalized working PDF should be placed
     * @return array{page_count: int, is_encrypted: bool}
     *
     * @throws RuntimeException
     */
    public function convertToPdf(string $inputPath, string $sourceType, string $outputPdfPath): array
    {
        if (! file_exists($inputPath)) {
            throw new RuntimeException("File sumber tidak ditemukan di: {$inputPath}");
        }

        File::ensureDirectoryExists(dirname($outputPdfPath));

        if (strtolower($sourceType) === 'docx') {
            $this->convertDocxToPdf($inputPath, $outputPdfPath);
        } elseif (strtolower($sourceType) === 'pdf') {
            $this->validateAndCopyPdf($inputPath, $outputPdfPath);
        } else {
            throw new RuntimeException("Format dokumen '{$sourceType}' tidak didukung. Harap unggah file DOCX atau PDF.");
        }

        // Validate the resulting PDF
        $metadata = $this->inspectPdf($outputPdfPath);

        if ($metadata['is_encrypted']) {
            if (file_exists($outputPdfPath)) {
                @unlink($outputPdfPath);
            }
            throw new RuntimeException('Dokumen PDF terenkripsi atau terproteksi kata sandi. Silakan unggah dokumen yang tidak terkunci.');
        }

        $maxPages = (int) config('notary.max_page_limit', 100);
        if ($metadata['page_count'] > $maxPages) {
            if (file_exists($outputPdfPath)) {
                @unlink($outputPdfPath);
            }
            throw new RuntimeException("Dokumen memiliki {$metadata['page_count']} halaman, melebihi batas maksimal {$maxPages} halaman.");
        }

        if ($metadata['page_count'] <= 0) {
            if (file_exists($outputPdfPath)) {
                @unlink($outputPdfPath);
            }
            throw new RuntimeException('Dokumen tidak memiliki halaman yang valid atau file PDF rusak.');
        }

        return $metadata;
    }

    /**
     * Convert DOCX to PDF using LibreOffice headless.
     */
    protected function convertDocxToPdf(string $docxPath, string $outputPdfPath): void
    {
        $libreOfficeBinary = config('notary.libreoffice_binary');
        if (! file_exists($libreOfficeBinary)) {
            // Check fallback standard paths
            $fallbacks = [
                'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
                'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
                'soffice',
                'libreoffice',
            ];
            $found = false;
            foreach ($fallbacks as $fallback) {
                if (file_exists($fallback)) {
                    $libreOfficeBinary = $fallback;
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                throw new RuntimeException("LibreOffice tidak ditemukan pada: {$libreOfficeBinary}. Pastikan LibreOffice terinstal untuk konversi DOCX.");
            }
        }

        $tempOutDir = dirname($outputPdfPath).DIRECTORY_SEPARATOR.'temp_conv_'.uniqid();
        File::ensureDirectoryExists($tempOutDir);

        $timeout = (int) config('notary.process_timeout_seconds', 60);

        $process = new Process([
            $libreOfficeBinary,
            '--headless',
            '--invisible',
            '--nologo',
            '--nodefault',
            '--nofirststartwizard',
            '--convert-to',
            'pdf:writer_pdf_Export',
            '--outdir',
            $tempOutDir,
            $docxPath,
        ]);

        $process->setTimeout($timeout);

        try {
            $process->run();
        } catch (ProcessTimedOutException $e) {
            File::deleteDirectory($tempOutDir);
            throw new RuntimeException('Proses konversi dokumen DOCX melebihi batas waktu (timeout). Silakan periksa ukuran dokumen Anda.');
        } catch (Exception $e) {
            File::deleteDirectory($tempOutDir);
            Log::error('Gagal menjalankan LibreOffice konversi: '.$e->getMessage());
            throw new RuntimeException('Gagal mengeksekusi konversi dokumen: '.$e->getMessage());
        }

        if (! $process->isSuccessful()) {
            File::deleteDirectory($tempOutDir);
            $errOutput = trim($process->getErrorOutput() ?: $process->getOutput());
            Log::error('LibreOffice conversion error: '.$errOutput);
            throw new RuntimeException('Konversi DOCX ke PDF gagal. Detail: '.($errOutput ?: 'Unknown error.'));
        }

        // Locate converted PDF in tempOutDir
        $convertedFiles = glob($tempOutDir.DIRECTORY_SEPARATOR.'*.pdf');
        if (empty($convertedFiles) || ! file_exists($convertedFiles[0])) {
            File::deleteDirectory($tempOutDir);
            throw new RuntimeException('File hasil konversi PDF tidak ditemukan.');
        }

        // Move to destination
        if (file_exists($outputPdfPath)) {
            @unlink($outputPdfPath);
        }
        rename($convertedFiles[0], $outputPdfPath);
        File::deleteDirectory($tempOutDir);
    }

    /**
     * Validate existing PDF and copy to working destination.
     */
    protected function validateAndCopyPdf(string $inputPdfPath, string $outputPdfPath): void
    {
        // Simple magic byte check (%PDF-)
        $handle = @fopen($inputPdfPath, 'rb');
        if (! $handle) {
            throw new RuntimeException('Tidak dapat membuka file PDF untuk dibaca.');
        }
        $header = fread($handle, 1024);
        fclose($handle);

        if (strpos($header, '%PDF-') === false) {
            throw new RuntimeException('File yang diunggah bukan file PDF yang valid.');
        }

        if (! copy($inputPdfPath, $outputPdfPath)) {
            throw new RuntimeException('Gagal menyalin file PDF ke penyimpanan kerja.');
        }
    }

    /**
     * Inspect PDF page count and encryption status.
     *
     * @return array{page_count: int, is_encrypted: bool}
     */
    public function inspectPdf(string $pdfPath): array
    {
        $pdfinfoBinary = config('notary.poppler_pdfinfo');

        if (file_exists($pdfinfoBinary)) {
            $process = new Process([$pdfinfoBinary, $pdfPath]);
            $process->setTimeout(15);
            $process->run();

            $output = $process->getOutput();
            $errorOutput = $process->getErrorOutput();

            // Check encrypted
            $isEncrypted = false;
            if (preg_match('/Encrypted:\s+yes/i', $output) || stripos($errorOutput, 'encrypted') !== false || stripos($errorOutput, 'password') !== false) {
                $isEncrypted = true;
            }

            // Check pages
            $pages = 0;
            if (preg_match('/Pages:\s+(\d+)/i', $output, $matches)) {
                $pages = (int) $matches[1];
            }

            if ($pages > 0 || $isEncrypted) {
                return [
                    'page_count' => $pages,
                    'is_encrypted' => $isEncrypted,
                ];
            }
        }

        // Fallback inspection via FPDI
        try {
            $fpdi = new Fpdi;
            $pageCount = $fpdi->setSourceFile($pdfPath);

            return [
                'page_count' => (int) $pageCount,
                'is_encrypted' => false,
            ];
        } catch (Exception $e) {
            $msg = strtolower($e->getMessage());
            $isEncrypted = (strpos($msg, 'encrypted') !== false || strpos($msg, 'password') !== false);

            return [
                'page_count' => 0,
                'is_encrypted' => $isEncrypted,
            ];
        }
    }
}
