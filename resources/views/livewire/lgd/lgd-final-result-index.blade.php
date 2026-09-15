<div>
    {{-- Header --}}
    <div class="mb-6">
        <h2 class="text-base font-semibold text-zinc-100">LGD Final per Segmen</h2>
        <p class="mt-1 text-xs text-zinc-400">Gabungan LGD Expected Recoveries + LGD Collateral Shortfall per segmen. Ref: PRD Bab 11</p>
    </div>

    {{-- Panel Kontrol --}}
    <div class="mb-6 rounded-xl border border-zinc-800 bg-zinc-900 p-5 shadow-sm">
        <h3 class="mb-4 text-sm font-semibold text-zinc-100">Periode Perhitungan</h3>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
            {{-- Select Periode --}}
            <div class="sm:w-52">
                <label class="mb-1 block text-xs font-medium text-zinc-400">Periode</label>
                <select wire:model.live="runPeriode"
                    class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm text-zinc-200 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">-- Pilih Periode --</option>
                    @foreach($availablePeriods as $p)
                        <option value="{{ $p }}">{{ $p }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Tombol aksi: state machine --}}
            @if($runPeriode !== '')
                <div class="flex flex-wrap items-center gap-2">
                    @if(! $runPeriodeHasResult)
                        <button wire:click="confirmJalankan"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-primary-700 transition-colors">
                            Hitung
                        </button>
                    @elseif(! $showResults)
                        <button wire:click="tampilkanData" wire:loading.attr="disabled" wire:target="tampilkanData"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-primary-700 disabled:opacity-60 transition-colors">
                            <span wire:loading.remove wire:target="tampilkanData">Tampilkan Data</span>
                            <span wire:loading wire:target="tampilkanData">Memuat...</span>
                        </button>
                    @else
                        <button wire:click="tampilkanData" wire:loading.attr="disabled" wire:target="tampilkanData"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-primary-700 disabled:opacity-60 transition-colors">
                            <span wire:loading.remove wire:target="tampilkanData">Tampilkan Data</span>
                            <span wire:loading wire:target="tampilkanData">Memuat...</span>
                        </button>
                        <button wire:click="confirmRekalkulasi"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-amber-500 px-4 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-amber-600 transition-colors">
                            Rekalkulasi
                        </button>
                        <button wire:click="confirmHapus"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-rose-800/80 bg-rose-950/40 px-4 py-1.5 text-xs font-semibold text-rose-300 shadow-sm hover:bg-rose-900/60 transition-colors">
                            Hapus
                        </button>
                    @endif
                </div>
            @endif
        </div>

        {{-- Status polling saat job berjalan --}}
        @if($isRunning)
            <div wire:poll.3s="pollJobStatus" class="mt-3 flex items-center gap-2 text-xs text-amber-700">
                <svg class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                Perhitungan LGD Final sedang berjalan...
            </div>
        @endif

        {{-- Run logs --}}
        @if(count($runLogs) > 0)
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-xs text-zinc-300">
                    <thead>
                        <tr class="border-b border-zinc-700 bg-zinc-800/50">
                            <th class="px-3 py-1.5 text-left font-semibold">Segmen</th>
                            <th class="px-3 py-1.5 text-left font-semibold">Status</th>
                            <th class="px-3 py-1.5 text-left font-semibold">Mulai</th>
                            <th class="px-3 py-1.5 text-left font-semibold">Selesai</th>
                            <th class="px-3 py-1.5 text-left font-semibold">Pesan Error</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        @foreach($runLogs as $log)
                            <tr>
                                <td class="px-3 py-1.5">
                                    {{ $log->usage_type instanceof \App\Enums\UsageType ? $log->usage_type->label() : ($log->usage_type ? \App\Enums\UsageType::from((int) $log->usage_type)->label() : '-') }}
                                </td>
                                <td class="px-3 py-1.5">
                                    <span @class([
                                        'inline-block rounded-full px-2 py-0.5 text-xs font-semibold',
                                        'bg-green-900/40 text-green-300' => $log->status === \App\Enums\RunStatus::Completed,
                                        'bg-red-950/60 text-red-300'     => $log->status === \App\Enums\RunStatus::Failed,
                                        'bg-amber-900/40 text-amber-300' => $log->status === \App\Enums\RunStatus::Processing,
                                        'bg-zinc-800 text-zinc-400' => $log->status === \App\Enums\RunStatus::Pending,
                                    ])>{{ $log->status->value }}</span>
                                </td>
                                <td class="px-3 py-1.5 font-mono">{{ $log->started_at?->format('H:i:s') ?? '-' }}</td>
                                <td class="px-3 py-1.5 font-mono">{{ $log->completed_at?->format('H:i:s') ?? '-' }}</td>
                                <td class="px-3 py-1.5 text-rose-400 max-w-xs truncate">{{ $log->error_message ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Panel Info Catatan Perhitungan --}}
    @php $completedLogsFinal = collect($runLogs)->filter(fn($l) => $l->notes); @endphp
    @if($completedLogsFinal->isNotEmpty())
        <div class="mb-4 rounded-xl border border-zinc-800 bg-zinc-900 p-4 shadow-sm">
            <h3 class="mb-3 text-sm font-semibold text-zinc-100">Informasi Perhitungan LGD Final</h3>
            <div class="space-y-3">
                @foreach($completedLogsFinal as $log)
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

    {{-- Tabel Hasil LGD Final per Segmen --}}
    @if($showResults)
        {{-- Search --}}
        <div class="mb-4">
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="Cari periode, jenis penggunaan..."
                class="w-full sm:w-64 px-3 py-1.5 text-sm border border-zinc-700 bg-zinc-950 text-zinc-200 placeholder-zinc-500 rounded-lg shadow-sm focus:outline-none focus:ring-1 focus:ring-primary-500 focus:border-primary-500"
            />
        </div>

        <div class="rounded-xl border border-zinc-700 bg-zinc-900 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-zinc-700 bg-zinc-800/50 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-zinc-100">Hasil LGD Final — Periode {{ $filterPeriode }}</h3>
                    <p class="mt-1 text-xs text-zinc-400">LGD = 1 - (Total Recover / Total Outstanding); Total Recover = Recovery ER + Shortfall CS; Total Outstanding = Write-off ER + Outstanding CS</p>
                </div>
                <div>
                    <label class="text-xs text-zinc-400 mr-1">Filter periode:</label>
                    <select wire:model.live="filterPeriode"
                        class="rounded-md border border-zinc-700 bg-zinc-950 px-2 py-1 text-xs text-zinc-200 shadow-sm focus:outline-none focus:border-primary-500 focus:ring-1 focus:ring-primary-500">
                        <option value="">Semua</option>
                        @foreach($snapshotPeriods as $sp)
                            <option value="{{ $sp }}">{{ $sp }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            @if($results->isEmpty())
                <div class="px-4 py-8 text-center text-sm text-zinc-500">Tidak ada data untuk ditampilkan.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-800 text-xs">
                        <thead class="bg-zinc-800/80 border-b border-zinc-700">
                            <tr>
                                <th class="px-4 py-2.5 text-left font-semibold text-zinc-300">Periode</th>
                                <th class="px-4 py-2.5 text-left font-semibold text-zinc-300">Segmen</th>
                                <th class="px-4 py-2.5 text-right font-semibold text-zinc-300">Write-off ER</th>
                                <th class="px-4 py-2.5 text-right font-semibold text-zinc-300">Recovery ER</th>
                                <th class="px-4 py-2.5 text-right font-semibold text-zinc-300">Outstanding CS</th>
                                <th class="px-4 py-2.5 text-right font-semibold text-zinc-300">Shortfall CS</th>
                                <th class="px-4 py-2.5 text-right font-semibold text-zinc-300">Total Recover</th>
                                <th class="px-4 py-2.5 text-right font-semibold text-zinc-300">Total Outstanding</th>
                                <th class="px-4 py-2.5 text-right font-semibold text-zinc-300">LGD Final (%)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-800 bg-zinc-900">
                            @foreach($results as $row)
                                <tr class="hover:bg-zinc-800/50">
                                    <td class="px-4 py-2 font-mono text-zinc-300">{{ $row->calculation_period }}</td>
                                    <td class="px-4 py-2 font-medium text-zinc-100">
                                        {{ $row->usage_type ? $row->usage_type->label() : '-' }}
                                    </td>
                                    <td class="px-4 py-2 text-right font-mono text-zinc-300">
                                        {{ number_format((float) $row->er_total_writeoff_amount, 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-2 text-right font-mono text-zinc-300">
                                        {{ number_format((float) $row->er_total_recovery_amount, 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-2 text-right font-mono text-zinc-300">
                                        {{ number_format((float) $row->cs_total_outstanding, 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-2 text-right font-mono text-zinc-300">
                                        {{ number_format((float) $row->cs_total_shortfall, 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-2 text-right font-mono text-zinc-300">
                                        {{ number_format((float) $row->total_recover, 0, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-2 text-right font-mono text-zinc-300">
                                        {{ number_format((float) $row->total_os, 0, ',', '.') }}
                                        {{-- total_recover = Total LGD, total_os = Total WO (nama kolom DB legacy) --}}
                                    </td>
                                    <td class="px-4 py-2 text-right font-semibold font-mono
                                        @if((float)$row->lgd_final_rate >= 0.7) text-red-300
                                        @elseif((float)$row->lgd_final_rate >= 0.4) text-amber-300
                                        @else text-green-300
                                        @endif">
                                        {{ number_format((float) $row->lgd_final_rate * 100, 4, ',', '.') }}%
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

    {{-- Modal Konfirmasi Hitung --}}
    <div x-show="$wire.confirmingAction === 'hitung'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="w-full max-w-sm rounded-xl border border-zinc-800 bg-zinc-900 p-6 shadow-2xl">
            <h3 class="font-semibold text-zinc-100 mb-2">Jalankan Perhitungan LGD Final?</h3>
            <p class="text-sm text-zinc-400 mb-1">Periode: <strong>{{ $runPeriode }}</strong></p>
            <p class="text-xs text-zinc-400 mb-4">Pastikan snapshot LGD ER dan LGD CS untuk periode ini sudah tersedia sebelum menjalankan perhitungan.</p>
            <div class="flex gap-2 justify-end">
                <button wire:click="$set('confirmingAction', '')" class="px-3 py-1.5 text-sm rounded-lg border border-zinc-700 bg-zinc-800 text-zinc-300 hover:bg-zinc-700 hover:text-white">Batal</button>
                <button wire:click="jalankanPerhitungan" class="px-3 py-1.5 text-sm rounded-lg bg-primary-600 text-white hover:bg-primary-500 font-semibold shadow-sm">Ya, Hitung</button>
            </div>
        </div>
    </div>

    {{-- Modal Konfirmasi Rekalkulasi --}}
    <div x-show="$wire.confirmingAction === 'rekalkulasi'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="w-full max-w-sm rounded-xl border border-zinc-800 bg-zinc-900 p-6 shadow-2xl">
            <h3 class="font-semibold text-zinc-100 mb-2">Rekalkulasi LGD Final?</h3>
            <p class="text-sm text-zinc-400 mb-4">Snapshot periode <strong>{{ $runPeriode }}</strong> akan dihapus dan dihitung ulang. Tindakan ini tidak bisa dibatalkan.</p>
            <div class="flex gap-2 justify-end">
                <button wire:click="$set('confirmingAction', '')" class="px-3 py-1.5 text-sm rounded-lg border border-zinc-700 bg-zinc-800 text-zinc-300 hover:bg-zinc-700 hover:text-white">Batal</button>
                <button wire:click="rekalkulasiPerhitungan" class="px-3 py-1.5 text-sm rounded-lg bg-amber-600 text-white hover:bg-amber-500 font-semibold shadow-sm">Ya, Rekalkulasi</button>
            </div>
        </div>
    </div>

    {{-- Modal Konfirmasi Hapus --}}
    <div x-show="$wire.confirmingAction === 'hapus'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="w-full max-w-sm rounded-xl border border-zinc-800 bg-zinc-900 p-6 shadow-2xl">
            <h3 class="font-semibold text-zinc-100 mb-2">Hapus Perhitungan LGD Final?</h3>
            <p class="text-sm text-zinc-400 mb-4">Yakin ingin menghapus seluruh perhitungan LGD Final untuk periode <strong>{{ $runPeriode }}</strong>? Tindakan ini tidak bisa dibatalkan.</p>
            <div class="flex gap-2 justify-end">
                <button wire:click="$set('confirmingAction', '')" class="px-3 py-1.5 text-sm rounded-lg border border-zinc-700 bg-zinc-800 text-zinc-300 hover:bg-zinc-700 hover:text-white">Batal</button>
                <button wire:click="hapusPerhitungan" class="px-3 py-1.5 text-sm rounded-lg bg-rose-600 text-white hover:bg-rose-500 font-semibold shadow-sm">Ya, Hapus</button>
            </div>
        </div>
    </div>
</div>
