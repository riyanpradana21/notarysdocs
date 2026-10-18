<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\DocumentConversionService;
use App\Services\DraftLineService;
use App\Services\PdfRenderingService;
use App\Services\StampPlacementService;
use App\Services\TextLayoutDetectionService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentController extends Controller
{
    public function __construct(
        protected DocumentConversionService $conversionService,
        protected TextLayoutDetectionService $layoutDetectionService,
        protected DraftLineService $draftLineService,
        protected StampPlacementService $stampPlacementService,
        protected PdfRenderingService $pdfRenderingService
    ) {}

    /**
     * Dashboard page with metrics and recent documents.
     */
    public function index(): View
    {
        $user = Auth::user();
        $totalDocs = $user->documents()->count();
        $readyDocs = $user->documents()->where('status', 'ready')->count();
        $processingDocs = $user->documents()->whereIn('status', ['uploaded', 'processing'])->count();
        $failedDocs = $user->documents()->where('status', 'failed')->count();

        $totalBytes = (int) $user->documents()->sum('file_size');
        $storageUsedFormatted = $this->formatBytes($totalBytes);

        $recentDocuments = $user->documents()
            ->latest()
            ->take(8)
            ->get();

        return view('dashboard', compact(
            'totalDocs',
            'readyDocs',
            'processingDocs',
            'failedDocs',
            'storageUsedFormatted',
            'recentDocuments'
        ));
    }

    /**
     * History list of all user documents with search and filtering.
     */
    public function history(Request $request): View
    {
        $user = Auth::user();
        $query = $user->documents()->latest();

        if ($request->filled('search')) {
            $search = '%'.trim($request->input('search')).'%';
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', $search)
                    ->orWhere('original_filename', 'like', $search);
            });
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('source_type') && $request->input('source_type') !== 'all') {
            $query->where('source_type', $request->input('source_type'));
        }

        $documents = $query->paginate(10)->withQueryString();

        return view('documents.history', compact('documents'));
    }

    /**
     * Show document upload form.
     */
    public function create(): View
    {
        return view('documents.upload');
    }

    /**
     * Handle document upload, conversion, and layout detection.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $maxKb = (int) config('notary.max_file_size_kb', 25600);

        $request->validate([
            'document' => [
                'required',
                'file',
                'max:'.$maxKb,
                'mimes:pdf,docx,doc',
            ],
            'title' => ['nullable', 'string', 'max:255'],
        ], [
            'document.required' => 'Silakan pilih file dokumen untuk diunggah.',
            'document.mimes' => 'Format file harus berupa DOCX atau PDF.',
            'document.max' => 'Ukuran file tidak boleh melebihi '.($maxKb / 1024).' MB.',
        ]);

        $file = $request->file('document');
        $originalFilename = $file->getClientOriginalName();
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = $file->getClientMimeType() ?: ($extension === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $fileSize = $file->getSize();

        $title = trim($request->input('title', '')) ?: pathinfo($originalFilename, PATHINFO_FILENAME);

        $sourceType = ($extension === 'pdf') ? 'pdf' : 'docx';

        // Secure storage using UUID
        $uuid = (string) Str::uuid();
        $originalRelativePath = "documents/originals/{$uuid}.{$extension}";
        $workingPdfRelativePath = "documents/working/{$uuid}.pdf";

        // Store original privately
        Storage::disk('local')->putFileAs('documents/originals', $file, "{$uuid}.{$extension}");

        $document = Document::create([
            'uuid' => $uuid,
            'user_id' => Auth::id(),
            'title' => $title,
            'original_filename' => $originalFilename,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'source_type' => $sourceType,
            'status' => 'processing',
            'original_path' => $originalRelativePath,
            'pdf_path' => $workingPdfRelativePath,
            'draft_line_config' => config('notary.default_draft_lines'),
            'stamp_config' => config('notary.default_stamp'),
        ]);

        // Process pipeline: Convert -> Extract Layout
        try {
            $inputAbsPath = Storage::disk('local')->path($originalRelativePath);
            $workingPdfAbsPath = Storage::disk('local')->path($workingPdfRelativePath);

            $meta = $this->conversionService->convertToPdf($inputAbsPath, $sourceType, $workingPdfAbsPath);
            $layoutData = $this->layoutDetectionService->extractLayout($workingPdfAbsPath);

            $document->update([
                'status' => 'ready',
                'page_count' => $meta['page_count'] ?: count($layoutData['pages']),
                'layout_data' => $layoutData,
                'error_message' => $layoutData['is_scanned']
                    ? 'Catatan: Dokumen ini terdeteksi memiliki sedikit teks atau berupa hasil scan. Garis draft otomatis memerlukan lapisan teks digital.'
                    : null,
            ]);
        } catch (Exception $e) {
            Log::error("Document processing failed for UUID {$uuid}: ".$e->getMessage());
            $document->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'uuid' => $document->uuid,
                ], 422);
            }

            return redirect()->route('documents.editor', $document)
                ->with('error', 'Gagal memproses dokumen: '.$e->getMessage());
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'uuid' => $document->uuid,
                'redirect_url' => route('documents.editor', $document),
            ]);
        }

        return redirect()->route('documents.editor', $document)
            ->with('success', 'Dokumen berhasil diunggah dan siap dikonfigurasi.');
    }

    /**
     * Editor & interactive preview page.
     */
    public function editor(Document $document): View
    {
        Gate::authorize('view', $document);

        $layoutData = $document->layout_data ?? ['pages' => []];
        $draftConfig = $document->draft_line_config ?? config('notary.default_draft_lines');
        $stampConfig = $document->stamp_config ?? config('notary.default_stamp');

        $calculatedDraftLines = $this->draftLineService->calculateDraftLines($layoutData, $draftConfig);
        $calculatedStamp = $this->stampPlacementService->calculatePlacements($stampConfig, $layoutData['pages'] ?? []);

        return view('documents.editor', compact(
            'document',
            'layoutData',
            'draftConfig',
            'stampConfig',
            'calculatedDraftLines',
            'calculatedStamp'
        ));
    }

    /**
     * Stream working PDF securely for PDF.js preview.
     */
    public function streamPdf(Document $document): BinaryFileResponse|Response
    {
        Gate::authorize('view', $document);

        $absPath = $document->getPdfAbsolutePath();
        if (! file_exists($absPath)) {
            abort(404, 'File PDF kerja tidak ditemukan.');
        }

        return response()->file($absPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.addslashes(pathinfo($document->original_filename, PATHINFO_FILENAME)).'.pdf"',
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * Stream final PDF for preview if generated.
     */
    public function streamFinalPdf(Document $document): BinaryFileResponse|Response
    {
        Gate::authorize('view', $document);

        $absPath = $document->getFinalPdfAbsolutePath();
        if (! $absPath || ! file_exists($absPath)) {
            abort(404, 'File PDF final belum di-generate.');
        }

        return response()->file($absPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.addslashes($document->title).'_final.pdf"',
            'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
        ]);
    }

    /**
     * Download the final stamped PDF document.
     */
    public function download(Document $document): BinaryFileResponse
    {
        Gate::authorize('view', $document);

        $absPath = $document->getFinalPdfAbsolutePath();
        if (! $absPath || ! file_exists($absPath)) {
            abort(404, 'File PDF final belum di-generate. Silakan klik tombol Generate PDF terlebih dahulu.');
        }

        $cleanTitle = Str::slug($document->title) ?: 'akta_notaris';
        $downloadName = "{$cleanTitle}_draft_notaris.pdf";

        return response()->download($absPath, $downloadName, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Update configuration and return recalculated coordinates in JSON for instant preview.
     */
    public function updateConfig(Request $request, Document $document): JsonResponse
    {
        Gate::authorize('update', $document);

        $validated = $request->validate([
            'draft_lines' => ['nullable', 'array'],
            'draft_lines.enabled' => ['nullable', 'boolean'],
            'draft_lines.color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'draft_lines.thickness' => ['nullable', 'numeric', 'min:0.5', 'max:5.0'],
            'draft_lines.offset' => ['nullable', 'numeric', 'min:1.0', 'max:30.0'],
            'draft_lines.paragraph_threshold' => ['nullable', 'numeric', 'min:1.0', 'max:5.0'],
            'draft_lines.ignore_headers_footers' => ['nullable', 'boolean'],
            'draft_lines.target_pages' => ['nullable', 'string', 'in:all_pages,first_page,custom'],
            'draft_lines.custom_pages' => ['nullable', 'string', 'max:100'],

            'stamp' => ['nullable', 'array'],
            'stamp.notary_name' => ['nullable', 'string', 'max:120'],
            'stamp.city_title' => ['nullable', 'string', 'max:120'],
            'stamp.color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'stamp.font_size_name' => ['nullable', 'numeric', 'min:6.0', 'max:30.0'],
            'stamp.font_size_city' => ['nullable', 'numeric', 'min:5.0', 'max:24.0'],
            'stamp.line_thickness' => ['nullable', 'numeric', 'min:0.5', 'max:5.0'],
            'stamp.line_length' => ['nullable', 'numeric', 'min:50.0', 'max:500.0'],
            'stamp.element_spacing' => ['nullable', 'numeric', 'min:1.0', 'max:20.0'],
            'stamp.target_pages' => ['nullable', 'string', 'in:first_page,all_pages,custom'],
            'stamp.custom_pages' => ['nullable', 'string', 'max:100'],
            'stamp.preset' => ['nullable', 'string', 'in:top_left,bottom_left,bottom_center,signature_area,custom'],
            'stamp.x_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'stamp.y_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $currentDraft = $document->draft_line_config ?? config('notary.default_draft_lines');
        $currentStamp = $document->stamp_config ?? config('notary.default_stamp');

        $mergedDraft = array_merge($currentDraft, $validated['draft_lines'] ?? []);
        $mergedStamp = array_merge($currentStamp, $validated['stamp'] ?? []);

        $document->update([
            'draft_line_config' => $mergedDraft,
            'stamp_config' => $mergedStamp,
        ]);

        $layoutData = $document->layout_data ?? ['pages' => []];
        $calculatedDraftLines = $this->draftLineService->calculateDraftLines($layoutData, $mergedDraft);
        $calculatedStamp = $this->stampPlacementService->calculatePlacements($mergedStamp, $layoutData['pages'] ?? []);

        return response()->json([
            'success' => true,
            'message' => 'Konfigurasi berhasil disimpan.',
            'draft_lines' => $calculatedDraftLines,
            'stamp' => $calculatedStamp,
        ]);
    }

    /**
     * Generate the final stamped PDF with exact draft lines.
     */
    public function generate(Request $request, Document $document): JsonResponse
    {
        Gate::authorize('update', $document);

        if (! $document->isReady()) {
            return response()->json([
                'success' => false,
                'message' => 'Dokumen belum siap untuk diproses.',
            ], 400);
        }

        // If client passes fresh config with generate request, update first
        if ($request->has('draft_lines') || $request->has('stamp')) {
            $currentDraft = $document->draft_line_config ?? config('notary.default_draft_lines');
            $currentStamp = $document->stamp_config ?? config('notary.default_stamp');

            $mergedDraft = array_merge($currentDraft, $request->input('draft_lines', []));
            $mergedStamp = array_merge($currentStamp, $request->input('stamp', []));

            $document->update([
                'draft_line_config' => $mergedDraft,
                'stamp_config' => $mergedStamp,
            ]);
        }

        $sourcePdfAbs = $document->getPdfAbsolutePath();
        $finalRelativePath = "documents/final/{$document->uuid}_final.pdf";
        $finalPdfAbs = Storage::disk('local')->path($finalRelativePath);

        try {
            $this->pdfRenderingService->renderFinalPdf(
                $sourcePdfAbs,
                $finalPdfAbs,
                $document->layout_data ?? ['pages' => []],
                $document->draft_line_config ?? [],
                $document->stamp_config ?? []
            );

            $document->update([
                'final_pdf_path' => $finalRelativePath,
                'generated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'PDF Final berhasil di-generate!',
                'download_url' => route('documents.download', $document),
                'stream_url' => route('documents.stream-final', $document),
                'generated_at' => $document->generated_at->format('d/m/Y H:i'),
            ]);
        } catch (Exception $e) {
            Log::error("Failed to generate final PDF for {$document->uuid}: ".$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghasilkan PDF final: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retry processing for failed document.
     */
    public function retry(Document $document): RedirectResponse
    {
        Gate::authorize('update', $document);

        $document->update([
            'status' => 'processing',
            'error_message' => null,
        ]);

        try {
            $inputAbsPath = $document->getOriginalAbsolutePath();
            $workingPdfAbsPath = $document->getPdfAbsolutePath();

            $meta = $this->conversionService->convertToPdf($inputAbsPath, $document->source_type, $workingPdfAbsPath);
            $layoutData = $this->layoutDetectionService->extractLayout($workingPdfAbsPath);

            $document->update([
                'status' => 'ready',
                'page_count' => $meta['page_count'] ?: count($layoutData['pages']),
                'layout_data' => $layoutData,
            ]);

            return redirect()->route('documents.editor', $document)
                ->with('success', 'Dokumen berhasil diproses ulang.');
        } catch (Exception $e) {
            $document->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return redirect()->route('documents.editor', $document)
                ->with('error', 'Proses ulang gagal: '.$e->getMessage());
        }
    }

    /**
     * Delete document and its associated storage files.
     */
    public function destroy(Document $document): RedirectResponse
    {
        Gate::authorize('delete', $document);

        if ($document->original_path && Storage::disk('local')->exists($document->original_path)) {
            Storage::disk('local')->delete($document->original_path);
        }
        if ($document->pdf_path && Storage::disk('local')->exists($document->pdf_path)) {
            Storage::disk('local')->delete($document->pdf_path);
        }
        if ($document->final_pdf_path && Storage::disk('local')->exists($document->final_pdf_path)) {
            Storage::disk('local')->delete($document->final_pdf_path);
        }

        $document->delete();

        return redirect()->route('documents.history')
            ->with('success', 'Dokumen berhasil dihapus.');
    }

    /**
     * Helper to format bytes into human-readable string.
     */
    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2).' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }
}
