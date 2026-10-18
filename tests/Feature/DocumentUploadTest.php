<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->user = User::factory()->create();
    }

    public function test_unauthenticated_user_cannot_upload_document(): void
    {
        $response = $this->post(route('documents.store'), [
            'document' => UploadedFile::fake()->create('test.pdf', 500, 'application/pdf'),
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_rejects_invalid_file_extension(): void
    {
        $response = $this->actingAs($this->user)->post(route('documents.store'), [
            'document' => UploadedFile::fake()->create('malicious.exe', 500, 'application/octet-stream'),
        ]);

        $response->assertSessionHasErrors('document');
    }

    public function test_rejects_file_exceeding_max_size(): void
    {
        // 30MB exceeds 25MB limit
        $response = $this->actingAs($this->user)->post(route('documents.store'), [
            'document' => UploadedFile::fake()->create('large.pdf', 31000, 'application/pdf'),
        ]);

        $response->assertSessionHasErrors('document');
    }

    public function test_requires_document_file(): void
    {
        $response = $this->actingAs($this->user)->post(route('documents.store'), []);

        $response->assertSessionHasErrors('document');
    }
}
