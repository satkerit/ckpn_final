<div>
    {{-- Header --}}
    <div class="mb-6">
        <h2 class="text-base font-semibold text-zinc-100">Anomali Data Quality</h2>
        <p class="mt-0.5 text-xs text-zinc-400">Ref: PRD Bab 7.3, FR-4.3 — daftar anomali validasi bucket movement. Klik "Tandai Resolved" untuk menandai anomali yang sudah ditangani.</p>
    </div>

    {{-- Filter --}}
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <div>
            <label class="mb-1 block text-xs font-medium text-zinc-400">Periode</label>
            <select wire:model.live="filterPeriod"
                class="rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-zinc-200 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                <option value="">Semua Periode</option>
                @foreach ($periods as $p)
                    <option value="{{ $p }}">{{ $p }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-zinc-400">Jenis Anomali</label>
            <select wire:model.live="filterAnomalyType"
                class="rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-zinc-200 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                <option value="">Semua Jenis</option>
                @foreach ($anomalyTypes as $at)
                    <option value="{{ $at->value }}">{{ $at->getLabel() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-zinc-400">Status</label>
            <select wire:model.live="filterResolved"
                class="rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-zinc-200 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                <option value="">Semua</option>
                <option value="0">Belum Resolved</option>
                <option value="1">Sudah Resolved</option>
            </select>
        </div>
    </div>

    {{-- Search --}}
    <div class="mb-4">
        <input
            type="text"
            wire:model.live.debounce.300ms="search"
            placeholder="Cari periode, jenis anomali, deskripsi..."
            class="w-full sm:w-64 px-3 py-2 text-sm border border-zinc-700 bg-zinc-950 text-zinc-200 placeholder-zinc-500 rounded-lg shadow-sm focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
        />
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto rounded-xl border border-zinc-700 bg-zinc-900 shadow-sm">
        <table class="min-w-full divide-y divide-zinc-800 whitespace-nowrap text-xs">
            <thead class="bg-zinc-800/50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-zinc-400">Periode</th>
                    <th class="px-4 py-3 text-left font-medium text-zinc-400">Jenis Penggunaan</th>
                    <th class="px-4 py-3 text-left font-medium text-zinc-400">Bucket</th>
                    <th class="px-4 py-3 text-left font-medium text-zinc-400">Jenis Anomali</th>
                    <th class="px-4 py-3 text-left font-medium text-zinc-400">Deskripsi</th>
                    <th class="px-4 py-3 text-center font-medium text-zinc-400">Status</th>
                    <th class="px-4 py-3 text-left font-medium text-zinc-400">Resolved Oleh</th>
                    <th class="px-4 py-3 text-left font-medium text-zinc-400">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-800">
                @forelse ($records as $record)
                    <tr class="hover:bg-zinc-800/50 {{ $record->is_resolved ? 'opacity-60' : '' }}">
                        <td class="px-4 py-3 font-mono text-zinc-300">{{ $record->period }}</td>
                        <td class="px-4 py-3 text-zinc-300">{{ $record->usage_type?->label() ?? '-' }}</td>
                        <td class="px-4 py-3 text-zinc-300">{{ $record->bucket?->code ?? '-' }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                {{ $record->anomaly_type->value === 'empty_bucket' ? 'bg-amber-100 text-amber-700' : '' }}
                                {{ $record->anomaly_type->value === 'destination_exceeds_source' ? 'bg-red-950/60 text-red-300' : '' }}
                                {{ $record->anomaly_type->value === 'negative_outstanding' ? 'bg-orange-100 text-orange-700' : '' }}
                            ">
                                {{ $record->anomaly_type->getLabel() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-zinc-400 max-w-xs truncate" title="{{ $record->description }}">
                            {{ $record->description }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if ($record->is_resolved)
                                <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">
                                    Resolved
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">
                                    Open
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-zinc-400">
                            @if ($record->is_resolved)
                                <span>{{ $record->resolvedBy?->name ?? '-' }}</span>
                                @if ($record->resolved_at)
                                    <br><span class="text-zinc-500">{{ $record->resolved_at->format('d/m/Y H:i') }}</span>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if (!$record->is_resolved)
                                <button wire:click="confirmMarkResolved({{ $record->id }})"
                                    class="inline-flex items-center gap-1 rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-emerald-700 transition-colors">
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                                    </svg>
                                    Tandai Resolved
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-10 text-center text-xs text-zinc-500">
                            Tidak ada anomali data quality ditemukan.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if ($records->hasPages())
        <div class="mt-4">
            {{ $records->links() }}
        </div>
    @endif

    {{-- Modal Konfirmasi Tandai Resolved --}}
    <div x-show="$wire.confirmingResolveId !== null" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="w-full max-w-sm rounded-xl border border-zinc-800 bg-zinc-900 p-6 shadow-2xl">
            <h3 class="font-semibold text-zinc-100 mb-2">Konfirmasi Resolved</h3>
            <p class="text-sm text-zinc-400 mb-4">Yakin ingin menandai anomali ini sebagai resolved? Status tidak bisa dikembalikan.</p>
            <div class="flex gap-2 justify-end">
                <button wire:click="$set('confirmingResolveId', null)" class="px-3 py-1.5 text-sm rounded-lg border border-zinc-700 bg-zinc-800 text-zinc-300 hover:bg-zinc-700 hover:text-white">Batal</button>
                <button wire:click="markResolved($wire.confirmingResolveId)" class="px-3 py-1.5 text-sm rounded-lg bg-emerald-600 text-white hover:bg-emerald-500 font-semibold shadow-sm">Ya, Tandai Resolved</button>
            </div>
        </div>
    </div>
</div>
