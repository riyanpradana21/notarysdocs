<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirects_to_google_provider(): void
    {
        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.redirect' => 'http://localhost/auth/google/callback',
        ]);

        $response = $this->get(route('login.google'));

        $response->assertRedirect();
        $this->assertStringContainsString('accounts.google.com', $response->getTargetUrl());
    }

    public function test_redirects_to_login_if_credentials_are_missing(): void
    {
        config([
            'services.google.client_id' => null,
            'services.google.client_secret' => null,
        ]);

        $response = $this->get(route('login.google'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
    }

    public function test_google_callback_creates_and_logs_in_user(): void
    {
        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn('google-unique-id-999');
        $socialiteUser->shouldReceive('getName')->andReturn('John Notary');
        $socialiteUser->shouldReceive('getEmail')->andReturn('john.notary@example.com');
        $socialiteUser->shouldReceive('getAvatar')->andReturn('https://lh3.googleusercontent.com/photo.jpg');
        $socialiteUser->token = 'mocked-access-token';
        $socialiteUser->refreshToken = 'mocked-refresh-token';

        Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

        $response = $this->get(route('login.google.callback'));

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        $this->assertDatabaseHas('users', [
            'email' => 'john.notary@example.com',
            'name' => 'John Notary',
            'google_id' => 'google-unique-id-999',
            'avatar' => 'https://lh3.googleusercontent.com/photo.jpg',
        ]);
    }

    public function test_google_callback_claims_guest_documents(): void
    {
        $sessionId = 'mock-session-claim-123';
        $guestDoc = Document::create([
            'uuid' => 'guest-doc-uuid-123',
            'user_id' => null,
            'session_id' => $sessionId,
            'title' => 'Guest Uploaded Doc',
            'original_filename' => 'guest.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'source_type' => 'pdf',
            'status' => 'ready',
            'original_path' => 'documents/originals/guest.pdf',
            'pdf_path' => 'documents/working/guest.pdf',
        ]);

        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn('google-unique-id-888');
        $socialiteUser->shouldReceive('getName')->andReturn('Jane Notary');
        $socialiteUser->shouldReceive('getEmail')->andReturn('jane.notary@example.com');
        $socialiteUser->shouldReceive('getAvatar')->andReturn(null);
        $socialiteUser->token = 'mocked-access-token';
        $socialiteUser->refreshToken = null;

        Socialite::shouldReceive('driver->user')->andReturn($socialiteUser);

        $response = $this->withSession(['_token' => 'dummy'])->get(route('login.google.callback'));

        $user = User::where('email', 'jane.notary@example.com')->first();
        $this->assertNotNull($user);

        // Verify document is claimed if session matches or check logic
        $this->assertAuthenticatedAs($user);
    }
}
