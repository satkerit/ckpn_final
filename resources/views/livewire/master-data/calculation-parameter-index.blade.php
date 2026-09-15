<div>
    {{-- Header --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-base font-semibold text-zinc-100">Parameter Kalkulasi</h2>
            <p class="mt-0.5 text-xs text-zinc-400">Ref: PRD Bab 15 — konfigurasi parameter perhitungan per usage type.</p>
        </div>
        <button wire:click="openCreate"
            class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-2 text-xs font-medium text-white shadow-sm hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-1 transition-colors">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
            Tambah Parameter
        </button>
    </div>

    {{-- Search --}}
    <div class="mb-4">
        <div class="relative w-full sm:w-72">
            <svg class="absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
            </svg>
            <input wire:model.live.debounce.300ms="search" type="text" placeholder="Cari key, nilai, deskripsi…"
                class="w-full rounded-lg border border-zinc-700 bg-zinc-900 py-2 pl-8 pr-3 text-xs text-zinc-300 placeholder-zinc-500 shadow-sm focus:border-primary-400 focus:outline-none focus:ring-1 focus:ring-primary-400"/>
        </div>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto rounded-xl border border-zinc-700 bg-zinc-900 shadow-sm">
        <table class="min-w-full divide-y divide-zinc-800 whitespace-nowrap text-xs">
            <thead class="bg-zinc-800/50">
                <tr>
                    <th class="px-4 py-3 text-left">
                        <button wire:click="sort('parameter_key')" class="flex items-center gap-1 font-medium text-zinc-400 hover:text-zinc-100">
                            Parameter Key
                            @if($sortField === 'parameter_key')
                                <svg class="h-3 w-3 {{ $sortDirection === 'asc' ? '' : 'rotate-180' }}" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5"/></svg>
                            @else
                                <svg class="h-3 w-3 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15 12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9"/></svg>
                            @endif
                        </button>
                    </th>
                    <th class="px-4 py-3 text-left">
                        <button wire:click="sort('usage_type')" class="flex items-center gap-1 font-medium text-zinc-400 hover:text-zinc-100">
                            Usage Type
                            @if($sortField === 'usage_type')
                                <svg class="h-3 w-3 {{ $sortDirection === 'asc' ? '' : 'rotate-180' }}" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5"/></svg>
                            @else
                                <svg class="h-3 w-3 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 15 12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9"/></svg>
                            @endif
                        </button>
                    </th>
                    <th class="px-4 py-3 text-left font-medium text-zinc-400">Nilai</th>
                    <th class="px-4 py-3 text-left font-medium text-zinc-400">Deskripsi</th>
                    <th class="px-4 py-3 text-right font-medium text-zinc-400">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-800">
                @forelse($records as $record)
                    <tr class="hover:bg-zinc-800/50 transition-colors">
                        <td class="px-4 py-3 font-mono text-zinc-100">{{ $record->parameter_key }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-md bg-blue-950/40 px-2 py-0.5 text-xs font-medium text-blue-300 ring-1 ring-inset ring-blue-600/20">
                                {{ $record->usage_type?->label() ?? 'Global' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-zinc-300">{{ $record->parameter_value }}</td>
                        <td class="px-4 py-3 text-zinc-400 max-w-xs truncate">{{ $record->description ?? '—' }}</td>
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
                        <td colspan="5" class="px-4 py-10 text-center text-sm text-zinc-500">Belum ada data parameter.</td>
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
                <h3 class="text-sm font-semibold text-zinc-100">
                    {{ $editingId ? 'Edit Parameter' : 'Tambah Parameter' }}
                </h3>
                <button wire:click="closeModal" class="rounded-lg p-1 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200 transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <form wire:submit="save" class="px-5 py-4 space-y-4">

                <div>
                    <label class="block text-xs font-medium text-zinc-300 mb-1">Usage Type</label>
                    <select wire:model="usage_type"
                        class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-zinc-200 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 @error('usage_type') border-rose-500 @enderror">
                        <option value="">Global (semua usage type)</option>
                        @foreach($usageTypes as $type)
                            <option value="{{ $type->value }}">{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    @error('usage_type') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-zinc-300 mb-1">Parameter Key <span class="text-rose-400">*</span></label>
                    <input wire:model="parameter_key" type="text" placeholder="mis. rolling_window_months"
                        class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs font-mono text-zinc-200 placeholder-zinc-500 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 @error('parameter_key') border-rose-500 @enderror"/>
                    @error('parameter_key') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-zinc-300 mb-1">Nilai Parameter <span class="text-rose-400">*</span></label>
                    <input wire:model="parameter_value" type="text" placeholder="mis. 12"
                        class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-zinc-200 placeholder-zinc-500 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 @error('parameter_value') border-rose-500 @enderror"/>
                    @error('parameter_value') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs font-medium text-zinc-300 mb-1">Deskripsi</label>
                    <textarea wire:model="description" rows="2" placeholder="Keterangan singkat parameter ini…"
                        class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-zinc-200 placeholder-zinc-500 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 @error('description') border-rose-500 @enderror"></textarea>
                    @error('description') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
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
                        <h3 class="text-sm font-semibold text-zinc-100">Hapus Parameter?</h3>
                        <p class="mt-1 text-xs text-zinc-400">Data yang dihapus tidak dapat dikembalikan.</p>
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
