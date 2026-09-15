<div>
    {{-- Header --}}
    <div class="mb-6">
        <h2 class="text-base font-semibold text-zinc-100">Ringkasan LGD</h2>
        <p class="mt-0.5 text-xs text-zinc-400">Ref: PRD Bab 9 &amp; 10 — ringkasan LGD Expected Recoveries dan Collateral Shortfall per periode &amp; jenis penggunaan.</p>
    </div>

    {{-- Search --}}
    <div class="mb-4">
        <input
            type="text"
            wire:model.live.debounce.300ms="search"
            placeholder="Cari..."
            class="w-full sm:w-64 px-3 py-2 text-sm border border-zinc-700 bg-zinc-950 text-zinc-200 rounded-lg shadow-sm placeholder-zinc-500 focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
        />
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
            <label class="mb-1 block text-xs font-medium text-zinc-400">Jenis Penggunaan</label>
            <select wire:model.live="filterUsageType"
                class="rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-zinc-200 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                <option value="">Semua</option>
                @foreach ($usageTypes as $ut)
                    <option value="{{ $ut->value }}">{{ $ut->label() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- LGD Expected Recoveries --}}
    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-400">LGD Expected Recoveries (ER)</h3>
    <div class="mb-6 overflow-x-auto rounded-xl border border-zinc-700 bg-zinc-900 shadow-sm">
        <table class="min-w-full divide-y divide-zinc-800 whitespace-nowrap text-xs">
            <thead class="bg-zinc-800/50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-zinc-400">Periode</th>
                    <th class="px-4 py-3 text-left font-medium text-zinc-400">Jenis Penggunaan</th>
                    <th class="px-4 py-3 text-right font-medium text-zinc-400">Total Write-off (Rp)</th>
                    <th class="px-4 py-3 text-right font-medium text-zinc-400">Total Recovery (Rp)</th>
                    <th class="px-4 py-3 text-right font-medium text-zinc-400">Avg LGD Rate ER</th>
                    <th class="px-4 py-3 text-right font-medium text-zinc-400">Baris Data</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-800">
                @forelse ($rows->filter(fn($r) => $r->row_count_er > 0) as $row)
                    <tr class="hover:bg-zinc-800/50">
                        <td class="px-4 py-3 font-mono text-zinc-300">{{ $row->period }}</td>
                        <td class="px-4 py-3 text-zinc-300">{{ $row->usage_type?->label() ?? '-' }}</td>
                        <td class="px-4 py-3 text-right font-mono text-zinc-300">
                            {{ $row->sum_writeoff !== null ? number_format($row->sum_writeoff, 2, ',', '.') : '-' }}
                        </td>
                        <td class="px-4 py-3 text-right font-mono text-zinc-300">
                            {{ $row->sum_recovery !== null ? number_format($row->sum_recovery, 2, ',', '.') : '-' }}
                        </td>
                        <td class="px-4 py-3 text-right font-mono font-semibold text-primary-400">
                            {{ $row->avg_lgd_rate_er !== null ? number_format($row->avg_lgd_rate_er * 100, 4, ',', '.') . '%' : '-' }}
                        </td>
                        <td class="px-4 py-3 text-right text-zinc-400">{{ $row->row_count_er }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-xs text-zinc-500">
                            Belum ada data LGD Expected Recoveries.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- LGD Collateral Shortfall --}}
    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-400">LGD Collateral Shortfall (CS)</h3>
    <div class="overflow-x-auto rounded-xl border border-zinc-700 bg-zinc-900 shadow-sm">
        <table class="min-w-full divide-y divide-zinc-800 whitespace-nowrap text-xs">
            <thead class="bg-zinc-800/50">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-zinc-400">Periode</th>
                    <th class="px-4 py-3 text-left font-medium text-zinc-400">Jenis Penggunaan</th>
                    <th class="px-4 py-3 text-right font-medium text-zinc-400">Total Outstanding (Rp)</th>
                    <th class="px-4 py-3 text-right font-medium text-zinc-400">Total Shortfall (Rp)</th>
                    <th class="px-4 py-3 text-right font-medium text-zinc-400">Avg LGD Rate CS</th>
                    <th class="px-4 py-3 text-right font-medium text-zinc-400">Jumlah Akun</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-800">
                @forelse ($rows->filter(fn($r) => $r->row_count_cs > 0) as $row)
                    <tr class="hover:bg-zinc-800/50">
                        <td class="px-4 py-3 font-mono text-zinc-300">{{ $row->period }}</td>
                        <td class="px-4 py-3 text-zinc-300">{{ $row->usage_type?->label() ?? '-' }}</td>
                        <td class="px-4 py-3 text-right font-mono text-zinc-300">
                            {{ $row->sum_outstanding !== null ? number_format($row->sum_outstanding, 2, ',', '.') : '-' }}
                        </td>
                        <td class="px-4 py-3 text-right font-mono text-zinc-300">
                            {{ $row->sum_shortfall !== null ? number_format($row->sum_shortfall, 2, ',', '.') : '-' }}
                        </td>
                        <td class="px-4 py-3 text-right font-mono font-semibold text-primary-400">
                            {{ $row->avg_lgd_rate_cs !== null ? number_format($row->avg_lgd_rate_cs * 100, 4, ',', '.') . '%' : '-' }}
                        </td>
                        <td class="px-4 py-3 text-right text-zinc-400">{{ $row->row_count_cs }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-xs text-zinc-500">
                            Belum ada data LGD Collateral Shortfall.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
