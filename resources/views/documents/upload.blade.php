@extends('layouts.app', ['title' => 'Unggah Dokumen — NotaryDraft'])

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
    <!-- Header -->
    <div class="border-b border-slate-200 pb-4">
        <a href="{{ route('dashboard') }}" class="inline-flex items-center text-xs font-semibold text-slate-500 hover:text-slate-800 mb-2">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Kembali ke Dashboard
        </a>
        <h1 class="text-2xl font-bold tracking-tight text-slate-900">Unggah Dokumen Akta</h1>
        <p class="mt-1 text-sm text-slate-500">
            Dukung format <strong class="text-slate-700">DOCX</strong> dan <strong class="text-slate-700">PDF</strong> hingga 25 MB (maksimal 100 halaman).
        </p>
    </div>

    <!-- Upload Form Card -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-xs p-6 sm:p-8">
        <form id="uploadForm" action="{{ route('documents.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <!-- Document Title (Optional) -->
            <div>
                <label for="title" class="block text-sm font-semibold text-slate-800 mb-1">
                    Judul Dokumen / Akta <span class="text-slate-400 font-normal">(opsional)</span>
                </label>
                <input type="text" id="title" name="title" placeholder="Contoh: Akta Pendirian PT Mandiri Jaya"
                       class="w-full px-3.5 py-2.5 border border-slate-300 rounded-xl text-sm placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500 transition-colors">
                <p class="mt-1 text-xs text-slate-400">Jika dikosongkan, nama file asli akan digunakan sebagai judul.</p>
            </div>

            <!-- Drag & Drop Area -->
            <div>
                <label class="block text-sm font-semibold text-slate-800 mb-2">Berkas Dokumen (DOCX / PDF)</label>
                <div id="dropZone"
                     class="relative border-2 border-dashed border-slate-300 hover:border-rose-400 bg-slate-50/50 hover:bg-rose-50/30 rounded-2xl p-8 text-center transition-all cursor-pointer group">
                    <input type="file" id="documentInput" name="document" accept=".docx,.pdf,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document" class="hidden">

                    <div id="dropPrompt" class="space-y-3">
                        <div class="w-14 h-14 mx-auto rounded-2xl bg-white border border-slate-200 shadow-xs flex items-center justify-center text-rose-600 group-hover:scale-105 transition-transform">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0l3 3m-3-3l-3 3M6.75 19.5a4.5 4.5 0 01-1.41-8.775 5.25 5.25 0 0110.233-2.33 3 3 0 013.758 3.848A3.752 3.752 0 0118 19.5H6.75z" />
                            </svg>
                        </div>
                        <div>
                            <span class="text-sm font-semibold text-slate-900 group-hover:text-rose-600">Klik untuk memilih file</span>
                            <span class="text-sm text-slate-500"> atau seret dan lepaskan di sini</span>
                        </div>
                        <p class="text-xs text-slate-400">
                            DOCX (akan otomatis dikonversi via LibreOffice) atau PDF asli dengan lapisan teks
                        </p>
                    </div>

                    <!-- Selected File Info (hidden initially) -->
                    <div id="selectedFileInfo" class="hidden text-left bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center justify-between">
                        <div class="flex items-center space-x-3 truncate">
                            <div class="w-10 h-10 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                </svg>
                            </div>
                            <div class="truncate">
                                <p id="fileName" class="text-sm font-semibold text-slate-900 truncate">document.pdf</p>
                                <p id="fileSize" class="text-xs text-slate-500">0 KB</p>
                            </div>
                        </div>
                        <button type="button" id="removeFileBtn" class="p-1.5 text-slate-400 hover:text-rose-600 rounded-lg hover:bg-slate-100 transition-colors">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
                @error('document')
                    <p class="mt-2 text-xs text-rose-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Upload & Processing Status Progress (hidden initially) -->
            <div id="processingCard" class="hidden bg-slate-50 border border-slate-200 rounded-xl p-5 space-y-3">
                <div class="flex items-center justify-between text-xs">
                    <span id="processStatusText" class="font-semibold text-slate-800 flex items-center">
                        <svg class="animate-spin -ml-0.5 mr-2 h-4 w-4 text-rose-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        Mengunggah dan memproses dokumen...
                    </span>
                    <span id="processPercentText" class="font-mono text-slate-500">0%</span>
                </div>
                <div class="w-full bg-slate-200 rounded-full h-2 overflow-hidden">
                    <div id="progressBar" class="bg-rose-600 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                </div>
                <p id="processSubText" class="text-2xs text-slate-500">
                    Sistem sedang mengonversi dokumen dan mendeteksi koordinat teks secara dinamis via Poppler...
                </p>
            </div>

            <!-- Error Banner (hidden initially) -->
            <div id="errorBox" class="hidden rounded-xl border border-rose-200 bg-rose-50 p-4 space-y-2">
                <div class="flex items-start space-x-3 text-rose-800">
                    <svg class="w-5 h-5 text-rose-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <div class="flex-1">
                        <h4 class="text-sm font-semibold">Proses Dokumen Gagal</h4>
                        <p id="errorMessage" class="text-xs text-rose-700 mt-0.5"></p>
                    </div>
                </div>
                <div class="pt-2 flex justify-end">
                    <button type="button" id="retryBtn" class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold text-rose-700 bg-white border border-rose-300 hover:bg-rose-50 transition-colors">
                        <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        Coba Lagi
                    </button>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="pt-2">
                <button type="submit" id="submitBtn" disabled
                        class="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-xl shadow-sm text-sm font-semibold text-white bg-rose-600 hover:bg-rose-700 disabled:opacity-50 disabled:cursor-not-allowed transition-all">
                    <span>Lanjutkan ke Editor Garis Draf & Stempel &rarr;</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Security & Notice Footer -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs text-slate-500">
        <div class="p-4 rounded-xl bg-white border border-slate-200">
            <span class="font-semibold text-slate-800 block mb-1">Penyimpanan Privat & Terenkripsi</span>
            Semua dokumen asli dan draf disimpan dalam direktori privat terlindungi dan hanya dapat diakses oleh Anda.
        </div>
        <div class="p-4 rounded-xl bg-white border border-slate-200">
            <span class="font-semibold text-slate-800 block mb-1">Deteksi Koordinat Dinamis</span>
            Garis vertikal akan diposisikan otomatis sesuai struktur teks aktual (paragraf, bullet, nomor) dengan jarak presisi.
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const dropZone = document.getElementById('dropZone');
    const input = document.getElementById('documentInput');
    const dropPrompt = document.getElementById('dropPrompt');
    const selectedFileInfo = document.getElementById('selectedFileInfo');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const removeFileBtn = document.getElementById('removeFileBtn');
    const submitBtn = document.getElementById('submitBtn');
    const uploadForm = document.getElementById('uploadForm');
    const processingCard = document.getElementById('processingCard');
    const progressBar = document.getElementById('progressBar');
    const processPercentText = document.getElementById('processPercentText');
    const processStatusText = document.getElementById('processStatusText');
    const errorBox = document.getElementById('errorBox');
    const errorMessage = document.getElementById('errorMessage');
    const retryBtn = document.getElementById('retryBtn');

    // Trigger file browser on drop zone click
    dropZone.addEventListener('click', function (e) {
        if (!e.target.closest('#removeFileBtn')) {
            input.click();
        }
    });

    // Drag over styling
    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, (e) => {
            e.preventDefault();
            dropZone.classList.add('border-rose-500', 'bg-rose-50/50');
        });
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, (e) => {
            e.preventDefault();
            dropZone.classList.remove('border-rose-500', 'bg-rose-50/50');
        });
    });

    // File drop
    dropZone.addEventListener('drop', (e) => {
        const dt = e.dataTransfer;
        if (dt.files && dt.files.length > 0) {
            handleFileSelect(dt.files[0]);
        }
    });

    // File select
    input.addEventListener('change', () => {
        if (input.files && input.files.length > 0) {
            handleFileSelect(input.files[0]);
        }
    });

    function formatBytes(bytes) {
        if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
        if (bytes >= 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return bytes + ' B';
    }

    function handleFileSelect(file) {
        const allowedExtensions = ['docx', 'pdf'];
        const ext = file.name.split('.').pop().toLowerCase();

        if (!allowedExtensions.includes(ext)) {
            showError('Hanya file bertipe .docx dan .pdf yang diperbolehkan.');
            return;
        }

        const maxBytes = 25 * 1024 * 1024;
        if (file.size > maxBytes) {
            showError('Ukuran file melebihi batas 25 MB.');
            return;
        }

        hideError();
        fileName.textContent = file.name;
        fileSize.textContent = formatBytes(file.size);

        dropPrompt.classList.add('hidden');
        selectedFileInfo.classList.remove('hidden');
        submitBtn.disabled = false;
    }

    removeFileBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        input.value = '';
        dropPrompt.classList.remove('hidden');
        selectedFileInfo.classList.add('hidden');
        submitBtn.disabled = true;
    });

    function showError(msg) {
        errorMessage.textContent = msg;
        errorBox.classList.remove('hidden');
    }

    function hideError() {
        errorBox.classList.add('hidden');
    }

    retryBtn.addEventListener('click', () => {
        hideError();
        processingCard.classList.add('hidden');
        submitBtn.disabled = false;
    });

    // AJAX upload with progress tracking
    uploadForm.addEventListener('submit', function (e) {
        e.preventDefault();

        if (!input.files || input.files.length === 0) {
            showError('Silakan pilih dokumen terlebih dahulu.');
            return;
        }

        hideError();
        submitBtn.disabled = true;
        processingCard.classList.remove('hidden');
        progressBar.style.width = '15%';
        processPercentText.textContent = '15%';

        const formData = new FormData(uploadForm);
        const xhr = new XMLHttpRequest();
        xhr.open('POST', uploadForm.action, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('X-CSRF-TOKEN', document.querySelector('meta[name="csrf-token"]').getAttribute('content'));

        xhr.upload.onprogress = function (e) {
            if (e.lengthComputable) {
                // Upload is 0 - 60% of the total experience
                const percent = Math.round((e.loaded / e.total) * 60);
                progressBar.style.width = percent + '%';
                processPercentText.textContent = percent + '%';
                if (percent >= 60) {
                    processStatusText.innerHTML = `
                        <svg class="animate-spin -ml-0.5 mr-2 h-4 w-4 text-rose-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        Menganalisis layout & koordinat teks via Poppler...
                    `;
                    progressBar.style.width = '85%';
                    processPercentText.textContent = '85%';
                }
            }
        };

        xhr.onload = function () {
            if (xhr.status >= 200 && xhr.status < 300) {
                progressBar.style.width = '100%';
                processPercentText.textContent = '100%';
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
                processingCard.classList.add('hidden');
                submitBtn.disabled = false;
            }
        };

        xhr.onerror = function () {
            showError('Koneksi jaringan terputus atau server tidak merespons.');
            processingCard.classList.add('hidden');
            submitBtn.disabled = false;
        };

        xhr.send(formData);
    });
});
</script>
@endpush
