<div class="min-w-0 text-zinc-100">
    {{-- Header --}}
    <div class="mb-6 overflow-hidden rounded-2xl border border-zinc-800 bg-zinc-950 px-5 py-6 text-white shadow-lg shadow-black/20 sm:px-7">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-sky-300">Risk analytics / PD Netflow</p>
                <h2 class="mt-2 text-2xl font-semibold tracking-tight">Detail pergerakan kualitas pembiayaan</h2>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-zinc-300">Pivot outstanding, transition rate, compound flow loss, dan proyeksi rolling 6 bulan dalam satu ruang kerja.</p>
            </div>
            <a href="{{ route('kalkulasi.pd.index') }}"
               class="inline-flex items-center justify-center gap-2 rounded-lg border border-white/20 bg-white/10 px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-white/20">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" /></svg>
                Kembali ke hasil PD
            </a>
        </div>
    </div>

    {{-- Filter Panel --}}
    <div class="mb-6 rounded-xl border border-zinc-800 bg-zinc-900 p-5 shadow-sm">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
            <div class="sm:w-52">
                <label class="mb-1 block text-xs font-medium text-zinc-400">Periode Penetapan</label>
                <select wire:model="filterPeriode" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm text-zinc-200 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">-- Pilih Periode --</option>
                    @foreach($periods as $period)
                        <option value="{{ $period }}">{{ $period }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:w-60">
                <label class="mb-1 block text-xs font-medium text-zinc-400">Jenis Penggunaan</label>
                <select wire:model="filterUsageType" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm text-zinc-200 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">-- Pilih Jenis --</option>
                    <option value="all">Semua Jenis Penggunaan</option>
                    @foreach($usageTypes as $type)
                        <option value="{{ $type->value }}">{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex gap-2 items-center flex-wrap">
                @if($filterPeriode !== '')
                    @if(! $hasSnapshot)
                        {{-- Belum ada snapshot: hanya Hitung --}}
                        <button wire:click="loadData" wire:loading.attr="disabled" wire:target="loadData"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-primary-700 transition-colors disabled:opacity-60">
                            <span wire:loading.remove wire:target="loadData">Hitung</span>
                            <span wire:loading wire:target="loadData">Menghitung...</span>
                        </button>
                    @else
                        {{-- Sudah ada snapshot: Tampilkan + Rekalkulasi + Hapus Data --}}
                        @if(! $dataLoaded)
                            <button wire:click="loadFromSnapshot" wire:loading.attr="disabled" wire:target="loadFromSnapshot"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-primary-700 transition-colors disabled:opacity-60">
                                <span wire:loading.remove wire:target="loadFromSnapshot">Tampilkan</span>
                                <span wire:loading wire:target="loadFromSnapshot">Memuat...</span>
                            </button>
                        @endif
                        <button wire:click="loadData" wire:loading.attr="disabled" wire:target="loadData"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-700 bg-zinc-900 px-4 py-1.5 text-xs font-semibold text-zinc-300 shadow-sm hover:bg-zinc-800 transition-colors disabled:opacity-60">
                            <span wire:loading.remove wire:target="loadData">Rekalkulasi</span>
                            <span wire:loading wire:target="loadData">Menghitung...</span>
                        </button>
                        <button wire:click="confirmDeleteSnapshot"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-rose-800/80 bg-rose-950/40 px-4 py-1.5 text-xs font-semibold text-rose-300 shadow-sm hover:bg-rose-900/60 transition-colors">
                            Hapus Data
                        </button>
                    @endif

                    {{-- Tombol Export: muncul setelah data ditampilkan atau direkalkulasi --}}
                    @if($dataLoaded)
                        @if($exportStatus === 'processing')
                            <div wire:poll.3000ms="pollExportStatus"
                                 class="inline-flex items-center gap-2 rounded-lg bg-amber-950/60 border border-amber-800 px-4 py-1.5 text-xs font-semibold text-amber-300">
                                <svg class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                                </svg>
                                Sedang diproses...
                            </div>
                        @elseif($exportStatus === 'done' && $exportJobId)
                            <a href="{{ route('exports.download', $exportJobId) }}"
                               class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 transition-colors">
                                Download Excel
                            </a>
                            <button wire:click="exportExcel"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-1.5 text-xs font-medium text-zinc-300 shadow-sm hover:bg-zinc-800 transition-colors">
                                Export Ulang
                            </button>
                        @elseif($exportStatus === 'failed')
                            <span class="text-xs text-rose-400">Export gagal: {{ $exportError }}</span>
                            <button wire:click="exportExcel"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 transition-colors">
                                Coba Lagi
                            </button>
                        @else
                            <button wire:click="exportExcel" wire:loading.attr="disabled" wire:target="exportExcel"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 transition-colors disabled:opacity-60">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                </svg>
                                Export Excel
                            </button>
                        @endif
                    @endif
                @endif
            </div>
        </div>

        {{-- Error message --}}
        @if($errorMessage)
            <div class="mt-3 flex items-center gap-2 rounded-lg bg-red-950/50 border border-red-800 px-3 py-2 text-xs text-red-300">
                <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
                {{ $errorMessage }}
            </div>
        @endif
    </div>

    @if($dataLoaded)

    {{-- ================================================================ --}}
    {{-- SECTION 1: Outstanding per Bucket per Periode --}}
    {{-- ================================================================ --}}
    <div class="mb-8">
        <div class="mb-3 flex items-center gap-2">
            <div class="h-3 w-1 rounded-full bg-blue-500"></div>
            <h3 class="text-sm font-semibold text-zinc-100">1. Outstanding per Bucket per Periode</h3>
            <span class="text-xs text-zinc-500">(dalam rupiah penuh)</span>
        </div>
        <div class="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-900 shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead>
                        <tr class="bg-blue-600 text-white">
                            <th class="sticky left-0 z-10 bg-blue-600 px-3 py-2.5 text-left font-semibold whitespace-nowrap min-w-[80px]">Bucket</th>
                            @foreach($outstandingPeriods as $period)
                                <th class="px-3 py-2.5 text-right font-semibold whitespace-nowrap">{{ $period }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        @foreach($buckets as $bucket)
                            <tr class="hover:bg-zinc-800/50 transition-colors">
                                <td class="sticky left-0 z-10 bg-zinc-900 px-3 py-2 font-medium text-zinc-300 whitespace-nowrap hover:bg-zinc-800">
                                    {{ $bucket['code'] }} — {{ $bucket['label'] }}
                                </td>
                                @foreach($outstandingPeriods as $period)
                                    @php $val = $outstanding[$bucket['id']][$period] ?? 0; @endphp
                                    <td class="px-3 py-2 text-right text-zinc-300 tabular-nums whitespace-nowrap {{ $val > 0 ? '' : 'text-zinc-600' }}">
                                        {{ $val > 0 ? number_format($val / 1000000, 2) : '-' }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ================================================================ --}}
    {{-- SECTION 2: Persentase Pergerakan Bucket (Transition Rate) --}}
    {{-- ================================================================ --}}
    <div class="mb-8">
        <div class="mb-3 flex items-center gap-2">
            <div class="h-3 w-1 rounded-full bg-amber-500"></div>
            <h3 class="text-sm font-semibold text-zinc-100">2. Persentase Pergerakan Bucket (Transition Rate)</h3>
            <span class="text-xs text-zinc-500">(Bn ke Bn+1 per periode)</span>
        </div>
        <div class="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-900 shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead>
                        <tr class="bg-amber-500 text-white">
                            <th class="sticky left-0 z-10 bg-zinc-800 px-3 py-2.5 text-left font-semibold whitespace-nowrap min-w-[100px]">Perpindahan</th>
                            @foreach($transitionPeriods as $period)
                                <th class="px-3 py-2.5 text-right font-semibold whitespace-nowrap">{{ $period }}</th>
                            @endforeach
                            @foreach($projectionPeriods as $period)
                                <th class="bg-amber-900/80 px-3 py-2.5 text-right font-semibold whitespace-nowrap">{{ $period }} (Proyeksi)</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        @php $bucketList = array_values($buckets); $bucketCount = count($bucketList); @endphp
                        @for($i = 0; $i < $bucketCount - 1; $i++)
                            @php $fromBucket = $bucketList[$i]; @endphp
                            <tr class="hover:bg-zinc-800/50 transition-colors">
                                <td class="sticky left-0 z-10 bg-zinc-900 px-3 py-2 font-medium text-zinc-300 whitespace-nowrap hover:bg-zinc-800">
                                    {{ $fromBucket['code'] }} → {{ $bucketList[$i + 1]['code'] }}
                                </td>
                                @foreach($transitionPeriods as $period)
                                    @php $val = $transition[$fromBucket['id']][$period] ?? null; @endphp
                                    <td class="bg-zinc-950 px-3 py-2 text-right tabular-nums whitespace-nowrap {{ $val !== null && $val > 0 ? 'text-amber-300' : 'text-zinc-500' }}">
                                        {{ $val !== null ? number_format(min(1, $val) * 100, 2) . '%' : '-' }}
                                    </td>
                                @endforeach
                                @foreach($projectionPeriods as $period)
                                    @php $val = $projection[$fromBucket['id']][$period] ?? null; @endphp
                                    <td class="bg-amber-950/50 px-3 py-2 text-right font-medium tabular-nums whitespace-nowrap {{ $val !== null && $val > 0 ? 'text-amber-300' : 'text-zinc-500' }}">
                                        {{ $val !== null ? number_format(min(1, $val) * 100, 2) . '%' : '-' }}
                                    </td>
                                @endforeach
                            </tr>
                        @endfor
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ================================================================ --}}
    {{-- SECTION 3: Compound Flow to Loss --}}
    {{-- ================================================================ --}}
    <div class="mb-8">
        <div class="mb-3 flex items-center gap-2">
            <div class="h-3 w-1 rounded-full bg-rose-500"></div>
            <h3 class="text-sm font-semibold text-zinc-100">3. Compound Flow to Loss per Bucket per Periode</h3>
            <span class="text-xs text-zinc-500">(produk berantai transition rate hingga bucket akhir)</span>
        </div>
        <div class="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-900 shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead>
                        <tr class="bg-rose-600 text-white">
                            <th class="sticky left-0 z-10 bg-rose-600 px-3 py-2.5 text-left font-semibold whitespace-nowrap min-w-[80px]">Bucket</th>
                            @foreach($compoundPeriods as $period)
                                <th class="px-3 py-2.5 text-right font-semibold whitespace-nowrap">{{ $period }}</th>
                            @endforeach
                            <th class="px-3 py-2.5 text-right font-semibold whitespace-nowrap bg-rose-700">Rata-rata PD</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        @php $bBuckets = array_values($buckets); $bCount = count($bBuckets); @endphp
                        @foreach($bBuckets as $bIdx => $bucket)
                            @if($bIdx < $bCount - 1)
                            @php
                                $avg = $compoundAvg[$bucket['id']] ?? 0;
                            @endphp
                            <tr class="hover:bg-zinc-800/50 transition-colors">
                                <td class="sticky left-0 z-10 bg-zinc-900 px-3 py-2 font-medium text-zinc-300 whitespace-nowrap hover:bg-zinc-800">
                                    {{ $bucket['code'] }} — {{ $bucket['label'] }}
                                </td>
                                @foreach($compoundPeriods as $period)
                                    @php $val = $compound[$bucket['id']][$period] ?? 0; @endphp
                                    <td class="px-3 py-2 text-right tabular-nums whitespace-nowrap {{ $val > 0 ? 'text-rose-300' : 'text-zinc-600' }}">
                                        {{ $val > 0 ? number_format($val * 100, 2) . '%' : '-' }}
                                    </td>
                                @endforeach
                                <td class="px-3 py-2 text-right font-semibold tabular-nums whitespace-nowrap bg-rose-950/50 text-rose-300">
                                    {{ $avg > 0 ? number_format($avg * 100, 2) . '%' : '-' }}
                                </td>
                            </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @elseif(!$errorMessage)
    {{-- Empty state --}}
    <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-700 bg-zinc-900 py-16 text-center">
        <svg class="h-10 w-10 text-zinc-600" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 01-1.125-1.125M3.375 19.5h1.5C5.496 19.5 6 18.996 6 18.375m-3.75.125v-13.5A1.125 1.125 0 013.375 3.75h13.5A1.125 1.125 0 0118 4.875v13.5M6 18.375a1.125 1.125 0 001.125 1.125H18M6 18.375V6.375" />
        </svg>
        <p class="mt-3 text-sm font-medium text-zinc-400">Pilih periode kalkulasi dan jenis penggunaan</p>
        <p class="mt-1 text-xs text-zinc-500">lalu klik <strong>Tampilkan Data</strong> untuk menghitung pivot PD Netflow.</p>
    </div>
    @endif

    {{-- Modal Konfirmasi Hapus Snapshot --}}
    <div x-show="$wire.confirmingAction === 'delete_snapshot'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="w-full max-w-sm rounded-xl bg-zinc-900 border border-zinc-800 p-6 shadow-xl">
            <h3 class="font-semibold text-zinc-100 mb-2">Konfirmasi Hapus Data</h3>
            <p class="text-sm text-zinc-300 mb-4">Yakin ingin menghapus data perhitungan untuk periode ini? Tindakan ini tidak bisa dibatalkan.</p>
            <div class="flex gap-2 justify-end">
                <button wire:click="$set('confirmingAction', '')" class="px-3 py-1.5 text-sm rounded-lg border border-zinc-700 text-zinc-300 hover:bg-zinc-800">Batal</button>
                <button wire:click="deleteSnapshot" class="px-3 py-1.5 text-sm rounded-lg bg-red-600 text-white hover:bg-red-700">Ya, Hapus</button>
            </div>
        </div>
    </div>
</div>
