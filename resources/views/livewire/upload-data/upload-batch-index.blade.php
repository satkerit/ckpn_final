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
                            </td>
                            <td class="px-5 py-3 text-right text-zinc-300 tabular-nums text-xs">
                                {{ number_format($batch->total_rows ?? 0) }}
                            </td>
                            <td class="px-5 py-3 text-right text-emerald-700 tabular-nums text-xs">
                                {{ number_format($batch->imported_rows ?? 0) }}
                            </td>
                            <td class="px-5 py-3 text-right tabular-nums text-xs {{ ($batch->failed_rows ?? 0) > 0 ? 'text-red-600 font-semibold' : 'text-zinc-500' }}">
                                {{ number_format($batch->failed_rows ?? 0) }}
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
</div>
