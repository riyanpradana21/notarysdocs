<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Document extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'user_id',
        'title',
        'original_filename',
        'mime_type',
        'file_size',
        'source_type',
        'status',
        'error_message',
        'page_count',
        'original_path',
        'pdf_path',
        'final_pdf_path',
        'layout_data',
        'draft_line_config',
        'stamp_config',
        'generated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'page_count' => 'integer',
            'layout_data' => 'array',
            'draft_line_config' => 'array',
            'stamp_config' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::creating(function (Document $document): void {
            if (empty($document->uuid)) {
                $document->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get route key name for Laravel model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * Document owner.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if document is ready for editing/generating.
     */
    public function isReady(): bool
    {
        return $this->status === 'ready';
    }

    /**
     * Check if processing failed.
     */
    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if final PDF is already generated.
     */
    public function hasFinalPdf(): bool
    {
        return ! empty($this->final_pdf_path) && Storage::disk('local')->exists($this->final_pdf_path);
    }

    /**
     * Absolute path on disk for original file.
     */
    public function getOriginalAbsolutePath(): string
    {
        return Storage::disk('local')->path($this->original_path);
    }

    /**
     * Absolute path on disk for working PDF.
     */
    public function getPdfAbsolutePath(): string
    {
        return Storage::disk('local')->path($this->pdf_path);
    }

    /**
     * Absolute path on disk for final PDF.
     */
    public function getFinalPdfAbsolutePath(): ?string
    {
        if (empty($this->final_pdf_path)) {
            return null;
        }

        return Storage::disk('local')->path($this->final_pdf_path);
    }

    /**
     * Formatted file size string.
     */
    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }
}
