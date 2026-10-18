<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $otherUser;

    protected Document $document;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->otherUser = User::factory()->create();

        $this->document = Document::create([
            'uuid' => 'test-uuid-auth-1234',
            'user_id' => $this->owner->id,
            'title' => 'Akta Notaris Rahasia',
            'original_filename' => 'akta.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'source_type' => 'pdf',
            'status' => 'ready',
            'original_path' => 'documents/originals/test.pdf',
            'pdf_path' => 'documents/working/test.pdf',
        ]);
    }

    public function test_owner_can_view_editor(): void
    {
        $response = $this->actingAs($this->owner)->get(route('documents.editor', $this->document));
        $response->assertOk();
    }

    public function test_other_user_forbidden_from_viewing_editor(): void
    {
        $response = $this->actingAs($this->otherUser)->get(route('documents.editor', $this->document));
        $response->assertForbidden();
    }

    public function test_other_user_forbidden_from_updating_config(): void
    {
        $response = $this->actingAs($this->otherUser)->patchJson(route('documents.config', $this->document), [
            'draft_lines' => ['offset' => 12.0],
        ]);
        $response->assertForbidden();
    }

    public function test_other_user_forbidden_from_generating_pdf(): void
    {
        $response = $this->actingAs($this->otherUser)->postJson(route('documents.generate', $this->document), []);
        $response->assertForbidden();
    }

    public function test_other_user_forbidden_from_downloading_pdf(): void
    {
        $response = $this->actingAs($this->otherUser)->get(route('documents.download', $this->document));
        $response->assertForbidden();
    }

    public function test_other_user_forbidden_from_deleting_document(): void
    {
        $response = $this->actingAs($this->otherUser)->delete(route('documents.destroy', $this->document));
        $response->assertForbidden();
    }
}
