<div class="space-y-6" wire:poll.3s="pollExportStatus">

    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="mb-1 text-[10px] font-semibold uppercase tracking-[0.2em] text-primary-400">Data Center</p>
            <h1 class="text-xl font-semibold tracking-tight text-zinc-100">Export Data</h1>
            <p class="mt-1 max-w-2xl text-xs text-zinc-400">Pilih dataset, tentukan periode, lalu proses export CSV di background tanpa mengganggu pekerjaan Anda.</p>
        </div>
        <div class="hidden rounded-lg border border-zinc-800 bg-zinc-900/70 px-3 py-2 text-right sm:block">
            <p class="text-[10px] uppercase tracking-wider text-zinc-500">Format</p>
            <p class="text-xs font-semibold text-zinc-200">CSV Streaming</p>
        </div>
    </div>

    @if ($exportJobId)
        <div class="rounded-lg border border-zinc-700 bg-zinc-800 p-3 text-xs text-zinc-300">
            Status export: <strong>{{ $exportStatus ?: 'pending' }}</strong>
            @if ($exportStatus === 'done')
                <a class="ml-2 text-primary-400 underline" href="{{ route('exports.download', $exportJobId) }}">Download CSV</a>
            @elseif ($exportStatus === 'failed')
                <span class="ml-2 text-red-400">{{ $exportError }}</span>
            @endif
        </div>
    @endif

    {{-- Filter Global --}}
    <div class="rounded-xl border border-zinc-800 bg-zinc-900/90 p-4">
        <p class="mb-3 text-xs font-medium text-zinc-300">Filter (opsional — berlaku untuk semua export)</p>
        <div class="flex flex-wrap gap-3">
            {{-- Filter Periode --}}
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-zinc-400">Periode</label>
                <select wire:model.live="filterPeriode"
                        class="rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-zinc-200 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 @error('filterPeriode') border-rose-500 @enderror">
                    <option value="">Semua Periode</option>
                    @foreach ($allPeriodes as $periode)
                        <option value="{{ $periode }}">{{ $periode }}</option>
                    @endforeach
                </select>
                @error('filterPeriode')
                    <span class="text-xs text-rose-400">{{ $message }}</span>
                @enderror
            </div>
            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-zinc-400">Jenis Penggunaan</label>
                <select wire:model.live="filterUsageType"
                        class="rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-zinc-200 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">Semua Segmen</option>
                    @foreach ($usageTypes as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @error('filterPeriode')
            <p class="mt-3 text-xs text-rose-400">{{ $message }}</p>
        @enderror

        @if ($filterPeriode !== '' || $filterUsageType !== '')
            <div class="mt-3 flex items-center gap-2">
                <span class="text-xs text-zinc-400">Filter aktif:</span>
                @if ($filterPeriode !== '')
                    <span class="rounded-full bg-primary-950/60 border border-primary-800/60 px-2.5 py-0.5 text-xs font-medium text-primary-300">
                        Periode: {{ $filterPeriode }}
                    </span>
                @endif
                @if ($filterUsageType !== '')
                    <span class="rounded-full bg-primary-950/60 border border-primary-800/60 px-2.5 py-0.5 text-xs font-medium text-primary-300">
                        Segmen: {{ collect($usageTypes)->firstWhere('value', $filterUsageType)?->label() ?? $filterUsageType }}
                    </span>
                @endif
                <button wire:click="$set('filterPeriode', ''); $set('filterUsageType', '')"
                        class="ml-1 text-xs text-zinc-400 underline hover:text-zinc-200">
                    Reset
                </button>
            </div>
        @endif
    </div>

    {{-- Kartu Export --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

        {{-- 1. Daftar Pembiayaan --}}
        <div class="flex flex-col justify-between rounded-xl border border-zinc-700/50 bg-zinc-800/40 p-5 transition-colors hover:border-zinc-600/60">
            <div class="mb-4">
                <div class="mb-2 flex items-center gap-2.5">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-500/15">
                        <svg class="h-4 w-4 text-blue-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 5.625c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
                        </svg>
                    </div>
                    <h3 class="text-sm font-semibold text-zinc-100">Daftar Pembiayaan</h3>
                </div>
                <p class="text-xs leading-relaxed text-zinc-400">
                    Data historis pembiayaan per periode — sumber dasar kalkulasi PD, LGD, dan CKPN.
                    Mencakup no. kontrak, nama nasabah, segmen, outstanding, kolektibilitas, dan status pembiayaan.
                </p>
            </div>
            <button wire:click="exportDaftarPembiayaan"
                    wire:loading.attr="disabled"
                    wire:target="exportDaftarPembiayaan"
                    class="flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-xs font-semibold text-white transition-colors hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-60">
                <span wire:loading.remove wire:target="exportDaftarPembiayaan">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                </span>
                <span wire:loading wire:target="exportDaftarPembiayaan">
                    <svg class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </span>
                <span wire:loading.remove wire:target="exportDaftarPembiayaan">Unduh CSV</span>
                <span wire:loading wire:target="exportDaftarPembiayaan">Memproses...</span>
            </button>
        </div>

        {{-- 2. History Pembiayaan --}}
        <div class="flex flex-col justify-between rounded-xl border border-zinc-700/50 bg-zinc-800/40 p-5 transition-colors hover:border-zinc-600/60">
            <div class="mb-4">
                <div class="mb-2 flex items-center gap-2.5">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-violet-500/15">
                        <svg class="h-4 w-4 text-violet-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m6-2a10 10 0 11-20 0 10 10 0 0120 0z" /></svg>
                    </div>
                    <h3 class="text-sm font-semibold text-zinc-100">History Pembiayaan</h3>
                </div>
                <p class="text-xs leading-relaxed text-zinc-400">Data historis dalam rolling window periode terpilih sebagai bahan verifikasi perhitungan PD Netflow.</p>
            </div>
            <button wire:click="exportHistoryPembiayaan" wire:loading.attr="disabled" wire:target="exportHistoryPembiayaan" class="flex w-full items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-xs font-semibold text-white transition-colors hover:bg-violet-500 disabled:cursor-not-allowed disabled:opacity-60">
                <span wire:loading.remove wire:target="exportHistoryPembiayaan">Unduh CSV</span>
                <span wire:loading wire:target="exportHistoryPembiayaan">Memproses...</span>
            </button>
        </div>

        {{-- 3. PD Netflow --}}
        <div class="flex flex-col justify-between rounded-xl border border-zinc-700/50 bg-zinc-800/40 p-5 transition-colors hover:border-zinc-600/60">
            <div class="mb-4">
                <div class="mb-2 flex items-center gap-2.5">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-violet-500/15">
                        <svg class="h-4 w-4 text-violet-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                        </svg>
                    </div>
                    <h3 class="text-sm font-semibold text-zinc-100">Data PD Netflow</h3>
                </div>
                <p class="text-xs leading-relaxed text-zinc-400">
                    Hasil perhitungan Probability of Default metode Netflow per bucket per segmen.
                    Mencakup netflow rate, jumlah akun, outstanding, dan periode data observasi.
                </p>
            </div>
            <button wire:click="exportPdNetflow"
                    wire:loading.attr="disabled"
                    wire:target="exportPdNetflow"
                    class="flex w-full items-center justify-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-xs font-semibold text-white transition-colors hover:bg-violet-500 disabled:cursor-not-allowed disabled:opacity-60">
                <span wire:loading.remove wire:target="exportPdNetflow">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                </span>
                <span wire:loading wire:target="exportPdNetflow">
                    <svg class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </span>
                <span wire:loading.remove wire:target="exportPdNetflow">Unduh Excel</span>
                <span wire:loading wire:target="exportPdNetflow">Memproses...</span>
            </button>
        </div>

        {{-- 3. LGD Expected Recoveries --}}
        <div class="flex flex-col justify-between rounded-xl border border-zinc-700/50 bg-zinc-800/40 p-5 transition-colors hover:border-zinc-600/60">
            <div class="mb-4">
                <div class="mb-2 flex items-center gap-2.5">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-500/15">
                        <svg class="h-4 w-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                        </svg>
                    </div>
                    <h3 class="text-sm font-semibold text-zinc-100">Data LGD Expected Recoveries</h3>
                </div>
                <p class="text-xs leading-relaxed text-zinc-400">
                    Hasil perhitungan Loss Given Default metode Expected Recoveries per segmen.
                    Mencakup total write-off, total recovery, recovery rate, LGD rate, dan periode observasi.
                </p>
            </div>
            <button wire:click="exportLgdEr"
                    wire:loading.attr="disabled"
                    wire:target="exportLgdEr"
                    class="flex w-full items-center justify-center gap-2 rounded-lg bg-amber-600 px-4 py-2 text-xs font-semibold text-white transition-colors hover:bg-amber-500 disabled:cursor-not-allowed disabled:opacity-60">
                <span wire:loading.remove wire:target="exportLgdEr">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                </span>
                <span wire:loading wire:target="exportLgdEr">
                    <svg class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </span>
                <span wire:loading.remove wire:target="exportLgdEr">Unduh Excel</span>
                <span wire:loading wire:target="exportLgdEr">Memproses...</span>
            </button>
        </div>

        {{-- 4. LGD Collateral Shortfall --}}
        <div class="flex flex-col justify-between rounded-xl border border-zinc-700/50 bg-zinc-800/40 p-5 transition-colors hover:border-zinc-600/60">
            <div class="mb-4">
                <div class="mb-2 flex items-center gap-2.5">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-500/15">
                        <svg class="h-4 w-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                        </svg>
                    </div>
                    <h3 class="text-sm font-semibold text-zinc-100">Data LGD Collateral Shortfall</h3>
                </div>
                <p class="text-xs leading-relaxed text-zinc-400">
                    Hasil perhitungan Loss Given Default metode Collateral Shortfall per nasabah per segmen.
                    Mencakup no. kontrak, nama nasabah, outstanding, nilai agunan bersih, shortfall, dan LGD rate.
                </p>
            </div>
            <button wire:click="exportLgdCs"
                    wire:loading.attr="disabled"
                    wire:target="exportLgdCs"
                    class="flex w-full items-center justify-center gap-2 rounded-lg bg-emerald-600 px-4 py-2 text-xs font-semibold text-white transition-colors hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-60">
                <span wire:loading.remove wire:target="exportLgdCs">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                </span>
                <span wire:loading wire:target="exportLgdCs">
                    <svg class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                </span>
                <span wire:loading.remove wire:target="exportLgdCs">Unduh CSV</span>
                <span wire:loading wire:target="exportLgdCs">Memproses...</span>
            </button>
        </div>

    </div>
</div>
