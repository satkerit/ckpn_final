<div>
    {{-- Header + Tombol Buat --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-base font-semibold text-zinc-100">Periode CKPN</h2>
            <p class="mt-0.5 text-xs text-zinc-400">Kelola periode penilaian CKPN (buat, edit, ubah status, hapus). Ref: PRD Bab 12a Step 2</p>
        </div>
        <button
            wire:click="buatBaru"
            class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm hover:bg-primary-700 transition-colors"
        >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            Buat Periode
        </button>
    </div>

    {{-- Flash message --}}
    @if($flashMessage)
        <div class="mb-4 rounded-lg px-4 py-2.5 text-sm {{ $flashType === 'success' ? 'bg-emerald-950/60 text-emerald-300 border border-emerald-700/60' : 'bg-rose-950/60 text-rose-300 border border-rose-700/60' }}">
            {{ $flashMessage }}
        </div>
    @endif

    {{-- Form Buat/Edit --}}
    @if($showForm)
        <div class="mb-6 rounded-xl border border-zinc-800 bg-zinc-900 p-5 shadow-xl">
            <h3 class="mb-4 text-sm font-semibold text-zinc-100">
                {{ $editingId ? 'Edit Periode' : 'Buat Periode Baru' }}
            </h3>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-zinc-300 mb-1">
                        Periode <span class="text-rose-400">*</span>
                    </label>
                    <select wire:model="formPeriod" {{ $editingId ? 'disabled' : '' }}
                            class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs font-medium text-zinc-200 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 disabled:bg-zinc-900 disabled:text-zinc-500 disabled:border-zinc-800 @error('formPeriod') border-rose-500 @enderror">
                        <option value="">-- Pilih Periode --</option>
                        @foreach($availablePeriods as $p)
                            <option value="{{ $p }}">{{ $p }}</option>
                        @endforeach
                    </select>
                    @if($editingId)
                        <p class="mt-1 text-xs text-zinc-400">Periode tidak dapat diubah setelah disimpan.</p>
                    @endif
                    @error('formPeriod') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-zinc-300 mb-1">Status <span class="text-rose-400">*</span></label>
                    <select wire:model="formStatus"
                            class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs font-medium text-zinc-200 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 @error('formStatus') border-rose-500 @enderror">
                        <option value="draft">Draft</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="approved">Approved</option>
                    </select>
                    @error('formStatus') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-zinc-300 mb-1">Metode PD (Kolektif)</label>
                    <select wire:model="formPdMethod"
                            class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs font-medium text-zinc-200 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 @error('formPdMethod') border-rose-500 @enderror">
                        <option value="">-- Belum ditentukan --</option>
                        @foreach($pdMethods as $method)
                            <option value="{{ $method->value }}">{{ $method->label() }}</option>
                        @endforeach
                    </select>
                    @error('formPdMethod') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-zinc-300 mb-1">Catatan</label>
                    <input type="text" wire:model="formNotes" placeholder="Opsional"
                           class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-zinc-200 placeholder-zinc-500 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 @error('formNotes') border-rose-500 @enderror" />
                    @error('formNotes') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="mt-4 flex gap-2">
                <button wire:click="simpan"
                        wire:loading.attr="disabled"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2 text-xs font-medium text-white shadow-sm hover:bg-primary-500 disabled:opacity-60 transition-colors">
                    <span wire:loading.remove wire:target="simpan">Simpan</span>
                    <span wire:loading wire:target="simpan">Menyimpan...</span>
                </button>
                <button wire:click="batalForm"
                        class="rounded-lg border border-zinc-700 bg-zinc-800/80 px-4 py-2 text-xs font-medium text-zinc-300 shadow-sm hover:bg-zinc-700/60 transition-colors">
                    Batal
                </button>
            </div>
        </div>
    @endif

    {{-- Modal Konfirmasi Hapus --}}
    @if($deletingId)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" wire:click="$set('deletingId', null)"></div>
            <div class="relative w-full max-w-sm rounded-xl border border-zinc-800 bg-zinc-900 p-6 shadow-2xl">
                <div class="flex items-start gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-rose-500/30 bg-rose-950/60 text-rose-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-zinc-100">Konfirmasi Hapus</h3>
                        <p class="mt-1 text-xs text-zinc-400">Yakin ingin menghapus periode ini? Aksi ini tidak dapat dibatalkan.</p>
                    </div>
                </div>
                <div class="mt-5 flex gap-2">
                    <button wire:click="hapus"
                            class="flex-1 rounded-lg bg-rose-600 px-4 py-2 text-xs font-medium text-white hover:bg-rose-500 transition-colors">
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

    {{-- Tabel --}}
    {{-- Search --}}
    <div class="mb-3">
        <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari periode, status, metode PD, atau catatan…"
               class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-zinc-200 shadow-sm placeholder-zinc-500 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 sm:w-80" />
    </div>

    <div class="overflow-hidden rounded-xl border border-zinc-700 bg-zinc-900 shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-800 text-sm">
                <thead class="bg-zinc-800/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-400">Periode</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-zinc-400">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-400">Metode PD</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-zinc-400">Terklasifikasi</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-zinc-400">Total Outstanding / EAD</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-400">Dibuat Oleh</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-400">Disetujui Oleh</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-400">Catatan</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-zinc-400">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800">
                    @forelse($periods as $period)
                        @php
                            $statusBadge = match($period->status) {
                                'draft'       => 'bg-zinc-800 text-zinc-400 border border-zinc-700',
                                'in_progress' => 'bg-blue-950/60 text-blue-300 border border-blue-800/60',
                                'completed'   => 'bg-emerald-950/60 text-emerald-300 border border-emerald-800/60',
                                'approved'    => 'bg-violet-950/60 text-violet-300 border border-violet-800/60',
                                default       => 'bg-zinc-800 text-zinc-400 border border-zinc-700',
                            };
                            $statusLabel = match($period->status) {
                                'draft'       => 'Draft',
                                'in_progress' => 'In Progress',
                                'completed'   => 'Completed',
                                'approved'    => 'Approved',
                                default       => $period->status,
                            };
                        @endphp
                        <tr class="hover:bg-zinc-800/50 transition-colors">
                            <td class="px-4 py-3 font-mono font-semibold text-zinc-100">{{ $period->period }}</td>
                            <td class="px-4 py-3 text-center">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $statusBadge }}">
                                    {{ $statusLabel }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-zinc-300">{{ $period->pd_method?->label() ?? '-' }}</td>
                            <td class="px-4 py-3 text-center">
                                @if($period->is_classified)
                                    <span class="inline-flex rounded-full bg-emerald-950/60 px-2 py-0.5 text-xs font-medium text-emerald-300 border border-emerald-800/60">Ya</span>
                                @else
                                    <span class="text-zinc-500 text-xs">Belum</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-zinc-100">
                                Rp {{ number_format((float) ($outstandingByPeriod[$period->period] ?? 0), 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3 text-zinc-400 text-xs">{{ $period->createdBy?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-zinc-400 text-xs">{{ $period->approvedBy?->name ?? '-' }}</td>
                            <td class="px-4 py-3 text-zinc-400 text-xs max-w-xs truncate" title="{{ $period->notes }}">
                                {{ $period->notes ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    {{-- Edit --}}
                                    @unless($period->isApproved())
                                        <button wire:click="edit({{ $period->id }})"
                                                class="rounded-md border border-zinc-700 bg-zinc-800/80 px-2 py-1 text-xs text-zinc-300 hover:bg-zinc-700/60 transition-colors">
                                            Edit
                                        </button>
                                    @endunless

                                    {{-- Ubah Status --}}
                                    @if($period->status === 'draft')
                                        <button wire:click="ubahStatus({{ $period->id }}, 'in_progress')"
                                                class="rounded-md border border-blue-700/50 bg-blue-950/60 px-2 py-1 text-xs font-medium text-blue-300 hover:bg-blue-900/60 transition-colors">
                                            Mulai
                                        </button>
                                    @elseif($period->status === 'in_progress')
                                        <button wire:click="ubahStatus({{ $period->id }}, 'completed')"
                                                class="rounded-md border border-emerald-700/50 bg-emerald-950/60 px-2 py-1 text-xs font-medium text-emerald-300 hover:bg-emerald-900/60 transition-colors">
                                            Selesai
                                        </button>
                                    @elseif($period->status === 'completed')
                                        <button wire:click="ubahStatus({{ $period->id }}, 'approved')"
                                                class="rounded-md border border-violet-700/50 bg-violet-950/60 px-2 py-1 text-xs font-medium text-violet-300 hover:bg-violet-900/60 transition-colors">
                                            Approve
                                        </button>
                                    @endif

                                    {{-- Hapus --}}
                                    @unless($period->isApproved())
                                        <button wire:click="konfirmasiHapus({{ $period->id }})"
                                                class="rounded-md border border-rose-700/50 bg-rose-950/60 px-2 py-1 text-xs font-medium text-rose-300 hover:bg-rose-900/60 transition-colors">
                                            Hapus
                                        </button>
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-10 text-center text-zinc-500">
                                Belum ada periode CKPN. Klik "Buat Periode" untuk memulai.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($periods->hasPages())
            <div class="border-t border-zinc-800 px-4 py-3">
                {{ $periods->links() }}
            </div>
        @endif
    </div>
</div>
