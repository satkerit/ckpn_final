<div>
    {{-- Header --}}
    <div class="mb-6">
        <h2 class="text-base font-semibold text-zinc-100">Hasil PD Netflow</h2>
        <p class="mt-1 text-xs text-zinc-400">Tabel hasil pd_netflow_result per periode & jenis penggunaan. Ref: PRD Bab 7</p>
    </div>

    {{-- Panel Perhitungan --}}
    <div class="mb-6 rounded-xl border border-zinc-800 bg-zinc-900 p-5 shadow-sm">
        <h3 class="mb-4 text-sm font-semibold text-zinc-100">Perhitungan PD Netflow</h3>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
            <div class="sm:w-52">
                <label class="mb-1 block text-xs font-medium text-zinc-400">Periode Penetapan</label>
                <select wire:model.live="runPeriode"
                        class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm text-zinc-200 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">-- Pilih Periode --</option>
                    @foreach($periods as $period)
                        <option value="{{ $period }}">{{ $period }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Jenis Penggunaan: hanya muncul setelah periode dipilih --}}
            @if($runPeriode !== '')
                <div class="sm:w-48">
                    <label class="mb-1 block text-xs font-medium text-zinc-400">Jenis Penggunaan</label>
                    <select wire:model.live="filterUsageType"
                            class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm text-zinc-200 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                        <option value="">Semua Data</option>
                        @foreach($usageTypes as $type)
                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="sm:w-48">
                    <label class="mb-1 block text-xs font-medium text-zinc-400">Kantor (Level 1)</label>
                    <select wire:model.live="filterOfficeCode"
                            class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm text-zinc-200 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                        <option value="">Semua Kantor</option>
                        @foreach($offices as $office)
                            <option value="{{ $office }}">{{ $office }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="sm:w-48">
                    <label class="mb-1 block text-xs font-medium text-zinc-400">Akad (Level 2)</label>
                    <select wire:model.live="filterAkadCode"
                            class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm text-zinc-200 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                        <option value="">Semua Akad</option>
                        @foreach($akadCodes as $akad)
                            <option value="{{ $akad }}">{{ $akad }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Tombol: muncul setelah dropdown jenis penggunaan tampil (periode sudah dipilih) --}}
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
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-primary-700 disabled:opacity-60 transition-colors">
                                <span wire:loading.remove wire:target="tampilkanData">Tampilkan Data</span>
                                <span wire:loading wire:target="tampilkanData">Memuat...</span>
                            </button>
                        @else
                            {{-- Data sudah ditampilkan: Rekalkulasi + Pivot + Hapus --}}
                            <button wire:click="confirmRekalkulasi"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-amber-500 px-4 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-amber-600 transition-colors">
                                Rekalkulasi
                            </button>
                            <a href="{{ route('kalkulasi.pd.pivot', array_filter(['periode' => $filterPeriode, 'usage_type' => $filterUsageType])) }}"
                               class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700 transition-colors">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M3 14h18M10 3v18M14 3v18" />
                                </svg>
                                Detail Pivot
                            </a>
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

    @if(! $showResults)
        {{-- Placeholder sebelum data ditampilkan --}}
        <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-700 bg-zinc-900 py-16 text-center text-zinc-400">
            <svg class="mb-3 h-10 w-10 text-zinc-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375 19.5h1.5C5.496 19.5 6 18.996 6 18.375m-3.75.125V5.625m0 12.75V5.625m0 0A2.625 2.625 0 0 1 5.625 3h12.75A2.625 2.625 0 0 1 21 5.625M21 5.625V18.375A1.125 1.125 0 0 1 19.875 19.5M21 5.625H3.375" />
            </svg>
            <p class="text-sm font-medium text-zinc-400">Belum ada data ditampilkan</p>
            <p class="mt-1 text-xs text-zinc-500">Pilih periode dan jenis penggunaan, lalu klik <strong>Tampilkan Data</strong> atau jalankan perhitungan.</p>
        </div>
    @else

    {{-- Search --}}
    <div class="mb-4">
        <input
            type="text"
            wire:model.live.debounce.300ms="search"
            placeholder="Cari periode, jenis penggunaan, kantor, akad..."
            class="w-full sm:w-64 px-3 py-1.5 text-sm border border-zinc-700 bg-zinc-800 text-zinc-100 rounded-lg shadow-sm focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
        />
    </div>

    {{-- Ringkasan PD Netflow dan CKPN --}}
    @if(count($summaryRows) > 0)
        <div class="mb-6 overflow-hidden rounded-2xl border border-zinc-800 bg-zinc-900 shadow-sm">
            <div class="border-b border-zinc-800 bg-zinc-950 px-5 py-4 text-white">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-sky-300">PD Netflow / CKPN view</p>
                        <h3 class="mt-1 text-lg font-semibold">Ringkasan penurunan nilai per bucket</h3>
                    </div>
                    <p class="text-xs text-zinc-400">Periode {{ $filterPeriode }} · {{ collect($usageTypes)->first(fn ($type) => (string) $type->value === (string) $filterUsageType)?->label() ?? 'Segmen' }}</p>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-zinc-800">
                        <tr>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-zinc-400">Bucket</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-400">Hari Tunggakan</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-zinc-400">Baki Debet posisi {{ $filterPeriode }} (EAD)</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-zinc-400">PD Net Flow</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-zinc-400">LGD Expected Recoveries</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-zinc-400">Penurunan Nilai</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        @foreach($summaryRows as $row)
                            <tr class="hover:bg-zinc-800/50">
                                <td class="px-4 py-2 text-center font-semibold text-zinc-100">{{ $row['bucket'] }}</td>
                                <td class="px-4 py-2 font-medium text-zinc-300">{{ $row['label'] }}</td>
                                <td class="px-4 py-2 text-right font-mono text-zinc-100">{{ number_format($row['ead'], 0, ',', '.') }}</td>
                                <td class="px-4 py-2 text-right font-mono text-zinc-100">{{ number_format($row['pd'] * 100, 2, ',', '.') }}%</td>
                                <td class="px-4 py-2 text-right font-mono text-zinc-100">{{ number_format($row['lgd'] * 100, 2, ',', '.') }}%</td>
                                <td class="px-4 py-2 text-right font-mono font-semibold text-zinc-100">{{ number_format($row['impairment'], 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t-2 border-zinc-700 bg-zinc-800">
                        <tr>
                            <td colspan="5" class="px-4 py-3 text-right text-xs font-semibold text-zinc-300">Jumlah cadangan yang harus dibentuk / jumlah penurunan nilai</td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-zinc-100">{{ number_format($summaryTotal, 0, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @endif

    {{-- Panel Info Catatan Perhitungan --}}
    @php
        $completedLogs = collect($runLogs)->filter(fn($l) => $l->notes);
    @endphp
    @if($completedLogs->isNotEmpty())
        <div class="mb-4 rounded-xl border border-sky-800/60 bg-zinc-900 p-4 shadow-sm">
            <h3 class="mb-3 text-sm font-semibold text-sky-300">Informasi Perhitungan PD Netflow</h3>
            <div class="space-y-3">
                @foreach($completedLogs as $log)
                    <div class="rounded-lg border border-zinc-800 bg-zinc-950 p-3">
                        <div class="mb-1 flex items-center gap-2">
                            <span class="inline-flex items-center rounded-full border border-sky-800/60 bg-sky-950/60 px-2 py-0.5 text-xs font-semibold text-sky-300">
                                {{ \App\Enums\UsageType::from($log->usage_type)->label() }}
                            </span>
                            <span class="text-xs text-zinc-400">Periode: {{ $log->period }}</span>
                        </div>
                        <p class="whitespace-pre-line text-xs text-zinc-300">{{ $log->notes }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Tabel Hasil --}}
    <div class="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-900 shadow-sm">
        <div class="flex items-center justify-between px-4 py-3 border-b border-zinc-800">
            <div>
                <h3 class="text-sm font-semibold text-zinc-100">Hasil PD Netflow</h3>
                <p class="mt-1 text-xs text-zinc-400">Data netflow rate per bucket per segmen.</p>
            </div>
            <button wire:click="exportSourceExcel" wire:loading.attr="disabled"
                class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-700/60 bg-zinc-900 px-3 py-1.5 text-xs font-semibold text-emerald-300 shadow-sm hover:bg-emerald-950/40 disabled:opacity-60 transition-colors">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                </svg>
                <span wire:loading.remove wire:target="exportSourceExcel">Export Sumber Data</span>
                <span wire:loading wire:target="exportSourceExcel">Menyiapkan...</span>
            </button>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-800 text-sm">
                <thead class="bg-zinc-800">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-400">Periode</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-400">Kantor</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-400">Akad</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-400">Jenis Penggunaan</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-400">Bucket Asal</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-zinc-400">PD Rate</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-zinc-400">Window (Bln)</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-zinc-400">Data Mulai</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-zinc-400">Data Akhir</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-zinc-400">Status Run</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800">
                    @forelse($results as $row)
                        @php
                            $runStatus = $row->calculationRunLog?->status;
                            $statusColor = match($runStatus) {
                                \App\Enums\RunStatus::Completed  => 'border border-emerald-800/60 bg-emerald-950/40 text-emerald-300',
                                \App\Enums\RunStatus::Failed     => 'border border-rose-800/60 bg-rose-950/40 text-rose-300',
                                \App\Enums\RunStatus::Processing => 'border border-blue-800/60 bg-blue-950/40 text-blue-300',
                                \App\Enums\RunStatus::Approved   => 'border border-violet-800/60 bg-violet-950/40 text-violet-300',
                                default                          => 'border border-zinc-700 bg-zinc-800 text-zinc-400',
                            };
                        @endphp
                        <tr class="hover:bg-zinc-800/50 transition-colors">
                            <td class="px-4 py-3 font-mono text-zinc-100">{{ $row->calculation_period }}</td>
                            <td class="px-4 py-3 text-zinc-300">{{ $row->office_code ?? '-' }}</td>
                            <td class="px-4 py-3 text-zinc-300">{{ $row->akad_code ?? '-' }}</td>
                            <td class="px-4 py-3 text-zinc-300">{{ $row->usage_type?->label() ?? '-' }}</td>
                            <td class="px-4 py-3 text-zinc-300">{{ $row->fromBucket?->label ?? '-' }}</td>
                            <td class="px-4 py-3 text-right font-mono text-zinc-100">
                                {{ number_format((float) $row->pd_rate * 100, 2) }}%
                            </td>
                            <td class="px-4 py-3 text-center text-zinc-300">{{ $row->window_months }}</td>
                            <td class="px-4 py-3 text-center font-mono text-zinc-300">{{ $row->data_period_start }}</td>
                            <td class="px-4 py-3 text-center font-mono text-zinc-300">{{ $row->data_period_end }}</td>
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
                                Belum ada data hasil PD Netflow.
                                @if($filterUsageType || $filterPeriode)
                                    <br><span class="text-xs">Coba ubah filter atau jalankan perhitungan untuk periode terpilih.</span>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($results->hasPages())
            <div class="border-t border-zinc-800 px-4 py-3">
                {{ $results->links() }}
            </div>
        @endif
    </div>
    @endif

    {{-- Tabel PD Netflow Per Segmen (4 tabel: Konsolidasi, Modal Kerja, Investasi, Konsumsi) --}}
    @if($showResults && count($segmentedTables) > 0)
        <div class="mb-6">
            <h3 class="mb-3 text-sm font-semibold text-zinc-100">
                Ringkasan PD Rate Per Segmen — Periode {{ $filterPeriode }}
            </h3>
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                @foreach($segmentedTables as $label => $rows)
                    @php
                        $colorMap = [
                            'Konsolidasi' => 'bg-zinc-800/50 border-zinc-800',
                            'Modal Kerja' => 'bg-blue-950/30 border-blue-900',
                            'Investasi'   => 'bg-emerald-950/30 border-emerald-900',
                            'Konsumsi'    => 'bg-amber-950/30 border-amber-900',
                        ];
                        $headerMap = [
                            'Konsolidasi' => 'bg-zinc-800 text-zinc-300',
                            'Modal Kerja' => 'bg-blue-950/60 text-blue-300',
                            'Investasi'   => 'bg-emerald-950/60 text-emerald-300',
                            'Konsumsi'    => 'bg-amber-950/60 text-amber-300',
                        ];
                        $cardColor   = $colorMap[$label]   ?? 'bg-zinc-900 border-zinc-800';
                        $headerColor = $headerMap[$label]  ?? 'bg-zinc-800 text-zinc-300';
                    @endphp
                    <div class="rounded-xl border {{ $cardColor }} overflow-hidden shadow-sm">
                        <div class="px-4 py-2.5 {{ $headerColor }}">
                            <span class="text-xs font-semibold uppercase tracking-wide">{{ $label }}</span>
                            <span class="ml-2 text-xs opacity-70">({{ $rows->count() }} bucket)</span>
                        </div>
                        @if($rows->isEmpty())
                            <p class="px-4 py-6 text-center text-xs text-zinc-500">Belum ada data untuk periode ini.</p>
                        @else
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-xs">
                                    <thead>
                                        <tr class="border-b border-zinc-700 bg-zinc-800/60">
                                            <th class="px-3 py-2 text-left font-medium text-zinc-400">Bucket</th>
                                            <th class="px-3 py-2 text-right font-medium text-zinc-400">PD Rate</th>
                                            <th class="px-3 py-2 text-right font-medium text-zinc-400">Transition Rate</th>
                                            <th class="px-3 py-2 text-right font-medium text-zinc-400">Compound Rate</th>
                                            <th class="px-3 py-2 text-right font-medium text-zinc-400">OS Awal</th>
                                            <th class="px-3 py-2 text-right font-medium text-zinc-400">OS Akhir</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-zinc-800">
                                        @foreach($rows as $row)
                                            <tr class="hover:bg-zinc-800/50">
                                                <td class="px-3 py-2 font-mono text-zinc-300">
                                                    {{ $row->fromBucket?->code ?? 'B'.$row->from_bucket_id }}
                                                </td>
                                                <td class="px-3 py-2 text-right font-mono font-semibold text-zinc-100">
                                                    {{ number_format((float)$row->pd_rate * 100, 4) }}%
                                                </td>
                                                <td class="px-3 py-2 text-right font-mono text-zinc-300">
                                                    {{ $row->transition_rate !== null ? number_format((float)$row->transition_rate * 100, 4).'%' : '-' }}
                                                </td>
                                                <td class="px-3 py-2 text-right font-mono text-zinc-300">
                                                    {{ $row->compound_rate !== null ? number_format((float)$row->compound_rate * 100, 4).'%' : '-' }}
                                                </td>
                                                <td class="px-3 py-2 text-right font-mono text-zinc-300">
                                                    {{ $row->source_outstanding !== null ? number_format((float)$row->source_outstanding, 0, ',', '.') : '-' }}
                                                </td>
                                                <td class="px-3 py-2 text-right font-mono text-zinc-300">
                                                    {{ $row->destination_outstanding !== null ? number_format((float)$row->destination_outstanding, 0, ',', '.') : '-' }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Modal Konfirmasi Hitung --}}
    <div x-show="$wire.confirmingAction === 'hitung'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="w-full max-w-sm rounded-xl bg-zinc-900 border border-zinc-800 p-6 shadow-xl">
            <h3 class="font-semibold text-zinc-100 mb-2">Konfirmasi Perhitungan</h3>
            <p class="text-sm text-zinc-300 mb-4">Yakin ingin menjalankan perhitungan PD Netflow untuk periode ini? Proses akan dimasukkan ke antrean.</p>
            <div class="flex gap-2 justify-end">
                <button wire:click="$set('confirmingAction', '')" class="px-3 py-1.5 text-sm rounded-lg border border-zinc-700 text-zinc-300 hover:bg-zinc-800">Batal</button>
                <button wire:click="jalankanPerhitungan" class="px-3 py-1.5 text-sm rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">Ya, Jalankan</button>
            </div>
        </div>
    </div>

    {{-- Modal Konfirmasi Rekalkulasi --}}
    <div x-show="$wire.confirmingAction === 'rekalkulasi'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="w-full max-w-sm rounded-xl bg-zinc-900 border border-zinc-800 p-6 shadow-xl">
            <h3 class="font-semibold text-zinc-100 mb-2">Konfirmasi Rekalkulasi</h3>
            <p class="text-sm text-zinc-300 mb-4">Yakin ingin menjalankan ulang perhitungan PD Netflow untuk periode ini? Data lama akan ditimpa.</p>
            <div class="flex gap-2 justify-end">
                <button wire:click="$set('confirmingAction', '')" class="px-3 py-1.5 text-sm rounded-lg border border-zinc-700 text-zinc-300 hover:bg-zinc-800">Batal</button>
                <button wire:click="rekalkulasi" class="px-3 py-1.5 text-sm rounded-lg bg-amber-500 text-white hover:bg-amber-600">Ya, Rekalkulasi</button>
            </div>
        </div>
    </div>

    {{-- Modal Konfirmasi Hapus --}}
    <div x-show="$wire.confirmingAction === 'hapus'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="w-full max-w-sm rounded-xl bg-zinc-900 border border-zinc-800 p-6 shadow-xl">
            <h3 class="font-semibold text-zinc-100 mb-2">Konfirmasi Hapus Perhitungan</h3>
            <p class="text-sm text-zinc-300 mb-4">Yakin ingin menghapus seluruh perhitungan PD Netflow untuk periode yang dipilih? Tindakan ini tidak bisa dibatalkan.</p>
            <div class="flex gap-2 justify-end">
                <button wire:click="$set('confirmingAction', '')" class="px-3 py-1.5 text-sm rounded-lg border border-zinc-700 text-zinc-300 hover:bg-zinc-800">Batal</button>
                <button wire:click="hapusPerhitungan" class="px-3 py-1.5 text-sm rounded-lg bg-rose-600 font-semibold text-white shadow-sm hover:bg-rose-500">Ya, Hapus</button>
            </div>
        </div>
    </div>
</div>
