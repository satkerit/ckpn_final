<div>
    {{-- Header --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-base font-semibold text-zinc-100">LGD Expected Recoveries</h2>
            <p class="mt-1 text-xs text-zinc-400">Pivot detail writeoff & recovery per tahun + trigger perhitungan snapshot. Ref: PRD Bab 9</p>
        </div>
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
                    <option value="">Semua Segmen (All Account)</option>
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
                        {{-- Ada hasil tapi belum ditampilkan: tombol Tampilkan Data --}}
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
    @php $completedLogsEr = collect($runLogs)->filter(fn($l) => $l->notes); @endphp
    @if($completedLogsEr->isNotEmpty())
        <div class="mb-4 rounded-xl border border-zinc-800 bg-zinc-900 p-4 shadow-sm">
            <h3 class="mb-3 text-sm font-semibold text-zinc-100">Informasi Perhitungan LGD Expected Recoveries</h3>
            <div class="space-y-3">
                @foreach($completedLogsEr as $log)
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
        <div class="mb-4 rounded-lg bg-amber-950/40 border border-amber-700/60 px-4 py-3 text-sm text-amber-300">{{ $pivotError }}</div>
    @endif

    @if($pivotLoaded && count($pivotData) > 0)
        @php
            $d = $pivotData;
            $years = $d['years'] ?? [];
        @endphp
        <div class="mb-6 rounded-xl border border-zinc-700 bg-zinc-900 shadow-sm overflow-hidden">
            {{-- Ringkasan Header --}}
            <div class="px-4 py-3 border-b border-zinc-800 flex flex-wrap gap-6 items-center bg-zinc-800/50">
                <div>
                    <span class="text-xs text-zinc-400">Periode</span>
                    <p class="text-sm font-semibold text-zinc-100">{{ $d['calculation_period'] }}</p>
                </div>
                <div>
                    <span class="text-xs text-zinc-400">Jendela Data</span>
                    <p class="text-sm font-medium text-zinc-300">{{ $d['window_start_date'] }} s.d. {{ $d['window_end_date'] }}</p>
                </div>
                <div>
                    <span class="text-xs text-zinc-400">Mode</span>
                    <p class="text-sm font-medium">
                        @if($d['is_all_account'])
                            <span class="inline-flex rounded-full bg-sky-900/50 px-2 py-0.5 text-xs font-medium text-sky-400">All Account</span>
                        @else
                            <span class="inline-flex rounded-full bg-zinc-800 px-2 py-0.5 text-xs font-medium text-zinc-300">Per Segmen</span>
                        @endif
                    </p>
                </div>
                <div>
                    <span class="text-xs text-zinc-400">Total Writeoff</span>
                    <p class="text-sm font-semibold text-zinc-100">Rp {{ number_format($d['total_writeoff'], 0, ',', '.') }}</p>
                </div>
                <div>
                    <span class="text-xs text-zinc-400">Total Recovery</span>
                    <p class="text-sm font-semibold text-zinc-100">Rp {{ number_format($d['total_recovery'], 0, ',', '.') }}</p>
                </div>
                <div>
                    <span class="text-xs text-zinc-400">Expected Recovery Rate</span>
                    <p class="text-sm font-semibold text-emerald-400">{{ number_format($d['expected_recovery_rate'] * 100, 4) }}%</p>
                </div>
                <div>
                    <span class="text-xs text-zinc-400">LGD Rate</span>
                    <p class="text-lg font-bold text-rose-400">{{ number_format($d['lgd_rate'] * 100, 4) }}%</p>
                </div>
            </div>

            {{-- Tabel Detail Per Tahun --}}
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-zinc-800/50 border-b border-zinc-700">
                            <th class="px-4 py-2 text-left text-xs font-semibold text-zinc-400 uppercase tracking-wide">Tahun</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold text-zinc-400 uppercase tracking-wide">Total Writeoff (jt)</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold text-zinc-400 uppercase tracking-wide">Total Recovery (jt)</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold text-zinc-400 uppercase tracking-wide">Recovery Rate (%)</th>
                            <th class="px-4 py-2 text-center text-xs font-semibold text-zinc-400 uppercase tracking-wide">Masuk Rata-rata</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        @forelse($years as $yr)
                            @php
                                $wo   = $d['writeoff_by_year'][$yr] ?? 0.0;
                                $rc   = $d['recovery_by_year'][$yr] ?? 0.0;
                                $rate = $d['recovery_rate_by_year'][$yr] ?? 0.0;
                                $masuk = $wo > 0;
                            @endphp
                            <tr class="hover:bg-zinc-800/50 {{ $masuk ? '' : 'opacity-50' }}">
                                <td class="px-4 py-2.5 font-medium text-zinc-100">{{ $yr }}</td>
                                <td class="px-4 py-2.5 text-right font-mono text-zinc-300">
                                    {{ $wo > 0 ? 'Rp '.number_format($wo, 0, ',', '.') : '-' }}
                                </td>
                                <td class="px-4 py-2.5 text-right font-mono text-zinc-300">
                                    {{ $rc > 0 ? 'Rp '.number_format($rc, 0, ',', '.') : '-' }}
                                </td>
                                <td class="px-4 py-2.5 text-right font-mono {{ $masuk ? 'text-emerald-400 font-semibold' : 'text-zinc-500' }}">
                                    {{ $masuk ? number_format($rate * 100, 4).'%' : '-' }}
                                </td>
                                <td class="px-4 py-2.5 text-center">
                                    @if($masuk)
                                        <span class="inline-flex rounded-full border border-emerald-800/60 bg-emerald-950/40 px-2 py-0.5 text-xs font-semibold text-emerald-300">Ya</span>
                                    @else
                                        <span class="text-zinc-500 text-xs">Tidak (WO=0)</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-zinc-500 text-xs">
                                    Tidak ada data writeoff dalam jendela 5 tahun untuk periode ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-zinc-700 bg-zinc-800/50 font-semibold">
                            <td class="px-4 py-2.5 text-zinc-100">Total / Rata-rata</td>
                            <td class="px-4 py-2.5 text-right font-mono text-zinc-100">Rp {{ number_format($d['total_writeoff'], 0, ',', '.') }}</td>
                            <td class="px-4 py-2.5 text-right font-mono text-zinc-100">Rp {{ number_format($d['total_recovery'], 0, ',', '.') }}</td>
                            <td class="px-4 py-2.5 text-right font-mono text-emerald-400">{{ number_format($d['expected_recovery_rate'] * 100, 4) }}%</td>
                            <td class="px-4 py-2.5 text-center text-xs text-zinc-400">{{ count($years) }} tahun</td>
                        </tr>
                        <tr class="bg-zinc-800/50">
                            <td colspan="3" class="px-4 py-2.5 text-sm font-semibold text-zinc-300">
                                LGD = 1 - Expected Recovery Rate
                            </td>
                            <td colspan="2" class="px-4 py-2.5 text-right">
                                <span class="text-lg font-bold text-rose-400">{{ number_format($d['lgd_rate'] * 100, 4) }}%</span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        {{-- Tabel Daftar Debitur Writeoff --}}
        @php $debtors = $d['debtors'] ?? []; @endphp
        <div class="mb-6 rounded-xl border border-zinc-700 bg-zinc-900 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-zinc-800 bg-zinc-800/50 flex items-center justify-between">
                <h4 class="text-sm font-semibold text-zinc-100">Daftar Debitur Writeoff</h4>
                <span class="text-xs text-zinc-400">{{ count($debtors) }} debitur dalam jendela {{ $d['window_years'] }} tahun</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-zinc-800/50 border-b border-zinc-700">
                            <th class="px-4 py-2 text-left text-xs font-semibold text-zinc-400 uppercase tracking-wide">No</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-zinc-400 uppercase tracking-wide">No. Rekening</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-zinc-400 uppercase tracking-wide">Nama Debitur</th>
                            <th class="px-4 py-2 text-center text-xs font-semibold text-zinc-400 uppercase tracking-wide">Tgl Writeoff</th>
                            <th class="px-4 py-2 text-center text-xs font-semibold text-zinc-400 uppercase tracking-wide">Tahun WO</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold text-zinc-400 uppercase tracking-wide">Outstanding WO (Rp)</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold text-zinc-400 uppercase tracking-wide">Outstanding Kini (Rp)</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold text-zinc-400 uppercase tracking-wide">Recovery (Rp)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        @forelse($debtors as $i => $debtor)
                            @php
                                $debtor = (object) $debtor;
                                $recovery = (float) $debtor->recovery;
                            @endphp
                            <tr class="hover:bg-zinc-800/50">
                                <td class="px-4 py-2.5 text-zinc-400 text-xs">{{ $i + 1 }}</td>
                                <td class="px-4 py-2.5 font-mono text-xs text-zinc-300">{{ $debtor->account_number }}</td>
                                <td class="px-4 py-2.5 text-zinc-100">{{ $debtor->customer_name }}</td>
                                <td class="px-4 py-2.5 text-center text-zinc-300">{{ $debtor->writeoff_date }}</td>
                                <td class="px-4 py-2.5 text-center font-medium text-zinc-300">{{ $debtor->writeoff_year }}</td>
                                <td class="px-4 py-2.5 text-right font-mono text-zinc-300">Rp {{ number_format((float) $debtor->outstanding_writeoff, 0, ',', '.') }}</td>
                                <td class="px-4 py-2.5 text-right font-mono text-zinc-400">
                                    {{ (float) $debtor->current_outstanding > 0 ? 'Rp '.number_format((float) $debtor->current_outstanding, 0, ',', '.') : '-' }}
                                </td>
                                <td class="px-4 py-2.5 text-right font-mono {{ $recovery > 0 ? 'text-emerald-400 font-semibold' : 'text-zinc-500' }}">
                                    {{ $recovery > 0 ? 'Rp '.number_format($recovery, 0, ',', '.') : '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-zinc-500 text-xs">
                                    Tidak ada debitur writeoff dalam jendela periode ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
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
                placeholder="Cari periode, jenis penggunaan..."
                class="w-full sm:w-64 px-3 py-1.5 text-sm border border-zinc-700 bg-zinc-950 text-zinc-200 placeholder-zinc-500 rounded-lg shadow-sm focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
            />
        </div>

        {{-- Filter Tabel Snapshot --}}
        <div class="mb-4 flex flex-col gap-3 sm:flex-row">
            <div>
                <label class="block text-xs font-medium text-zinc-400 mb-1">Filter Jenis Penggunaan</label>
                <select wire:model.live="filterUsageType"
                    class="rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm text-zinc-200 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">Semua</option>
                    @foreach($usageTypes as $ut)
                        <option value="{{ $ut->value }}">{{ $ut->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-zinc-400 mb-1">Filter Periode Snapshot</label>
                <select wire:model.live="filterPeriode"
                    class="rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm text-zinc-200 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">Semua</option>
                    @foreach($snapshotPeriods as $p)
                        <option value="{{ $p }}">{{ $p }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Tabel Snapshot Hasil --}}
        <div class="rounded-xl border border-zinc-700 bg-zinc-900 shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-4 py-3 border-b border-zinc-800">
                <div>
                    <h3 class="text-sm font-semibold text-zinc-100">Snapshot Hasil LGD ER</h3>
                    <p class="mt-1 text-xs text-zinc-400">Hasil tersimpan dari job perhitungan per segmen.</p>
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
                    <thead>
                        <tr class="bg-zinc-800/50 border-b border-zinc-700">
                            <th class="px-4 py-2 text-left text-xs font-semibold text-zinc-400 uppercase tracking-wide">Periode</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-zinc-400 uppercase tracking-wide">Segmen</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold text-zinc-400 uppercase tracking-wide">Total Writeoff (jt)</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold text-zinc-400 uppercase tracking-wide">Total Recovery (jt)</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold text-zinc-400 uppercase tracking-wide">Expected Recovery (%)</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold text-zinc-400 uppercase tracking-wide">LGD Rate (%)</th>
                            <th class="px-4 py-2 text-center text-xs font-semibold text-zinc-400 uppercase tracking-wide">All Account</th>
                            <th class="px-4 py-2 text-center text-xs font-semibold text-zinc-400 uppercase tracking-wide">Status Run</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        @forelse($results as $row)
                            @php
                                $runStatus  = $row->calculationRunLog?->status ?? null;
                                $statusColor = match($runStatus) {
                                    \App\Enums\RunStatus::Completed  => 'text-emerald-400 bg-emerald-900/30',
                                    \App\Enums\RunStatus::Failed     => 'text-red-400 bg-red-900/30',
                                    \App\Enums\RunStatus::Processing => 'text-blue-400 bg-blue-900/30',
                                    default                          => 'text-zinc-400 bg-zinc-800',
                                };
                            @endphp
                            <tr class="hover:bg-zinc-800/50">
                                <td class="px-4 py-3 font-medium text-zinc-100">{{ $row->calculation_period }}</td>
                                <td class="px-4 py-3 text-zinc-300">
                                    {{ $row->usage_type instanceof \App\Enums\UsageType ? $row->usage_type->label() : ($row->usage_type ?? '-') }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-zinc-300">
                                    Rp {{ number_format((float) $row->total_writeoff_amount, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-zinc-300">
                                    Rp {{ number_format((float) $row->total_recovery_amount, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-emerald-400 font-semibold">
                                    {{ number_format((float) $row->expected_recovery_rate * 100, 4) }}%
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-bold text-rose-400">
                                    {{ number_format((float) $row->lgd_rate * 100, 4) }}%
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if($row->is_all_account)
                                        <span class="inline-flex rounded-full border border-sky-800/60 bg-sky-950/40 px-2 py-0.5 text-xs font-medium text-sky-300">Ya</span>
                                    @else
                                        <span class="text-zinc-500 text-xs">Segmen</span>
                                    @endif
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
                                    Belum ada snapshot LGD Expected Recoveries.
                                    @if($filterUsageType || $filterPeriode)
                                        <br><span class="text-xs">Coba ubah filter atau jalankan perhitungan.</span>
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
            <p class="text-sm text-zinc-400 mb-4">Yakin ingin menjalankan perhitungan LGD Expected Recoveries untuk periode ini? Proses akan dimasukkan ke antrean.</p>
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
            <p class="text-sm text-zinc-400 mb-4">Yakin ingin menjalankan ulang perhitungan LGD Expected Recoveries untuk periode ini? Data lama akan ditimpa.</p>
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
            <p class="text-sm text-zinc-400 mb-4">Yakin ingin menghapus seluruh perhitungan LGD Expected Recoveries untuk periode yang dipilih? Tindakan ini tidak bisa dibatalkan.</p>
            <div class="flex gap-2 justify-end">
                <button wire:click="$set('confirmingAction', '')" class="px-3 py-1.5 text-sm rounded-lg border border-zinc-700 bg-zinc-800 text-zinc-300 hover:bg-zinc-700 hover:text-white">Batal</button>
                <button wire:click="hapusPerhitungan" class="px-3 py-1.5 text-sm rounded-lg bg-rose-600 text-white hover:bg-rose-500 font-semibold shadow-sm">Ya, Hapus</button>
            </div>
        </div>
    </div>
</div>
