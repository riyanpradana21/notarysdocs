@extends('layouts.app', ['title' => 'Dashboard — NotaryDraft'])

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
    <!-- Hero / Direct Tool Area (iLovePDF Style) -->
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-slate-800 to-rose-950 text-white shadow-xl p-8 sm:p-10 border border-slate-700/50">
        <div class="max-w-3xl space-y-3">
            <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full bg-rose-500/20 text-rose-300 border border-rose-500/30 text-xs font-semibold">
                <span class="w-2 h-2 rounded-full bg-rose-400 animate-pulse"></span>
                <span>Sistem Akta & Draf Notaris Resmi</span>
            </div>
            <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-white leading-tight">
                Garis Draf Dinamis & Stempel Notaris dalam Hitungan Detik
            </h1>
            <p class="text-slate-300 text-sm sm:text-base leading-relaxed">
                Langsung unggah dokumen DOCX atau PDF Anda di bawah ini. Garis vertikal draf akan mengikuti koordinat teks secara dinamis dan stempel resmi Notaris Kota Denpasar siap diposisikan.
            </p>
        </div>

        <!-- Quick Upload Card on Hero -->
        <div class="mt-8 bg-white/10 backdrop-blur-md rounded-2xl p-6 border border-white/20">
            <form id="heroUploadForm" action="{{ route('documents.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div id="heroDropZone" class="relative border-2 border-dashed border-white/40 hover:border-rose-400 bg-white/5 hover:bg-white/10 rounded-xl p-8 text-center transition-all cursor-pointer group">
                    <input type="file" id="heroDocumentInput" name="document" accept=".docx,.pdf,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document" class="hidden">
                    
                    <div id="heroPrompt" class="space-y-3">
                        <div class="w-14 h-14 mx-auto rounded-2xl bg-rose-600 text-white shadow-lg shadow-rose-600/30 flex items-center justify-center group-hover:scale-105 transition-transform">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z" />
                            </svg>
                        </div>
                        <div>
                            <span class="text-base font-bold text-white group-hover:text-rose-300">Pilih Dokumen PDF atau DOCX</span>
                            <span class="text-sm text-slate-300 block mt-0.5">atau seret & lepaskan file Anda di sini untuk langsung mulai</span>
                        </div>
                        <div class="inline-flex items-center space-x-2 text-2xs text-slate-400">
                            <span>Maksimal 25 MB</span>
                            <span>&bull;</span>
                            <span>Maksimal 100 Halaman</span>
                            <span>&bull;</span>
                            <span>Privat & Aman</span>
                        </div>
                    </div>

                    <!-- Selected file info -->
                    <div id="heroSelectedFile" class="hidden text-left bg-slate-900/80 p-4 rounded-xl border border-white/20 flex items-center justify-between">
                        <div class="flex items-center space-x-3 truncate">
                            <div class="w-10 h-10 rounded-lg bg-rose-500/20 text-rose-300 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                </svg>
                            </div>
                            <div class="truncate">
                                <p id="heroFileName" class="text-sm font-semibold text-white truncate">document.pdf</p>
                                <p id="heroFileSize" class="text-xs text-slate-400">0 KB</p>
                            </div>
                        </div>
                        <button type="submit" id="heroSubmitBtn" class="px-5 py-2.5 rounded-xl font-semibold text-xs text-white bg-rose-600 hover:bg-rose-500 shadow-md transition-colors shrink-0">
                            Proses & Buka Editor &rarr;
                        </button>
                    </div>
                </div>

                <!-- Upload Progress -->
                <div id="heroProgressBox" class="hidden bg-slate-900/90 rounded-xl p-4 space-y-2 border border-white/20">
                    <div class="flex justify-between text-xs text-white">
                        <span id="heroProgressStatus" class="font-medium flex items-center">
                            <svg class="animate-spin -ml-0.5 mr-2 h-4 w-4 text-rose-400" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            Mengunggah dokumen...
                        </span>
                        <span id="heroProgressPercent" class="font-mono text-rose-400">0%</span>
                    </div>
                    <div class="w-full bg-slate-700 rounded-full h-2 overflow-hidden">
                        <div id="heroProgressBar" class="bg-rose-500 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                    </div>
                </div>

                <!-- Error Display -->
                <div id="heroErrorBox" class="hidden p-3 rounded-xl bg-rose-950/80 border border-rose-500/50 text-xs text-rose-200">
                    <span id="heroErrorMessage"></span>
                </div>
            </form>
        </div>

        @guest
        <div class="mt-4 flex flex-col sm:flex-row sm:items-center justify-between text-xs text-slate-400 gap-2">
            <div>
                <span class="text-rose-300 font-semibold">Mode Tamu Aktif:</span> Anda dapat mengunggah dan mengunduh hasil dokumen secara instan tanpa login.
            </div>
            <div>
                <a href="{{ route('login') }}" class="text-white hover:text-rose-300 font-medium underline">Masuk atau Daftar</a> untuk menyimpan riwayat permanen.
            </div>
        </div>
        @endguest
    </div>

    <!-- Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <!-- Total Documents -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Dokumen</p>
                <p class="text-2xl font-bold text-slate-900 mt-1">{{ $totalDocs }}</p>
            </div>
            <div class="w-11 h-11 rounded-xl bg-slate-100 flex items-center justify-center text-slate-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                </svg>
            </div>
        </div>

        <!-- Ready to Process -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Siap Dikonfigurasi</p>
                <p class="text-2xl font-bold text-emerald-600 mt-1">{{ $readyDocs }}</p>
            </div>
            <div class="w-11 h-11 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
        </div>

        <!-- Processing / Failed -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Status Proses</p>
                <div class="flex items-center space-x-2 mt-1">
                    <span class="text-sm font-semibold text-amber-600">{{ $processingDocs }} Proses</span>
                    <span class="text-slate-300">/</span>
                    <span class="text-sm font-semibold text-rose-600">{{ $failedDocs }} Gagal</span>
                </div>
            </div>
            <div class="w-11 h-11 rounded-xl bg-amber-50 flex items-center justify-center text-amber-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                </svg>
            </div>
        </div>

        <!-- Storage Used -->
        <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Penyimpanan Privat</p>
                <p class="text-2xl font-bold text-slate-900 mt-1">{{ $storageUsedFormatted }}</p>
            </div>
            <div class="w-11 h-11 rounded-xl bg-sky-50 flex items-center justify-center text-sky-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 5.625c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                </svg>
            </div>
        </div>
    </div>

    <!-- Recent Documents Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-900">Dokumen Terbaru</h2>
            @auth
            <a href="{{ route('documents.history') }}" class="text-xs font-semibold text-rose-600 hover:text-rose-700">Lihat Semua Riwayat &rarr;</a>
            @endauth
        </div>

        @if ($recentDocuments->isEmpty())
            <div class="p-12 text-center">
                <div class="w-12 h-12 rounded-xl bg-slate-100 flex items-center justify-center text-slate-400 mx-auto mb-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                    </svg>
                </div>
                <h3 class="text-sm font-semibold text-slate-900">Belum ada dokumen di sesi ini</h3>
                <p class="text-xs text-slate-500 mt-1">Unggah dokumen DOCX atau PDF pertama Anda di atas untuk memulai pembuatan garis draf dan stempel.</p>
                <div class="mt-4">
                    <a href="{{ route('documents.upload') }}" class="inline-flex items-center px-4 py-2 rounded-lg text-xs font-semibold text-white bg-rose-600 hover:bg-rose-700 shadow-xs">
                        Halaman Form Unggah &rarr;
                    </a>
                </div>
            </div>
        @else
            <!-- Desktop Table View (>= md) -->
            <div class="hidden md:block overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-6 py-3 font-semibold">Judul Dokumen</th>
                            <th class="px-6 py-3 font-semibold">Format</th>
                            <th class="px-6 py-3 font-semibold">Halaman</th>
                            <th class="px-6 py-3 font-semibold">Ukuran</th>
                            <th class="px-6 py-3 font-semibold">Status</th>
                            <th class="px-6 py-3 font-semibold">Tanggal</th>
                            <th class="px-6 py-3 font-semibold text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach ($recentDocuments as $doc)
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-slate-900">{{ $doc->title }}</div>
                                    <div class="text-xs text-slate-400 truncate max-w-xs">{{ $doc->original_filename }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold uppercase tracking-wider {{ $doc->source_type === 'pdf' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-blue-50 text-blue-700 border border-blue-200' }}">
                                        {{ $doc->source_type }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-slate-600 text-xs">
                                    {{ $doc->page_count > 0 ? $doc->page_count . ' hlm' : '-' }}
                                </td>
                                <td class="px-6 py-4 text-slate-600 text-xs">
                                    {{ $doc->formatted_file_size }}
                                </td>
                                <td class="px-6 py-4">
                                    @if ($doc->status === 'ready')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span>
                                            Siap
                                        </span>
                                    @elseif ($doc->status === 'failed')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-800" title="{{ $doc->error_message }}">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500 mr-1.5"></span>
                                            Gagal
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 mr-1.5 animate-pulse"></span>
                                            Memproses
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-xs text-slate-500">
                                    {{ $doc->created_at->format('d/m/Y H:i') }}
                                </td>
                                <td class="px-6 py-4 text-right space-x-2 whitespace-nowrap">
                                    @if ($doc->isReady())
                                        <a href="{{ route('documents.editor', $doc) }}" class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 transition-colors">
                                            Buka Editor
                                        </a>
                                        @if ($doc->hasFinalPdf())
                                            <a href="{{ route('documents.download', $doc) }}" class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 transition-colors">
                                                Unduh Final
                                            </a>
                                        @endif
                                    @elseif ($doc->isFailed())
                                        <form action="{{ route('documents.retry', $doc) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold text-amber-800 bg-amber-50 hover:bg-amber-100 border border-amber-200">
                                                Coba Lagi
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Mobile Card List View (< md) -->
            <div class="md:hidden divide-y divide-slate-200">
                @foreach ($recentDocuments as $doc)
                    <div class="p-4 space-y-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <h3 class="text-sm font-semibold text-slate-900 truncate">{{ $doc->title }}</h3>
                                <p class="text-xs text-slate-400 truncate">{{ $doc->original_filename }}</p>
                            </div>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-2xs font-bold uppercase tracking-wider shrink-0 {{ $doc->source_type === 'pdf' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-blue-50 text-blue-700 border border-blue-200' }}">
                                {{ $doc->source_type }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between text-xs text-slate-500 pt-1">
                            <span>{{ $doc->page_count > 0 ? $doc->page_count . ' Halaman' : '-' }} &bull; {{ $doc->formatted_file_size }}</span>
                            @if ($doc->status === 'ready')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-2xs font-medium bg-emerald-100 text-emerald-800">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1"></span>
                                    Siap
                                </span>
                            @elseif ($doc->status === 'failed')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-2xs font-medium bg-rose-100 text-rose-800">
                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500 mr-1"></span>
                                    Gagal
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-2xs font-medium bg-amber-100 text-amber-800">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 mr-1 animate-pulse"></span>
                                    Memproses
                                </span>
                            @endif
                        </div>

                        <div class="pt-2 flex items-center justify-end space-x-2 border-t border-slate-100">
                            @if ($doc->isReady())
                                <a href="{{ route('documents.editor', $doc) }}" class="flex-1 text-center py-2 px-3 rounded-lg text-xs font-semibold text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 transition-colors">
                                    Buka Editor
                                </a>
                                @if ($doc->hasFinalPdf())
                                    <a href="{{ route('documents.download', $doc) }}" class="flex-1 text-center py-2 px-3 rounded-lg text-xs font-semibold text-slate-700 bg-slate-100 hover:bg-slate-200 transition-colors">
                                        Unduh Final
                                    </a>
                                @endif
                            @elseif ($doc->isFailed())
                                <form action="{{ route('documents.retry', $doc) }}" method="POST" class="w-full">
                                    @csrf
                                    <button type="submit" class="w-full py-2 px-3 rounded-lg text-xs font-semibold text-amber-800 bg-amber-50 hover:bg-amber-100 border border-amber-200">
                                        Coba Lagi
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const dropZone = document.getElementById('heroDropZone');
    const input = document.getElementById('heroDocumentInput');
    const prompt = document.getElementById('heroPrompt');
    const selectedFile = document.getElementById('heroSelectedFile');
    const fileName = document.getElementById('heroFileName');
    const fileSize = document.getElementById('heroFileSize');
    const form = document.getElementById('heroUploadForm');
    const progressBox = document.getElementById('heroProgressBox');
    const progressBar = document.getElementById('heroProgressBar');
    const progressStatus = document.getElementById('heroProgressStatus');
    const progressPercent = document.getElementById('heroProgressPercent');
    const errorBox = document.getElementById('heroErrorBox');
    const errorMessage = document.getElementById('heroErrorMessage');

    dropZone.addEventListener('click', function (e) {
        if (!e.target.closest('#heroSubmitBtn')) {
            input.click();
        }
    });

    ['dragenter', 'dragover'].forEach(name => {
        dropZone.addEventListener(name, (e) => {
            e.preventDefault();
            dropZone.classList.add('border-rose-400', 'bg-white/15');
        });
    });

    ['dragleave', 'drop'].forEach(name => {
        dropZone.addEventListener(name, (e) => {
            e.preventDefault();
            dropZone.classList.remove('border-rose-400', 'bg-white/15');
        });
    });

    dropZone.addEventListener('drop', (e) => {
        if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
            handleFile(e.dataTransfer.files[0]);
        }
    });

    input.addEventListener('change', () => {
        if (input.files && input.files.length > 0) {
            handleFile(input.files[0]);
        }
    });

    function formatBytes(bytes) {
        if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
        if (bytes >= 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return bytes + ' B';
    }

    function handleFile(file) {
        const ext = file.name.split('.').pop().toLowerCase();
        if (!['docx', 'pdf'].includes(ext)) {
            showError('Hanya format file .docx dan .pdf yang didukung.');
            return;
        }

        if (file.size > 25 * 1024 * 1024) {
            showError('Ukuran file melebihi batas 25 MB.');
            return;
        }

        hideError();
        fileName.textContent = file.name;
        fileSize.textContent = formatBytes(file.size);

        prompt.classList.add('hidden');
        selectedFile.classList.remove('hidden');

        // Automatically trigger upload processing on drop/select
        startUpload();
    }

    function showError(msg) {
        errorMessage.textContent = msg;
        errorBox.classList.remove('hidden');
    }

    function hideError() {
        errorBox.classList.add('hidden');
    }

    function startUpload() {
        hideError();
        progressBox.classList.remove('hidden');
        progressBar.style.width = '20%';
        progressPercent.textContent = '20%';

        const formData = new FormData(form);
        const xhr = new XMLHttpRequest();
        xhr.open('POST', form.action, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

        xhr.upload.onprogress = function (e) {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 60);
                progressBar.style.width = percent + '%';
                progressPercent.textContent = percent + '%';
                if (percent >= 60) {
                    progressStatus.innerHTML = `
                        <svg class="animate-spin -ml-0.5 mr-2 h-4 w-4 text-rose-400" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        Menganalisis layout teks via Poppler...
                    `;
                    progressBar.style.width = '85%';
                    progressPercent.textContent = '85%';
                }
            }
        };

        xhr.onload = function () {
            if (xhr.status >= 200 && xhr.status < 300) {
                progressBar.style.width = '100%';
                progressPercent.textContent = '100%';
                try {
                    const res = JSON.parse(xhr.responseText);
                    if (res.success && res.redirect_url) {
                        window.location.href = res.redirect_url;
                    } else {
                        showError(res.message || 'Respons tidak valid dari server.');
                    }
                } catch (err) {
                    window.location.reload();
                }
            } else {
                let err = 'Terjadi kesalahan saat memproses dokumen.';
                try {
                    const res = JSON.parse(xhr.responseText);
                    err = res.message || err;
                } catch (e) {}
                showError(err);
                progressBox.classList.add('hidden');
            }
        };

        xhr.onerror = function () {
            showError('Koneksi terputus. Silakan coba lagi.');
            progressBox.classList.add('hidden');
        };

        xhr.send(formData);
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        startUpload();
    });
});
</script>
@endpush
