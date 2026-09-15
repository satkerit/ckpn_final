<div>
    {{-- Header --}}
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-base font-semibold text-zinc-100">Preview Kalkulasi CKPN</h2>
            <p class="mt-0.5 text-xs text-zinc-400">Simulasi read-only PD &times; LGD &times; EAD dari snapshot engine — pilah Individual (Top-N saldo signifikan) vs Kolektif. Tidak menulis hasil ke tabel snapshot.</p>
        </div>
        @if ($result !== null)
            <button wire:click="exportExcel" wire:loading.attr="disabled"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-1 disabled:cursor-not-allowed disabled:opacity-60">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span wire:loading.remove wire:target="exportExcel">Export Excel</span>
                <span wire:loading wire:target="exportExcel">Menyiapkan…</span>
            </button>
        @endif
    </div>

    {{-- Filter --}}
    <div class="mb-6 rounded-xl border border-zinc-700 bg-zinc-900 p-4 shadow-sm card-lift">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="flex-1">
                <label class="mb-1 block text-xs font-medium text-zinc-300">Periode Data <span class="text-zinc-500">(yyyymm)</span></label>
                <select wire:model.live="period" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm text-zinc-200 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">Pilih periode</option>
                    @foreach ($periods as $p)
                        <option value="{{ $p }}">{{ $p }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:w-40">
                <label class="mb-1 block text-xs font-medium text-zinc-300">Top-N Individual</label>
                <input type="number" min="1" max="200" wire:model.live.debounce.400ms="topN"
                       class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm text-zinc-200 tabular focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
            </div>
        </div>
    </div>

    @if ($result === null)
        <div class="rounded-xl border border-dashed border-zinc-700 bg-zinc-900/60 px-5 py-10 text-center text-sm text-zinc-500">
            Pilih periode data untuk melihat preview kalkulasi.
        </div>
    @else
        {{-- Ringkasan --}}
        <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl bg-zinc-900 p-4 shadow-sm border border-zinc-800 card-lift animate-fade-up" style="animation-delay: 0ms">
                <p class="text-xs font-medium text-zinc-400">Total EAD</p>
                <p class="mt-1 text-xl font-bold text-zinc-100 tabular">Rp {{ number_format($result->totalEad, 0, ',', '.') }}</p>
                <p class="mt-0.5 text-xs text-zinc-500">{{ $result->individual->count() + $result->collective->count() }} akun · periode {{ $result->period }}</p>
            </div>
            <div class="rounded-xl bg-zinc-900 p-4 shadow-sm border border-zinc-800 card-lift animate-fade-up" style="animation-delay: 60ms">
                <p class="text-xs font-medium text-zinc-400">Total CKPN</p>
                <p class="mt-1 text-xl font-bold text-primary-700 tabular">Rp {{ number_format($result->totalCkpn(), 0, ',', '.') }}</p>
                <p class="mt-0.5 text-xs text-zinc-500">PD &times; LGD &times; EAD</p>
            </div>
            <div class="rounded-xl bg-zinc-900 p-4 shadow-sm border border-zinc-800 card-lift animate-fade-up" style="animation-delay: 120ms">
                <p class="text-xs font-medium text-zinc-400">CKPN Individual</p>
                <p class="mt-1 text-xl font-bold text-zinc-100 tabular">Rp {{ number_format($result->totalCkpnIndividual, 0, ',', '.') }}</p>
                <p class="mt-0.5 text-xs text-zinc-500">{{ $result->individual->count() }} akun · Top-{{ $result->topN }} outstanding</p>
            </div>
            <div class="rounded-xl bg-zinc-900 p-4 shadow-sm border border-zinc-800 card-lift animate-fade-up" style="animation-delay: 180ms">
                <p class="text-xs font-medium text-zinc-400">CKPN Kolektif</p>
                <p class="mt-1 text-xl font-bold text-zinc-100 tabular">Rp {{ number_format($result->totalCkpnCollective, 0, ',', '.') }}</p>
                <p class="mt-0.5 text-xs text-zinc-500">{{ $result->collective->count() }} akun</p>
            </div>
        </div>

        @php
            $lgdMethodLabel = fn (string $m) => $m === 'collateral_shortfall' ? 'CS' : 'ER';
        @endphp

        {{-- Tabel Individual --}}
        <div class="mb-3">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari no kontrak atau nama nasabah…"
                   class="w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-1.5 text-sm text-zinc-100 shadow-sm placeholder-zinc-500 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 sm:w-72" />
        </div>

        {{-- Tabel Individual (data) --}}
        <div class="mb-6 overflow-hidden rounded-xl bg-zinc-900 shadow-sm border border-zinc-800 animate-fade-up" style="animation-delay: 220ms">
            <div class="flex items-center justify-between border-b border-zinc-800 px-5 py-3.5">
                <div>
                    <h3 class="text-sm font-semibold text-zinc-100">Penelaahan Individual <span class="ml-1 rounded-full bg-primary-950/40 px-2 py-0.5 text-[11px] font-medium text-primary-700">Top-N Saldo Signifikan</span></h3>
                </div>
                <span class="text-xs text-zinc-500 tabular">{{ $result->individual->count() }} akun</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="border-b border-zinc-800 bg-zinc-800/50 text-left font-medium text-zinc-400">
                            <th class="px-4 py-2.5">No Kontrak</th>
                            <th class="px-4 py-2.5">Nasabah</th>
                            <th class="px-3 py-2.5">Akad</th>
                            <th class="px-3 py-2.5">Jatuh Tempo</th>
                            <th class="px-3 py-2.5">Segmen</th>
                            <th class="px-3 py-2.5 text-center">Kol</th>
                            <th class="px-4 py-2.5 text-right">EAD</th>
                            <th class="px-3 py-2.5 text-right">PD</th>
                            <th class="px-3 py-2.5 text-right">LGD</th>
                            <th class="px-4 py-2.5">Mitigasi LGD (nilai diperhitungkan / penjamin / penilaian)</th>
                            <th class="px-4 py-2.5 text-right">CKPN</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @forelse ($result->individual as $row)
                            <tr class="transition-colors hover:bg-primary-950/40 ">
                                <td class="whitespace-nowrap px-4 py-2.5 font-medium text-zinc-100 tabular">{{ $row->accountNumber }}</td>
                                <td class="max-w-[160px] truncate px-4 py-2.5 text-zinc-300" title="{{ $row->customerName }}">{{ $row->customerName }}</td>
                                <td class="px-3 py-2.5"><span class="inline-flex rounded-md bg-zinc-800 px-1.5 py-0.5 font-medium text-zinc-400">{{ $row->akadCode ?? '-' }}</span></td>
                                <td class="whitespace-nowrap px-3 py-2.5 tabular text-zinc-400">{{ $row->maturityDate ?? '-' }}</td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-zinc-400">{{ $row->usageType->label() }}</td>
                                <td class="px-3 py-2.5 text-center">
                                    @php $kolColor = match(true) { $row->collectibility >= 5 => 'bg-red-950/40 text-red-300', $row->collectibility >= 3 => 'bg-amber-100 text-amber-700', default => 'bg-emerald-950/40 text-emerald-300' }; @endphp
                                    <span class="inline-flex h-5 w-5 items-center justify-center rounded-full font-semibold {{ $kolColor }}">{{ $row->collectibility }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right text-zinc-100 tabular">{{ number_format($row->ead, 0, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-right text-zinc-400 tabular" title="{{ $row->pdMethodUsed }}">{{ number_format($row->pdRate * 100, 2, ',', '.') }}%</td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-right text-zinc-400 tabular" title="{{ $row->lgdMethodUsed }}"><span class="mr-1 rounded bg-zinc-800 px-1 text-[10px] uppercase">{{ $lgdMethodLabel($row->lgdMethodUsed) }}</span>{{ number_format($row->lgdRate * 100, 2, ',', '.') }}%</td>
                                <td class="px-4 py-2.5">
                                    <div class="tabular">{{ number_format($row->mitigationValue, 0, ',', '.') }}</div>
                                    <div class="mt-0.5 flex items-center gap-1.5 text-[11px] text-zinc-400">
                                        <span class="max-w-[140px] truncate" title="{{ $row->penjaminGroup ?: 'Tanpa jaminan' }}">{{ $row->penjaminGroup ?: '-' }}</span>
                                        @if ($row->lastAppraisalDate !== null)
                                            <span class="inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 {{ $row->appraisalValid ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}" title="Penilaian terakhir {{ $row->lastAppraisalDate }}">
                                                {{ $row->appraisalValid ? 'valid' : 'kedaluwarsa' }} · {{ \Carbon\Carbon::parse($row->lastAppraisalDate)->format('d/m/y') }}
                                            </span>
                                        @else
                                            <span class="rounded-full bg-zinc-800 px-1.5 py-0.5">tanpa penilaian</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right font-semibold text-primary-700 tabular">{{ number_format($row->ckpnAmount, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="px-4 py-6 text-center text-zinc-500">Tidak ada akun individual pada periode ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Tabel Kolektif --}}
        <div class="overflow-hidden rounded-xl bg-zinc-900 shadow-sm border border-zinc-800 animate-fade-up" style="animation-delay: 260ms">
            <div class="flex items-center justify-between border-b border-zinc-800 px-5 py-3.5">
                <div>
                    <h3 class="text-sm font-semibold text-zinc-100">Kolektif <span class="ml-1 rounded-full bg-zinc-800 px-2 py-0.5 text-[11px] font-medium text-zinc-400">Selain Top-N</span></h3>
                </div>
                <span class="text-xs text-zinc-500 tabular">{{ number_format($result->collective->count(), 0, ',', '.') }} akun</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="border-b border-zinc-800 bg-zinc-800/50 text-left font-medium text-zinc-400">
                            <th class="px-4 py-2.5">No Kontrak</th>
                            <th class="px-4 py-2.5">Nasabah</th>
                            <th class="px-3 py-2.5">Akad</th>
                            <th class="px-3 py-2.5">Jatuh Tempo</th>
                            <th class="px-3 py-2.5">Segmen</th>
                            <th class="px-3 py-2.5 text-center">Kol</th>
                            <th class="px-4 py-2.5 text-right">EAD</th>
                            <th class="px-3 py-2.5 text-right">PD</th>
                            <th class="px-3 py-2.5 text-right">LGD</th>
                            <th class="px-4 py-2.5">Mitigasi LGD (nilai diperhitungkan / penjamin / penilaian)</th>
                            <th class="px-4 py-2.5 text-right">CKPN</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        @forelse ($collectivePage as $row)
                            <tr class="transition-colors hover:bg-zinc-800/50">
                                <td class="whitespace-nowrap px-4 py-2.5 font-medium text-zinc-100 tabular">{{ $row->accountNumber }}</td>
                                <td class="max-w-[160px] truncate px-4 py-2.5 text-zinc-300" title="{{ $row->customerName }}">{{ $row->customerName }}</td>
                                <td class="px-3 py-2.5"><span class="inline-flex rounded-md bg-zinc-800 px-1.5 py-0.5 font-medium text-zinc-400">{{ $row->akadCode ?? '-' }}</span></td>
                                <td class="whitespace-nowrap px-3 py-2.5 tabular text-zinc-400">{{ $row->maturityDate ?? '-' }}</td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-zinc-400">{{ $row->usageType->label() }}</td>
                                <td class="px-3 py-2.5 text-center">
                                    @php $kolColor = match(true) { $row->collectibility >= 5 => 'bg-red-100 text-red-700', $row->collectibility >= 3 => 'bg-amber-100 text-amber-700', default => 'bg-emerald-100 text-emerald-700' }; @endphp
                                    <span class="inline-flex h-5 w-5 items-center justify-center rounded-full font-semibold {{ $kolColor }}">{{ $row->collectibility }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right text-zinc-100 tabular">{{ number_format($row->ead, 0, ',', '.') }}</td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-right text-zinc-400 tabular" title="{{ $row->pdMethodUsed }}">{{ number_format($row->pdRate * 100, 2, ',', '.') }}%</td>
                                <td class="whitespace-nowrap px-3 py-2.5 text-right text-zinc-400 tabular" title="{{ $row->lgdMethodUsed }}"><span class="mr-1 rounded bg-zinc-800 px-1 text-[10px] uppercase">{{ $lgdMethodLabel($row->lgdMethodUsed) }}</span>{{ number_format($row->lgdRate * 100, 2, ',', '.') }}%</td>
                                <td class="px-4 py-2.5">
                                    <div class="tabular">{{ number_format($row->mitigationValue, 0, ',', '.') }}</div>
                                    <div class="mt-0.5 flex items-center gap-1.5 text-[11px] text-zinc-400">
                                        <span class="max-w-[140px] truncate" title="{{ $row->penjaminGroup ?: 'Tanpa jaminan' }}">{{ $row->penjaminGroup ?: '-' }}</span>
                                        @if ($row->lastAppraisalDate !== null)
                                            <span class="inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 {{ $row->appraisalValid ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}" title="Penilaian terakhir {{ $row->lastAppraisalDate }}">
                                                {{ $row->appraisalValid ? 'valid' : 'kedaluwarsa' }} · {{ \Carbon\Carbon::parse($row->lastAppraisalDate)->format('d/m/y') }}
                                            </span>
                                        @else
                                            <span class="rounded-full bg-zinc-800 px-1.5 py-0.5">tanpa penilaian</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-2.5 text-right font-semibold text-zinc-300 tabular">{{ number_format($row->ckpnAmount, 0, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="px-4 py-6 text-center text-zinc-500">Tidak ada akun kolektif pada periode ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($collectivePage->hasPages())
                <div class="flex items-center justify-between border-t border-zinc-800 px-5 py-3">
                    <span class="text-xs text-zinc-500 tabular">Halaman {{ $collectivePage->currentPage() }} / {{ $collectivePage->lastPage() }}</span>
                    <div class="flex gap-2">
                        <button wire:click="prevKolPage" {{ $collectivePage->currentPage() <= 1 ? 'disabled' : '' }} class="rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-1 text-xs font-medium text-zinc-300 transition-colors hover:bg-zinc-800/50 disabled:cursor-not-allowed disabled:opacity-40">Sebelumnya</button>
                        <button wire:click="nextKolPage" {{ $collectivePage->hasMorePages() ? '' : 'disabled' }} class="rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-1 text-xs font-medium text-zinc-300 transition-colors hover:bg-zinc-800/50 disabled:cursor-not-allowed disabled:opacity-40">Berikutnya</button>
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
