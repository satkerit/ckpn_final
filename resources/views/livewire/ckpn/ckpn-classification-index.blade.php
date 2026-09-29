<div>
    {{-- Header --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-base font-semibold text-zinc-100">Klasifikasi Pembiayaan CKPN</h2>
            <p class="mt-0.5 text-xs text-zinc-400">Tabel klasifikasi pembiayaan (Individual vs Kolektif) per periode. Ref: PRD Bab 6.1, 12a Step 3</p>
        </div>
        @if($showTable)
            <div class="flex items-center gap-3 rounded-xl border border-zinc-700 bg-zinc-900 px-4 py-2.5 shadow-sm">
                <div>
                    <p class="text-xs font-medium text-zinc-400">Total Outstanding / EAD</p>
                    <p class="mt-0.5 font-mono text-base font-semibold text-zinc-100">
                        Rp {{ number_format((float) $totalOutstanding, 0, ',', '.') }}
                    </p>
                </div>
            </div>
        @endif
    </div>

    {{-- Panel: Jalankan Klasifikasi --}}
    <div class="mb-6 rounded-xl border border-zinc-700 bg-zinc-900 p-5 shadow-sm">
        <div class="flex flex-col gap-4">
            {{-- Header panel: judul + badge status --}}
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-zinc-100">Jalankan Klasifikasi</h3>
                    <p class="mt-0.5 text-xs text-zinc-400">Pilih periode penetapan CKPN untuk memuat dan mengklasifikasikan data debitur.</p>
                </div>
                @if($classificationPeriod !== '')
                    @if($periodClassified)
                        <span class="inline-flex w-fit items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-200">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                            Sudah diklasifikasi
                        </span>
                    @else
                        <span class="inline-flex w-fit items-center gap-1.5 rounded-full bg-zinc-800 px-3 py-1 text-xs font-medium text-zinc-400 ring-1 ring-inset ring-slate-200">
                            <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                            Belum diklasifikasi
                        </span>
                    @endif
                @endif
            </div>

            {{-- Form: periode + tombol aksi --}}
            <div class="flex flex-col gap-4 border-t border-zinc-800 pt-4 md:flex-row md:items-end md:justify-between">
                <div class="w-full md:w-72">
                    <label for="classification-period" class="mb-1.5 block text-xs font-medium text-zinc-400">Periode Penetapan</label>
                    <select id="classification-period" wire:model.live="classificationPeriod"
                            class="h-10 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 text-sm text-zinc-100 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                        <option value="">Pilih periode</option>
                        @foreach($periods as $p)
                            <option value="{{ $p }}">{{ $p }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex flex-wrap items-end gap-2">
                    @if($classificationPeriod !== '')
                        @if($periodClassified)
                            <button wire:click="tampilkan"
                                    class="inline-flex h-10 items-center gap-1.5 rounded-lg bg-emerald-600 px-4 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-700">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                Tampil
                            </button>
                            @if($showTable)
                                <button wire:click="exportExcel"
                                        class="inline-flex h-10 items-center gap-1.5 rounded-lg border border-emerald-700/80 bg-emerald-950/50 px-4 text-sm font-medium text-emerald-300 shadow-sm transition hover:bg-emerald-900/60">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                    </svg>
                                    Export Excel
                                </button>
                                <button wire:click="confirmHapusKlasifikasi"
                                        class="inline-flex h-10 items-center gap-1.5 rounded-lg border border-rose-800/60 bg-rose-950/40 px-4 text-sm font-medium text-rose-300 transition hover:bg-rose-900/60">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                    </svg>
                                    Hapus Klasifikasi
                                </button>
                            @endif
                        @else
                            <button wire:click="confirmKlasifikasi"
                                    class="inline-flex h-10 items-center gap-1.5 rounded-lg bg-blue-600 px-4 text-sm font-medium text-white shadow-sm transition hover:bg-blue-700">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                Klasifikasi
                            </button>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if($showTable)
    {{-- Filter --}}
    <div class="mb-4 rounded-xl border border-zinc-700 bg-zinc-900 p-4 shadow-sm">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
            <div class="w-full sm:w-40">
                <label class="mb-1.5 block text-xs font-medium text-zinc-400">Periode</label>
                <select wire:model.live="filterPeriode"
                        class="h-10 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 text-sm text-zinc-100 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">Semua Periode</option>
                    @foreach($periods as $p)
                        <option value="{{ $p }}">{{ $p }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-full sm:w-48">
                <label class="mb-1.5 block text-xs font-medium text-zinc-400">Jenis Penggunaan</label>
                <select wire:model.live="filterUsageType"
                        class="h-10 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 text-sm text-zinc-100 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">Semua Segmen</option>
                    @foreach($usageTypes as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-full sm:w-48">
                <label class="mb-1.5 block text-xs font-medium text-zinc-400">Kantor (Level 1)</label>
                <select wire:model.live="filterOfficeCode"
                        class="h-10 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 text-sm text-zinc-100 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">Semua Kantor</option>
                    @foreach($offices as $office)
                        <option value="{{ $office->office_code }}">{{ $office->office_code }} — {{ $office->office_name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-full sm:w-48">
                <label class="mb-1.5 block text-xs font-medium text-zinc-400">Akad (Level 2)</label>
                <select wire:model.live="filterAkadCode"
                        class="h-10 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 text-sm text-zinc-100 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">Semua Akad</option>
                    @foreach($akadCodes as $akad)
                        <option value="{{ $akad }}">{{ $akad }}</option>
                    @endforeach
                </select>
            </div>
            <div class="w-full sm:w-44">
                <label class="mb-1.5 block text-xs font-medium text-zinc-400">Klasifikasi</label>
                <select wire:model.live="filterClassification"
                        class="h-10 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 text-sm text-zinc-100 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">Semua Klasifikasi</option>
                    @foreach($classificationTypes as $ct)
                        <option value="{{ $ct->value }}">{{ $ct->label() }}</option>
                    @endforeach
                </select>
            </div>
            @if($filterPeriode || $filterUsageType || $filterClassification || $filterOfficeCode || $filterAkadCode)
                <div class="flex items-end pb-1">
                    <button wire:click="$set('filterPeriode',''); $set('filterUsageType',''); $set('filterClassification',''); $set('filterOfficeCode',''); $set('filterAkadCode','');"
                            class="inline-flex h-10 items-center gap-1 rounded-lg border border-zinc-700 bg-zinc-900 px-3 text-xs font-medium text-zinc-400 shadow-sm transition hover:bg-zinc-800/50">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        Reset filter
                    </button>
                </div>
            @endif
        </div>
    </div>

    {{-- Tabel --}}
    @if ($showTable)
        {{-- Search --}}
        <div class="mb-3">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari periode atau nama debitur…"
                   class="w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-1.5 text-sm text-zinc-100 shadow-sm placeholder-zinc-500 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 sm:w-72" />
        </div>
    @endif

    <div class="overflow-hidden rounded-xl border border-zinc-700 bg-zinc-900 shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-800 text-sm">
                <thead class="bg-zinc-800/50">
                    <tr>
                        <th class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold text-zinc-400">Periode</th>
                        <th class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold text-zinc-400">Kantor</th>
                        <th class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold text-zinc-400">Akad</th>
                        <th class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold text-zinc-400">Nama Debitur</th>
                        <th class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold text-zinc-400">Jenis Penggunaan</th>
                        <th class="whitespace-nowrap px-4 py-3 text-center text-xs font-semibold text-zinc-400">Klasifikasi</th>
                        <th class="whitespace-nowrap px-4 py-3 text-right text-xs font-semibold text-zinc-400">Outstanding</th>
                        <th class="whitespace-nowrap px-4 py-3 text-center text-xs font-semibold text-zinc-400">Kolektibilitas</th>
                        <th class="whitespace-nowrap px-4 py-3 text-center text-xs font-semibold text-zinc-400">Status Pembiayaan</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-400">Alasan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800">
                    @forelse($classifications as $row)
                        @php
                            $classColor = match($row->classification?->value) {
                                'individual' => 'bg-amber-950/40 text-amber-300',
                                'collective' => 'bg-sky-100 text-sky-700',
                                default      => 'bg-zinc-800 text-zinc-400',
                            };
                        @endphp
                        <tr class="transition-colors hover:bg-zinc-800/50">
                            <td class="whitespace-nowrap px-4 py-3 font-mono text-zinc-100">{{ $row->period }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-zinc-300">{{ $row->office_code ?? '-' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-zinc-300">{{ $row->akad_code ?? '-' }}</td>
                            <td class="px-4 py-3 text-zinc-300">{{ $row->financingAccount?->customer_name ?? '-' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-zinc-400">{{ $row->usage_type?->label() ?? '-' }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium {{ $classColor }}">
                                    {{ $row->classification?->label() ?? '-' }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-zinc-100">
                                {{ number_format((float) $row->outstanding_balance, 0, ',', '.') }}
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-center text-zinc-300">{{ $row->collectibility ?? '-' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-center text-xs text-zinc-400">{{ $row->financing_status?->value ?? '-' }}</td>
                            <td class="max-w-xs truncate px-4 py-3 text-xs text-zinc-400" title="{{ $row->classification_reason }}">
                                {{ $row->classification_reason ?? '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-zinc-500">
                                Belum ada data klasifikasi.
                                @if($filterPeriode || $filterUsageType || $filterClassification)
                                    <br><span class="text-xs">Coba ubah filter atau jalankan ClassifyPeriodDataJob.</span>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($classifications->hasPages())
            <div class="border-t border-zinc-800 px-4 py-3">
                {{ $classifications->links() }}
            </div>
        @endif
    </div>
@else
    {{-- Empty state awal (belum pilih periode / belum klik Tampil) --}}
    <div class="rounded-xl border-2 border-dashed border-zinc-700 bg-zinc-900 px-6 py-16 text-center">
        <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-zinc-800">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-6 w-6 text-zinc-500">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
            </svg>
        </div>
        <p class="text-sm font-medium text-zinc-400">Belum ada data yang ditampilkan</p>
        <p class="mt-1 text-xs text-zinc-500">
            Pilih periode penetapan di atas, lalu klik <span class="font-medium text-emerald-600">Tampil</span>
            untuk melihat hasil klasifikasi periode tersebut.
        </p>
    </div>
@endif

    {{-- Modal Konfirmasi Klasifikasi --}}
    <div x-show="$wire.confirmingAction === 'klasifikasi'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" wire:click="$set('confirmingAction', '')"></div>
        <div class="relative w-full max-w-sm rounded-xl border border-zinc-800 bg-zinc-900 p-6 shadow-2xl">
            <h3 class="text-sm font-semibold text-zinc-100">Konfirmasi Klasifikasi</h3>
            <p class="mt-2 text-xs text-zinc-400">Yakin ingin menjalankan klasifikasi pembiayaan untuk periode yang dipilih? Data akan diperbarui.</p>
            <div class="mt-5 flex gap-2">
                <button wire:click="$set('confirmingAction', '')" class="flex-1 rounded-lg border border-zinc-700 bg-zinc-800/80 px-4 py-2 text-xs font-medium text-zinc-300 transition hover:bg-zinc-700/60">Batal</button>
                <button wire:click="klasifikasikan" class="flex-1 rounded-lg bg-blue-600 px-4 py-2 text-xs font-medium text-white transition hover:bg-blue-500">Ya, Klasifikasikan</button>
            </div>
        </div>
    </div>

    {{-- Modal Konfirmasi Hapus Klasifikasi --}}
    <div x-show="$wire.confirmingAction === 'hapus'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" wire:click="$set('confirmingAction', '')"></div>
        <div class="relative w-full max-w-sm rounded-xl border border-zinc-800 bg-zinc-900 p-6 shadow-2xl">
            <div class="flex items-start gap-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-rose-500/30 bg-rose-950/60 text-rose-400">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-zinc-100">Konfirmasi Hapus Klasifikasi</h3>
                    <p class="mt-1 text-xs text-zinc-400">Yakin ingin menghapus klasifikasi periode <span class="font-semibold text-zinc-200">{{ $classificationPeriod }}</span>? Data klasifikasi akan dihapus dan periode kembali ke status belum diklasifikasi.</p>
                </div>
            </div>
            <div class="mt-5 flex gap-2">
                <button wire:click="$set('confirmingAction', '')" class="flex-1 rounded-lg border border-zinc-700 bg-zinc-800/80 px-4 py-2 text-xs font-medium text-zinc-300 transition hover:bg-zinc-700/60">Batal</button>
                <button wire:click="hapusKlasifikasi" class="flex-1 rounded-lg bg-rose-600 px-4 py-2 text-xs font-medium text-white transition hover:bg-rose-500">Ya, Hapus</button>
            </div>
        </div>
    </div>
</div>
