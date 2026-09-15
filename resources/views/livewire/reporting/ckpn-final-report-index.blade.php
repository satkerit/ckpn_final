<div>
    {{-- Header --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-base font-semibold text-zinc-100">Laporan CKPN Final</h2>
            <p class="mt-0.5 text-xs text-zinc-400">Ref: PRD Bab 6.1 &amp; 11 — detail per nasabah CKPN Kolektif dan Individual.</p>
        </div>
        {{-- Tombol Export Excel --}}
        <button
            wire:click="exportExcel"
            wire:loading.attr="disabled"
            wire:target="exportExcel"
            type="button"
            class="inline-flex items-center gap-2 rounded-lg border border-emerald-600 bg-emerald-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1 disabled:cursor-not-allowed disabled:opacity-60"
        >
            {{-- Icon saat idle --}}
            <span wire:loading.remove wire:target="exportExcel">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/>
                </svg>
            </span>
            {{-- Spinner saat loading --}}
            <span wire:loading wire:target="exportExcel">
                <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
                </svg>
            </span>
            <span wire:loading.remove wire:target="exportExcel">Export Excel</span>
            <span wire:loading wire:target="exportExcel">Menyiapkan...</span>
        </button>
    </div>

    {{-- Kartu Ringkasan Total --}}
    <div class="mb-2 grid grid-cols-2 gap-3 sm:grid-cols-3">
        <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4 shadow-sm">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-zinc-500">Total NoA Kolektif</p>
            <p class="mt-1 text-xl font-bold text-zinc-100">{{ number_format($colTotals->noa) }}</p>
        </div>
        <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4 shadow-sm">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-zinc-500">Total EAD Kolektif</p>
            <p class="mt-1 text-xl font-bold text-primary-700">{{ number_format($colTotals->total_ead, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-4 shadow-sm">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-zinc-500">Total CKPN Kolektif</p>
            <p class="mt-1 text-xl font-bold text-indigo-700">{{ number_format($colTotals->total_ckpn, 0, ',', '.') }}</p>
        </div>
    </div>
    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3">
        <div class="rounded-xl border border-emerald-800/60 bg-emerald-950/40 p-4 shadow-sm">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-emerald-400">Total NoA Individual</p>
            <p class="mt-1 text-xl font-bold text-emerald-200">{{ number_format($indTotals->noa) }}</p>
        </div>
        <div class="rounded-xl border border-emerald-800/60 bg-emerald-950/40 p-4 shadow-sm">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-emerald-400">Total EAD Individual</p>
            <p class="mt-1 text-xl font-bold text-emerald-300">{{ number_format($indTotals->total_ead, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-emerald-800/60 bg-emerald-950/40 p-4 shadow-sm">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-emerald-400">Total CKPN Individual</p>
            <p class="mt-1 text-xl font-bold text-emerald-300">{{ number_format($indTotals->total_ckpn, 0, ',', '.') }}</p>
        </div>
    </div>

    {{-- Filter & Search --}}
    <div class="mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="mb-1 block text-xs font-medium text-zinc-400">Cari Nasabah / No. Akun</label>
            <input type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Cari..."
                class="w-48 rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-zinc-200 placeholder-zinc-500 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
        </div>
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

    {{-- ═══════════════════════════════════════════════════ --}}
    {{-- TABEL CKPN KOLEKTIF --}}
    {{-- ═══════════════════════════════════════════════════ --}}
    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-400">CKPN Kolektif</h3>
    <div class="overflow-x-auto rounded-xl border border-zinc-800 bg-zinc-900 shadow-sm">
        <table class="w-full text-xs">
            <thead>
                <tr class="border-b border-zinc-800 bg-zinc-800/50 text-left">
                    <th class="px-4 py-3 font-semibold text-zinc-400">No. Akun</th>
                    <th class="px-4 py-3 font-semibold text-zinc-400">Nama Nasabah</th>
                    <th class="px-4 py-3 font-semibold text-zinc-400">Periode</th>
                    <th class="px-4 py-3 font-semibold text-zinc-400">Jenis</th>
                    <th class="px-4 py-3 text-right font-semibold text-zinc-400">EAD</th>
                    <th class="px-4 py-3 text-right font-semibold text-zinc-400">Nominal Agunan</th>
                    <th class="px-4 py-3 text-right font-semibold text-zinc-400">Rate PD</th>
                    <th class="px-4 py-3 text-right font-semibold text-zinc-400">Rate LGD</th>
                    <th class="px-4 py-3 text-right font-semibold text-zinc-400">CKPN</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @forelse ($collectiveRows as $row)
                    <tr class="hover:bg-zinc-800/60 transition-colors">
                        <td class="px-4 py-3 font-mono text-zinc-300">{{ $row->account_number }}</td>
                        <td class="px-4 py-3 text-zinc-300">{{ $row->customer_name }}</td>
                        <td class="px-4 py-3 text-zinc-400">{{ $row->calculation_period }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-medium text-indigo-600">
                                {{ $row->usage_type instanceof \App\Enums\UsageType ? $row->usage_type->label() : $row->usage_type }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right font-mono text-zinc-300">
                            {{ number_format((float) $row->ead, 0, ',', '.') }}
                        </td>
                        <td class="px-4 py-3 text-right font-mono text-zinc-300">
                            {{ number_format((float) $row->total_collateral_value, 0, ',', '.') }}
                        </td>
                        <td class="px-4 py-3 text-right font-mono text-zinc-400">
                            {{ $row->pd_rate !== null ? number_format((float) $row->pd_rate * 100, 4, ',', '.') . '%' : '-' }}
                        </td>
                        <td class="px-4 py-3 text-right font-mono text-zinc-400">
                            {{ $row->lgd_rate !== null ? number_format((float) $row->lgd_rate * 100, 4, ',', '.') . '%' : '-' }}
                        </td>
                        <td class="px-4 py-3 text-right font-mono font-semibold text-primary-700">
                            {{ number_format((float) $row->ckpn_amount, 0, ',', '.') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-10 text-center text-xs text-zinc-500">
                            Belum ada data CKPN Kolektif untuk filter yang dipilih.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if ($collectiveRows->isNotEmpty())
            <tfoot>
                <tr class="border-t border-zinc-700 bg-zinc-800/50">
                    <td colspan="4" class="px-4 py-3 text-xs font-semibold text-zinc-400">
                        Total — {{ number_format($colTotals->noa) }} NoA
                    </td>
                    <td class="px-4 py-3 text-right font-mono font-semibold text-zinc-300">
                        {{ number_format($colTotals->total_ead, 0, ',', '.') }}
                    </td>
                    <td class="px-4 py-3"></td>
                    <td class="px-4 py-3"></td>
                    <td class="px-4 py-3"></td>
                    <td class="px-4 py-3 text-right font-mono font-bold text-primary-700">
                        {{ number_format($colTotals->total_ckpn, 0, ',', '.') }}
                    </td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>

    {{-- Pagination Kolektif --}}
    @if ($collectiveRows->hasPages())
        <div class="mt-3">{{ $collectiveRows->links() }}</div>
    @endif

    {{-- ═══════════════════════════════════════════════════ --}}
    {{-- RANGKUMAN CKPN KOLEKTIF — KONSOL PER SEGMEN --}}
    {{-- ═══════════════════════════════════════════════════ --}}
    <h3 class="mb-3 mt-8 text-xs font-semibold uppercase tracking-wide text-zinc-400">Rangkuman CKPN Kolektif — Konsol per Segmen</h3>

    <div x-data="{ openSegment: null }" class="flex flex-col gap-6">

        @foreach ($bucketSummary as $segmentKey => $segment)
            @php
                $bucketCount = $segment['rows']->count();
                $hasData = $bucketCount > 0;
            @endphp

            {{-- Kartu Konsol Segmen --}}
            <div class="overflow-hidden rounded-xl border border-zinc-700 bg-zinc-900 shadow-sm">
                {{-- Header konsol (gaya terminal) --}}
                <div class="flex flex-col gap-2 bg-slate-900 px-5 py-4 text-white sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-sky-300">Segmen</p>
                        <h4 class="mt-0.5 text-base font-semibold">{{ $segment['type']->label() }}</h4>
                    </div>
                    <button @click="openSegment = {{ $loop->index }}"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-sky-500">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        Lihat Detail
                    </button>
                </div>

                {{-- Metrik ringkas --}}
                <div class="grid grid-cols-2 gap-px bg-zinc-800 sm:grid-cols-4">
                    <div class="bg-zinc-900 px-5 py-4">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-zinc-500">Jumlah Bucket</p>
                        <p class="mt-1 text-lg font-bold text-zinc-100">{{ $hasData ? $bucketCount : 0 }}</p>
                    </div>
                    <div class="bg-zinc-900 px-5 py-4">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-zinc-500">Total EAD</p>
                        <p class="mt-1 text-lg font-bold text-zinc-100">{{ number_format($segment['total_ead'], 0, ',', '.') }}</p>
                    </div>
                    <div class="bg-zinc-900 px-5 py-4">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-zinc-500">Total CKPN</p>
                        <p class="mt-1 text-lg font-bold text-primary-700">{{ number_format($segment['total_ckpn'], 0, ',', '.') }}</p>
                    </div>
                    <div class="bg-zinc-900 px-5 py-4">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-zinc-500">Rata-rata PD</p>
                        <p class="mt-1 text-lg font-bold text-zinc-100">
                            {{ $segment['total_ead'] > 0
                                ? number_format(($segment['rows']->sum(fn ($r) => $r->wavg_pd * $r->total_ead) / $segment['total_ead']) * 100, 4, ',', '.') . '%'
                                : '-' }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- Modal Detail per Segmen --}}
            <div x-show="openSegment === {{ $loop->index }}" x-cloak
                 x-transition.opacity
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                 @keydown.escape.window="openSegment = null">
                <div @click.outside="openSegment = null"
                     class="flex max-h-[85vh] w-full max-w-4xl flex-col overflow-hidden rounded-xl bg-zinc-900 shadow-2xl">
                    <div class="flex items-center justify-between border-b border-zinc-700 bg-slate-900 px-5 py-4 text-white">
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-[0.2em] text-sky-300">Detail CKPN Kolektif</p>
                            <h4 class="mt-0.5 text-base font-semibold">Segmen — {{ $segment['type']->label() }}</h4>
                        </div>
                        <button @click="openSegment = null"
                                class="rounded-lg p-1.5 text-zinc-500 transition hover:bg-zinc-900/10 hover:text-white">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="overflow-auto p-5">
                        @if ($hasData)
                            <div class="overflow-x-auto rounded-lg border border-zinc-800">
                                <table class="w-full text-xs">
                                    <thead>
                                        <tr class="border-b border-zinc-800 bg-zinc-800/50 text-left">
                                            <th class="px-4 py-3 font-semibold text-zinc-400">Bucket</th>
                                            <th class="px-4 py-3 font-semibold text-zinc-400">Hari Tunggakan</th>
                                            <th class="px-4 py-3 text-right font-semibold text-zinc-400">Baki Debet / EAD</th>
                                            <th class="px-4 py-3 text-right font-semibold text-zinc-400">PD Net Flow</th>
                                            <th class="px-4 py-3 text-right font-semibold text-zinc-400">LGD</th>
                                            <th class="px-4 py-3 text-right font-semibold text-zinc-400">CKPN</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-50">
                                        @foreach ($segment['rows'] as $row)
                                            <tr class="hover:bg-zinc-800/50/60 transition-colors">
                                                <td class="px-4 py-3 font-mono font-semibold text-zinc-300">{{ $row->bucket_code }}</td>
                                                <td class="px-4 py-3 text-zinc-400">{{ $row->bucket_label }}</td>
                                                <td class="px-4 py-3 text-right font-mono text-zinc-300">
                                                    {{ number_format($row->total_ead, 0, ',', '.') }}
                                                </td>
                                                <td class="px-4 py-3 text-right font-mono text-zinc-400">
                                                    {{ $row->wavg_pd > 0 ? number_format($row->wavg_pd * 100, 4, ',', '.') . '%' : '-' }}
                                                </td>
                                                <td class="px-4 py-3 text-right font-mono text-zinc-400">
                                                    {{ $row->wavg_lgd > 0 ? number_format($row->wavg_lgd * 100, 4, ',', '.') . '%' : '-' }}
                                                </td>
                                                <td class="px-4 py-3 text-right font-mono font-semibold text-primary-700">
                                                    {{ number_format($row->total_ckpn, 0, ',', '.') }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr class="border-t border-zinc-700 bg-zinc-800/50">
                                            <td colspan="2" class="px-4 py-3 text-xs font-semibold text-zinc-400">Sub Total</td>
                                            <td class="px-4 py-3 text-right font-mono font-semibold text-zinc-300">
                                                {{ number_format($segment['total_ead'], 0, ',', '.') }}
                                            </td>
                                            <td class="px-4 py-3"></td>
                                            <td class="px-4 py-3"></td>
                                            <td class="px-4 py-3 text-right font-mono font-bold text-primary-700">
                                                {{ number_format($segment['total_ckpn'], 0, ',', '.') }}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        @else
                            <p class="py-10 text-center text-sm text-zinc-500">
                                Belum ada data CKPN Kolektif segmen {{ $segment['type']->label() }} untuk filter yang dipilih.
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ═══════════════════════════════════════════════════ --}}
    {{-- TABEL CKPN INDIVIDUAL --}}
    {{-- ═══════════════════════════════════════════════════ --}}
    <h3 class="mb-2 mt-8 text-xs font-semibold uppercase tracking-wide text-zinc-400">CKPN Individual</h3>
    <div class="overflow-x-auto rounded-xl border border-zinc-800 bg-zinc-900 shadow-sm">
        <table class="w-full text-xs">
            <thead>
                <tr class="border-b border-zinc-800 bg-zinc-800/50 text-left">
                    <th class="px-4 py-3 font-semibold text-zinc-400">No. Akun</th>
                    <th class="px-4 py-3 font-semibold text-zinc-400">Nama Nasabah</th>
                    <th class="px-4 py-3 font-semibold text-zinc-400">Periode</th>
                    <th class="px-4 py-3 text-right font-semibold text-zinc-400">EAD (Outstanding)</th>
                    <th class="px-4 py-3 text-right font-semibold text-zinc-400">Nominal Agunan</th>
                    <th class="px-4 py-3 text-right font-semibold text-zinc-400">Kolektibilitas</th>
                    <th class="px-4 py-3 text-right font-semibold text-zinc-400">CKPN</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @forelse ($individualRows as $row)
                    <tr class="hover:bg-zinc-800/50/60 transition-colors">
                        <td class="px-4 py-3 font-mono text-zinc-300">{{ $row->account_number }}</td>
                        <td class="px-4 py-3 text-zinc-300">{{ $row->customer_name }}</td>
                        <td class="px-4 py-3 text-zinc-400">{{ $row->calculation_period }}</td>
                        <td class="px-4 py-3 text-right font-mono text-zinc-300">
                            {{ number_format((float) $row->outstanding_balance, 0, ',', '.') }}
                        </td>
                        <td class="px-4 py-3 text-right font-mono text-zinc-300">
                            {{ number_format((float) $row->total_collateral_liquidation_value, 0, ',', '.') }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-medium text-amber-700">
                                {{ $row->collectibility }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right font-mono font-semibold text-emerald-700">
                            {{ number_format((float) $row->ckpn_amount, 0, ',', '.') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-xs text-zinc-500">
                            Belum ada data CKPN Individual untuk filter yang dipilih.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if ($individualRows->isNotEmpty())
            <tfoot>
                <tr class="border-t border-zinc-700 bg-zinc-800/50">
                    <td colspan="3" class="px-4 py-3 text-xs font-semibold text-zinc-400">
                        Total — {{ number_format($indTotals->noa) }} NoA
                    </td>
                    <td class="px-4 py-3 text-right font-mono font-semibold text-zinc-300">
                        {{ number_format($indTotals->total_ead, 0, ',', '.') }}
                    </td>
                    <td class="px-4 py-3"></td>
                    <td class="px-4 py-3"></td>
                    <td class="px-4 py-3 text-right font-mono font-bold text-emerald-700">
                        {{ number_format($indTotals->total_ckpn, 0, ',', '.') }}
                    </td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>

    {{-- Pagination Individual --}}
    @if ($individualRows->hasPages())
        <div class="mt-3">{{ $individualRows->links() }}</div>
    @endif
</div>
