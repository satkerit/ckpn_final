{{-- resources/views/livewire/upload-data/upload-batch-index.blade.php --}}
{{-- Ref: PRD Bab 3 - Riwayat Upload Batch --}}
<div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-base font-semibold text-zinc-100">Riwayat Upload</h2>
            <p class="mt-1 text-sm text-zinc-400">Daftar semua batch upload data pembiayaan.</p>
        </div>
        <a href="{{ route('upload.index') }}" wire:navigate
           class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-xs font-semibold text-white hover:bg-primary-700 transition">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Upload Baru
        </a>
    </div>

    {{-- Filters --}}
    <div class="mb-4 flex flex-wrap gap-3">
        <select wire:model.live="filterStatus"
                class="rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-zinc-200 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
            <option value="">Semua Status</option>
            @foreach ($statusOptions as $status)
                <option value="{{ $status->value }}">{{ ucfirst($status->value) }}</option>
            @endforeach
        </select>

        <select wire:model.live="filterType"
                class="rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-zinc-200 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
            <option value="">Semua Jenis</option>
            @foreach ($typeLabels as $key => $label)
                <option value="{{ $key }}">{{ $label }}</option>
            @endforeach
        </select>

        <input
            type="text"
            wire:model.live.debounce.400ms="filterPeriod"
            placeholder="Filter periode (cth: 202401)"
            class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-zinc-200 placeholder-zinc-500 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 sm:w-52"
        />
        <input
            type="text"
            wire:model.live.debounce.300ms="search"
            placeholder="Cari periode, jenis, status, nama file..."
            class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-zinc-200 placeholder-zinc-500 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 sm:w-52"
        />
    </div>

    {{-- Table --}}
    <div class="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-900 shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="border-b border-zinc-800 bg-zinc-800/50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-zinc-400 uppercase tracking-wide">Jenis</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-zinc-400 uppercase tracking-wide">Periode</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-zinc-400 uppercase tracking-wide">File</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-zinc-400 uppercase tracking-wide">Status</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-zinc-400 uppercase tracking-wide">Total</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-zinc-400 uppercase tracking-wide">Berhasil</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-zinc-400 uppercase tracking-wide">Gagal</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-zinc-400 uppercase tracking-wide">Diupload Oleh</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-zinc-400 uppercase tracking-wide">Waktu Upload</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800">
                    @forelse ($batches as $batch)
                        <tr class="hover:bg-zinc-800/50 transition-colors">
                            <td class="px-5 py-3 text-zinc-300">
                                {{ $typeLabels[$batch->upload_type] ?? $batch->upload_type }}
                            </td>
                            <td class="px-5 py-3 text-zinc-300 font-mono text-xs">
                                {{ $batch->period ?? '-' }}
                            </td>
                            <td class="px-5 py-3 text-zinc-400 max-w-[180px] truncate text-xs" title="{{ $batch->filename }}">
                                {{ $batch->filename }}
                            </td>
                            <td class="px-5 py-3">
                                @php
                                    $statusClass = match($batch->status->value ?? '') {
                                        'done'       => 'bg-emerald-100 text-emerald-700',
                                        'processing' => 'bg-amber-100 text-amber-700',
                                        'failed'     => 'bg-red-950/60 text-red-300',
                                        default      => 'bg-zinc-800 text-zinc-400',
                                    };
                                    $statusLabel = match($batch->status->value ?? '') {
                                        'done'       => 'Selesai',
                                        'processing' => 'Diproses',
                                        'failed'     => 'Gagal',
                                        'pending'    => 'Antri',
                                        default      => $batch->status->value ?? '-',
                                    };
                                @endphp
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $statusClass }}">
                                    {{ $statusLabel }}
                                </span>
                                @if ($statusLabel === 'Gagal' && $batch->hasErrorDetails())
                                    <button
                                        type="button"
                                        wire:click="openErrorModal({{ $batch->id }})"
                                        class="mt-1 block text-xs text-red-400 hover:underline cursor-pointer"
                                        title="Lihat detail error"
                                    >
                                        Lihat detail
                                    </button>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right text-zinc-300 tabular-nums text-xs">
                                {{ number_format($batch->total_rows ?? 0) }}
                            </td>
                            <td class="px-5 py-3 text-right text-emerald-700 tabular-nums text-xs">
                                {{ number_format($batch->imported_rows ?? 0) }}
                            </td>
                            <td class="px-5 py-3 text-right tabular-nums text-xs {{ ($batch->failed_rows ?? 0) > 0 ? 'text-red-600 font-semibold' : 'text-zinc-500' }}">
                                @if ($batch->hasErrorDetails())
                                    <button
                                        type="button"
                                        wire:click="openErrorModal({{ $batch->id }})"
                                        class="inline-flex items-center gap-1 hover:underline cursor-pointer"
                                        title="Klik untuk melihat detail error"
                                    >
                                        {{ number_format($batch->failed_rows ?? 0) }}
                                        <svg class="h-3 w-3 opacity-70" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                        </svg>
                                    </button>
                                @else
                                    {{ number_format($batch->failed_rows ?? 0) }}
                                @endif
                            </td>
                            <td class="px-5 py-3 text-zinc-400 text-xs">
                                {{ $batch->uploadedBy?->name ?? '-' }}
                            </td>
                            <td class="px-5 py-3 text-zinc-400 text-xs whitespace-nowrap">
                                {{ $batch->uploaded_at?->format('d/m/Y H:i') ?? '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-5 py-10 text-center text-zinc-500 text-sm">
                                Belum ada riwayat upload.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if ($batches->hasPages())
            <div class="border-t border-zinc-800 px-5 py-3">
                {{ $batches->links() }}
            </div>
        @endif
    </div>

    {{-- Modal Detail Error — server-rendered via Livewire (stabil di wire:navigate, tanpa race condition Alpine) --}}
    @if ($showErrorModal && $this->selectedBatch !== null)
        @php
            $batch = $this->selectedBatch;
            $errorEntries = collect($batch->error_summary ?? []);
            $maxVisible = 50;
            $visibleErrors = $errorEntries->take($maxVisible);
        @endphp
        <div class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" wire:key="error-modal-{{ $batch->id }}">
            <div class="fixed inset-0 bg-black/70" wire:click="closeErrorModal"></div>

            <div class="relative flex min-h-full items-center justify-center p-4">
                <div class="relative w-full max-w-2xl rounded-xl border border-zinc-700 bg-zinc-900 p-6 text-left shadow-xl">
                    <!-- Header -->
                    <div class="mb-4 flex items-start justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-zinc-100">Detail Error Upload</h3>
                            <p class="mt-0.5 text-sm text-zinc-400">
                                {{ $typeLabels[$batch->upload_type] ?? $batch->upload_type }}
                                &mdash; {{ $batch->filename }}
                                @if ($batch->period)
                                    ({{ $batch->period }})
                                @endif
                            </p>
                        </div>
                        <button type="button" wire:click="closeErrorModal" class="text-zinc-400 hover:text-zinc-200" aria-label="Tutup">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <!-- Error Summary -->
                    <div class="mb-4 rounded-lg border border-rose-700/60 bg-rose-950/60 p-4">
                        <div class="mb-1 flex items-center gap-2">
                            <svg class="h-5 w-5 text-rose-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-sm font-medium text-rose-300">
                                @if ($errorEntries->isNotEmpty())
                                    Total {{ number_format($errorEntries->count()) }} catatan error
                                @else
                                    {{ number_format($batch->failed_rows ?? 0) }} baris gagal
                                @endif
                            </span>
                        </div>
                        <div class="text-xs text-rose-200">
                            Silakan perbaiki error berikut dan upload ulang file Anda.
                        </div>
                    </div>

                    <!-- Error List -->
                    <div class="max-h-96 space-y-2 overflow-y-auto">
                        @forelse ($visibleErrors as $index => $error)
                            <div class="rounded-lg border border-zinc-700 bg-zinc-800/50 p-3">
                                @if (is_array($error))
                                    <div class="mb-1 flex flex-wrap items-center gap-2">
                                        @if (isset($error['row']) && $error['row'] !== null && $error['row'] !== '')
                                            <span class="inline-flex rounded px-2 py-0.5 text-xs font-medium bg-red-100 text-red-800">
                                                Baris {{ $error['row'] }}
                                            </span>
                                        @endif
                                        @if (isset($error['field']) && $error['field'] !== null && $error['field'] !== '' && $error['field'] !== 'general')
                                            <span class="inline-flex rounded px-2 py-0.5 text-xs font-medium bg-amber-100 text-amber-800">
                                                {{ $error['field'] }}
                                            </span>
                                        @endif
                                    </div>
                                    <p class="mb-1 text-sm text-zinc-200">{{ $error['error'] ?? 'Terjadi kesalahan tidak diketahui' }}</p>
                                    @if (isset($error['value']) && $error['value'] !== null && $error['value'] !== '')
                                        <p class="text-xs text-zinc-400">Nilai: {{ $error['value'] }}</p>
                                    @endif
                                @else
                                    <p class="text-sm text-zinc-200">{{ is_string($error) ? $error : json_encode($error) }}</p>
                                @endif
                            </div>
                        @empty
                            <div class="py-8 text-center text-zinc-400">
                                Tidak ada catatan error detail untuk batch ini.
                            </div>
                        @endforelse

                        @if ($errorEntries->count() > $maxVisible)
                            <p class="pt-2 text-center text-xs text-zinc-500">
                                Menampilkan {{ $maxVisible }} dari {{ number_format($errorEntries->count()) }} catatan error.
                            </p>
                        @endif
                    </div>

                    <!-- Footer -->
                    <div class="mt-6 flex justify-end">
                        <button
                            type="button"
                            wire:click="closeErrorModal"
                            class="rounded-lg bg-zinc-700 px-4 py-2 text-sm font-medium text-zinc-200 transition-colors hover:bg-zinc-600"
                        >
                            Tutup
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
