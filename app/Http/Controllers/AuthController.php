<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\User;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    /**
     * Show login form.
     */
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    /**
     * Handle user login.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $previousSessionId = $request->session()->getId();

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $user = Auth::user();
            $this->claimGuestDocuments($previousSessionId, $user->id);

            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors([
            'email' => 'Kombinasi email dan password yang Anda masukkan tidak sesuai.',
        ])->onlyInput('email');
    }

    /**
     * Redirect to Google OAuth.
     */
    public function redirectToGoogle(): RedirectResponse
    {
        $clientId = config('services.google.client_id');
        $clientSecret = config('services.google.client_secret');

        if (empty($clientId) || empty($clientSecret)) {
            return redirect()->route('login')->with('error', 'Kredensial Google OAuth belum dikonfigurasi di file .env (GOOGLE_CLIENT_ID dan GOOGLE_CLIENT_SECRET).');
        }

        try {
            return Socialite::driver('google')->redirect();
        } catch (Exception $e) {
            Log::error('Google OAuth Redirect Error: '.$e->getMessage());

            return redirect()->route('login')->with('error', 'Gagal mengarahkan ke halaman login Google: '.$e->getMessage());
        }
    }

    /**
     * Handle Google OAuth callback.
     */
    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        $previousSessionId = $request->session()->getId();

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Exception $e) {
            Log::error('Google OAuth Callback Error: '.$e->getMessage());

            return redirect()->route('login')->with('error', 'Otentikasi dengan akun Google dibatalkan atau gagal.');
        }

        // Find or create user
        $user = User::where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if ($user) {
            $user->update([
                'google_id' => $googleUser->getId(),
                'google_token' => $googleUser->token,
                'google_refresh_token' => $googleUser->refreshToken,
                'avatar' => $googleUser->getAvatar(),
            ]);
        } else {
            $user = User::create([
                'name' => $googleUser->getName() ?: $googleUser->getNickname() ?: 'Pengguna Google',
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
                'google_token' => $googleUser->token,
                'google_refresh_token' => $googleUser->refreshToken,
                'avatar' => $googleUser->getAvatar(),
                'email_verified_at' => now(),
            ]);
        }

        Auth::login($user, true);
        $this->claimGuestDocuments($previousSessionId, $user->id);

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'))->with('success', 'Berhasil masuk menggunakan akun Google: '.$user->email);
    }

    /**
     * Quick demo login for testing purposes.
     */
    public function demoLogin(Request $request): RedirectResponse
    {
        $previousSessionId = $request->session()->getId();

        $user = User::firstOrCreate(
            ['email' => 'notaris@denpasar.go.id'],
            [
                'name' => 'Robert, S.H., M.Kn.',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );

        Auth::login($user);
        $this->claimGuestDocuments($previousSessionId, $user->id);

        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Berhasil masuk sebagai Notaris Robert.');
    }

    /**
     * Show registration form.
     */
    public function showRegister(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.register');
    }

    /**
     * Handle user registration.
     */
    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $previousSessionId = $request->session()->getId();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        Auth::login($user);
        $this->claimGuestDocuments($previousSessionId, $user->id);

        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Pendaftaran akun berhasil!');
    }

    /**
     * Handle logout.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('dashboard')->with('info', 'Anda telah keluar dari aplikasi.');
    }

    /**
     * Associate guest session documents with newly logged-in user.
     */
    protected function claimGuestDocuments(?string $sessionId, int $userId): void
    {
        if (! empty($sessionId)) {
            Document::whereNull('user_id')
                ->where('session_id', $sessionId)
                ->update(['user_id' => $userId]);
        }
    }
}
