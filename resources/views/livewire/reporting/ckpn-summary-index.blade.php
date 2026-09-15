<div>
    {{-- Header --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-base font-semibold text-zinc-100">Ringkasan CKPN</h2>
            <p class="mt-0.5 text-xs text-zinc-400">Ref: PRD FR-12 — agregasi CKPN Individual + Kolektif dan Konsolidasi Total per periode &amp; jenis penggunaan.</p>
        </div>
        <button wire:click="exportExcel"
            wire:loading.attr="disabled"
            class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-medium text-white shadow-sm hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1 transition-colors disabled:opacity-60">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
            </svg>
            <span wire:loading.remove wire:target="exportExcel">Export Excel</span>
            <span wire:loading wire:target="exportExcel">Mengunduh…</span>
        </button>
    </div>

    {{-- Filter --}}
    <div class="mb-6 flex flex-wrap items-end gap-4">
        <div>
            <label class="mb-1 block text-xs font-medium text-zinc-400">Periode</label>
            <select wire:model.live="filterPeriod"
                class="rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-xs text-zinc-300 shadow-sm focus:border-primary-400 focus:outline-none focus:ring-1 focus:ring-primary-400">
                <option value="">Semua Periode</option>
                @foreach ($periods as $p)
                    <option value="{{ $p }}">{{ $p }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-medium text-zinc-400">Cari Periode</label>
            <input type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Cari periode..."
                class="rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-xs text-zinc-300 shadow-sm focus:border-primary-400 focus:outline-none focus:ring-1 focus:ring-primary-400 w-44" />
        </div>
    </div>

    {{-- Metric Cards: Konsolidasi Total CKPN --}}
    <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {{-- Card 1: Total CKPN Individual --}}
        <div class="rounded-xl border border-zinc-700 bg-zinc-900 p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-zinc-400">Total CKPN Individual</span>
                <span class="rounded bg-sky-950 px-2 py-0.5 text-[10px] font-semibold text-sky-400">Individual</span>
            </div>
            <p class="mt-2 font-mono text-lg font-bold text-zinc-100">
                Rp {{ number_format($consolidatedSummary->total_individual, 2, ',', '.') }}
            </p>
            <p class="mt-1 text-[11px] text-zinc-400">
                {{ number_format($consolidatedSummary->count_individual) }} akun dinilai individual
            </p>
        </div>

        {{-- Card 2: Total CKPN Kolektif --}}
        <div class="rounded-xl border border-zinc-700 bg-zinc-900 p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-zinc-400">Total CKPN Kolektif</span>
                <span class="rounded bg-indigo-950 px-2 py-0.5 text-[10px] font-semibold text-indigo-400">Kolektif</span>
            </div>
            <p class="mt-2 font-mono text-lg font-bold text-zinc-100">
                Rp {{ number_format($consolidatedSummary->total_collective, 2, ',', '.') }}
            </p>
            <p class="mt-1 text-[11px] text-zinc-400">
                {{ number_format($consolidatedSummary->count_collective) }} akun dinilai kolektif
            </p>
        </div>

        {{-- Card 3: Total Akun Pembiayaan --}}
        <div class="rounded-xl border border-zinc-700 bg-zinc-900 p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-zinc-400">Total Akun Terhitung</span>
                <span class="rounded bg-zinc-800 px-2 py-0.5 text-[10px] font-semibold text-zinc-300">Portofolio</span>
            </div>
            <p class="mt-2 font-mono text-lg font-bold text-zinc-100">
                {{ number_format($consolidatedSummary->total_accounts) }}
            </p>
            <p class="mt-1 text-[11px] text-zinc-400">
                Gabungan akun individual &amp; kolektif
            </p>
        </div>

        {{-- Card 4: Grand Total Konsolidasi CKPN --}}
        <div class="rounded-xl border border-emerald-600/40 bg-gradient-to-br from-emerald-950/40 via-zinc-900 to-zinc-900 p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-emerald-400">Total Konsolidasi CKPN</span>
                <span class="rounded bg-emerald-900/60 px-2 py-0.5 text-[10px] font-bold text-emerald-300">Grand Total</span>
            </div>
            <p class="mt-2 font-mono text-lg font-bold text-emerald-400">
                Rp {{ number_format($consolidatedSummary->grand_total, 2, ',', '.') }}
            </p>
            <p class="mt-1 text-[11px] text-zinc-400">
                Total beban cadangan kerugian
            </p>
        </div>
    </div>

    {{-- Tabel 1: Konsolidasi Total CKPN per Periode --}}
    <div class="mb-10">
        <div class="mb-3 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="inline-block rounded-md bg-emerald-950 border border-emerald-800/60 px-3 py-1 text-xs font-bold text-emerald-300">
                    KONSOLIDASI TOTAL CKPN (SELURUH SEGMEN)
                </span>
                <span class="text-xs text-zinc-400">Rekapitulasi gabungan per periode</span>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-zinc-700 bg-zinc-900 shadow-sm">
            <table class="min-w-full divide-y divide-zinc-800 whitespace-nowrap text-xs">
                <thead class="bg-zinc-800/60">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-zinc-400">Periode</th>
                        <th class="px-4 py-3 text-right font-medium text-zinc-400">CKPN Individual (Rp)</th>
                        <th class="px-4 py-3 text-right font-medium text-zinc-400">Akun Ind.</th>
                        <th class="px-4 py-3 text-right font-medium text-zinc-400">CKPN Kolektif (Rp)</th>
                        <th class="px-4 py-3 text-right font-medium text-zinc-400">Akun Kol.</th>
                        <th class="px-4 py-3 text-right font-medium text-zinc-400">Total Akun</th>
                        <th class="px-4 py-3 text-right font-medium text-emerald-400">Total Konsolidasi CKPN (Rp)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800">
                    @forelse ($consolidatedRows as $crow)
                        <tr class="hover:bg-zinc-800/50">
                            <td class="px-4 py-3 font-mono font-semibold text-zinc-200">{{ $crow->period }}</td>
                            <td class="px-4 py-3 text-right font-mono text-zinc-300">
                                {{ number_format($crow->total_individual, 2, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-zinc-400">
                                {{ number_format($crow->count_individual) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-zinc-300">
                                {{ number_format($crow->total_collective, 2, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-zinc-400">
                                {{ number_format($crow->count_collective) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-zinc-300">
                                {{ number_format($crow->total_accounts) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-emerald-400">
                                {{ number_format($crow->grand_total, 2, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-xs text-zinc-500">
                                Belum ada data hasil CKPN untuk periode yang dipilih.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($consolidatedRows->isNotEmpty())
                    <tfoot class="border-t-2 border-zinc-700 bg-zinc-800/80">
                        <tr>
                            <td class="px-4 py-3 text-xs font-bold text-zinc-200">TOTAL KONSOLIDASI</td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-zinc-100">
                                {{ number_format($consolidatedSummary->total_individual, 2, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-zinc-200">
                                {{ number_format($consolidatedSummary->count_individual) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-zinc-100">
                                {{ number_format($consolidatedSummary->total_collective, 2, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-zinc-200">
                                {{ number_format($consolidatedSummary->count_collective) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-zinc-100">
                                {{ number_format($consolidatedSummary->total_accounts) }}
                            </td>
                            <td class="px-4 py-3 text-right font-mono font-extrabold text-emerald-400 text-sm">
                                {{ number_format($consolidatedSummary->grand_total, 2, ',', '.') }}
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- Rincian per Segmen Penggunaan --}}
    <div class="mb-4">
        <h3 class="text-sm font-semibold text-zinc-200">Rincian CKPN per Segmen Penggunaan</h3>
        <p class="text-xs text-zinc-400">Breakdown CKPN Individual dan Kolektif berdasarkan jenis penggunaan pembiayaan.</p>
    </div>

    <div class="flex flex-col gap-8">
        @foreach ($rowsGrouped as $segment)
            @php
                $type = $segment['type'];
                $segRows = $segment['rows'];
                $subTotalIndividual = $segRows->sum('total_individual');
                $subTotalCollective = $segRows->sum('total_collective');
                $subTotalCount      = $segRows->sum('count_collective');
                $subTotalGrand      = $segRows->sum('grand_total');
            @endphp

            <div>
                {{-- Label Segmen --}}
                <div class="mb-2 flex items-center gap-2">
                    <span class="inline-block rounded-md bg-primary-950 px-3 py-1 text-xs font-semibold text-primary-300">
                        {{ $type->label() }}
                    </span>
                </div>

                <div class="overflow-x-auto rounded-xl border border-zinc-700 bg-zinc-900 shadow-sm">
                    <table class="min-w-full divide-y divide-zinc-800 whitespace-nowrap text-xs">
                        <thead class="bg-zinc-800/50">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-zinc-400">Periode</th>
                                <th class="px-4 py-3 text-right font-medium text-zinc-400">CKPN Individual (Rp)</th>
                                <th class="px-4 py-3 text-right font-medium text-zinc-400">CKPN Kolektif (Rp)</th>
                                <th class="px-4 py-3 text-right font-medium text-zinc-400">Jumlah Akun Kolektif</th>
                                <th class="px-4 py-3 text-right font-medium text-zinc-400">Total CKPN (Rp)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800">
                            @forelse ($segRows as $row)
                                <tr class="hover:bg-zinc-800/50">
                                    <td class="px-4 py-3 font-mono text-zinc-300">{{ $row->period }}</td>
                                    <td class="px-4 py-3 text-right font-mono text-zinc-300">
                                        {{ number_format($row->total_individual, 2, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-zinc-300">
                                        {{ number_format($row->total_collective, 2, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-zinc-300">
                                        {{ number_format($row->count_collective) }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono font-semibold text-primary-400">
                                        {{ number_format($row->grand_total, 2, ',', '.') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-xs text-zinc-500">
                                        Belum ada data CKPN segmen {{ $type->label() }} untuk periode yang dipilih.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if ($segRows->isNotEmpty())
                            <tfoot class="border-t-2 border-zinc-700 bg-zinc-800/50">
                                <tr>
                                    <td class="px-4 py-3 text-xs font-semibold text-zinc-400">Sub Total</td>
                                    <td class="px-4 py-3 text-right font-mono font-semibold text-zinc-100">
                                        {{ number_format($subTotalIndividual, 2, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono font-semibold text-zinc-100">
                                        {{ number_format($subTotalCollective, 2, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold text-zinc-100">
                                        {{ number_format($subTotalCount) }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono font-bold text-primary-400">
                                        {{ number_format($subTotalGrand, 2, ',', '.') }}
                                    </td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        @endforeach
    </div>
</div>
