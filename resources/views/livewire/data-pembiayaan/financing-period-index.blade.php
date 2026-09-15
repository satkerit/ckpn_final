{{-- resources/views/livewire/data-pembiayaan/financing-period-index.blade.php --}}
{{-- Ref: PRD Bab 15 - Historis Data Pembiayaan per Periode --}}
<div>
    <div class="mb-6">
        <h2 class="text-base font-semibold text-zinc-100">Historis Pembiayaan</h2>
        <p class="mt-1 text-sm text-zinc-400">Data pembiayaan per periode dari seluruh akun.</p>
    </div>

    {{-- Filters --}}
    <div class="mb-4 flex flex-wrap gap-3">
        <select wire:model.live="filterPeriod"
                class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-zinc-200 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 sm:w-44">
            <option value="">Semua Periode</option>
            @foreach ($availablePeriods as $p)
                <option value="{{ $p }}">{{ $p }}</option>
            @endforeach
        </select>

        <div class="relative">
            <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
            </svg>
            <input
                type="text"
                wire:model.live.debounce.400ms="filterAccount"
                placeholder="Cari no. kontrak / nasabah..."
                class="w-full rounded-lg border border-zinc-700 bg-zinc-950 pl-8 pr-3 py-2 text-xs text-zinc-200 placeholder-zinc-500 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 sm:w-56"
            />
        </div>

        <select wire:model.live="filterStatus"
                class="rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-zinc-200 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
            <option value="">Semua Status Pembiayaan</option>
            <option value="active">Aktif</option>
            <option value="writeoff">Write-Off</option>
            <option value="paid_off">Lunas</option>
        </select>
    </div>

    {{-- Table --}}
    <div class="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-900 shadow-sm">
        <div class="flex items-center justify-between px-4 py-3 border-b border-zinc-800">
            <div>
                <h3 class="text-sm font-semibold text-zinc-100">Daftar Pembiayaan</h3>
                <p class="text-xs text-zinc-400 mt-0.5">Data pembiayaan per periode sebagai sumber perhitungan CKPN.</p>
            </div>
            <button wire:click="exportSourceExcel" wire:loading.attr="disabled"
                class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-800 bg-zinc-900 px-3 py-1.5 text-xs font-semibold text-emerald-400 shadow-sm hover:bg-zinc-800 disabled:opacity-60 transition-colors">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                <span wire:loading.remove wire:target="exportSourceExcel">Export Sumber Data</span>
                <span wire:loading wire:target="exportSourceExcel">Menyiapkan...</span>
            </button>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="border-b border-zinc-800 bg-zinc-800/50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-zinc-400 uppercase tracking-wide">Periode</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-zinc-400 uppercase tracking-wide">No. Kontrak</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-zinc-400 uppercase tracking-wide">Nama Nasabah</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-zinc-400 uppercase tracking-wide">Tgl Awal Pembiayaan</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-zinc-400 uppercase tracking-wide">Tgl Jatuh Tempo</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-zinc-400 uppercase tracking-wide">Saldo Pokok</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-zinc-400 uppercase tracking-wide">Kolektibilitas</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-zinc-400 uppercase tracking-wide">TGK Hari</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-zinc-400 uppercase tracking-wide">TGK Modal</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-zinc-400 uppercase tracking-wide">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800">
                    @forelse ($periods as $row)
                        <tr class="hover:bg-zinc-800/50 transition-colors">
                            <td class="px-5 py-3 font-mono text-xs text-zinc-300">{{ $row->period }}</td>
                            <td class="px-5 py-3 font-mono text-xs text-zinc-100">
                                {{ $row->financingAccount?->account_number ?? $row->financing_account_id }}
                            </td>
                            <td class="px-5 py-3 text-zinc-300 text-xs">
                                {{ $row->financingAccount?->customer_name ?? '-' }}
                            </td>
                            <td class="px-5 py-3 text-zinc-400 text-xs whitespace-nowrap">
                                {{ $row->origination_date ?? '-' }}
                            </td>
                            <td class="px-5 py-3 text-zinc-400 text-xs whitespace-nowrap">
                                {{ $row->maturity_date ?? '-' }}
                            </td>
                            <td class="px-5 py-3 text-right tabular-nums text-xs text-zinc-300">
                                {{ number_format((float) $row->outstanding_balance, 0, ',', '.') }}
                            </td>
                            <td class="px-5 py-3 text-center">
                                @php
                                    $colClass = match((int) $row->collectibility) {
                                        1       => 'bg-emerald-900/40 text-emerald-300',
                                        2       => 'bg-yellow-900/40 text-yellow-300',
                                        3       => 'bg-orange-900/40 text-orange-300',
                                        4, 5    => 'bg-red-950/60 text-red-300',
                                        default => 'bg-zinc-800 text-zinc-400',
                                    };
                                @endphp
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $colClass }}">
                                    {{ $row->collectibility ?? '-' }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right tabular-nums text-xs text-zinc-400">
                                {{ $row->tgkhari !== null ? number_format((int) $row->tgkhari) : '-' }}
                            </td>
                            <td class="px-5 py-3 text-right tabular-nums text-xs text-zinc-400">
                                {{ $row->tgkmdl !== null ? number_format((float) $row->tgkmdl, 0, ',', '.') : '-' }}
                            </td>
                            <td class="px-5 py-3 text-xs text-zinc-400">
                                {{ $row->financing_status?->value ?? '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-5 py-10 text-center text-zinc-500 text-sm">
                                @if ($filterPeriod || $filterAccount || $filterStatus)
                                    Tidak ada data yang sesuai dengan filter.
                                @else
                                    Belum ada data historis pembiayaan.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if ($periods->hasPages())
            <div class="border-t border-zinc-800 px-5 py-3">
                {{ $periods->links() }}
            </div>
        @endif
    </div>

    <p class="mt-3 text-xs text-zinc-500">
        Menampilkan {{ $periods->firstItem() ?? 0 }}–{{ $periods->lastItem() ?? 0 }}
        dari {{ number_format($periods->total()) }} baris
    </p>
</div>

