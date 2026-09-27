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
                                @if (($batch->failed_rows ?? 0) > 0)
                                    <button 
                                        x-data=""
                                        @click="$dispatch('show-error-details', { batchId: {{ $batch->id }}, errors: {{ json_encode($batch->error_summary ?? []) }} })"
                                        class="hover:underline cursor-pointer"
                                        title="Klik untuk melihat detail error"
                                    >
                                        {{ number_format($batch->failed_rows ?? 0) }}
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

    <!-- Error Details Modal -->
    <div 
        x-data="errorDetailsModal()"
        x-show="show"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto"
        aria-labelledby="modal-title" 
        role="dialog" 
        aria-modal="true"
        @show-error-details.window="openModal($event.detail)"
        @keydown.escape.window="show = false"
    >
        <!-- Backdrop -->
        <div 
            x-show="show"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
            @click="show = false"
        ></div>

        <!-- Modal -->
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div 
                x-show="show"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                class="relative transform overflow-hidden rounded-lg bg-zinc-900 border border-zinc-700 px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-4xl sm:p-6"
                @click.stop
            >
                <!-- Header -->
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-semibold text-zinc-100">Detail Error Upload</h3>
                        <p class="text-sm text-zinc-400" x-text="'Batch ID: ' + batchId"></p>
                    </div>
                    <button @click="show = false" class="text-zinc-400 hover:text-zinc-200">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <!-- Error Summary -->
                <div x-show="errors.length > 0" class="mb-4">
                    <div class="bg-rose-950/60 border border-rose-700/60 rounded-lg p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <svg class="h-5 w-5 text-rose-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-sm font-medium text-rose-300" x-text="'Total ' + errors.length + ' error ditemukan'"></span>
                        </div>
                        <div class="text-xs text-rose-200">
                            Silakan perbaiki error berikut dan upload ulang file Anda.
                        </div>
                    </div>
                </div>

                <!-- Error List -->
                <div class="max-h-96 overflow-y-auto">
                    <div x-show="errors.length === 0" class="text-center py-8 text-zinc-400">
                        Tidak ada error detail tersedia.
                    </div>

                    <div x-show="errors.length > 0" class="space-y-2">
                        <template x-for="(error, index) in errors" :key="index">
                            <div class="border border-zinc-700 rounded-lg p-3 bg-zinc-800/50">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800" x-text="'Baris ' + error.row"></span>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800" x-text="error.field || 'Unknown Field'"></span>
                                        </div>
                                        <p class="text-sm text-zinc-200 mb-1" x-text="error.error || 'Unknown error'"></p>
                                        <p class="text-xs text-zinc-400" x-show="error.value" x-text="'Nilai: ' + (error.value || '')"></p>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Footer -->
                <div class="mt-6 flex justify-end">
                    <button @click="show = false" class="px-4 py-2 bg-zinc-700 hover:bg-zinc-600 text-zinc-200 text-sm font-medium rounded-lg transition-colors">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function errorDetailsModal() {
    return {
        show: false,
        batchId: null,
        errors: [],
        
        openModal(data) {
            this.batchId = data.batchId;
            this.errors = data.errors || [];
            this.show = true;
        }
    }
}
</script>
