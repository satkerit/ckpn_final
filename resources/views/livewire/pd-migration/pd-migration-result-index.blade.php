<div>
    {{-- Header --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h2 class="text-base font-semibold text-zinc-100">Hasil PD Migration</h2>
            <p class="mt-1 text-xs text-zinc-400">Tabel hasil pd_migration_result per periode & jenis penggunaan. Ref: PRD Bab 8</p>
        </div>
    </div>

    {{-- Panel Perhitungan --}}
    <div class="mb-6 rounded-xl border border-zinc-800 bg-zinc-900 p-5 shadow-sm">
        <h3 class="mb-4 text-sm font-semibold text-zinc-100">Perhitungan PD Migration</h3>
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

            @if($runPeriode !== '')
                <div class="sm:w-48">
                    <label class="block text-xs font-medium text-zinc-400 mb-1">Jenis Penggunaan</label>
                    <select wire:model.live="filterUsageType"
                            class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm text-zinc-200 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                        <option value="">Semua Data</option>
                        @foreach($usageTypes as $type)
                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    @if(! $runPeriodeHasResult)
                        {{-- Belum ada hasil: tombol Hitung --}}
                        <button wire:click="confirmJalankan"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-primary-700 transition-colors">
                            Hitung PD Migration
                        </button>
                    @elseif(! $showResults)
                        {{-- Ada hasil tapi belum ditampilkan: tombol Tampilkan Data --}}
                        <button wire:click="tampilkanData" wire:loading.attr="disabled" wire:target="tampilkanData"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-primary-700 disabled:opacity-60 transition-colors">
                            <span wire:loading.remove wire:target="tampilkanData">Tampilkan Data</span>
                            <span wire:loading wire:target="tampilkanData">Memuat...</span>
                        </button>
                    @else
                        {{-- Data sudah ditampilkan: Rekalkulasi + Hapus --}}
                        <button wire:click="confirmJalankan"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-amber-500 px-4 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-amber-600 transition-colors">
                            Rekalkulasi
                        </button>
                        <button wire:click="confirmHapus"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-rose-800/80 bg-rose-950/40 px-4 py-1.5 text-xs font-semibold text-rose-300 shadow-sm hover:bg-rose-900/60 transition-colors">
                            Hapus Data
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

    {{-- Panel Info Catatan Perhitungan --}}
    @php
        $completedLogs = collect($runLogs)->filter(fn($l) => $l->notes);
    @endphp
    @if($completedLogs->isNotEmpty())
        <div class="mb-4 rounded-xl border border-amber-800/60 bg-zinc-900 p-4 shadow-sm">
            <h3 class="mb-3 text-sm font-semibold text-amber-300">Informasi Perhitungan PD Migration</h3>
            <div class="space-y-3">
                @foreach($completedLogs as $log)
                    <div class="rounded-lg border border-zinc-800 bg-zinc-950 p-3">
                        <div class="mb-1 flex items-center gap-2">
                            <span class="inline-flex items-center rounded-full border border-amber-800/60 bg-amber-950/60 px-2 py-0.5 text-xs font-semibold text-amber-300">
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
    <div class="overflow-hidden rounded-xl border border-zinc-700 bg-zinc-900 shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-800 text-sm">
                <thead class="bg-zinc-800/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-400">Periode</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-400">Jenis Penggunaan</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-400">Quality Grade Asal</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-zinc-400">PD Rate</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-zinc-400">Jumlah Cohort</th>
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
                            <td class="px-4 py-3 text-zinc-300">{{ $row->usage_type?->label() ?? '-' }}</td>
                            <td class="px-4 py-3 text-zinc-300">
                                @if($row->fromQualityGrade)
                                    {{ $row->fromQualityGrade->label }}
                                    <span class="ml-1 text-xs text-zinc-500">({{ $row->fromQualityGrade->code }})</span>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-zinc-100">
                                {{ number_format((float) $row->pd_rate * 100, 4) }}%
                            </td>
                            <td class="px-4 py-3 text-center text-zinc-400">{{ $row->cohort_count ?? '-' }}</td>
                            <td class="px-4 py-3 text-center font-mono text-zinc-400">{{ $row->data_period_start }}</td>
                            <td class="px-4 py-3 text-center font-mono text-zinc-400">{{ $row->data_period_end }}</td>
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
                                Belum ada data hasil PD Migration.
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

    {{-- ── Migration Matrix per-segment (Kertas Kerja B2) ── --}}
    @if($showResults && count($matrixBySegment) > 0)
        <div class="mt-6 space-y-6">
            <div class="flex items-center gap-2">
                <h3 class="text-sm font-semibold text-zinc-100">Migration Matrix per Segmen — Kertas Kerja B2</h3>
                <span class="rounded-full border border-blue-800/60 bg-blue-950/40 px-2 py-0.5 text-xs font-medium text-blue-300">{{ count($matrixBySegment) }} segmen</span>
            </div>

            @foreach($matrixBySegment as $utValue => $segment)
                @php
                    $utLabel = \App\Enums\UsageType::from($utValue)->label();
                @endphp
                <div class="overflow-hidden rounded-xl border border-zinc-700 bg-zinc-900 shadow-sm">
                    <div class="flex items-center justify-between border-b border-zinc-700 bg-blue-950/60 px-4 py-3">
                        <div>
                            <h4 class="text-sm font-semibold text-blue-100">{{ $utLabel }}</h4>
                            <p class="mt-1 text-xs text-blue-300">
                                Rate rata-rata tertimbang (weighted by outstanding) per pasangan kualitas aktiva.
                                @if(($segment['cohortCount'] ?? 0) > 0)
                                    <span class="ml-1 font-medium text-blue-200">{{ $segment['cohortCount'] }} cohort periode.</span>
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="bg-blue-950/40">
                                    <th class="sticky left-0 z-10 bg-blue-950/40 px-4 py-2.5 text-left text-xs font-semibold text-blue-300 whitespace-nowrap border-b border-zinc-700">
                                        Dari \ Ke
                                    </th>
                                    @foreach($segment['cols'] as $colCode => $colLabel)
                                        <th class="px-4 py-2.5 text-center text-xs font-semibold whitespace-nowrap border-b border-zinc-700
                                            {{ $colCode === 'WO' ? 'text-rose-400 bg-rose-950/40' : 'text-blue-300' }}">
                                            {{ $colLabel }}
                                            @if($colCode !== 'WO')
                                                <span class="block text-xs font-normal text-blue-400">({{ $colCode }})</span>
                                            @endif
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-800">
                                @foreach($grades as $fromGrade)
                                    @php
                                        $hasRow = isset($segment['matrix'][$fromGrade->id]);
                                    @endphp
                                    @if($hasRow)
                                    <tr class="hover:bg-zinc-800/50 transition-colors">
                                        <td class="sticky left-0 z-10 bg-zinc-900 px-4 py-2 whitespace-nowrap border-r border-zinc-800">
                                            <span class="font-medium text-zinc-100">{{ $fromGrade->label }}</span>
                                            <span class="ml-1 text-xs text-zinc-500">({{ $fromGrade->code }})</span>
                                        </td>
                                        @foreach($segment['cols'] as $colCode => $colLabel)
                                            @php
                                                $rate = $segment['matrix'][$fromGrade->id][$colCode] ?? null;
                                                $isWO  = $colCode === 'WO';
                                                $isDiag = $fromGrade->code === $colCode; // stay
                                            @endphp
                                            <td class="px-4 py-2 text-center font-mono text-xs
                                                {{ $rate !== null
                                                    ? ($isWO ? 'text-rose-400 font-semibold'
                                                        : ($isDiag ? 'text-blue-300 font-semibold bg-blue-950/40'
                                                            : 'text-zinc-300'))
                                                    : 'text-zinc-500' }}">
                                                @if($rate !== null)
                                                    {{ number_format($rate * 100, 4) }}%
                                                @else
                                                    —
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-zinc-800 bg-zinc-800/50 px-4 py-2 text-xs text-zinc-500">
                        Nilai diagonal (biru) = tingkat bertahan di grade yang sama. Kolom merah = Hapus Buku (Write-Off).
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @endif {{-- end $showResults --}}

    {{-- Modal Konfirmasi Perhitungan --}}
    <div x-show="$wire.confirmingAction === 'hitung'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="w-full max-w-sm rounded-xl border border-zinc-800 bg-zinc-900 p-6 shadow-2xl">
            <h3 class="font-semibold text-zinc-100 mb-2">Konfirmasi Perhitungan</h3>
            <p class="text-sm text-zinc-400 mb-4">Yakin ingin menjalankan perhitungan PD Migration untuk periode ini? Proses akan dimasukkan ke antrean.</p>
            <div class="flex gap-2 justify-end">
                <button wire:click="$set('confirmingAction', '')" class="px-3 py-1.5 text-sm rounded-lg border border-zinc-700 bg-zinc-800 text-zinc-300 hover:bg-zinc-700 hover:text-white">Batal</button>
                <button wire:click="jalankanPerhitungan" class="px-3 py-1.5 text-sm rounded-lg bg-primary-600 text-white hover:bg-primary-500 font-semibold shadow-sm">Ya, Jalankan</button>
            </div>
        </div>
    </div>

    {{-- Modal Konfirmasi Hapus --}}
    <div x-show="$wire.confirmingAction === 'hapus'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="w-full max-w-sm rounded-xl border border-zinc-800 bg-zinc-900 p-6 shadow-2xl">
            <h3 class="font-semibold text-zinc-100 mb-2">Hapus Data PD Migration</h3>
            <p class="text-sm text-zinc-400 mb-1">Tindakan ini akan menghapus <strong>seluruh data hasil PD Migration</strong> untuk periode <strong>{{ $filterPeriode ?: $runPeriode }}</strong>, termasuk:</p>
            <ul class="mb-4 mt-2 list-disc pl-5 text-xs text-zinc-400 space-y-0.5">
                <li>Hasil PD Rate per quality grade (<code>pd_migration_result</code>)</li>
                <li>Data matrix cohort (<code>pd_migration_matrix</code>)</li>
            </ul>
            <p class="text-xs text-rose-400 mb-4">Data yang telah dihapus tidak dapat dipulihkan. Lakukan perhitungan ulang untuk mengisi kembali data ini.</p>
            <div class="flex gap-2 justify-end">
                <button wire:click="$set('confirmingAction', '')" class="px-3 py-1.5 text-sm rounded-lg border border-zinc-700 bg-zinc-800 text-zinc-300 hover:bg-zinc-700 hover:text-white">Batal</button>
                <button wire:click="hapusPerhitungan" class="px-3 py-1.5 text-sm rounded-lg bg-rose-600 text-white hover:bg-rose-500 font-semibold shadow-sm">Ya, Hapus Data</button>
            </div>
        </div>
    </div>
</div>
