<div>
    {{-- Header --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-base font-semibold text-zinc-100">Master Bucket</h2>
            <p class="mt-0.5 text-xs text-zinc-400">Ref: PRD Bab 7 — definisi rentang hari tunggakan per bucket.</p>
        </div>
        <button wire:click="openCreate"
            class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-2 text-xs font-medium text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-1 transition-colors">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
            Tambah Bucket
        </button>
    </div>

    {{-- Search --}}
    <div class="mb-4">
        <div class="relative w-full sm:w-72">
            <svg class="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
            </svg>
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Cari kode atau label…"
                class="w-full rounded-lg border border-zinc-700 bg-zinc-900 py-2 pl-8 pr-3 text-xs text-zinc-300 placeholder-zinc-500 shadow-sm focus:border-primary-400 focus:outline-none focus:ring-1 focus:ring-primary-400"/>
        </div>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto rounded-xl border border-zinc-700 bg-zinc-900 shadow-sm">
        <table class="min-w-full divide-y divide-zinc-800 whitespace-nowrap text-xs">
            <thead class="bg-zinc-800/50">
                <tr>
                    <th class="px-4 py-3 text-left">
                        <button wire:click="sort('bucket_order')" class="flex items-center gap-1 font-medium text-zinc-400 hover:text-zinc-100">
                            Urutan
                            @if($sortField === 'bucket_order')
                                <svg class="h-3 w-3 {{ $sortDirection === 'asc' ? '' : 'rotate-180' }}" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5"/></svg>
                            @else
                                <svg class="h-3 w-3 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15 12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9"/></svg>
                            @endif
                        </button>
                    </th>
                    <th class="px-4 py-3 text-left">
                        <button wire:click="sort('code')" class="flex items-center gap-1 font-medium text-zinc-400 hover:text-zinc-100">
                            Kode
                            @if($sortField === 'code')
                                <svg class="h-3 w-3 {{ $sortDirection === 'asc' ? '' : 'rotate-180' }}" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5"/></svg>
                            @else
                                <svg class="h-3 w-3 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15 12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9"/></svg>
                            @endif
                        </button>
                    </th>
                    <th class="px-4 py-3 text-left">
                        <button wire:click="sort('label')" class="flex items-center gap-1 font-medium text-zinc-400 hover:text-zinc-100">
                            Label
                            @if($sortField === 'label')
                                <svg class="h-3 w-3 {{ $sortDirection === 'asc' ? '' : 'rotate-180' }}" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5"/></svg>
                            @else
                                <svg class="h-3 w-3 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15 12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9"/></svg>
                            @endif
                        </button>
                    </th>
                    <th class="px-4 py-3 text-left">
                        <button wire:click="sort('min_days_overdue')" class="flex items-center gap-1 font-medium text-zinc-400 hover:text-zinc-100">
                            Min Hari
                            @if($sortField === 'min_days_overdue')
                                <svg class="h-3 w-3 {{ $sortDirection === 'asc' ? '' : 'rotate-180' }}" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5"/></svg>
                            @else
                                <svg class="h-3 w-3 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15 12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9"/></svg>
                            @endif
                        </button>
                    </th>
                    <th class="px-4 py-3 text-left">
                        <button wire:click="sort('max_days_overdue')" class="flex items-center gap-1 font-medium text-zinc-400 hover:text-zinc-100">
                            Max Hari
                            @if($sortField === 'max_days_overdue')
                                <svg class="h-3 w-3 {{ $sortDirection === 'asc' ? '' : 'rotate-180' }}" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5"/></svg>
                            @else
                                <svg class="h-3 w-3 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15 12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9"/></svg>
                            @endif
                        </button>
                    </th>
                    <th class="px-4 py-3 text-center font-medium text-zinc-400">Default</th>
                    <th class="px-4 py-3 text-right font-medium text-zinc-400">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-800">
                @forelse($records as $record)
                    <tr class="hover:bg-zinc-800/50 transition-colors">
                        <td class="px-4 py-3 text-center font-mono text-zinc-400">{{ $record->bucket_order }}</td>
                        <td class="px-4 py-3 font-mono font-semibold text-zinc-100">{{ $record->code }}</td>
                        <td class="px-4 py-3 text-zinc-300">{{ $record->label }}</td>
                        <td class="px-4 py-3 text-zinc-300">{{ $record->min_days_overdue }}</td>
                        <td class="px-4 py-3 text-zinc-300">{{ $record->max_days_overdue ?? '∞' }}</td>
                        <td class="px-4 py-3 text-center">
                            @if($record->is_default_bucket)
                                <span class="inline-flex items-center rounded-full bg-emerald-900/40 px-2 py-0.5 text-xs font-medium text-emerald-300 ring-1 ring-inset ring-emerald-600/20">Ya</span>
                            @else
                                <span class="text-zinc-500">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button wire:click="openEdit({{ $record->id }})"
                                    class="rounded-md p-1 text-zinc-500 hover:bg-zinc-800 hover:text-zinc-300 transition-colors" title="Edit">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/></svg>
                                </button>
                                <button wire:click="confirmDelete({{ $record->id }})"
                                    class="rounded-md p-1 text-zinc-500 hover:bg-red-900/30 hover:text-red-400 transition-colors" title="Hapus">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-sm text-zinc-500">Belum ada data bucket.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($records->hasPages())
        <div class="mt-4">
            {{ $records->links() }}
        </div>
    @endif

    {{-- Modal Create/Edit --}}
    @if($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
         x-data x-on:keydown.escape.window="$wire.closeModal()">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" wire:click="closeModal"></div>
        <div class="relative w-full max-w-lg rounded-xl border border-zinc-800 bg-zinc-900 shadow-2xl">
            <div class="flex items-center justify-between border-b border-zinc-800 px-5 py-4">
                <h3 class="text-sm font-semibold text-zinc-100">{{ $editingId ? 'Edit Bucket' : 'Tambah Bucket' }}</h3>
                <button wire:click="closeModal" class="rounded-lg p-1 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200 transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form wire:submit="save" class="px-5 py-4 space-y-4">

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-zinc-300 mb-1">Kode <span class="text-rose-400">*</span></label>
                        <input wire:model="code" type="text" placeholder="mis. B1"
                            class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs font-mono text-zinc-200 placeholder-zinc-500 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 @error('code') border-rose-500 @enderror"/>
                        @error('code') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-zinc-300 mb-1">Urutan <span class="text-rose-400">*</span></label>
                        <input wire:model="bucket_order" type="number" min="1" placeholder="1"
                            class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-zinc-200 placeholder-zinc-500 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 @error('bucket_order') border-rose-500 @enderror"/>
                        @error('bucket_order') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-zinc-300 mb-1">Label <span class="text-rose-400">*</span></label>
                    <input wire:model="label" type="text" placeholder="mis. Bucket 1 — Lancar"
                        class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-zinc-200 placeholder-zinc-500 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 @error('label') border-rose-500 @enderror"/>
                    @error('label') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-zinc-300 mb-1">Min Hari Tunggakan <span class="text-rose-400">*</span></label>
                        <input wire:model="min_days_overdue" type="number" min="0" placeholder="0"
                            class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-zinc-200 placeholder-zinc-500 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 @error('min_days_overdue') border-rose-500 @enderror"/>
                        @error('min_days_overdue') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-zinc-300 mb-1">Max Hari Tunggakan <span class="text-zinc-400 font-normal">(kosong = tidak terbatas)</span></label>
                        <input wire:model="max_days_overdue" type="number" min="0" placeholder="∞"
                            class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-zinc-200 placeholder-zinc-500 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 @error('max_days_overdue') border-rose-500 @enderror"/>
                        @error('max_days_overdue') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <input wire:model="is_default_bucket" type="checkbox" id="is_default_bucket"
                        class="h-3.5 w-3.5 rounded border-zinc-700 bg-zinc-950 text-primary-600 focus:ring-primary-500"/>
                    <label for="is_default_bucket" class="text-xs text-zinc-300">Default bucket (akun tanpa tunggakan)</label>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-zinc-800">
                    <button type="button" wire:click="closeModal"
                        class="rounded-lg border border-zinc-700 px-4 py-2 text-xs font-medium text-zinc-300 hover:bg-zinc-800 hover:text-white transition-colors">
                        Batal
                    </button>
                    <button type="submit"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-xs font-medium text-white hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 focus:ring-offset-zinc-900 transition-colors">
                        <span wire:loading.remove wire:target="save">{{ $editingId ? 'Simpan Perubahan' : 'Tambah' }}</span>
                        <span wire:loading wire:target="save">Menyimpan…</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    @endif

    {{-- Delete Confirm Modal --}}
    @if($showDeleteConfirm)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
         x-data x-on:keydown.escape.window="$wire.closeModal()">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" wire:click="closeModal"></div>
        <div class="relative w-full max-w-sm rounded-xl border border-zinc-800 bg-zinc-900 shadow-2xl">
            <div class="px-5 py-5">
                <div class="flex items-start gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-rose-500/30 bg-rose-950/60 text-rose-400">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-zinc-100">Hapus Bucket?</h3>
                        <p class="mt-1 text-xs text-zinc-400">Data yang dihapus tidak dapat dikembalikan. Pastikan bucket ini tidak digunakan oleh data pembiayaan aktif.</p>
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-end gap-2">
                    <button wire:click="closeModal"
                        class="rounded-lg border border-zinc-700 px-4 py-2 text-xs font-medium text-zinc-300 hover:bg-zinc-800 hover:text-white transition-colors">
                        Batal
                    </button>
                    <button wire:click="delete"
                        class="rounded-lg bg-rose-600 px-4 py-2 text-xs font-medium text-white hover:bg-rose-500 transition-colors">
                        <span wire:loading.remove wire:target="delete">Ya, Hapus</span>
                        <span wire:loading wire:target="delete">Menghapus…</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
