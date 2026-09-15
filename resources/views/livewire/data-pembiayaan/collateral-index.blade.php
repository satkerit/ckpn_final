{{-- resources/views/livewire/data-pembiayaan/collateral-index.blade.php --}}
{{-- Ref: PRD Bab 10 - Data Jaminan (Collateral) --}}
<div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-base font-semibold text-zinc-100">Data Jaminan</h2>
            <p class="mt-1 text-sm text-zinc-400">Daftar seluruh agunan/collateral per akun pembiayaan.</p>
        </div>
        @if($collaterals->total() > 0)
            <button wire:click="konfirmasiHapusSemua"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-rose-800/80 bg-rose-950/40 px-3 py-2 text-xs font-medium text-rose-300 shadow-sm transition hover:bg-rose-900/60">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                </svg>
                Hapus Semua Jaminan
            </button>
        @endif
    </div>

    {{-- Filters --}}
    <div class="mb-4 flex flex-wrap gap-3">
        <div class="relative flex-1 min-w-[200px]">
            <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
            </svg>
            <input
                type="text"
                wire:model.live.debounce.400ms="search"
                placeholder="Cari kode jaminan / deskripsi / no. kontrak / nasabah..."
                class="w-full rounded-lg border border-zinc-700 bg-zinc-950 pl-8 pr-3 py-2 text-xs text-zinc-200 placeholder-zinc-500 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
            />
        </div>

        <select wire:model.live="filterType"
                class="rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-zinc-200 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
            <option value="">Semua Jenis Jaminan</option>
            @foreach ($collateralTypes as $ct)
                <option value="{{ $ct->id }}">{{ $ct->name }}</option>
            @endforeach
        </select>

        <select wire:model.live="filterActive"
                class="rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-zinc-200 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
            <option value="">Semua Status</option>
            <option value="1">Aktif</option>
            <option value="0">Tidak Aktif</option>
        </select>
    </div>

    {{-- Table --}}
    <div class="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-900 shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="border-b border-zinc-800 bg-zinc-800/50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-zinc-400 uppercase tracking-wide">Kode Jaminan</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-zinc-400 uppercase tracking-wide">No. Urut</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-zinc-400 uppercase tracking-wide">No. Kontrak</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-zinc-400 uppercase tracking-wide">Deskripsi</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-zinc-400 uppercase tracking-wide">Jenis Jaminan</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-zinc-400 uppercase tracking-wide">Nilai Appraisal</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold text-zinc-400 uppercase tracking-wide">Est. Nilai Jual</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-zinc-400 uppercase tracking-wide">Tgl Appraisal</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-zinc-400 uppercase tracking-wide">Status</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-zinc-400 uppercase tracking-wide">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800">
                    @forelse ($collaterals as $col)
                        <tr class="hover:bg-zinc-800/50 transition-colors">
                            <td class="px-5 py-3 font-mono text-xs text-zinc-100">{{ $col->collateral_code }}</td>
                            <td class="px-5 py-3 text-center text-xs text-zinc-400">{{ $col->sequence_number ?? '-' }}</td>
                            <td class="px-5 py-3 font-mono text-xs text-zinc-300">
                                {{ $col->financingAccount?->account_number ?? '-' }}
                            </td>
                            <td class="px-5 py-3 text-zinc-300 text-xs max-w-[200px] truncate" title="{{ $col->description }}">
                                {{ $col->description ?? '-' }}
                            </td>
                            <td class="px-5 py-3 text-zinc-400 text-xs">
                                {{ $col->collateralType?->name ?? '-' }}
                            </td>
                            <td class="px-5 py-3 text-right tabular-nums text-xs text-zinc-300">
                                {{ $col->appraisal_value !== null ? number_format((float) $col->appraisal_value, 0, ',', '.') : '-' }}
                            </td>
                            <td class="px-5 py-3 text-right tabular-nums text-xs text-zinc-300">
                                {{ $col->estimated_sale_value !== null ? number_format((float) $col->estimated_sale_value, 0, ',', '.') : '-' }}
                            </td>
                            <td class="px-5 py-3 text-zinc-400 text-xs whitespace-nowrap">
                                {{ $col->appraised_at ?? '-' }}
                            </td>
                            <td class="px-5 py-3 text-center">
                                @if ($col->is_active)
                                    <span class="inline-flex rounded-full bg-emerald-950/40 text-emerald-300 px-2 py-0.5 text-xs font-medium border border-emerald-800/60">Aktif</span>
                                @else
                                    <span class="inline-flex rounded-full bg-zinc-800 px-2 py-0.5 text-xs font-medium text-zinc-400 border border-zinc-700">Nonaktif</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-center">
                                <button wire:click="konfirmasiHapus({{ $col->id }})"
                                        title="Hapus Jaminan"
                                        class="inline-flex items-center rounded-md border border-rose-800/60 bg-rose-950/40 p-1.5 text-rose-300 transition hover:bg-rose-900/60">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-3.5 w-3.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                    </svg>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-5 py-10 text-center text-zinc-500 text-sm">
                                @if ($search || $filterType || $filterActive)
                                    Tidak ada data yang sesuai dengan filter.
                                @else
                                    Belum ada data jaminan.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if ($collaterals->hasPages())
            <div class="border-t border-zinc-800 px-5 py-3">
                {{ $collaterals->links() }}
            </div>
        @endif
    </div>

    <p class="mt-3 text-xs text-zinc-500">
        Menampilkan {{ $collaterals->firstItem() ?? 0 }}–{{ $collaterals->lastItem() ?? 0 }}
        dari {{ number_format($collaterals->total()) }} jaminan
    </p>

    {{-- Modal Konfirmasi Hapus Single --}}
    @if($deletingId)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" wire:click="$set('deletingId', null)"></div>
            <div class="relative w-full max-w-sm rounded-xl border border-zinc-800 bg-zinc-900 p-6 shadow-2xl">
                <div class="flex items-start gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-rose-500/30 bg-rose-950/60 text-rose-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-zinc-100">Konfirmasi Hapus Jaminan</h3>
                        <p class="mt-1 text-xs text-zinc-400">Yakin ingin menghapus data agunan/jaminan ini? Tindakan ini tidak dapat dibatalkan.</p>
                    </div>
                </div>
                <div class="mt-5 flex gap-2">
                    <button wire:click="hapus"
                            class="flex-1 rounded-lg bg-rose-600 px-4 py-2 text-xs font-semibold text-white hover:bg-rose-500 transition-colors">
                        Ya, Hapus
                    </button>
                    <button wire:click="$set('deletingId', null)"
                            class="flex-1 rounded-lg border border-zinc-700 bg-zinc-800/80 px-4 py-2 text-xs font-medium text-zinc-300 hover:bg-zinc-700/60 transition-colors">
                        Batal
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Modal Konfirmasi Hapus Semua Data Jaminan --}}
    @if($confirmingDeleteAll)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" wire:click="$set('confirmingDeleteAll', false)"></div>
            <div class="relative w-full max-w-sm rounded-xl border border-zinc-800 bg-zinc-900 p-6 shadow-2xl">
                <div class="flex items-start gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-rose-500/30 bg-rose-950/60 text-rose-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-zinc-100">Hapus Semua Data Jaminan</h3>
                        <p class="mt-1 text-xs text-zinc-400">Yakin ingin menghapus <strong class="text-rose-400">seluruh ({{ number_format($collaterals->total()) }})</strong> data jaminan? Seluruh data agunan yang telah di-upload akan terhapus bersih.</p>
                    </div>
                </div>
                <div class="mt-5 flex gap-2">
                    <button wire:click="hapusSemuaData"
                            class="flex-1 rounded-lg bg-rose-600 px-4 py-2 text-xs font-semibold text-white hover:bg-rose-500 transition-colors">
                        Hapus Semua
                    </button>
                    <button wire:click="$set('confirmingDeleteAll', false)"
                            class="flex-1 rounded-lg border border-zinc-700 bg-zinc-800/80 px-4 py-2 text-xs font-medium text-zinc-300 hover:bg-zinc-700/60 transition-colors">
                        Batal
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
