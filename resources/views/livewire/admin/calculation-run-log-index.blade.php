<div>
    {{-- Header --}}
    <div class="mb-6">
        <h2 class="text-base font-semibold text-zinc-100">Log Kalkulasi</h2>
        <p class="mt-0.5 text-xs text-zinc-400">Ref: PRD Bab 13.2 &amp; 16 — riwayat eksekusi job perhitungan. Read-only.</p>
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
            <label class="mb-1 block text-xs font-medium text-zinc-400">Jenis Run</label>
            <select wire:model.live="filterRunType"
                class="rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-zinc-200 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                <option value="">Semua</option>
                @foreach ($runTypes as $rt)
                    <option value="{{ $rt->value }}">{{ str_replace('_', ' ', ucfirst($rt->value)) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-zinc-400">Status</label>
            <select wire:model.live="filterStatus"
                class="rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-zinc-200 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                <option value="">Semua</option>
                @foreach ($statuses as $st)
                    <option value="{{ $st->value }}">{{ ucfirst($st->value) }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Search --}}
    <div class="mb-4">
        <input
            type="text"
            wire:model.live.debounce.300ms="search"
            placeholder="Cari periode, jenis run, status, catatan..."
            class="w-full sm:w-64 px-3 py-2 text-sm border border-zinc-700 bg-zinc-950 text-zinc-200 rounded-lg shadow-sm placeholder-zinc-500 focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
        />
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto rounded-xl border border-zinc-700 bg-zinc-900 shadow-sm">
        <table class="min-w-full divide-y divide-zinc-800 whitespace-nowrap text-xs">
            <thead class="bg-zinc-800/50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-zinc-400">Periode</th>
                    <th class="px-4 py-3 text-left font-medium text-zinc-400">Jenis Run</th>
                    <th class="px-4 py-3 text-left font-medium text-zinc-400">Jenis Penggunaan</th>
                    <th class="px-4 py-3 text-center font-medium text-zinc-400">Status</th>
                    <th class="px-4 py-3 text-left font-medium text-zinc-400">Dimulai Oleh</th>
                    <th class="px-4 py-3 text-left font-medium text-zinc-400">Waktu Mulai</th>
                    <th class="px-4 py-3 text-left font-medium text-zinc-400">Waktu Selesai</th>
                    <th class="px-4 py-3 text-left font-medium text-zinc-400">Catatan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-800">
                @forelse ($records as $log)
                    <tr class="hover:bg-zinc-800/50">
                        <td class="px-4 py-3 font-mono text-zinc-300">{{ $log->period }}</td>
                        <td class="px-4 py-3 text-zinc-300">
                            {{ str_replace('_', ' ', ucfirst($log->run_type?->value ?? '-')) }}
                        </td>
                        <td class="px-4 py-3 text-zinc-300">
                            {{ $log->usage_type?->label() ?? 'Semua' }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            @php
                                $badgeClass = match ($log->status) {
                                    \App\Enums\RunStatus::Pending    => 'bg-zinc-800 text-zinc-400',
                                    \App\Enums\RunStatus::Processing => 'bg-blue-100 text-blue-700',
                                    \App\Enums\RunStatus::Completed  => 'bg-emerald-100 text-emerald-700',
                                    \App\Enums\RunStatus::Failed     => 'bg-red-100 text-red-700',
                                    \App\Enums\RunStatus::Approved   => 'bg-violet-100 text-violet-700',
                                    default                          => 'bg-zinc-800 text-zinc-400',
                                };
                            @endphp
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $badgeClass }}">
                                {{ ucfirst($log->status?->value ?? '-') }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-zinc-400">{{ $log->triggeredBy?->name ?? '-' }}</td>
                        <td class="px-4 py-3 font-mono text-zinc-400">
                            {{ $log->started_at?->format('d/m/Y H:i:s') ?? '-' }}
                        </td>
                        <td class="px-4 py-3 font-mono text-zinc-400">
                            {{ $log->completed_at?->format('d/m/Y H:i:s') ?? '-' }}
                        </td>
                        <td class="px-4 py-3 text-zinc-400 max-w-xs">
                            @if ($log->error_message)
                                <span class="text-red-600 truncate block" title="{{ $log->error_message }}">
                                    {{ Str::limit($log->error_message, 60) }}
                                </span>
                            @elseif ($log->notes)
                                <span class="truncate block" title="{{ $log->notes }}">
                                    {{ Str::limit($log->notes, 60) }}
                                </span>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-10 text-center text-xs text-zinc-500">
                            Belum ada log kalkulasi.
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
