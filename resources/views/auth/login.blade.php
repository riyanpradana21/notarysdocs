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
            <!-- 1-Click Demo Login Box -->
            <div class="mb-6 p-4 rounded-xl bg-rose-50/70 border border-rose-200">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold uppercase tracking-wider text-rose-800">Akses Pengujian Cepat</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-rose-200 text-rose-800">Demo</span>
                </div>
                <p class="text-xs text-rose-700 mb-3">
                    Gunakan akun Notaris Robert untuk langsung menguji unggah DOCX/PDF, preview PDF.js, dan generate PDF.
                </p>
                <form action="{{ route('login.demo') }}" method="POST">
                    @csrf
                    <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 text-sm font-semibold rounded-lg text-white bg-rose-600 hover:bg-rose-700 shadow-xs focus:ring-2 focus:ring-offset-2 focus:ring-rose-500 transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        Masuk Cepat sebagai Notaris Robert
                    </button>
                </form>
            </div>

            <div class="relative my-6">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-slate-200"></div>
                </div>
                <div class="relative flex justify-center text-xs uppercase">
                    <span class="bg-white px-3 text-slate-400 font-medium">Atau masuk dengan akun</span>
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
