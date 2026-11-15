@extends('layouts.app', ['title' => 'Masuk — NotaryDraft'])

@section('content')
<div class="min-h-[calc(100vh-12rem)] flex flex-col justify-center py-12 sm:px-6 lg:px-8">
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
        <h2 class="text-center text-2xl font-bold tracking-tight text-slate-900">
            Masuk ke Portal NotaryDraft
        </h2>
        <p class="mt-2 text-center text-sm text-slate-600">
            Sistem Pembuatan Garis Draf Dinamis & Stempel Notaris Resmi
        </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md px-4">
        <div class="bg-white py-8 px-6 shadow-sm border border-slate-200 rounded-2xl sm:px-10">
            <!-- Google Sign-In Button -->
            <div>
                <a href="{{ route('login.google') }}" class="w-full inline-flex justify-center items-center px-4 py-2.5 border border-slate-300 rounded-xl shadow-xs text-sm font-semibold text-slate-700 bg-white hover:bg-slate-50 hover:border-slate-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-400 transition-colors">
                    <svg class="w-5 h-5 mr-3 shrink-0" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                    </svg>
                    Masuk dengan Akun Google
                </a>
            </div>

            <!-- Divider -->
            <div class="relative my-6">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-slate-200"></div>
                </div>
                <div class="relative flex justify-center text-xs uppercase">
                    <span class="bg-white px-3 text-slate-400 font-medium">Atau dengan email</span>
                </div>
            </div>

            <form action="{{ route('login') }}" method="POST" class="space-y-4">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700">Alamat Email</label>
                    <div class="mt-1">
                        <input id="email" name="email" type="email" autocomplete="email" required
                               value="{{ old('email') }}"
                               class="appearance-none block w-full px-3 py-2 border border-slate-300 rounded-lg shadow-xs placeholder-slate-400 focus:outline-none focus:ring-rose-500 focus:border-rose-500 text-sm">
                    </div>
                    @error('email')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700">Kata Sandi</label>
                    <div class="mt-1">
                        <input id="password" name="password" type="password" autocomplete="current-password" required
                               class="appearance-none block w-full px-3 py-2 border border-slate-300 rounded-lg shadow-xs placeholder-slate-400 focus:outline-none focus:ring-rose-500 focus:border-rose-500 text-sm">
                    </div>
                    @error('password')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input id="remember" name="remember" type="checkbox"
                               class="h-4 w-4 text-rose-600 focus:ring-rose-500 border-slate-300 rounded">
                        <label for="remember" class="ml-2 block text-xs text-slate-700">Ingat saya</label>
                    </div>
                </div>

                <div>
                    <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-lg shadow-xs text-sm font-semibold text-white bg-slate-900 hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-900 transition-colors">
                        Masuk
                    </button>
                </div>
            </form>

            <div class="mt-6 text-center text-xs text-slate-500">
                Belum memiliki akun?
                <a href="{{ route('register') }}" class="font-semibold text-rose-600 hover:text-rose-500">Daftar sekarang</a>
            </div>
        </div>
    </div>
</div>
@endsection
