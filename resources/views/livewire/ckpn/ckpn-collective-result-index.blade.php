<div>
    {{-- Header --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-base font-semibold text-zinc-100">Hasil CKPN Kolektif</h2>
            <p class="mt-0.5 text-xs text-zinc-400">Tabel hasil ckpn_collective_result (PD × LGD × EAD) per debitur. Ref: PRD Bab 11</p>
        </div>
        @if($results->total() > 0)
            <div class="flex items-center gap-4 rounded-xl border border-zinc-700 bg-zinc-900 px-4 py-2.5 shadow-sm">
                <div>
                    <p class="text-xs font-medium text-zinc-400">Total CKPN</p>
                    <p class="mt-0.5 font-mono text-sm font-semibold text-emerald-700">
                        Rp {{ number_format($totalCkpn, 0, ',', '.') }}
                    </p>
                </div>
                <div class="h-8 w-px bg-zinc-700"></div>
                <div>
                    <p class="text-xs font-medium text-zinc-400">Total EAD</p>
                    <p class="mt-0.5 font-mono text-sm font-semibold text-zinc-100">
                        Rp {{ number_format($totalOutstanding, 0, ',', '.') }}
                    </p>
                </div>
            </div>
        @endif
    </div>

    {{-- Panel Kontrol Perhitungan --}}
    <div class="mb-6 rounded-xl border border-zinc-700 bg-zinc-900 p-5 shadow-sm">
        <div class="flex flex-col gap-4">
            {{-- Header panel + badge status --}}
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-zinc-100">Periode Perhitungan CKPN Kolektif</h3>
                    <p class="mt-0.5 text-xs text-zinc-400">Pilih periode, tampilkan data, lalu jalankan perhitungan.</p>
                </div>
                @if($runPeriode !== '')
                    @if($periodCalculated)
                        <span class="inline-flex w-fit items-center gap-1.5 rounded-full border border-emerald-800/60 bg-emerald-950/60 px-3 py-1 text-xs font-medium text-emerald-300">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                            Sudah dihitung
                        </span>
                    @else
                        <span class="inline-flex w-fit items-center gap-1.5 rounded-full border border-zinc-700 bg-zinc-800 px-3 py-1 text-xs font-medium text-zinc-300">
                            <span class="h-1.5 w-1.5 rounded-full bg-zinc-400"></span>
                            Belum dihitung
                        </span>
                    @endif
                @endif
            </div>

            {{-- Form: periode + tombol aksi --}}
            <div class="flex flex-col gap-4 border-t border-zinc-800 pt-4 md:flex-row md:items-end md:justify-between">
                <div class="w-full md:w-72">
                    <label for="run-periode" class="mb-1.5 block text-xs font-medium text-zinc-300">Periode Penetapan</label>
                    <select id="run-periode" wire:model.live="runPeriode"
                            class="h-10 w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 text-sm text-zinc-200 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                        <option value="">Pilih periode penetapan</option>
                        @foreach($periods as $period)
                            <option value="{{ $period->period }}">{{ $period->period }} — {{ str_replace('_', ' ', $period->status) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    @if($runPeriode !== '')
                        @if(! $runPeriodeHasResult)
                            {{-- Belum ada data → Hitung saja --}}
                            <button wire:click="confirmJalankan"
                                @if(! $pdLgdAvailable) disabled @endif
                                class="inline-flex h-10 items-center gap-1.5 rounded-lg px-4 text-sm font-medium text-white shadow-sm transition-colors
                                    {{ ! $pdLgdAvailable ? 'bg-zinc-800 text-zinc-500 border border-zinc-700 cursor-not-allowed' : 'bg-primary-600 hover:bg-primary-500' }}">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 010 1.972l-11.54 6.347a1.125 1.125 0 01-1.667-.986V5.653z" />
                                </svg>
                                Hitung CKPN Kolektif
                            </button>
                        @elseif(! $showResults)
                            {{-- Ada data, belum ditampilkan → Tampil saja --}}
                            <button wire:click="tampilkanData" wire:loading.attr="disabled" wire:target="tampilkanData"
                                class="inline-flex h-10 items-center gap-1.5 rounded-lg bg-emerald-600 px-4 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-500 disabled:opacity-60">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <span wire:loading.remove wire:target="tampilkanData">Tampil</span>
                                <span wire:loading wire:target="tampilkanData">Memuat...</span>
                            </button>
                        @else
                            {{-- Data sudah ditampilkan → Rekalkulasi + Hapus saja --}}
                            <button wire:click="confirmRekalkulasi"
                                class="inline-flex h-10 items-center gap-1.5 rounded-lg bg-amber-500 px-4 text-sm font-medium text-zinc-950 shadow-sm transition hover:bg-amber-400 font-semibold">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                                </svg>
                                Rekalkulasi
                            </button>
                            <button wire:click="confirmHapus"
                                class="inline-flex h-10 items-center gap-1.5 rounded-lg border border-rose-800/80 bg-rose-950/40 px-4 text-sm font-medium text-rose-300 shadow-sm transition hover:bg-rose-900/50">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                </svg>
                                Hapus Perhitungan
                            </button>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        {{-- Info metode PD per segmen (dinamis dari parameter) --}}
        @if($pdMethodPerSegmen->isNotEmpty())
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach($pdMethodPerSegmen as $segmen => $method)
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $method === 'NETFLOW' ? 'bg-primary-50 text-primary-700' : 'bg-violet-50 text-violet-700' }}">
                        {{ $segmen }}: PD {{ $method }}
                    </span>
                @endforeach
            </div>
        @endif

        {{-- Guard: tampilkan peringatan jika PD/LGD belum tersedia --}}
        @if($runPeriode !== '' && ! $pdLgdAvailable)
            <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5">
                <p class="text-xs font-semibold text-amber-800 mb-1">Data PD dan/atau LGD Final belum tersedia untuk periode ini:</p>
                <ul class="space-y-0.5">
                    @foreach($pdLgdMissing as $item)
                        <li class="text-xs text-amber-700">• {{ $item }}</li>
                    @endforeach
                </ul>
                <p class="mt-1.5 text-xs text-amber-600">Jalankan perhitungan PD (Netflow/Migration) dan LGD (Expected Recoveries) terlebih dahulu.</p>
            </div>
        @endif

        {{-- Status run (polling saat job berjalan) --}}
        @if($isRunning && $runLogs->count() > 0)
            <div class="mt-3 space-y-1" wire:poll.3s="pollJobStatus">
                @foreach($runLogs as $log)
                    @php
                        $statusColor = match($log->status) {
                            \App\Enums\RunStatus::Completed  => 'text-emerald-700 bg-emerald-50',
                            \App\Enums\RunStatus::Failed     => 'text-red-700 bg-red-50',
                            \App\Enums\RunStatus::Processing => 'text-blue-700 bg-blue-50',
                            default                          => 'text-zinc-400 bg-zinc-800',
                        };
                    @endphp
                    <div class="flex items-center justify-between rounded-md px-3 py-1.5 text-xs {{ $statusColor }}">
                        <span class="font-medium">{{ $log->usage_type?->label() ?? $log->usage_type }}</span>
                        <span>{{ $log->status->value }}</span>
                        @if($log->error_message)
                            <span class="ml-2 max-w-xs truncate text-red-600" title="{{ $log->error_message }}">
                                {{ Str::limit($log->error_message, 60) }}
                            </span>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Flash message --}}
    @if($flashMessage)
        <div class="mb-4 rounded-lg px-4 py-2.5 text-sm {{ $flashType === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200' }}">
            {{ $flashMessage }}
        </div>
    @endif

    {{-- Placeholder sebelum data ditampilkan --}}
    @if(! $showResults)
        <div class="flex flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-zinc-700 bg-zinc-800/50/50 py-16 text-center">
            <svg class="h-10 w-10 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m6 4.125l2.25 2.25m0 0l2.25 2.25m-2.25-2.25l2.25-2.25m-2.25 2.25l-2.25-2.25m9.75-6.75H3.75A.75.75 0 003 4.5v1.5a.75.75 0 00.75.75h16.5a.75.75 0 00.75-.75V4.5a.75.75 0 00-.75-.75z" />
            </svg>
            <p class="text-sm font-medium text-zinc-400">Pilih periode perhitungan untuk melihat data</p>
            <p class="text-xs text-zinc-500">Klik "Tampilkan Data" untuk menampilkan hasil perhitungan, atau "Hitung" untuk menjalankan perhitungan baru.</p>
        </div>
    @else
        {{-- Ringkasan per Segmen — Kertas Kerja B --}}
        @if(count($summaryPerSegment) > 0)
        <div class="mb-6">
            <div class="mb-2 flex items-center gap-2 rounded-t-xl bg-emerald-700 px-4 py-2.5">
                <span class="text-sm font-semibold text-white">Ringkasan CKPN Kolektif per Segmen — Kertas Kerja B</span>
                <span class="ml-auto text-xs text-emerald-200">Periode: {{ $filterPeriode }}</span>
            </div>
            <div class="overflow-hidden rounded-b-xl border border-t-0 border-emerald-200 bg-zinc-900 shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-800 text-sm">
                        <thead class="bg-emerald-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-300">Segmen</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-zinc-300">Jml Akun</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-zinc-300">Total EAD (Baki Debet)</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-zinc-300">Metode PD</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-zinc-300">Rata-rata PD</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold text-zinc-300">Metode LGD</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-zinc-300">Rata-rata LGD</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-emerald-700">Total CKPN</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800">
                            @foreach($summaryPerSegment as $seg)
                            <tr class="hover:bg-emerald-50/50 transition-colors">
                                <td class="px-4 py-3 font-medium text-zinc-100">{{ $seg['label'] }}</td>
                                <td class="px-4 py-3 text-center font-mono text-zinc-400">{{ number_format($seg['account_count']) }}</td>
                                <td class="px-4 py-3 text-right font-mono text-zinc-100">
                                    {{ number_format($seg['total_ead'], 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex rounded-full bg-blue-950/40 text-blue-300 ring-1 ring-inset ring-blue-800/60">
                                        {{ $seg['pd_method'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-zinc-300">
                                    {{ number_format($seg['avg_pd_rate'] * 100, 4) }}%
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex rounded-full bg-violet-950/40 text-violet-300 ring-1 ring-inset ring-violet-800/60">
                                        {{ $seg['lgd_method'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-zinc-300">
                                    {{ number_format($seg['avg_lgd_rate'] * 100, 4) }}%
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-semibold text-emerald-700">
                                    {{ number_format($seg['total_ckpn'], 0, ',', '.') }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-zinc-800/50">
                            <tr class="border-t-2 border-zinc-700">
                                <td class="px-4 py-3 text-xs font-bold text-zinc-300" colspan="2">TOTAL</td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-zinc-100">
                                    {{ number_format(array_sum(array_column($summaryPerSegment, 'total_ead')), 0, ',', '.') }}
                                </td>
                                <td colspan="4"></td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-emerald-700">
                                    {{ number_format(array_sum(array_column($summaryPerSegment, 'total_ckpn')), 0, ',', '.') }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        @endif

        {{-- Filter --}}
        <div class="mb-4 flex flex-wrap gap-3">
            <div class="sm:w-48">
                <label class="block text-xs font-medium text-zinc-300 mb-1">Jenis Penggunaan</label>
                <select wire:model.live="filterUsageType"
                        class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm text-zinc-200 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">Semua Segmen</option>
                    @foreach($usageTypes as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:w-40">
                <label class="block text-xs font-medium text-zinc-300 mb-1">Periode</label>
                <input type="text" wire:model.live="filterPeriode" placeholder="yyyymm"
                       maxlength="6"
                       class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm text-zinc-200 shadow-sm placeholder-zinc-500 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
            </div>
            @if($filterUsageType || $filterPeriode)
                <div class="flex items-end">
                    <button wire:click="$set('filterUsageType',''); $set('filterPeriode','');"
                            class="text-xs text-zinc-400 hover:text-zinc-200 underline">Reset filter</button>
                </div>
            @endif
        </div>

        {{-- Search --}}
        <div class="mb-3">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari periode, segmen, atau nama debitur…"
                   class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm text-zinc-200 shadow-sm placeholder-zinc-500 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 sm:w-80" />
        </div>

        {{-- Toolbar bulk select --}}
        @if(count($selectedIds) > 0)
            <div class="mb-3 flex items-center gap-3 rounded-lg border border-rose-800/80 bg-rose-950/40 px-4 py-2.5">
                <span class="text-sm font-medium text-rose-300">{{ count($selectedIds) }} baris dipilih</span>
                <button wire:click="konfirmasiBulkHapus"
                        class="inline-flex h-8 items-center gap-1.5 rounded-lg bg-rose-600 px-3 text-xs font-medium text-white shadow-sm transition hover:bg-rose-500">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                    </svg>
                    Hapus Terpilih
                </button>
                <button wire:click="$set('selectedIds', []); $set('selectAll', false)"
                        class="text-xs text-rose-400 hover:text-rose-200 underline">
                    Batal pilih
                </button>
            </div>
        @endif

        {{-- Panel Info Catatan Perhitungan --}}
        @php $completedLogsColl = $runLogs->filter(fn($l) => $l->notes); @endphp
        @if($completedLogsColl->isNotEmpty())
            <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 p-4 shadow-sm">
                <h3 class="mb-2 text-sm font-semibold text-rose-800">Informasi Perhitungan CKPN Kolektif</h3>
                <div class="space-y-3">
                    @foreach($completedLogsColl as $log)
                        <div class="rounded-lg border border-rose-100 bg-zinc-900 p-3">
                            <div class="mb-1 flex items-center gap-2">
                                <span class="inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-800">
                                    {{ $log->usage_type?->label() ?? $log->usage_type }}
                                </span>
                                <span class="text-xs text-zinc-500">Periode: {{ $log->period }}</span>
                            </div>
                            <p class="whitespace-pre-line text-xs text-zinc-300">{{ $log->notes }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Tabel --}}
        <div class="overflow-hidden rounded-xl border border-zinc-700 bg-zinc-900 shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-800 text-sm">
                    <thead class="bg-zinc-800/50">
                        <tr>
                            <th class="px-4 py-3 text-left">
                                <input type="checkbox" wire:model.live="selectAll"
                                       class="h-4 w-4 rounded border-zinc-700 text-primary-600 focus:ring-primary-500" />
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-400">Periode</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-400">Jenis Penggunaan</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-400">Nama Debitur</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-zinc-400">Bucket / Kualitas</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-400">Metode PD</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-zinc-400">PD Rate</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-400">Metode LGD</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-zinc-400">LGD Rate</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-zinc-400">EAD</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-zinc-400">CKPN Amount</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-zinc-400">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        @forelse($results as $row)
                            <tr class="hover:bg-zinc-800/50 transition-colors {{ in_array((string) $row->id, $selectedIds) ? 'bg-primary-50/40' : '' }}">
                                <td class="px-4 py-3">
                                    <input type="checkbox" wire:model.live="selectedIds" value="{{ $row->id }}"
                                           class="h-4 w-4 rounded border-zinc-700 text-primary-600 focus:ring-primary-500" />
                                </td>
                                <td class="px-4 py-3 font-mono text-zinc-100">{{ $row->calculation_period }}</td>
                                <td class="px-4 py-3 text-zinc-300">{{ $row->usage_type?->label() ?? '-' }}</td>
                                <td class="px-4 py-3 text-zinc-300">{{ $row->financingAccount?->customer_name ?? '-' }}</td>
                                <td class="px-4 py-3 text-center">
                                    @if($row->pd_method_used === 'netflow' && $row->pd_bucket_id !== null)
                                        {{-- Netflow: tampilkan label bucket dari relasi --}}
                                        <span class="inline-flex items-center rounded-md bg-blue-100 px-1.5 py-0.5 text-xs font-semibold text-blue-700"
                                              title="Bucket PD Netflow">
                                            {{ $row->pdBucket?->code ?? 'B'.$row->pd_bucket_id }}
                                        </span>
                                    @elseif($row->pd_method_used === 'migration' && $row->pd_quality_grade_id !== null)
                                        {{-- Migration: tampilkan kualitas pembiayaan --}}
                                        @php
                                            $qLabel = match($row->pd_quality_grade_id) {
                                                1 => ['label' => 'Lancar', 'class' => 'bg-emerald-100 text-emerald-700'],
                                                2 => ['label' => 'DPK', 'class' => 'bg-yellow-100 text-yellow-700'],
                                                3 => ['label' => 'KL', 'class' => 'bg-orange-100 text-orange-700'],
                                                4 => ['label' => 'Diragukan', 'class' => 'bg-red-100 text-red-700'],
                                                5 => ['label' => 'Macet', 'class' => 'bg-red-200 text-red-800'],
                                                default => ['label' => '-', 'class' => 'bg-zinc-800 text-zinc-400'],
                                            };
                                        @endphp
                                        <span class="inline-flex items-center rounded-md px-1.5 py-0.5 text-xs font-semibold {{ $qLabel['class'] }}"
                                              title="Kualitas Pembiayaan PD Migration">
                                            {{ $qLabel['label'] }}
                                        </span>
                                    @else
                                        <span class="text-xs text-zinc-500">-</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-zinc-400 text-xs uppercase font-medium">{{ $row->pd_method_used ?? '-' }}</td>
                                <td class="px-4 py-3 text-right font-mono text-zinc-100">
                                    {{ number_format((float) $row->pd_rate * 100, 4) }}%
                                </td>
                                <td class="px-4 py-3 text-zinc-400 text-xs">{{ $row->lgd_method_used ?? '-' }}</td>
                                <td class="px-4 py-3 text-right font-mono text-zinc-100">
                                    {{ number_format((float) $row->lgd_rate * 100, 4) }}%
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-zinc-100">
                                    {{ number_format((float) $row->ead, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-semibold text-zinc-100">
                                    {{ number_format((float) $row->ckpn_amount, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <button wire:click="konfirmasiHapus({{ $row->id }})"
                                            class="rounded-md p-1.5 text-zinc-500 hover:bg-red-50 hover:text-red-600 transition-colors"
                                            title="Hapus baris ini">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13" class="px-4 py-10 text-center text-zinc-500">
                                    Belum ada data hasil CKPN Kolektif.
                                    @if($filterUsageType || $filterPeriode)
                                        <br><span class="text-xs">Coba ubah filter atau jalankan perhitungan untuk periode terpilih.</span>
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($results->hasPages())
                <div class="border-t border-zinc-800 px-4 py-3">
                    {{ $results->links() }}
                </div>
            @endif
        </div>
    @endif

    {{-- Modal Konfirmasi Perhitungan --}}
    <div x-show="$wire.confirmingAction === 'hitung'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="w-full max-w-sm rounded-xl border border-zinc-800 bg-zinc-900 p-6 shadow-2xl">
            <h3 class="font-semibold text-zinc-100 mb-2">Konfirmasi Perhitungan</h3>
            <p class="text-sm text-zinc-300 mb-4">Yakin ingin menjalankan perhitungan CKPN Kolektif untuk periode <span class="font-semibold text-white">{{ $runPeriode }}</span>? Proses akan dimasukkan ke antrean.</p>
            <div class="flex gap-2 justify-end">
                <button wire:click="$set('confirmingAction', '')" class="px-3.5 py-1.5 text-xs font-medium rounded-lg border border-zinc-700 bg-zinc-800 text-zinc-300 hover:bg-zinc-700">Batal</button>
                <button wire:click="jalankanPerhitungan" class="px-3.5 py-1.5 text-xs font-semibold rounded-lg bg-primary-600 text-white hover:bg-primary-500">Ya, Jalankan</button>
            </div>
        </div>
    </div>

    {{-- Modal Konfirmasi Rekalkulasi --}}
    <div x-show="$wire.confirmingAction === 'rekalkulasi'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="w-full max-w-sm rounded-xl border border-zinc-800 bg-zinc-900 p-6 shadow-2xl">
            <h3 class="font-semibold text-zinc-100 mb-2">Konfirmasi Rekalkulasi</h3>
            <p class="text-sm text-zinc-300 mb-4">Yakin ingin menghitung ulang CKPN Kolektif untuk periode <span class="font-semibold text-white">{{ $runPeriode }}</span>? Hasil lama akan tetap tersimpan sebagai history, hasil baru ditambahkan sebagai run terpisah.</p>
            <div class="flex gap-2 justify-end">
                <button wire:click="$set('confirmingAction', '')" class="px-3.5 py-1.5 text-xs font-medium rounded-lg border border-zinc-700 bg-zinc-800 text-zinc-300 hover:bg-zinc-700">Batal</button>
                <button wire:click="rekalkulasi" class="px-3.5 py-1.5 text-xs font-semibold rounded-lg bg-amber-500 text-zinc-950 hover:bg-amber-400">Ya, Rekalkulasi</button>
            </div>
        </div>
    </div>

    {{-- Modal Pilih Metode PD (Mode Dual: hitung_dual) --}}
    <div x-show="$wire.confirmingAction === 'hitung_dual'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="w-full max-w-md rounded-xl border border-zinc-800 bg-zinc-900 p-6 shadow-2xl">
            <h3 class="font-semibold text-zinc-100 mb-1">Pilih Metode PD untuk Perhitungan</h3>
            <p class="text-xs text-zinc-400 mb-4">
                Mode dual aktif. Pilih metode PD yang akan digunakan untuk menghitung CKPN Kolektif periode
                <span class="font-semibold text-zinc-200">{{ $runPeriode }}</span>.
            </p>
            <div class="grid grid-cols-2 gap-3 mb-5">
                <button wire:click="jalankanDenganMetode('netflow')"
                        class="flex flex-col items-center gap-2 rounded-xl border border-zinc-700 bg-zinc-950 p-4 text-left hover:border-primary-500 hover:bg-zinc-800/60 transition-colors group">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-blue-950/60 text-blue-300 border border-blue-800/60">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                        </svg>
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-zinc-100">PD Netflow</p>
                        <p class="text-xs text-zinc-400 mt-0.5">Berdasarkan pergerakan bucket (netflow rate)</p>
                    </div>
                </button>
                <button wire:click="jalankanDenganMetode('migration')"
                        class="flex flex-col items-center gap-2 rounded-xl border border-zinc-700 bg-zinc-950 p-4 text-left hover:border-amber-500 hover:bg-zinc-800/60 transition-colors group">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-amber-950/60 text-amber-300 border border-amber-800/60">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                        </svg>
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-zinc-100">PD Migration</p>
                        <p class="text-xs text-zinc-400 mt-0.5">Berdasarkan matriks perpindahan kualitas</p>
                    </div>
                </button>
            </div>
            <div class="flex justify-end">
                <button wire:click="batalDual" class="px-3.5 py-1.5 text-xs font-medium rounded-lg border border-zinc-700 bg-zinc-800 text-zinc-300 hover:bg-zinc-700">Batal</button>
            </div>
        </div>
    </div>

    {{-- Modal Pilih Metode PD (Mode Dual: rekalkulasi_dual) --}}
    <div x-show="$wire.confirmingAction === 'rekalkulasi_dual'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="w-full max-w-md rounded-xl border border-zinc-800 bg-zinc-900 p-6 shadow-2xl">
            <h3 class="font-semibold text-zinc-100 mb-1">Pilih Metode PD untuk Rekalkulasi</h3>
            <p class="text-xs text-zinc-400 mb-4">
                Mode dual aktif. Pilih metode PD yang akan digunakan untuk rekalkulasi CKPN Kolektif periode
                <span class="font-semibold text-zinc-200">{{ $runPeriode }}</span>.
                Hasil lama tetap tersimpan sebagai history.
            </p>
            <div class="grid grid-cols-2 gap-3 mb-5">
                <button wire:click="jalankanDenganMetode('netflow')"
                        class="flex flex-col items-center gap-2 rounded-xl border border-zinc-700 bg-zinc-950 p-4 text-left hover:border-primary-500 hover:bg-zinc-800/60 transition-colors group">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-blue-950/60 text-blue-300 border border-blue-800/60">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                        </svg>
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-zinc-100">PD Netflow</p>
                        <p class="text-xs text-zinc-400 mt-0.5">Berdasarkan pergerakan bucket (netflow rate)</p>
                    </div>
                </button>
                <button wire:click="jalankanDenganMetode('migration')"
                        class="flex flex-col items-center gap-2 rounded-xl border border-zinc-700 bg-zinc-950 p-4 text-left hover:border-amber-500 hover:bg-zinc-800/60 transition-colors group">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-amber-950/60 text-amber-300 border border-amber-800/60">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
                        </svg>
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-zinc-100">PD Migration</p>
                        <p class="text-xs text-zinc-400 mt-0.5">Berdasarkan matriks perpindahan kualitas</p>
                    </div>
                </button>
            </div>
            <div class="flex justify-end">
                <button wire:click="batalDual" class="px-3.5 py-1.5 text-xs font-medium rounded-lg border border-zinc-700 bg-zinc-800 text-zinc-300 hover:bg-zinc-700">Batal</button>
            </div>
        </div>
    </div>

    {{-- Modal Konfirmasi Hapus Periode --}}
    <div x-show="$wire.confirmingAction === 'hapus'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="w-full max-w-sm rounded-xl border border-zinc-800 bg-zinc-900 p-6 shadow-2xl">
            <h3 class="font-semibold text-zinc-100 mb-2">Konfirmasi Hapus</h3>
            <p class="text-sm text-zinc-300 mb-4">Yakin ingin menghapus seluruh hasil perhitungan periode <span class="font-semibold text-white">{{ $runPeriode }}</span>?</p>
            <div class="flex gap-2 justify-end">
                <button wire:click="$set('confirmingAction', '')" class="px-3.5 py-1.5 text-xs font-medium rounded-lg border border-zinc-700 bg-zinc-800 text-zinc-300 hover:bg-zinc-700">Batal</button>
                <button wire:click="hapusPerhitungan" class="px-3.5 py-1.5 text-xs font-semibold rounded-lg bg-rose-600 text-white hover:bg-rose-500">Ya, Hapus</button>
            </div>
        </div>
    </div>

    {{-- Modal Konfirmasi Hapus Baris --}}
    <div x-show="$wire.deletingId !== null" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="w-full max-w-sm rounded-xl border border-zinc-800 bg-zinc-900 p-6 shadow-2xl">
            <h3 class="font-semibold text-zinc-100 mb-2">Konfirmasi Hapus</h3>
            <p class="text-sm text-zinc-300 mb-4">Yakin ingin menghapus baris hasil ini? Tindakan ini tidak dapat dibatalkan.</p>
            <div class="flex gap-2 justify-end">
                <button wire:click="batalHapus" class="px-3.5 py-1.5 text-xs font-medium rounded-lg border border-zinc-700 bg-zinc-800 text-zinc-300 hover:bg-zinc-700">Batal</button>
                <button wire:click="hapus" class="px-3.5 py-1.5 text-xs font-semibold rounded-lg bg-rose-600 text-white hover:bg-rose-500">Ya, Hapus</button>
            </div>
        </div>
    </div>

    {{-- Modal Konfirmasi Bulk Hapus --}}
    @if($confirmingAction === 'bulk_hapus')
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-sm rounded-xl bg-zinc-900 p-6 shadow-xl">
                <h3 class="font-semibold text-zinc-100 mb-2">Konfirmasi Hapus Massal</h3>
                <p class="text-sm text-zinc-400 mb-4">
                    Yakin ingin menghapus <strong>{{ count($selectedIds) }} baris</strong> yang dipilih?
                    Tindakan ini tidak dapat dibatalkan.
                </p>
                <div class="flex gap-2 justify-end">
                    <button wire:click="batalBulkHapus"
                            class="px-3 py-1.5 text-sm rounded-lg border border-zinc-700 text-zinc-400 hover:bg-zinc-800/50">
                        Batal
                    </button>
                    <button wire:click="bulkHapus"
                            class="px-3 py-1.5 text-sm rounded-lg bg-red-600 text-white hover:bg-red-700">
                        Ya, Hapus Semua
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
