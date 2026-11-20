@extends('layouts.app', ['title' => 'Editor Draf & Stempel: ' . $document->title . ' — NotaryDraft'])

@section('content')
<div class="h-[calc(100vh-4.1rem)] flex flex-col bg-slate-100 overflow-hidden">
    <!-- Top Action Toolbar -->
    <div class="bg-white border-b border-slate-200 px-2 sm:px-4 py-2 sm:py-2.5 flex items-center justify-between shrink-0 z-30">
        <div class="flex items-center space-x-1.5 sm:space-x-3 truncate min-w-0 mr-1 sm:mr-3">
            <a href="{{ route('dashboard') }}" class="p-1 sm:p-1.5 text-slate-400 hover:text-slate-800 rounded-lg hover:bg-slate-100 transition-colors shrink-0" title="Kembali ke Dashboard">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                </svg>
            </a>
            <div class="truncate min-w-0">
                <div class="flex items-center space-x-1 sm:space-x-2 min-w-0">
                    <h1 class="text-xs sm:text-sm font-bold text-slate-900 truncate max-w-[110px] xs:max-w-[150px] sm:max-w-xs md:max-w-md">{{ $document->title }}</h1>
                    <span class="inline-flex items-center px-1 py-0.5 rounded text-2xs font-bold uppercase tracking-wider shrink-0 {{ $document->source_type === 'pdf' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-blue-50 text-blue-700 border border-blue-200' }}">
                        {{ $document->source_type }}
                    </span>
                    @if ($document->hasFinalPdf())
                        <span class="hidden md:inline-flex items-center px-2 py-0.5 rounded-full text-2xs font-semibold bg-emerald-100 text-emerald-800 shrink-0">
                            PDF Siap
                        </span>
                    @endif
                </div>
                <div class="text-xs text-slate-400 truncate hidden sm:block">{{ $document->original_filename }} &bull; {{ $document->page_count }} Halaman</div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center space-x-1 sm:space-x-2 shrink-0">
            <span id="saveStatus" class="hidden text-xs text-slate-400 italic mr-1 sm:mr-2 sm:inline">Tersimpan otomatis</span>

            <button type="button" id="saveConfigBtn" class="inline-flex items-center px-2 sm:px-3 py-1.5 rounded-lg text-xs font-semibold text-slate-700 bg-white border border-slate-300 hover:bg-slate-50 shadow-2xs transition-colors" title="Simpan Konfigurasi">
                <svg class="w-3.5 h-3.5 sm:mr-1 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
                <span class="hidden sm:inline">Simpan</span>
            </button>

            <button type="button" id="generatePdfBtn" class="inline-flex items-center px-2 sm:px-4 py-1.5 rounded-lg text-xs font-semibold text-white bg-rose-600 hover:bg-rose-700 shadow-sm shadow-rose-200 transition-colors">
                <svg id="generateSpinner" class="hidden animate-spin -ml-0.5 sm:-ml-1 mr-1 sm:mr-1.5 h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                <svg id="generateIcon" class="w-3.5 h-3.5 sm:mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                </svg>
                <span id="generateBtnText" class="hidden sm:inline">Generate PDF Final</span>
                <span class="sm:hidden">Generate</span>
            </button>

            <a id="downloadBtn" href="{{ route('documents.download', $document) }}"
               class="{{ $document->hasFinalPdf() ? 'inline-flex' : 'hidden' }} items-center px-2 sm:px-3 py-1.5 rounded-lg text-xs font-semibold text-emerald-800 bg-emerald-100 hover:bg-emerald-200 border border-emerald-300 transition-colors">
                <svg class="w-3.5 h-3.5 sm:mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                <span class="hidden sm:inline">Unduh PDF</span>
            </a>
        </div>
    </div>

    <!-- Main Workspace (Split View) -->
    <div class="flex-1 flex overflow-hidden relative">
        <!-- Left Panel: Configuration Tabs (Static Split-View) -->
        <div id="configPanel" class="w-80 bg-white border-r border-slate-200 flex flex-col shrink-0">
            <!-- Tabs Header -->
            <div class="flex border-b border-slate-200 bg-slate-50/70 p-1">
                <button type="button" id="tabDraftBtn" class="flex-1 py-2 text-xs font-semibold rounded-lg text-rose-700 bg-white shadow-2xs transition-all">
                    Garis Draf
                </button>
                <button type="button" id="tabStampBtn" class="flex-1 py-2 text-xs font-semibold rounded-lg text-slate-600 hover:text-slate-900 transition-all">
                    Stempel Notaris
                </button>
            </div>

            <!-- Tab 1: Dynamic Draft Lines -->
            <div id="tabDraftContent" class="flex-1 overflow-y-auto p-4 space-y-5 text-xs">
                <!-- Master Toggle -->
                <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-200">
                    <div>
                        <span class="font-semibold text-slate-800 block text-xs">Aktifkan Garis Draf</span>
                        <span class="text-2xs text-slate-500">Garis vertikal mengikuti koordinat teks</span>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="draftEnabled" class="sr-only peer" {{ ($draftConfig['enabled'] ?? true) ? 'checked' : '' }}>
                        <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-rose-600"></div>
                    </label>
                </div>

                <!-- Color Selection -->
                <div>
                    <label class="block font-semibold text-slate-700 mb-1.5">Warna Garis</label>
                    <div class="flex items-center space-x-2">
                        <input type="color" id="draftColor" value="{{ $draftConfig['color'] ?? '#DC2626' }}" class="w-8 h-8 rounded-lg border border-slate-300 cursor-pointer p-0.5">
                        <input type="text" id="draftColorHex" value="{{ $draftConfig['color'] ?? '#DC2626' }}" class="flex-1 px-2.5 py-1.5 border border-slate-300 rounded-lg font-mono text-xs uppercase">
                    </div>
                    <div class="flex space-x-2 mt-2">
                        <button type="button" class="draft-color-preset px-2 py-1 rounded bg-red-100 text-red-800 text-2xs font-semibold hover:bg-red-200" data-color="#DC2626">Merah Draf</button>
                        <button type="button" class="draft-color-preset px-2 py-1 rounded bg-blue-100 text-blue-800 text-2xs font-semibold hover:bg-blue-200" data-color="#2563EB">Biru</button>
                        <button type="button" class="draft-color-preset px-2 py-1 rounded bg-slate-100 text-slate-800 text-2xs font-semibold hover:bg-slate-200" data-color="#1F2937">Hitam</button>
                    </div>
                </div>

                <!-- Line Thickness Slider -->
                <div>
                    <div class="flex justify-between items-center mb-1">
                        <label for="draftThickness" class="font-semibold text-slate-700">Ketebalan Garis</label>
                        <span id="draftThicknessVal" class="font-mono text-slate-500 font-semibold">{{ $draftConfig['thickness'] ?? 1.2 }} pt</span>
                    </div>
                    <input type="range" id="draftThickness" min="0.5" max="4.0" step="0.1" value="{{ $draftConfig['thickness'] ?? 1.2 }}"
                           class="w-full accent-rose-600">
                </div>

                <!-- Offset Distance from Text Slider -->
                <div>
                    <div class="flex justify-between items-center mb-1">
                        <label for="draftOffset" class="font-semibold text-slate-700">Jarak ke Teks (Offset)</label>
                        <span id="draftOffsetVal" class="font-mono text-slate-500 font-semibold">{{ $draftConfig['offset'] ?? 8.0 }} pt</span>
                    </div>
                    <input type="range" id="draftOffset" min="2.0" max="25.0" step="0.5" value="{{ $draftConfig['offset'] ?? 8.0 }}"
                           class="w-full accent-rose-600">
                    <p class="text-2xs text-slate-400 mt-0.5">Jarak ruang vertikal garis ke tepi kiri teks akta (dinamis mengikuti teks tanpa menumpuk).</p>
                </div>

                <!-- Paragraph Gap Threshold -->
                <div>
                    <div class="flex justify-between items-center mb-1">
                        <label for="draftParagraphThreshold" class="font-semibold text-slate-700">Sensitivitas Jeda Paragraf</label>
                        <span id="draftParagraphThresholdVal" class="font-mono text-slate-500 font-semibold">{{ $draftConfig['paragraph_threshold'] ?? 1.6 }}x</span>
                    </div>
                    <input type="range" id="draftParagraphThreshold" min="1.1" max="3.5" step="0.1" value="{{ $draftConfig['paragraph_threshold'] ?? 1.6 }}"
                           class="w-full accent-rose-600">
                    <p class="text-2xs text-slate-400 mt-0.5">Batas jarak vertikal untuk memutus garis antarparagraf dan nomor item.</p>
                </div>

                <!-- Ignore Header & Footer Toggle -->
                <div class="flex items-center justify-between pt-2 border-t border-slate-200">
                    <div>
                        <span class="font-semibold text-slate-700 block text-xs">Abaikan Header & Footer</span>
                        <span class="text-2xs text-slate-400">Tidak menggambar garis pada nomor halaman atas/bawah</span>
                    </div>
                    <input type="checkbox" id="draftIgnoreHeaderFooter" class="h-4 w-4 text-rose-600 rounded border-slate-300" {{ ($draftConfig['ignore_headers_footers'] ?? true) ? 'checked' : '' }}>
                </div>

                <!-- Line Style: Solid or Dashed -->
                <div class="pt-2 border-t border-slate-200">
                    <label class="block font-semibold text-slate-700 mb-1.5">Gaya Garis</label>
                    <div class="flex space-x-2">
                        <button type="button" id="lineStyleSolid"
                            class="flex-1 py-1.5 rounded-lg border text-xs font-semibold transition-colors
                                {{ ($draftConfig['line_style'] ?? 'dashed') === 'solid' ? 'bg-rose-600 text-white border-rose-600' : 'bg-white text-slate-700 border-slate-300 hover:border-rose-400' }}">
                            <svg class="w-14 h-3 mx-auto" viewBox="0 0 56 12"><line x1="2" y1="6" x2="54" y2="6" stroke="currentColor" stroke-width="2"/></svg>
                            Solid
                        </button>
                        <button type="button" id="lineStyleDashed"
                            class="flex-1 py-1.5 rounded-lg border text-xs font-semibold transition-colors
                                {{ ($draftConfig['line_style'] ?? 'dashed') === 'dashed' ? 'bg-rose-600 text-white border-rose-600' : 'bg-white text-slate-700 border-slate-300 hover:border-rose-400' }}">
                            <svg class="w-14 h-3 mx-auto" viewBox="0 0 56 12"><line x1="2" y1="6" x2="54" y2="6" stroke="currentColor" stroke-width="2" stroke-dasharray="6 4"/></svg>
                            Putus-putus
                        </button>
                    </div>
                    <input type="hidden" id="draftLineStyle" value="{{ $draftConfig['line_style'] ?? 'dashed' }}">
                </div>

                <!-- Dash Size Controls (shown only when dashed) -->
                <div id="dashControls" class="{{ ($draftConfig['line_style'] ?? 'dashed') === 'solid' ? 'hidden' : '' }} space-y-2">
                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <label for="dashLength" class="font-semibold text-slate-700">Panjang Garis Putus</label>
                            <span id="dashLengthVal" class="font-mono text-slate-500 font-semibold">{{ $draftConfig['dash_length'] ?? 4.0 }} pt</span>
                        </div>
                        <input type="range" id="dashLength" min="2" max="12" step="0.5" value="{{ $draftConfig['dash_length'] ?? 4.0 }}"
                               class="w-full accent-rose-600">
                    </div>
                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <label for="dashGap" class="font-semibold text-slate-700">Jarak antar Garis</label>
                            <span id="dashGapVal" class="font-mono text-slate-500 font-semibold">{{ $draftConfig['dash_gap'] ?? 3.0 }} pt</span>
                        </div>
                        <input type="range" id="dashGap" min="1" max="10" step="0.5" value="{{ $draftConfig['dash_gap'] ?? 3.0 }}"
                               class="w-full accent-rose-600">
                    </div>
                </div>

                <!-- Page Selection for Draft Lines -->
                <div class="pt-2 border-t border-slate-200">
                    <label class="block font-semibold text-slate-700 mb-1.5">Terapkan pada Halaman</label>
                    <select id="draftTargetPages" class="w-full px-2.5 py-1.5 border border-slate-300 rounded-lg text-xs text-slate-700 focus:ring-rose-500 focus:border-rose-500">
                        <option value="all_pages" {{ ($draftConfig['target_pages'] ?? '') === 'all_pages' ? 'selected' : '' }}>Semua Halaman</option>
                        <option value="first_page" {{ ($draftConfig['target_pages'] ?? '') === 'first_page' ? 'selected' : '' }}>Halaman Pertama Saja</option>
                        <option value="custom" {{ ($draftConfig['target_pages'] ?? '') === 'custom' ? 'selected' : '' }}>Halaman Tertentu (Rentang)</option>
                    </select>

                    <div id="draftCustomPagesBox" class="{{ ($draftConfig['target_pages'] ?? '') === 'custom' ? '' : 'hidden' }} mt-2">
                        <input type="text" id="draftCustomPages" value="{{ $draftConfig['custom_pages'] ?? '' }}" placeholder="Contoh: 1, 3, 5-8"
                               class="w-full px-2.5 py-1.5 border border-slate-300 rounded-lg text-xs placeholder-slate-400">
                    </div>
                </div>

                <!-- Info summary -->
                <div class="p-3 bg-rose-50/50 rounded-xl border border-rose-100 text-2xs text-rose-800 space-y-1">
                    <div class="font-semibold">Deteksi Struktur Teks Aktif:</div>
                    <ul class="list-disc pl-4 space-y-0.5 text-rose-700">
                        <li>Pengelompokan baris dalam paragraf sama</li>
                        <li>Pemisahan otomatis pada item bernomor (1., 2., a.)</li>
                        <li>Tidak menggambar garis pada ruang kosong</li>
                    </ul>
                </div>
            </div>

            <!-- Tab 2: Dynamic Stamp Editor -->
            <div id="tabStampContent" class="hidden flex-1 overflow-y-auto p-4 space-y-5 text-xs">
                <!-- 3 Required Red Elements Design Notice -->
                <div class="p-3 rounded-xl bg-amber-50 border border-amber-200 text-2xs text-amber-900">
                    <div class="font-semibold flex items-center mb-1">
                        <svg class="w-3.5 h-3.5 mr-1 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        Ketentuan Desain Stempel Notaris Resmi
                    </div>
                    Stempel wajib terdiri dari 3 elemen merah tanpa kotak, lingkaran, atau dekorasi luar:
                    <ol class="list-decimal pl-4 mt-1 space-y-0.5">
                        <li>Nama Notaris (Merah)</li>
                        <li>Satu Garis Horizontal (Merah)</li>
                        <li>Teks NOTARIS KOTA DENPASAR (Merah)</li>
                    </ol>
                </div>

                <!-- Notary Name Input -->
                <div>
                    <label for="stampNotaryName" class="block font-semibold text-slate-700 mb-1">Nama Notaris</label>
                    <input type="text" id="stampNotaryName" value="{{ $stampConfig['notary_name'] ?? 'ROBERT, S.H., M.Kn.' }}"
                           class="w-full px-2.5 py-1.5 border border-slate-300 rounded-lg text-xs font-semibold text-rose-700 uppercase focus:ring-rose-500 focus:border-rose-500">
                </div>

                <!-- Bottom Title Text Input -->
                <div>
                    <label for="stampCityTitle" class="block font-semibold text-slate-700 mb-1">Teks Jabatan & Wilayah</label>
                    <input type="text" id="stampCityTitle" value="{{ $stampConfig['city_title'] ?? 'NOTARIS KOTA DENPASAR' }}"
                           class="w-full px-2.5 py-1.5 border border-slate-300 rounded-lg text-xs font-semibold text-rose-700 uppercase focus:ring-rose-500 focus:border-rose-500">
                </div>

                <!-- Preset Positions -->
                <div>
                    <label class="block font-semibold text-slate-700 mb-1.5">Preset Posisi</label>
                    <div class="grid grid-cols-2 gap-2">
                        <button type="button" class="stamp-preset-btn px-2.5 py-1.5 rounded-lg border border-rose-300 bg-rose-50 hover:bg-rose-100 text-left transition-colors" data-preset="top_left">
                            <span class="font-semibold block text-rose-900">Kiri Atas (Default)</span>
                            <span class="text-2xs text-rose-600">Marjin atas dokumen</span>
                        </button>
                        <button type="button" class="stamp-preset-btn px-2.5 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-rose-50 hover:border-rose-300 text-left transition-colors" data-preset="bottom_left">
                            <span class="font-semibold block text-slate-800">Kiri Bawah</span>
                            <span class="text-2xs text-slate-400">Sisi penutup kiri</span>
                        </button>
                        <button type="button" class="stamp-preset-btn px-2.5 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-rose-50 hover:border-rose-300 text-left transition-colors" data-preset="bottom_center">
                            <span class="font-semibold block text-slate-800">Tengah Bawah</span>
                            <span class="text-2xs text-slate-400">Pusat kaki halaman</span>
                        </button>
                        <button type="button" class="stamp-preset-btn px-2.5 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-rose-50 hover:border-rose-300 text-left transition-colors" data-preset="signature_area">
                            <span class="font-semibold block text-slate-800">Area Tanda Tangan</span>
                            <span class="text-2xs text-slate-400">Deteksi kata kunci</span>
                        </button>
                    </div>
                    <p class="text-2xs text-slate-400 mt-1">Preset adalah titik awal. Anda dapat menyeret (drag-and-drop) stempel di preview untuk penempatan presisi.</p>
                </div>

                <!-- Target Pages for Stamp -->
                <div>
                    <label class="block font-semibold text-slate-700 mb-1.5">Halaman Stempel</label>
                    <select id="stampTargetPages" class="w-full px-2.5 py-1.5 border border-slate-300 rounded-lg text-xs text-slate-700 focus:ring-rose-500 focus:border-rose-500">
                        <option value="first_page" {{ ($stampConfig['target_pages'] ?? '') === 'first_page' ? 'selected' : '' }}>Halaman Pertama Saja</option>
                        <option value="all_pages" {{ ($stampConfig['target_pages'] ?? '') === 'all_pages' ? 'selected' : '' }}>Semua Halaman</option>
                        <option value="custom" {{ ($stampConfig['target_pages'] ?? '') === 'custom' ? 'selected' : '' }}>Halaman Tertentu (Rentang)</option>
                    </select>

                    <div id="stampCustomPagesBox" class="{{ ($stampConfig['target_pages'] ?? '') === 'custom' ? '' : 'hidden' }} mt-2">
                        <input type="text" id="stampCustomPages" value="{{ $stampConfig['custom_pages'] ?? '' }}" placeholder="Contoh: 1, 3, 5-8"
                               class="w-full px-2.5 py-1.5 border border-slate-300 rounded-lg text-xs placeholder-slate-400">
                    </div>
                </div>

                <!-- Stamp Sizing & Geometry Sliders -->
                <div class="space-y-3 pt-2 border-t border-slate-200">
                    <span class="font-semibold text-slate-800 block text-xs">Ukuran & Geometri Stempel</span>

                    <!-- Line Length (Width) -->
                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <label for="stampLineLength" class="text-slate-600">Panjang Garis (Lebar)</label>
                            <span id="stampLineLengthVal" class="font-mono text-slate-500">{{ $stampConfig['line_length'] ?? 180 }} pt</span>
                        </div>
                        <input type="range" id="stampLineLength" min="100" max="320" step="5" value="{{ $stampConfig['line_length'] ?? 180 }}" class="w-full accent-rose-600">
                    </div>

                    <!-- Font Size Name -->
                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <label for="stampFontSizeName" class="text-slate-600">Ukuran Teks Nama</label>
                            <span id="stampFontSizeNameVal" class="font-mono text-slate-500">{{ $stampConfig['font_size_name'] ?? 11 }} pt</span>
                        </div>
                        <input type="range" id="stampFontSizeName" min="8" max="18" step="0.5" value="{{ $stampConfig['font_size_name'] ?? 11 }}" class="w-full accent-rose-600">
                    </div>

                    <!-- Font Size City -->
                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <label for="stampFontSizeCity" class="text-slate-600">Ukuran Teks Wilayah</label>
                            <span id="stampFontSizeCityVal" class="font-mono text-slate-500">{{ $stampConfig['font_size_city'] ?? 9 }} pt</span>
                        </div>
                        <input type="range" id="stampFontSizeCity" min="6" max="14" step="0.5" value="{{ $stampConfig['font_size_city'] ?? 9 }}" class="w-full accent-rose-600">
                    </div>

                    <!-- Line Thickness -->
                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <label for="stampLineThickness" class="text-slate-600">Ketebalan Garis Horizontal</label>
                            <span id="stampLineThicknessVal" class="font-mono text-slate-500">{{ $stampConfig['line_thickness'] ?? 1.2 }} pt</span>
                        </div>
                        <input type="range" id="stampLineThickness" min="0.5" max="3.0" step="0.1" value="{{ $stampConfig['line_thickness'] ?? 1.2 }}" class="w-full accent-rose-600">
                    </div>

                    <!-- Element Spacing -->
                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <label for="stampSpacing" class="text-slate-600">Jarak Antarelemen</label>
                            <span id="stampSpacingVal" class="font-mono text-slate-500">{{ $stampConfig['element_spacing'] ?? 4 }} pt</span>
                        </div>
                        <input type="range" id="stampSpacing" min="1" max="10" step="0.5" value="{{ $stampConfig['element_spacing'] ?? 4 }}" class="w-full accent-rose-600">
                    </div>
                </div>

                <!-- Position X & Y in percent -->
                <div class="pt-2 border-t border-slate-200">
                    <span class="font-semibold text-slate-800 block text-xs mb-2">Koordinat Posisi Relatif Halaman</span>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="stampXPercent" class="text-2xs text-slate-500 block">Posisi X (%)</label>
                            <input type="number" id="stampXPercent" min="0" max="95" step="0.1" value="{{ $stampConfig['x_percent'] ?? 8.0 }}"
                                   class="w-full px-2.5 py-1.5 border border-slate-300 rounded-lg text-xs font-mono">
                        </div>
                        <div>
                            <label for="stampYPercent" class="text-2xs text-slate-500 block">Posisi Y (%)</label>
                            <input type="number" id="stampYPercent" min="0" max="95" step="0.1" value="{{ $stampConfig['y_percent'] ?? 8.0 }}"
                                   class="w-full px-2.5 py-1.5 border border-slate-300 rounded-lg text-xs font-mono">
                        </div>
                    </div>
                    <p class="text-2xs text-slate-400 mt-1">Koordinat disimpan relatif (% ukuran halaman) agar proporsi akurat pada portrait, landscape, maupun legal.</p>
                </div>
            </div>
        </div>

        <!-- Right Panel: Interactive PDF.js Canvas & SVG Overlay -->
        <div class="flex-1 flex flex-col min-w-0 bg-slate-200/90 overflow-hidden">
            <!-- Viewer Toolbar -->
            <div class="bg-white/95 backdrop-blur-xs border-b border-slate-200 px-2 sm:px-4 py-1.5 sm:py-2 flex items-center justify-between shrink-0 shadow-2xs z-10">
                <!-- Page Navigation -->
                <div class="flex items-center space-x-1 sm:space-x-1.5">
                    <button type="button" id="prevPageBtn" class="p-1 sm:p-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed" title="Halaman Sebelumnya">
                        <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                        </svg>
                    </button>
                    <div class="flex items-center space-x-0.5 sm:space-x-1 text-xs font-medium text-slate-700">
                        <span class="hidden sm:inline">Halaman</span>
                        <input type="number" id="currentPageInput" min="1" max="{{ $document->page_count }}" value="1"
                               class="w-8 sm:w-12 px-1 py-0.5 sm:py-1 text-center font-mono text-xs border border-slate-300 rounded-md">
                        <span>/<span id="totalPagesText">{{ $document->page_count }}</span></span>
                    </div>
                    <button type="button" id="nextPageBtn" class="p-1 sm:p-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed" title="Halaman Berikutnya">
                        <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>
                    </button>
                </div>

                <!-- Zoom Controls -->
                <div class="flex items-center space-x-1 sm:space-x-2">
                    <button type="button" id="zoomOutBtn" class="p-1 sm:p-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50" title="Perkecil">
                        <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12h-15" />
                        </svg>
                    </button>
                    <span id="zoomLevelText" class="text-xs font-mono font-medium text-slate-700 min-w-8 sm:min-w-12 text-center">100%</span>
                    <button type="button" id="zoomInBtn" class="p-1 sm:p-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50" title="Perbesar">
                        <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                    </button>
                    <button type="button" id="fitWidthBtn" class="px-1.5 sm:px-2.5 py-1 text-2xs sm:text-xs font-medium rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 shadow-2xs whitespace-nowrap" title="Sesuaikan dengan lebar layar">
                        Pas Lebar
                    </button>
                </div>
            </div>

            <!-- Notice for scanned PDF -->
            @if (!empty($document->error_message))
                <div class="bg-amber-50 border-b border-amber-200 px-4 py-2 text-xs text-amber-800 flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>{{ $document->error_message }}</span>
                    </div>
                </div>
            @endif

            <!-- PDF Viewer Stage Container -->
            <div id="viewerScrollContainer" class="flex-1 overflow-auto p-2 sm:p-6 md:p-8 flex justify-center items-start">
                <!-- Wrapper for Canvas & Overlay -->
                <div id="pageStage" class="relative bg-white shadow-xl rounded-sm transition-transform duration-75 min-w-[260px] min-h-[360px]">
                    <!-- PDF.js Canvas -->
                    <canvas id="pdfCanvas" class="block"></canvas>

                    <!-- Interactive SVG Overlay Layer for Dynamic Draft Lines & Stamp -->
                    <svg id="overlaySvg" class="absolute inset-0 w-full h-full pointer-events-none" xmlns="http://www.w3.org/2000/svg">
                        <!-- Dynamic Draft Lines Group -->
                        <g id="draftLinesGroup"></g>

                        <!-- Dynamic Notary Stamp Group (Draggable) -->
                        <g id="stampGroup" class="pointer-events-auto cursor-move select-none" style="display: none;">
                            <!-- Transparent Grab Area for smooth dragging -->
                            <rect id="stampHitRect" fill="transparent" class="hover:stroke-rose-400/50 hover:stroke-dashed"></rect>
                            <!-- Line 1: Notary Name -->
                            <text id="stampNameText" font-family="Helvetica, Arial, sans-serif" font-weight="bold" fill="#DC2626"></text>
                            <!-- Line 2: Single Horizontal Red Line -->
                            <line id="stampDividerLine" stroke="#DC2626"></line>
                            <!-- Line 3: NOTARIS KOTA DENPASAR -->
                            <text id="stampCityText" font-family="Helvetica, Arial, sans-serif" font-weight="bold" fill="#DC2626"></text>
                        </g>
                    </svg>

                    <!-- Loading Indicator Overlay -->
                    <div id="canvasLoader" class="absolute inset-0 bg-white/80 backdrop-blur-2xs flex flex-col items-center justify-center space-y-2 z-10">
                        <svg class="animate-spin h-8 w-8 text-rose-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span class="text-xs font-semibold text-slate-700">Merender halaman PDF...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<!-- Load PDF.js from Cloudflare CDN (Universally supported on cPanel & local) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
    if (window.pdfjsLib) {
        window.pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    }
</script>
<script>
    const pdfjsLib = window.pdfjsLib;

    // Application State passed from Backend
    const PDF_STREAM_URL = '{{ route("documents.stream", $document) }}';
    const CONFIG_UPDATE_URL = '{{ route("documents.config", $document) }}';
    const GENERATE_URL = '{{ route("documents.generate", $document) }}';

    const rawLayoutData = @json($layoutData);
    let draftConfig = @json($draftConfig);
    draftConfig.line_style = draftConfig.line_style || 'dashed';
    draftConfig.dash_length = draftConfig.dash_length ? parseFloat(draftConfig.dash_length) : 4.0;
    draftConfig.dash_gap = draftConfig.dash_gap ? parseFloat(draftConfig.dash_gap) : 3.0;
    let stampConfig = @json($stampConfig);
    let calculatedDraftLines = @json($calculatedDraftLines);
    let calculatedStamp = @json($calculatedStamp);

    let pdfDoc = null;
    let currentPage = 1;
    let totalPages = {{ $document->page_count ?: 1 }};
    let currentScale = 1.0;
    let isRendering = false;
    let renderPending = null;
    let currentRenderTask = null;

    // DOM References
    const pdfCanvas = document.getElementById('pdfCanvas');
    const overlaySvg = document.getElementById('overlaySvg');
    const draftLinesGroup = document.getElementById('draftLinesGroup');
    const stampGroup = document.getElementById('stampGroup');
    const stampHitRect = document.getElementById('stampHitRect');
    const stampNameText = document.getElementById('stampNameText');
    const stampDividerLine = document.getElementById('stampDividerLine');
    const stampCityText = document.getElementById('stampCityText');
    const canvasLoader = document.getElementById('canvasLoader');
    const pageStage = document.getElementById('pageStage');
    const viewerScrollContainer = document.getElementById('viewerScrollContainer');

    // Toolbar Controls
    const prevPageBtn = document.getElementById('prevPageBtn');
    const nextPageBtn = document.getElementById('nextPageBtn');
    const currentPageInput = document.getElementById('currentPageInput');
    const totalPagesText = document.getElementById('totalPagesText');
    const zoomInBtn = document.getElementById('zoomInBtn');
    const zoomOutBtn = document.getElementById('zoomOutBtn');
    const zoomLevelText = document.getElementById('zoomLevelText');
    const fitWidthBtn = document.getElementById('fitWidthBtn');

    // Tab buttons
    const tabDraftBtn = document.getElementById('tabDraftBtn');
    const tabStampBtn = document.getElementById('tabStampBtn');
    const tabDraftContent = document.getElementById('tabDraftContent');
    const tabStampContent = document.getElementById('tabStampContent');

    // Inputs: Draft Lines
    const draftEnabled = document.getElementById('draftEnabled');
    const draftColor = document.getElementById('draftColor');
    const draftColorHex = document.getElementById('draftColorHex');
    const draftThickness = document.getElementById('draftThickness');
    const draftThicknessVal = document.getElementById('draftThicknessVal');
    const draftOffset = document.getElementById('draftOffset');
    const draftOffsetVal = document.getElementById('draftOffsetVal');
    const draftParagraphThreshold = document.getElementById('draftParagraphThreshold');
    const draftParagraphThresholdVal = document.getElementById('draftParagraphThresholdVal');
    const draftIgnoreHeaderFooter = document.getElementById('draftIgnoreHeaderFooter');
    const draftTargetPages = document.getElementById('draftTargetPages');
    const draftCustomPagesBox = document.getElementById('draftCustomPagesBox');
    const draftCustomPages = document.getElementById('draftCustomPages');
    const draftLineStyle = document.getElementById('draftLineStyle');
    const lineStyleSolid = document.getElementById('lineStyleSolid');
    const lineStyleDashed = document.getElementById('lineStyleDashed');
    const dashControls = document.getElementById('dashControls');
    const dashLength = document.getElementById('dashLength');
    const dashLengthVal = document.getElementById('dashLengthVal');
    const dashGap = document.getElementById('dashGap');
    const dashGapVal = document.getElementById('dashGapVal');

    // Inputs: Stamp
    const stampNotaryName = document.getElementById('stampNotaryName');
    const stampCityTitle = document.getElementById('stampCityTitle');
    const stampTargetPages = document.getElementById('stampTargetPages');
    const stampCustomPagesBox = document.getElementById('stampCustomPagesBox');
    const stampCustomPages = document.getElementById('stampCustomPages');
    const stampLineLength = document.getElementById('stampLineLength');
    const stampLineLengthVal = document.getElementById('stampLineLengthVal');
    const stampFontSizeName = document.getElementById('stampFontSizeName');
    const stampFontSizeNameVal = document.getElementById('stampFontSizeNameVal');
    const stampFontSizeCity = document.getElementById('stampFontSizeCity');
    const stampFontSizeCityVal = document.getElementById('stampFontSizeCityVal');
    const stampLineThickness = document.getElementById('stampLineThickness');
    const stampLineThicknessVal = document.getElementById('stampLineThicknessVal');
    const stampSpacing = document.getElementById('stampSpacing');
    const stampSpacingVal = document.getElementById('stampSpacingVal');
    const stampXPercent = document.getElementById('stampXPercent');
    const stampYPercent = document.getElementById('stampYPercent');

    // Save & Generate
    const saveConfigBtn = document.getElementById('saveConfigBtn');
    const generatePdfBtn = document.getElementById('generatePdfBtn');
    const generateBtnText = document.getElementById('generateBtnText');
    const generateSpinner = document.getElementById('generateSpinner');
    const generateIcon = document.getElementById('generateIcon');
    const downloadBtn = document.getElementById('downloadBtn');
    const saveStatus = document.getElementById('saveStatus');

    // 1. Initialize Tabs
    tabDraftBtn.addEventListener('click', () => {
        tabDraftBtn.classList.add('bg-white', 'text-rose-700', 'shadow-2xs');
        tabDraftBtn.classList.remove('text-slate-600');
        tabStampBtn.classList.remove('bg-white', 'text-rose-700', 'shadow-2xs');
        tabStampBtn.classList.add('text-slate-600');
        tabDraftContent.classList.remove('hidden');
        tabStampContent.classList.add('hidden');
    });

    tabStampBtn.addEventListener('click', () => {
        tabStampBtn.classList.add('bg-white', 'text-rose-700', 'shadow-2xs');
        tabStampBtn.classList.remove('text-slate-600');
        tabDraftBtn.classList.remove('bg-white', 'text-rose-700', 'shadow-2xs');
        tabDraftBtn.classList.add('text-slate-600');
        tabStampContent.classList.remove('hidden');
        tabDraftContent.classList.add('hidden');
    });

    // Color sync
    draftColor.addEventListener('input', (e) => {
        draftColorHex.value = e.target.value;
        onDraftConfigChanged();
    });
    draftColorHex.addEventListener('input', (e) => {
        if (/^#[0-9a-fA-F]{6}$/.test(e.target.value)) {
            draftColor.value = e.target.value;
            onDraftConfigChanged();
        }
    });
    document.querySelectorAll('.draft-color-preset').forEach(btn => {
        btn.addEventListener('click', () => {
            const col = btn.getAttribute('data-color');
            draftColor.value = col;
            draftColorHex.value = col;
            onDraftConfigChanged();
        });
    });

    // Sliders display update & trigger
    draftThickness.addEventListener('input', (e) => {
        draftThicknessVal.textContent = e.target.value + ' pt';
        onDraftConfigChanged();
    });
    draftOffset.addEventListener('input', (e) => {
        draftOffsetVal.textContent = e.target.value + ' pt';
        onDraftConfigChanged();
    });
    draftParagraphThreshold.addEventListener('input', (e) => {
        draftParagraphThresholdVal.textContent = e.target.value + 'x';
        onDraftConfigChanged();
    });
    draftEnabled.addEventListener('change', onDraftConfigChanged);
    draftIgnoreHeaderFooter.addEventListener('change', onDraftConfigChanged);
    draftTargetPages.addEventListener('change', () => {
        draftCustomPagesBox.classList.toggle('hidden', draftTargetPages.value !== 'custom');
        onDraftConfigChanged();
    });
    draftCustomPages.addEventListener('input', debounce(onDraftConfigChanged, 500));

    // Line style toggle (solid vs dashed)
    function setLineStyleUI(style) {
        draftLineStyle.value = style;
        const isDashed = style === 'dashed';
        const activeClasses = ['bg-rose-600', 'text-white', 'border-rose-600'];
        const inactiveClasses = ['bg-white', 'text-slate-700', 'border-slate-300'];
        // Solid button
        lineStyleSolid.classList.toggle('bg-rose-600', !isDashed);
        lineStyleSolid.classList.toggle('text-white', !isDashed);
        lineStyleSolid.classList.toggle('border-rose-600', !isDashed);
        lineStyleSolid.classList.toggle('bg-white', isDashed);
        lineStyleSolid.classList.toggle('text-slate-700', isDashed);
        lineStyleSolid.classList.toggle('border-slate-300', isDashed);
        // Dashed button
        lineStyleDashed.classList.toggle('bg-rose-600', isDashed);
        lineStyleDashed.classList.toggle('text-white', isDashed);
        lineStyleDashed.classList.toggle('border-rose-600', isDashed);
        lineStyleDashed.classList.toggle('bg-white', !isDashed);
        lineStyleDashed.classList.toggle('text-slate-700', !isDashed);
        lineStyleDashed.classList.toggle('border-slate-300', !isDashed);
        // Show/hide dash controls
        dashControls.classList.toggle('hidden', !isDashed);
    }
    if (lineStyleSolid) lineStyleSolid.addEventListener('click', () => { setLineStyleUI('solid'); onDraftConfigChanged(); });
    if (lineStyleDashed) lineStyleDashed.addEventListener('click', () => { setLineStyleUI('dashed'); onDraftConfigChanged(); });
    if (dashLength) dashLength.addEventListener('input', (e) => { dashLengthVal.textContent = e.target.value + ' pt'; onDraftConfigChanged(); });
    if (dashGap) dashGap.addEventListener('input', (e) => { dashGapVal.textContent = e.target.value + ' pt'; onDraftConfigChanged(); });
    // Initialize line style buttons UI based on initial configuration
    setLineStyleUI(draftConfig.line_style || 'dashed');

    // Stamp sliders
    stampNotaryName.addEventListener('input', onStampConfigChanged);
    stampCityTitle.addEventListener('input', onStampConfigChanged);
    stampTargetPages.addEventListener('change', () => {
        stampCustomPagesBox.classList.toggle('hidden', stampTargetPages.value !== 'custom');
        onStampConfigChanged();
    });
    stampCustomPages.addEventListener('input', debounce(onStampConfigChanged, 500));
    stampLineLength.addEventListener('input', (e) => {
        stampLineLengthVal.textContent = e.target.value + ' pt';
        onStampConfigChanged();
    });
    stampFontSizeName.addEventListener('input', (e) => {
        stampFontSizeNameVal.textContent = e.target.value + ' pt';
        onStampConfigChanged();
    });
    stampFontSizeCity.addEventListener('input', (e) => {
        stampFontSizeCityVal.textContent = e.target.value + ' pt';
        onStampConfigChanged();
    });
    stampLineThickness.addEventListener('input', (e) => {
        stampLineThicknessVal.textContent = e.target.value + ' pt';
        onStampConfigChanged();
    });
    stampSpacing.addEventListener('input', (e) => {
        stampSpacingVal.textContent = e.target.value + ' pt';
        onStampConfigChanged();
    });
    stampXPercent.addEventListener('input', onStampCoordsInput);
    stampYPercent.addEventListener('input', onStampCoordsInput);

    // Preset buttons
    document.querySelectorAll('.stamp-preset-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const preset = btn.getAttribute('data-preset');
            stampConfig.preset = preset;
            sendConfigUpdate();
        });
    });

    // Helper to calculate fit-to-width scale based on current container width
    function calculateFitWidthScale(unscaledWidth) {
        const horizontalPadding = window.innerWidth < 640 ? 16 : 48;
        const containerWidth = Math.max(180, (viewerScrollContainer.clientWidth || window.innerWidth) - horizontalPadding);
        return Math.max(0.25, Math.min(3.0, containerWidth / unscaledWidth));
    }

    // 2. Load PDF via PDF.js using ArrayBuffer (reliable across all browsers and devices)
    fetch(PDF_STREAM_URL, { credentials: 'same-origin' })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.arrayBuffer();
        })
        .then(arrayBuffer => {
            return pdfjsLib.getDocument({
                data: arrayBuffer,
            }).promise;
        })
        .then(doc => {
            pdfDoc = doc;
            totalPages = doc.numPages;
            totalPagesText.textContent = totalPages;
            currentPageInput.max = totalPages;

            // If layout data is missing from backend (e.g. cPanel without proc_open), extract via PDF.js!
            const needsLayout = !rawLayoutData || rawLayoutData.fallback_mode || !rawLayoutData.pages || !rawLayoutData.pages[1] || !rawLayoutData.pages[1].blocks || rawLayoutData.pages[1].blocks.length === 0;
            if (needsLayout) {
                extractAndSyncLayout(doc);
            }

            return doc.getPage(1);
        })
        .then(firstPage => {
            const unscaled = firstPage.getViewport({ scale: 1.0 });
            if (window.innerWidth < 1024) {
                currentScale = calculateFitWidthScale(unscaled.width);
                zoomLevelText.textContent = Math.round(currentScale * 100) + '%';
            }
            renderPage(1);
        })
        .catch(err => {
            console.error('Error loading PDF:', err);
            canvasLoader.innerHTML = `<div class="p-4 text-center"><span class="text-xs text-rose-600 font-semibold block">Gagal memuat file PDF</span><span class="text-2xs text-slate-500 mt-1 block">${err.message || 'Silakan refresh halaman'}</span></div>`;
        });

    // Auto extract layout from PDF.js client-side if server hosting does not have poppler/proc_open
    async function extractAndSyncLayout(doc) {
        try {
            const pages = {};
            for (let p = 1; p <= doc.numPages; p++) {
                const page = await doc.getPage(p);
                const unscaled = page.getViewport({ scale: 1.0 });
                const textContent = await page.getTextContent();

                const lineMap = new Map();
                const yTolerance = 4.0;

                for (const item of textContent.items) {
                    const str = item.str;
                    if (!str || !str.trim()) continue;

                    const tx = item.transform[4];
                    const ty = unscaled.height - item.transform[5] - (item.height || 10);
                    const w = item.width || 0;
                    const h = item.height || 10;

                    let foundKey = null;
                    for (const key of lineMap.keys()) {
                        if (Math.abs(key - ty) <= yTolerance) {
                            foundKey = key;
                            break;
                        }
                    }

                    const targetKey = foundKey !== null ? foundKey : ty;
                    if (!lineMap.has(targetKey)) {
                        lineMap.set(targetKey, []);
                    }
                    lineMap.get(targetKey).push({
                        xMin: tx,
                        yMin: ty,
                        xMax: tx + w,
                        yMax: ty + h,
                        text: str
                    });
                }

                const sortedYKeys = Array.from(lineMap.keys()).sort((a, b) => a - b);
                const lines = [];
                let blockMinX = 999999, blockMinY = 999999, blockMaxX = 0, blockMaxY = 0;

                for (const yKey of sortedYKeys) {
                    const items = lineMap.get(yKey).sort((a, b) => a.xMin - b.xMin);
                    const lineXMin = items[0].xMin;
                    const lineXMax = items[items.length - 1].xMax;
                    const lineYMin = Math.min(...items.map(it => it.yMin));
                    const lineYMax = Math.max(...items.map(it => it.yMax));
                    const lineText = items.map(it => it.text).join(' ');

                    blockMinX = Math.min(blockMinX, lineXMin);
                    blockMinY = Math.min(blockMinY, lineYMin);
                    blockMaxX = Math.max(blockMaxX, lineXMax);
                    blockMaxY = Math.max(blockMaxY, lineYMax);

                    lines.push({
                        xMin: Math.round(lineXMin * 100) / 100,
                        yMin: Math.round(lineYMin * 100) / 100,
                        xMax: Math.round(lineXMax * 100) / 100,
                        yMax: Math.round(lineYMax * 100) / 100,
                        text: lineText,
                        is_list_item: /^[\u2022\u25cf\-\*]|\d+[\.\)]|[a-zA-Z][\.\)]/u.test(lineText.trim()),
                        is_header: lineYMin < 50,
                        is_footer: lineYMin > (unscaled.height - 50)
                    });
                }

                pages[p] = {
                    page_number: p,
                    width: Math.round(unscaled.width * 100) / 100,
                    height: Math.round(unscaled.height * 100) / 100,
                    orientation: unscaled.width > unscaled.height ? 'L' : 'P',
                    word_count: lines.length,
                    line_count: lines.length,
                    is_scanned: lines.length === 0,
                    blocks: lines.length > 0 ? [{
                        xMin: Math.round(blockMinX * 100) / 100,
                        yMin: Math.round(blockMinY * 100) / 100,
                        xMax: Math.round(blockMaxX * 100) / 100,
                        yMax: Math.round(blockMaxY * 100) / 100,
                        lines: lines
                    }] : []
                };
            }

            const clientLayout = {
                page_count: doc.numPages,
                is_scanned: false,
                fallback_mode: false,
                pages: pages
            };

            const response = await fetch(CONFIG_UPDATE_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    layout_data: clientLayout,
                    draft_lines: draftConfig,
                    stamp: stampConfig,
                }),
            });

            if (response.ok) {
                const data = await response.json();
                if (data.draft_lines) {
                    calculatedDraftLines = data.draft_lines;
                }
                if (data.stamp) {
                    calculatedStamp = data.stamp;
                }
                const warningEl = document.querySelector('.bg-amber-50');
                if (warningEl) warningEl.remove();

                const curPageObj = await doc.getPage(currentPage);
                const unscaledCur = curPageObj.getViewport({ scale: 1.0 });
                updateOverlayVisuals(currentPage, unscaledCur.width, unscaledCur.height);
            }
        } catch (err) {
            console.warn('Auto client-side text layout extraction notice:', err);
        }
    }

    // Window resize handler for mobile responsiveness (debounced)
    window.addEventListener('resize', debounce(() => {
        if (window.innerWidth < 1024 && pdfDoc) {
            fitToWidth();
        }
    }, 300));

    // 3. Render Page with cancellation guard & safety timeout
    let renderTimeout = null;
    function renderPage(pageNum) {
        if (!pdfDoc) return;

        if (renderTimeout) clearTimeout(renderTimeout);

        // Cleanly cancel previous in-flight render task if any
        if (currentRenderTask) {
            currentRenderTask.cancel();
            currentRenderTask = null;
        }

        isRendering = true;
        canvasLoader.classList.remove('hidden');

        // Safety watchdog: Automatically dismiss loader if rendering hangs beyond 8s
        renderTimeout = setTimeout(() => {
            if (isRendering) {
                console.warn('PDF rendering timeout exceeded, removing loader overlay.');
                isRendering = false;
                canvasLoader.classList.add('hidden');
            }
        }, 8000);

        pdfDoc.getPage(pageNum).then(page => {
            const viewport = page.getViewport({ scale: currentScale });
            const outputScale = window.devicePixelRatio || 1;

            pdfCanvas.width = Math.floor(viewport.width * outputScale);
            pdfCanvas.height = Math.floor(viewport.height * outputScale);
            pdfCanvas.style.width = Math.floor(viewport.width) + 'px';
            pdfCanvas.style.height = Math.floor(viewport.height) + 'px';

            const unscaledViewport = page.getViewport({ scale: 1.0 });
            const pagePtWidth = unscaledViewport.width;
            const pagePtHeight = unscaledViewport.height;

            // Set overlay viewBox to match PDF native point coordinates
            overlaySvg.setAttribute('viewBox', `0 0 ${pagePtWidth} ${pagePtHeight}`);
            overlaySvg.style.width = Math.floor(viewport.width) + 'px';
            overlaySvg.style.height = Math.floor(viewport.height) + 'px';

            const transform = outputScale !== 1
                ? [outputScale, 0, 0, outputScale, 0, 0]
                : null;

            const renderContext = {
                canvasContext: pdfCanvas.getContext('2d'),
                transform: transform,
                viewport: viewport,
            };

            currentRenderTask = page.render(renderContext);

            currentRenderTask.promise.then(() => {
                if (renderTimeout) clearTimeout(renderTimeout);
                currentRenderTask = null;
                isRendering = false;
                canvasLoader.classList.add('hidden');

                if (renderPending !== null) {
                    const nextNum = renderPending;
                    renderPending = null;
                    renderPage(nextNum);
                } else {
                    // Update Overlay visuals for this page
                    updateOverlayVisuals(pageNum, pagePtWidth, pagePtHeight);
                }
            }).catch(err => {
                if (err && err.name === 'RenderingCancelledException') {
                    // Normal: previous render was cancelled for a new scale or page
                    return;
                }
                if (renderTimeout) clearTimeout(renderTimeout);
                console.error('Render page error:', err);
                currentRenderTask = null;
                isRendering = false;
                canvasLoader.classList.add('hidden');
            });
        }).catch(err => {
            if (renderTimeout) clearTimeout(renderTimeout);
            console.error('Get page error:', err);
            isRendering = false;
            canvasLoader.classList.add('hidden');
        });

        // Update nav buttons
        currentPageInput.value = pageNum;
        prevPageBtn.disabled = (pageNum <= 1);
        nextPageBtn.disabled = (pageNum >= totalPages);
    }

    function queueRenderPage(num) {
        if (isRendering) {
            renderPending = num;
        } else {
            renderPage(num);
        }
    }

    // Navigation events
    prevPageBtn.addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            queueRenderPage(currentPage);
        }
    });
    nextPageBtn.addEventListener('click', () => {
        if (currentPage < totalPages) {
            currentPage++;
            queueRenderPage(currentPage);
        }
    });
    currentPageInput.addEventListener('change', (e) => {
        let val = parseInt(e.target.value);
        if (val >= 1 && val <= totalPages) {
            currentPage = val;
            queueRenderPage(currentPage);
        } else {
            e.target.value = currentPage;
        }
    });

    // Zoom events
    zoomInBtn.addEventListener('click', () => {
        currentScale = Math.min(3.0, currentScale + 0.2);
        zoomLevelText.textContent = Math.round(currentScale * 100) + '%';
        queueRenderPage(currentPage);
    });
    zoomOutBtn.addEventListener('click', () => {
        currentScale = Math.max(0.25, currentScale - 0.2);
        zoomLevelText.textContent = Math.round(currentScale * 100) + '%';
        queueRenderPage(currentPage);
    });
    function fitToWidth() {
        if (!pdfDoc) return;
        pdfDoc.getPage(currentPage).then(page => {
            const unscaled = page.getViewport({ scale: 1.0 });
            currentScale = calculateFitWidthScale(unscaled.width);
            zoomLevelText.textContent = Math.round(currentScale * 100) + '%';
            queueRenderPage(currentPage);
        }).catch(err => {
            console.error('fitToWidth error:', err);
        });
    }
    fitWidthBtn.addEventListener('click', fitToWidth);

    // 4. Update Overlay Visuals (Draft Lines & Notary Stamp)
    function updateOverlayVisuals(pageNo, pageWidth, pageHeight) {
        // --- A. Render Draft Lines ---
        draftLinesGroup.innerHTML = '';
        const pageDraft = calculatedDraftLines[pageNo];

        if (draftConfig.enabled && pageDraft && pageDraft.is_enabled && pageDraft.segments) {
            const color = draftConfig.color || '#DC2626';
            const thickness = draftConfig.thickness || 1.2;
            const lineStyle = draftConfig.line_style || 'dashed';
            const dashLen = parseFloat(draftConfig.dash_length) || 4.0;
            const dashGapVal = parseFloat(draftConfig.dash_gap) || 3.0;

            pageDraft.segments.forEach(seg => {
                const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                line.setAttribute('x1', seg.x);
                line.setAttribute('y1', seg.y_start);
                line.setAttribute('x2', seg.x);
                line.setAttribute('y2', seg.y_end);
                line.setAttribute('stroke', color);
                line.setAttribute('stroke-width', thickness);
                line.setAttribute('stroke-linecap', 'butt');
                if (lineStyle === 'dashed') {
                    line.setAttribute('stroke-dasharray', `${dashLen} ${dashGapVal}`);
                }
                draftLinesGroup.appendChild(line);
            });
        }

        // --- B. Render 3-Element Red Notary Stamp ---
        const placement = calculatedStamp.placements ? calculatedStamp.placements[pageNo] : null;

        if (placement && placement.is_active) {
            stampGroup.style.display = 'block';

            const notaryName = calculatedStamp.stamp_dimensions.notary_name;
            const cityTitle = calculatedStamp.stamp_dimensions.city_title;
            const color = '#DC2626'; // Red mandatory
            const fontSizeName = calculatedStamp.stamp_dimensions.font_size_name;
            const fontSizeCity = calculatedStamp.stamp_dimensions.font_size_city;
            const lineThickness = calculatedStamp.stamp_dimensions.line_thickness;
            const stampWidth = calculatedStamp.stamp_dimensions.width;
            const stampHeight = calculatedStamp.stamp_dimensions.height;

            const x = placement.x;
            const y = placement.y;
            const nameY = placement.name_y;
            const lineY = placement.line_y;
            const cityY = placement.city_y;

            const centerX = x + (stampWidth / 2);

            // Element 1: Notary Name (Centered)
            stampNameText.textContent = notaryName;
            stampNameText.setAttribute('x', centerX);
            stampNameText.setAttribute('y', nameY + fontSizeName * 0.85);
            stampNameText.setAttribute('font-size', fontSizeName + 'px');
            stampNameText.setAttribute('text-anchor', 'middle');
            stampNameText.setAttribute('fill', color);

            // Element 2: Horizontal Line matching name length
            stampDividerLine.setAttribute('x1', x);
            stampDividerLine.setAttribute('y1', lineY);
            stampDividerLine.setAttribute('x2', x + stampWidth);
            stampDividerLine.setAttribute('y2', lineY);
            stampDividerLine.setAttribute('stroke', color);
            stampDividerLine.setAttribute('stroke-width', lineThickness);

            // Element 3: NOTARIS KOTA DENPASAR (Centered)
            stampCityText.textContent = cityTitle;
            stampCityText.setAttribute('x', centerX);
            stampCityText.setAttribute('y', cityY + fontSizeCity * 0.85);
            stampCityText.setAttribute('font-size', fontSizeCity + 'px');
            stampCityText.setAttribute('text-anchor', 'middle');
            stampCityText.setAttribute('fill', color);

            // Invisible grab rectangle for easy dragging
            stampHitRect.setAttribute('x', x - 4);
            stampHitRect.setAttribute('y', y - 4);
            stampHitRect.setAttribute('width', stampWidth + 8);
            stampHitRect.setAttribute('height', stampHeight + 8);

            // Update percentage inputs
            stampXPercent.value = placement.x_percent;
            stampYPercent.value = placement.y_percent;
        } else {
            stampGroup.style.display = 'none';
        }
    }

    // 5. Interactive Drag & Drop on PDF.js Canvas (Mouse + Touch Support)
    let isDraggingStamp = false;
    let dragStartPt = { x: 0, y: 0 };
    let stampStartPt = { x: 0, y: 0 };

    function onStampDragStart(e) {
        if (e.type === 'touchstart') {
            if (!e.touches || e.touches.length !== 1) return;
        } else {
            e.preventDefault();
        }
        isDraggingStamp = true;

        const placement = calculatedStamp.placements[currentPage];
        if (!placement) return;

        const pt = getSvgPoint(e);
        dragStartPt = { x: pt.x, y: pt.y };
        stampStartPt = { x: placement.x, y: placement.y };

        window.addEventListener('mousemove', onStampDragMove);
        window.addEventListener('mouseup', onStampDragEnd);
        window.addEventListener('touchmove', onStampDragMove, { passive: false });
        window.addEventListener('touchend', onStampDragEnd);
        window.addEventListener('touchcancel', onStampDragEnd);
    }

    stampGroup.addEventListener('mousedown', onStampDragStart);
    stampGroup.addEventListener('touchstart', onStampDragStart, { passive: false });

    function getSvgPoint(e) {
        const rect = overlaySvg.getBoundingClientRect();
        const viewBox = overlaySvg.viewBox.baseVal;
        const scaleX = viewBox.width / rect.width;
        const scaleY = viewBox.height / rect.height;

        const clientX = e.touches && e.touches.length > 0 ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches && e.touches.length > 0 ? e.touches[0].clientY : e.clientY;

        return {
            x: (clientX - rect.left) * scaleX,
            y: (clientY - rect.top) * scaleY,
        };
    }

    function onStampDragMove(e) {
        if (!isDraggingStamp) return;
        if (e.type === 'touchmove') {
            e.preventDefault();
        }

        const pt = getSvgPoint(e);
        const deltaX = pt.x - dragStartPt.x;
        const deltaY = pt.y - dragStartPt.y;

        const viewBox = overlaySvg.viewBox.baseVal;
        const pageWidth = viewBox.width;
        const pageHeight = viewBox.height;

        const stampWidth = calculatedStamp.stamp_dimensions.width;
        const stampHeight = calculatedStamp.stamp_dimensions.height;

        let newX = stampStartPt.x + deltaX;
        let newY = stampStartPt.y + deltaY;

        // Keep stamp strictly within page boundaries
        newX = Math.max(10, Math.min(pageWidth - stampWidth - 10, newX));
        newY = Math.max(10, Math.min(pageHeight - stampHeight - 10, newY));

        const xPercent = (newX / pageWidth) * 100;
        const yPercent = (newY / pageHeight) * 100;

        stampXPercent.value = xPercent.toFixed(1);
        stampYPercent.value = yPercent.toFixed(1);

        // Update active placement directly in state for smooth 60fps drag
        const placement = calculatedStamp.placements[currentPage];
        if (placement) {
            placement.x = newX;
            placement.y = newY;
            placement.x_percent = parseFloat(xPercent.toFixed(1));
            placement.y_percent = parseFloat(yPercent.toFixed(1));

            const fontSizeName = calculatedStamp.stamp_dimensions.font_size_name;
            const elementSpacing = calculatedStamp.stamp_dimensions.element_spacing;
            const lineThickness = calculatedStamp.stamp_dimensions.line_thickness;

            placement.name_y = newY;
            placement.line_y = newY + fontSizeName + elementSpacing;
            placement.city_y = placement.line_y + lineThickness + elementSpacing;

            updateOverlayVisuals(currentPage, pageWidth, pageHeight);
        }
    }

    function onStampDragEnd() {
        if (!isDraggingStamp) return;
        isDraggingStamp = false;
        window.removeEventListener('mousemove', onStampDragMove);
        window.removeEventListener('mouseup', onStampDragEnd);
        window.removeEventListener('touchmove', onStampDragMove);
        window.removeEventListener('touchend', onStampDragEnd);
        window.removeEventListener('touchcancel', onStampDragEnd);

        stampConfig.preset = 'custom';
        stampConfig.x_percent = parseFloat(stampXPercent.value);
        stampConfig.y_percent = parseFloat(stampYPercent.value);

        // Persist to backend debounced
        debouncedConfigUpdate();
    }

    function onStampCoordsInput() {
        stampConfig.preset = 'custom';
        stampConfig.x_percent = parseFloat(stampXPercent.value) || 0;
        stampConfig.y_percent = parseFloat(stampYPercent.value) || 0;
        debouncedConfigUpdate();
    }

    // 6. Config synchronization with Backend
    function onDraftConfigChanged() {
        draftConfig.enabled = draftEnabled.checked;
        draftConfig.color = draftColor.value;
        draftConfig.thickness = parseFloat(draftThickness.value);
        draftConfig.offset = parseFloat(draftOffset.value);
        draftConfig.paragraph_threshold = parseFloat(draftParagraphThreshold.value);
        draftConfig.ignore_headers_footers = draftIgnoreHeaderFooter.checked;
        draftConfig.target_pages = draftTargetPages.value;
        draftConfig.custom_pages = draftCustomPages.value;
        draftConfig.line_style = draftLineStyle ? draftLineStyle.value : 'dashed';
        draftConfig.dash_length = dashLength ? parseFloat(dashLength.value) : 4.0;
        draftConfig.dash_gap = dashGap ? parseFloat(dashGap.value) : 3.0;

        // Immediate visual feedback on SVG overlay
        if (overlaySvg) {
            const viewBox = overlaySvg.viewBox.baseVal;
            if (viewBox && viewBox.width > 0) {
                updateOverlayVisuals(currentPage, viewBox.width, viewBox.height);
            }
        }

        debouncedConfigUpdate();
    }

    function onStampConfigChanged() {
        stampConfig.notary_name = stampNotaryName.value;
        stampConfig.city_title = stampCityTitle.value;
        stampConfig.target_pages = stampTargetPages.value;
        stampConfig.custom_pages = stampCustomPages.value;
        stampConfig.line_length = parseFloat(stampLineLength.value);
        stampConfig.font_size_name = parseFloat(stampFontSizeName.value);
        stampConfig.font_size_city = parseFloat(stampFontSizeCity.value);
        stampConfig.line_thickness = parseFloat(stampLineThickness.value);
        stampConfig.element_spacing = parseFloat(stampSpacing.value);

        debouncedConfigUpdate();
    }

    const debouncedConfigUpdate = debounce(sendConfigUpdate, 350);

    function sendConfigUpdate() {
        saveStatus.classList.remove('hidden');
        saveStatus.textContent = 'Menyimpan...';

        const payload = {
            draft_lines: draftConfig,
            stamp: stampConfig,
        };

        fetch(CONFIG_UPDATE_URL, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload),
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                saveStatus.textContent = 'Tersimpan';
                setTimeout(() => saveStatus.classList.add('hidden'), 2000);

                calculatedDraftLines = data.draft_lines;
                calculatedStamp = data.stamp;

                const viewBox = overlaySvg.viewBox.baseVal;
                updateOverlayVisuals(currentPage, viewBox.width, viewBox.height);
            }
        })
        .catch(err => {
            console.error('Save config failed:', err);
            saveStatus.textContent = 'Gagal menyimpan';
        });
    }

    saveConfigBtn.addEventListener('click', sendConfigUpdate);

    // 7. Generate PDF Final
    generatePdfBtn.addEventListener('click', () => {
        generatePdfBtn.disabled = true;
        generateSpinner.classList.remove('hidden');
        generateIcon.classList.add('hidden');
        generateBtnText.textContent = 'Menghasilkan PDF...';

        fetch(GENERATE_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                draft_lines: draftConfig,
                stamp: stampConfig,
            }),
        })
        .then(res => res.json())
        .then(data => {
            generatePdfBtn.disabled = false;
            generateSpinner.classList.add('hidden');
            generateIcon.classList.remove('hidden');
            generateBtnText.textContent = 'Generate PDF Final';

            if (data.success) {
                downloadBtn.classList.remove('hidden');
                downloadBtn.classList.add('inline-flex');
                downloadBtn.href = data.download_url;

                // Show success notification
                alert('PDF Final berhasil dibuat dengan garis draf dan stempel! Anda dapat mengunduhnya sekarang.');
            } else {
                alert('Gagal menghasilkan PDF: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(err => {
            generatePdfBtn.disabled = false;
            generateSpinner.classList.add('hidden');
            generateIcon.classList.remove('hidden');
            generateBtnText.textContent = 'Generate PDF Final';
            alert('Terjadi kesalahan saat memproses PDF final: ' + err.message);
        });
    });

    // Utility: Debounce helper
    function debounce(func, wait) {
        let timeout;
        return function (...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }
</script>
@endpush
