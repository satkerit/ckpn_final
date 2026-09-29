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
            <label class="mb-1 block text-xs font-medium text-zinc-400">Severity</label>
            <select wire:model.live="filterSeverity"
                class="rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-zinc-200 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                <option value="">Semua Level</option>
                @foreach ($severities as $sev)
                    <option value="{{ $sev->value }}">{{ $sev->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-zinc-400">Status</label>
            <select wire:model.live="filterStatus"
                class="rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-zinc-200 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                <option value="">Semua Status</option>
                <option value="pending">Pending</option>
                <option value="resolved">Resolved</option>
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
                    <th class="px-4 py-3 text-center font-medium text-zinc-400">Severity</th>
                    <th class="px-4 py-3 text-center font-medium text-zinc-400">Status</th>
                    <th class="px-4 py-3 text-left font-medium text-zinc-400">Reviewed By</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-800">
                @forelse ($records as $record)
                    <tr class="hover:bg-zinc-800/50">
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
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                {{ $record->severity->value === 'critical' ? 'bg-red-950/60 text-red-300' : '' }}
                                {{ $record->severity->value === 'warning' ? 'bg-amber-950/60 text-amber-300' : '' }}
                                {{ $record->severity->value === 'info' ? 'bg-blue-950/60 text-blue-300' : '' }}
                            ">
                                {{ $record->severity->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                {{ $record->status === 'pending' ? 'bg-amber-100 text-amber-700' : '' }}
                                {{ $record->status === 'resolved' ? 'bg-emerald-100 text-emerald-700' : '' }}
                            ">
                                {{ ucfirst($record->status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-zinc-400">
                            @if ($record->reviewed_by)
                                <span>{{ $record->reviewedBy?->name ?? '-' }}</span>
                                @if ($record->reviewed_at)
                                    <br><span class="text-zinc-500">{{ $record->reviewed_at->format('d/m/Y H:i') }}</span>
                                @endif
                            @else
                                —
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
</div>
