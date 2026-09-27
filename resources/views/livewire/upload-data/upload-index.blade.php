{{-- resources/views/livewire/upload-data/upload-index.blade.php --}}
{{-- Ref: PRD Bab 3 - Upload Data Pembiayaan --}}
<div>
    <div class="mb-6">
        <h2 class="text-base font-semibold text-zinc-100">Upload Data Pembiayaan</h2>
        <p class="mt-1 text-sm text-zinc-400">Unggah file Excel (.xlsx) atau CSV (.csv) untuk setiap jenis data.</p>
    </div>

    <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($uploadTypes as $typeKey => $type)
            <div class="rounded-xl bg-zinc-900 border border-zinc-800 shadow-sm p-5 flex flex-col gap-4">
                {{-- Header --}}
                <div class="flex items-start gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $type['icon_color'] }}">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-zinc-100">{{ $type['label'] }}</p>
                        <p class="text-xs text-zinc-400 mt-0.5">{{ $type['description'] }}</p>
                        @if ($type['template'] ?? false)
                            <a href="{{ route('upload.template.download', $type['upload_type']) }}"
                               class="mt-1.5 inline-flex items-center gap-1 text-xs font-semibold text-primary-400 hover:text-primary-300">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                </svg>
                                Download Template
                            </a>
                        @endif
                    </div>
                </div>

                {{-- Alert message --}}
                @if (isset($messages[$typeKey]))
                    <div @class([
                        'flex flex-col gap-1 rounded-lg px-3 py-2 text-xs',
                        'bg-emerald-950/60 text-emerald-300 border border-emerald-700/60' => $messages[$typeKey]['type'] === 'success',
                        'bg-rose-950/60 text-rose-300 border border-rose-700/60'             => $messages[$typeKey]['type'] === 'error',
                        'bg-amber-950/60 text-amber-300 border border-amber-700/60'         => $messages[$typeKey]['type'] === 'warning',
                        'bg-blue-950/60 text-blue-300 border border-blue-700/60'           => $messages[$typeKey]['type'] === 'info',
                    ])>
                        <div class="flex items-start justify-between gap-2">
                            <span class="flex-1">{{ $messages[$typeKey]['text'] }}</span>
                            <button wire:click="clearMessage('{{ $typeKey }}')" class="shrink-0 text-zinc-400 hover:text-zinc-200">&times;</button>
                        </div>
                        @if (isset($messages[$typeKey]['details']) && $messages[$typeKey]['details'])
                            <div class="text-xs opacity-90 mt-1">
                                {{ $messages[$typeKey]['details'] }}
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Upload form --}}
                <form wire:submit="processUpload('{{ $typeKey }}')" class="flex flex-col gap-3">
                    {{-- File input --}}
                    <div>
                        <label class="block text-xs font-medium text-zinc-300 mb-1.5">Pilih file (xlsx / csv)</label>
                        <input
                            type="file"
                            wire:model="{{ $type['field'] }}"
                            accept=".xlsx,.csv"
                            class="block w-full text-xs text-zinc-300 file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-800 file:px-3 file:py-2 file:text-xs file:font-medium file:text-zinc-200 hover:file:bg-zinc-700 focus:outline-none"
                        />
                        @error($type['field'])
                            <p class="mt-1.5 text-xs text-rose-400">{{ $message }}</p>
                        @enderror
                        <p class="mt-1.5 text-xs text-zinc-400">
                            Format: XLSX, XLS, CSV &middot; Maksimal {{ $maxFileSizeMb }} MB
                        </p>
                    </div>

                    {{-- Progress indicator saat upload --}}
                    <div wire:loading wire:target="{{ $type['field'] }}" class="flex items-center gap-2 text-xs text-zinc-300">
                        <svg class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                        </svg>
                        <span>Mengunggah file...</span>
                    </div>

                    {{-- Submit button (non-aktif jika file belum dipilih) --}}
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="processUpload('{{ $typeKey }}')"
                        @disabled(! $this->{$type['field']})
                        class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-xs font-semibold text-white transition hover:bg-primary-500 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <svg wire:loading.remove wire:target="processUpload('{{ $typeKey }}')" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                        </svg>
                        <svg wire:loading wire:target="processUpload('{{ $typeKey }}')" class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                        </svg>
                        <span wire:loading.remove wire:target="processUpload('{{ $typeKey }}')">Upload</span>
                        <span wire:loading wire:target="processUpload('{{ $typeKey }}')">Memproses...</span>
                    </button>
                </form>
            </div>
        @endforeach
    </div>

    {{-- Link ke riwayat --}}
    <div class="mt-6">
        <a href="{{ route('upload.batches.index') }}" wire:navigate
           class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary-400 hover:text-primary-300 transition-colors">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
            </svg>
            Lihat riwayat semua upload
        </a>
    </div>
</div>
