<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('original_filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            $table->string('source_type', 10); // 'pdf' or 'docx'
            $table->string('status', 20)->default('uploaded'); // 'uploaded', 'processing', 'ready', 'failed'
            $table->text('error_message')->nullable();
            $table->unsignedInteger('page_count')->default(0);
            $table->string('original_path');
            $table->string('pdf_path');
            $table->string('final_pdf_path')->nullable();
            $table->json('layout_data')->nullable();
            $table->json('draft_line_config')->nullable();
            $table->json('stamp_config')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
