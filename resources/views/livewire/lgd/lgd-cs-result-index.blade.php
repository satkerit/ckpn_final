<div>
    {{-- Header --}}
    <div class="mb-6">
        <h2 class="text-base font-semibold text-zinc-100">LGD Collateral Shortfall</h2>
        <p class="mt-1 text-xs text-zinc-400">Pivot detail per segmen & per akun + trigger perhitungan snapshot. Ref: PRD Bab 10</p>
    </div>

    {{-- Panel Kontrol (Periode + Segmen + Tombol) --}}
    <div class="mb-6 rounded-xl border border-zinc-800 bg-zinc-900 p-5 shadow-sm">
        <h3 class="mb-4 text-sm font-semibold text-zinc-100">Periode & Segmen</h3>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
            {{-- Select Periode --}}
            <div class="sm:w-52">
                <label class="mb-1 block text-xs font-medium text-zinc-400">Periode Perhitungan</label>
                <select wire:model.live="runPeriode"
                    class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm text-zinc-200 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">-- Pilih Periode --</option>
                    @foreach($availablePeriods as $p)
                        <option value="{{ $p }}">{{ $p }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Select Segmen untuk Pivot --}}
            <div class="sm:w-56">
                <label class="mb-1 block text-xs font-medium text-zinc-400">Jenis Penggunaan (Pivot Detail)</label>
                <select wire:model="runUsageTypePivot"
                    class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm text-zinc-200 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">Semua Segmen</option>
                    @foreach($usageTypes as $ut)
                        <option value="{{ $ut->value }}">{{ $ut->label() }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Tombol aksi --}}
            @if($runPeriode !== '')
                <div class="flex flex-wrap items-center gap-2">
                    @if(! $runPeriodeHasResult)
                        {{-- Belum ada hasil: hanya tombol Hitung --}}
                        <button wire:click="confirmJalankan"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-primary-700 transition-colors">
                            Hitung
                        </button>
                    @elseif(! $showResults)
                        {{-- Ada hasil tapi belum ditampilkan: tombol Tampil --}}
                        <button wire:click="tampilkanData" wire:loading.attr="disabled" wire:target="tampilkanData"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 disabled:opacity-60 transition-colors">
                            <span wire:loading.remove wire:target="tampilkanData">Tampil</span>
                            <span wire:loading wire:target="tampilkanData">Memuat...</span>
                        </button>
                    @else
                        {{-- Data sudah ditampilkan: Lihat Detail + Rekalkulasi + Hapus --}}
                        <button wire:click="loadPivot" wire:loading.attr="disabled" wire:target="loadPivot"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-zinc-700 px-4 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-zinc-800 disabled:opacity-60 transition-colors">
                            <span wire:loading.remove wire:target="loadPivot">Lihat Detail</span>
                            <span wire:loading wire:target="loadPivot">Memuat...</span>
                        </button>
                        <button wire:click="confirmRekalkulasi"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-amber-500 px-4 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-amber-600 transition-colors">
                            Rekalkulasi
                        </button>
                        <button wire:click="confirmHapus"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-rose-800/80 bg-rose-950/40 px-4 py-1.5 text-xs font-semibold text-rose-300 shadow-sm hover:bg-rose-900/60 transition-colors">
                            Hapus Perhitungan
                        </button>
                    @endif
                </div>
            @endif
        </div>

        {{-- Status job per UsageType --}}
        @if($isRunning && count($runLogs) > 0)
            <div class="mt-3 space-y-1" wire:poll.3s="pollJobStatus">
                @foreach($runLogs as $log)
                    @php
                        $statusColor = match($log->status) {
                            \App\Enums\RunStatus::Completed  => 'border border-emerald-800/60 bg-emerald-950/40 text-emerald-300',
                            \App\Enums\RunStatus::Failed     => 'border border-rose-800/60 bg-rose-950/40 text-rose-300',
                            \App\Enums\RunStatus::Processing => 'border border-blue-800/60 bg-blue-950/40 text-blue-300',
                            default                          => 'border border-zinc-700 bg-zinc-800 text-zinc-400',
                        };
                    @endphp
                    <div class="flex items-center justify-between rounded-md px-3 py-1.5 text-xs {{ $statusColor }}">
                        <span class="font-medium">{{ $log->usage_type?->label() ?? $log->usage_type }}</span>
                        <span>{{ $log->status->value }}</span>
                        @if($log->error_message)
                            <span class="ml-2 max-w-xs truncate text-rose-400" title="{{ $log->error_message }}">
                                {{ Str::limit($log->error_message, 60) }}
                            </span>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Panel Info Catatan Perhitungan --}}
    @php $completedLogsCs = collect($runLogs)->filter(fn($l) => $l->notes); @endphp
    @if($completedLogsCs->isNotEmpty())
        <div class="mb-4 rounded-xl border border-zinc-800 bg-zinc-900 p-4 shadow-sm">
            <h3 class="mb-3 text-sm font-semibold text-zinc-100">Informasi Perhitungan LGD Collateral Shortfall</h3>
            <div class="space-y-3">
                @foreach($completedLogsCs as $log)
                    <div class="rounded-lg border border-zinc-700 bg-zinc-800 p-3">
                        <div class="mb-1 flex items-center gap-2">
                            <span class="inline-flex items-center rounded-full bg-zinc-700 px-2 py-0.5 text-xs font-semibold text-zinc-300">
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

    {{-- Pivot Detail On-the-fly --}}
    @if($pivotError)
        <div class="mb-4 rounded-lg bg-amber-900/30 border border-amber-700/50 px-4 py-3 text-sm text-amber-400">{{ $pivotError }}</div>
    @endif

    @if($pivotLoaded && count($pivotData) > 0)
        @php $pivot = $pivotData; @endphp
        <div class="mb-6 rounded-xl border border-zinc-800 bg-zinc-900 p-4 shadow-sm">
            <div class="mb-3 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-zinc-100">
                        Pivot Detail — Periode {{ $pivot['calculation_period'] }}
                        @if($pivot['is_all_segment'])
                            <span class="ml-1 rounded bg-zinc-700 px-1.5 py-0.5 text-xs font-medium text-zinc-300">Semua Segmen</span>
                        @endif
                    </h3>
                    <p class="mt-0.5 text-xs text-zinc-400">LGD CS = Shortfall / Outstanding Balance (rata-rata per segmen)</p>
                </div>
            </div>

            {{-- Ringkasan per Segmen --}}
            <div class="mb-4 overflow-x-auto rounded-lg border border-indigo-900/60 bg-zinc-950">
                <table class="w-full text-xs">
                    <thead class="bg-indigo-950/60 border-b border-indigo-900/50">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold text-indigo-300">Segmen</th>
                            <th class="px-3 py-2 text-right font-semibold text-indigo-300">Jml Akun</th>
                            <th class="px-3 py-2 text-right font-semibold text-indigo-300">Total Outstanding</th>
                            <th class="px-3 py-2 text-right font-semibold text-indigo-300">Total Nilai Agunan</th>
                            <th class="px-3 py-2 text-right font-semibold text-indigo-300">Total Shortfall</th>
                            <th class="px-3 py-2 text-right font-semibold text-indigo-300">LGD Rate (rata-rata)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        @foreach($pivot['segments'] as $seg)
                            <tr class="hover:bg-zinc-800/50 transition-colors">
                                <td class="px-3 py-2 font-medium text-zinc-100">{{ $seg['label'] }}</td>
                                <td class="px-3 py-2 text-right font-mono text-zinc-300">
                                    {{ number_format($seg['account_count']) }}
                                </td>
                                <td class="px-3 py-2 text-right font-mono text-zinc-300">
                                    {{ number_format($seg['total_outstanding'], 0, ',', '.') }}
                                </td>
                                <td class="px-3 py-2 text-right font-mono text-zinc-300">
                                    {{ number_format($seg['total_collateral_net_value'], 0, ',', '.') }}
                                </td>
                                <td class="px-3 py-2 text-right font-mono {{ $seg['total_shortfall'] > 0 ? 'font-semibold text-rose-400' : 'text-zinc-300' }}">
                                    {{ number_format($seg['total_shortfall'], 0, ',', '.') }}
                                </td>
                                <td class="px-3 py-2 text-right font-mono font-semibold text-zinc-100">
                                    {{ number_format($seg['avg_lgd_rate'] * 100, 4) }}%
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Detail per Akun per Segmen --}}
            @foreach($pivot['segments'] as $seg)
                @if(count($seg['accounts']) > 0)
                    <div class="mb-4">
                        <h4 class="mb-2 text-xs font-semibold text-indigo-300">
                            Detail Akun — {{ $seg['label'] }}
                            <span class="ml-1 font-normal text-zinc-400">({{ number_format($seg['account_count']) }} akun)</span>
                        </h4>
                        <div class="overflow-x-auto rounded-lg border border-zinc-800 bg-zinc-950">
                            <table class="w-full text-xs">
                                <thead class="bg-zinc-900 border-b border-zinc-800">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-semibold text-zinc-400">#</th>
                                        <th class="px-3 py-2 text-left font-semibold text-zinc-400">Financing Code</th>
                                        <th class="px-3 py-2 text-right font-semibold text-zinc-400">Outstanding</th>
                                        <th class="px-3 py-2 text-right font-semibold text-zinc-400">Nilai Agunan (Net)</th>
                                        <th class="px-3 py-2 text-right font-semibold text-zinc-400">Shortfall</th>
                                        <th class="px-3 py-2 text-right font-semibold text-zinc-400">LGD Rate</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-800">
                                    @foreach($seg['accounts'] as $idx => $acc)
                                        <tr class="hover:bg-zinc-800/50">
                                            <td class="px-3 py-1.5 text-zinc-500">{{ $idx + 1 }}</td>
                                            <td class="px-3 py-1.5 font-mono text-zinc-300">{{ $acc['financing_code'] ?? $acc['financing_account_id'] }}</td>
                                            <td class="px-3 py-1.5 text-right font-mono text-zinc-300">
                                                {{ number_format($acc['outstanding_balance'], 0, ',', '.') }}
                                            </td>
                                            <td class="px-3 py-1.5 text-right font-mono text-zinc-300">
                                                {{ number_format($acc['collateral_net_value'], 0, ',', '.') }}
                                            </td>
                                            <td class="px-3 py-1.5 text-right font-mono {{ $acc['shortfall'] > 0 ? 'font-semibold text-rose-400' : 'text-zinc-300' }}">
                                                {{ number_format($acc['shortfall'], 0, ',', '.') }}
                                            </td>
                                            <td class="px-3 py-1.5 text-right font-mono font-semibold text-zinc-100">
                                                {{ number_format($acc['lgd_rate'] * 100, 4) }}%
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="border-t border-zinc-800 bg-zinc-900">
                                    <tr>
                                        <td colspan="2" class="px-3 py-1.5 text-xs font-semibold text-indigo-300">Rata-rata LGD</td>
                                        <td class="px-3 py-1.5 text-right font-mono text-xs font-semibold text-zinc-300">
                                            {{ number_format($seg['total_outstanding'], 0, ',', '.') }}
                                        </td>
                                        <td class="px-3 py-1.5 text-right font-mono text-xs font-semibold text-zinc-300">
                                            {{ number_format($seg['total_collateral_net_value'], 0, ',', '.') }}
                                        </td>
                                        <td class="px-3 py-1.5 text-right font-mono text-xs font-semibold text-rose-400">
                                            {{ number_format($seg['total_shortfall'], 0, ',', '.') }}
                                        </td>
                                        <td class="px-3 py-1.5 text-right font-mono text-xs font-bold text-zinc-100">
                                            {{ number_format($seg['avg_lgd_rate'] * 100, 4) }}%
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                @endif
            @endforeach
        </div>
    @endif

    @if(! $showResults)
        {{-- Placeholder sebelum data ditampilkan --}}
        <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-700 bg-zinc-900 py-16 text-center text-zinc-500">
            <svg class="mb-3 h-10 w-10 text-zinc-500" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h1.5C5.496 19.5 6 18.996 6 18.375m-3.75.125V5.625m0 12.75V5.625m0 0A2.625 2.625 0 0 1 5.625 3h12.75A2.625 2.625 0 0 1 21 5.625M21 5.625V18.375A1.125 1.125 0 0 1 19.875 19.5M21 5.625H3.375" />
            </svg>
            <p class="text-sm font-medium text-zinc-400">Belum ada data ditampilkan</p>
            <p class="mt-1 text-xs text-zinc-500">Pilih periode, lalu klik <strong>Tampilkan Data</strong> atau jalankan perhitungan.</p>
        </div>
    @else
        {{-- Search --}}
        <div class="mb-4">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Cari periode, jenis penggunaan, kode akun..."
                class="w-full sm:w-64 px-3 py-1.5 text-sm border border-zinc-700 rounded-lg shadow-sm focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
            />
        </div>

        {{-- Filter Tabel Snapshot --}}
        <div class="mb-4 flex flex-col gap-3 sm:flex-row">
            <div>
                <label class="block text-xs font-medium text-zinc-400 mb-1">Filter Jenis Penggunaan</label>
                <select wire:model.live="filterUsageType"
                    class="rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-1.5 text-sm text-zinc-100 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">Semua</option>
                    @foreach($usageTypes as $ut)
                        <option value="{{ $ut->value }}">{{ $ut->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-zinc-400 mb-1">Filter Periode Snapshot</label>
                <select wire:model.live="filterPeriode"
                    class="rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-1.5 text-sm text-zinc-100 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">Semua</option>
                    @foreach($snapshotPeriods as $p)
                        <option value="{{ $p }}">{{ $p }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Tabel Snapshot Agregat per Segmen --}}
        <div class="mb-6 rounded-xl border border-zinc-700 bg-zinc-900 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-zinc-800">
                <h3 class="text-sm font-semibold text-zinc-100">Snapshot Hasil Agregat per Segmen</h3>
                <p class="text-xs text-zinc-400 mt-0.5">Ringkasan LGD Collateral Shortfall yang telah dikelompokkan berdasarkan segmen. Ref: PRD Bab 10</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-zinc-700 bg-zinc-800/50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-400">Periode</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-400">Segmen</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-400">Jml Akun</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-400">Total Outstanding</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-400">Total Nilai Agunan</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-400">Total Shortfall</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-400">LGD Rate</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        @forelse($aggregates as $row)
                            <tr class="hover:bg-zinc-800/50 transition-colors">
                                <td class="px-4 py-3 font-mono text-zinc-300">{{ $row->calculation_period }}</td>
                                <td class="px-4 py-3 text-zinc-300">{{ $row->usage_type?->label() ?? '-' }}</td>
                                <td class="px-4 py-3 text-right font-mono text-zinc-100">{{ number_format($row->account_count) }}</td>
                                <td class="px-4 py-3 text-right font-mono text-zinc-100">
                                    {{ number_format((float) $row->total_outstanding, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-zinc-100">
                                    {{ number_format((float) $row->total_collateral_net_value, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono {{ (float) $row->total_shortfall > 0 ? 'text-rose-400 font-semibold' : 'text-zinc-100' }}">
                                    {{ number_format((float) $row->total_shortfall, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-semibold text-zinc-100">
                                    {{ number_format((float) $row->avg_lgd_rate * 100, 4) }}%
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-zinc-500">
                                    Belum ada snapshot agregat untuk filter ini — snapshot lama (sebelum fitur ini) hanya menyimpan detail per akun.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Tabel Snapshot Hasil --}}
        <div class="rounded-xl border border-zinc-700 bg-zinc-900 shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 border-b border-zinc-800">
                <div>
                    <h3 class="text-sm font-semibold text-zinc-100">Snapshot Hasil LGD CS</h3>
                    <p class="mt-1 text-xs text-zinc-400">Hasil tersimpan dari job perhitungan per akun pembiayaan.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button wire:click="exportSourceExcel" wire:loading.attr="disabled"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-sky-800/60 bg-sky-950/40 px-3 py-1.5 text-xs font-semibold text-sky-300 shadow-sm hover:bg-sky-900/60 disabled:opacity-60 transition-colors">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        <span wire:loading.remove wire:target="exportSourceExcel">Export Sumber Data</span>
                        <span wire:loading wire:target="exportSourceExcel">Menyiapkan...</span>
                    </button>
                    <button wire:click="exportExcel" wire:loading.attr="disabled"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-800/60 bg-emerald-950/40 px-3 py-1.5 text-xs font-semibold text-emerald-300 shadow-sm hover:bg-emerald-900/60 disabled:opacity-60 transition-colors">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        <span wire:loading.remove wire:target="exportExcel">Export Hasil</span>
                        <span wire:loading wire:target="exportExcel">Menyiapkan...</span>
                    </button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-zinc-700 bg-zinc-800/50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-400">Periode</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-400">Segmen</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-400">Financing Code</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-400">Outstanding</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-400">Nilai Agunan</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-400">Shortfall</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-400">LGD Rate</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-zinc-400">Status Run</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        @forelse($results as $row)
                            @php
                                $runLog    = $row->calculationRunLog;
                                $runStatus = $runLog?->status;
                                $statusColor = match($runStatus) {
                                            \App\Enums\RunStatus::Completed  => 'text-emerald-400 bg-emerald-900/30',
                                            \App\Enums\RunStatus::Failed     => 'text-red-400 bg-red-900/30',
                                            \App\Enums\RunStatus::Processing => 'text-blue-400 bg-blue-900/30',
                                            default                          => 'text-zinc-400 bg-zinc-800',
                                        };
                            @endphp
                            <tr class="hover:bg-zinc-800/50 transition-colors">
                                <td class="px-4 py-3 font-mono text-zinc-300">{{ $row->calculation_period }}</td>
                                <td class="px-4 py-3 text-zinc-300">{{ $row->usage_type?->label() ?? '-' }}</td>
                                <td class="px-4 py-3 font-mono text-zinc-400">{{ $row->financingAccount?->account_number ?? $row->financing_account_id }}</td>
                                <td class="px-4 py-3 text-right font-mono text-zinc-100">
                                    {{ number_format((float) $row->outstanding_balance, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-zinc-100">
                                    {{ number_format((float) $row->collateral_net_value, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono {{ (float) $row->shortfall > 0 ? 'text-rose-400 font-semibold' : 'text-zinc-100' }}">
                                    {{ number_format((float) $row->shortfall, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-semibold text-zinc-100">
                                    {{ number_format((float) $row->lgd_rate * 100, 4) }}%
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if($runStatus)
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $statusColor }}">
                                            {{ $runStatus->value }}
                                        </span>
                                    @else
                                        <span class="text-zinc-500">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-10 text-center text-zinc-500">
                                    Belum ada data snapshot LGD Collateral Shortfall.
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

    {{-- Modal Konfirmasi Hitung --}}
    <div x-show="$wire.confirmingAction === 'hitung'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="w-full max-w-sm rounded-xl border border-zinc-800 bg-zinc-900 p-6 shadow-2xl">
            <h3 class="font-semibold text-zinc-100 mb-2">Konfirmasi Perhitungan</h3>
            <p class="text-sm text-zinc-400 mb-4">Yakin ingin menjalankan perhitungan LGD Collateral Shortfall untuk periode ini? Proses akan dimasukkan ke antrean.</p>
            <div class="flex gap-2 justify-end">
                <button wire:click="$set('confirmingAction', '')" class="px-3 py-1.5 text-sm rounded-lg border border-zinc-700 bg-zinc-800 text-zinc-300 hover:bg-zinc-700 hover:text-white">Batal</button>
                <button wire:click="jalankanPerhitungan" class="px-3 py-1.5 text-sm rounded-lg bg-primary-600 text-white hover:bg-primary-500 font-semibold shadow-sm">Ya, Jalankan</button>
            </div>
        </div>
    </div>

    {{-- Modal Konfirmasi Rekalkulasi --}}
    <div x-show="$wire.confirmingAction === 'rekalkulasi'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="w-full max-w-sm rounded-xl border border-zinc-800 bg-zinc-900 p-6 shadow-2xl">
            <h3 class="font-semibold text-zinc-100 mb-2">Konfirmasi Rekalkulasi</h3>
            <p class="text-sm text-zinc-400 mb-4">Yakin ingin menjalankan ulang perhitungan LGD Collateral Shortfall untuk periode ini? Data lama akan ditimpa.</p>
            <div class="flex gap-2 justify-end">
                <button wire:click="$set('confirmingAction', '')" class="px-3 py-1.5 text-sm rounded-lg border border-zinc-700 bg-zinc-800 text-zinc-300 hover:bg-zinc-700 hover:text-white">Batal</button>
                <button wire:click="rekalkulasi" class="px-3 py-1.5 text-sm rounded-lg bg-amber-600 text-white hover:bg-amber-500 font-semibold shadow-sm">Ya, Rekalkulasi</button>
            </div>
        </div>
    </div>

    {{-- Modal Konfirmasi Hapus --}}
    <div x-show="$wire.confirmingAction === 'hapus'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="w-full max-w-sm rounded-xl border border-zinc-800 bg-zinc-900 p-6 shadow-2xl">
            <h3 class="font-semibold text-zinc-100 mb-2">Konfirmasi Hapus Perhitungan</h3>
            <p class="text-sm text-zinc-400 mb-4">Yakin ingin menghapus seluruh perhitungan LGD Collateral Shortfall untuk periode yang dipilih? Tindakan ini tidak bisa dibatalkan.</p>
            <div class="flex gap-2 justify-end">
                <button wire:click="$set('confirmingAction', '')" class="px-3 py-1.5 text-sm rounded-lg border border-zinc-700 bg-zinc-800 text-zinc-300 hover:bg-zinc-700 hover:text-white">Batal</button>
                <button wire:click="hapusPerhitungan" class="px-3 py-1.5 text-sm rounded-lg bg-rose-600 text-white hover:bg-rose-500 font-semibold shadow-sm">Ya, Hapus</button>
            </div>
        </div>
    </div>
</div>
