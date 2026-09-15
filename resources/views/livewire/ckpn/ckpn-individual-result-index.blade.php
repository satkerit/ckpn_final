<div>
    {{-- Header --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-base font-semibold text-zinc-100">CKPN Individual</h2>
            <p class="mt-0.5 text-xs text-zinc-400">Hitung dan tampilkan hasil CKPN Individual per debitur. Ref: PRD Bab 6.1</p>
        </div>
        @if($showTable)
            <div class="flex items-center gap-4 rounded-xl border border-zinc-700 bg-zinc-900 px-4 py-2.5 shadow-sm">
                <div>
                    <p class="text-xs font-medium text-zinc-400">Total CKPN</p>
                    <p class="mt-0.5 font-mono text-sm font-semibold text-emerald-700">
                        Rp {{ number_format($totalCkpn, 0, ',', '.') }}
                    </p>
                </div>
                <div class="h-8 w-px bg-zinc-700"></div>
                <div>
                    <p class="text-xs font-medium text-zinc-400">Total Outstanding</p>
                    <p class="mt-0.5 font-mono text-sm font-semibold text-zinc-100">
                        Rp {{ number_format($totalOutstanding, 0, ',', '.') }}
                    </p>
                </div>
            </div>
        @endif
    </div>

    {{-- Panel Kontrol --}}
    <div class="mb-6 rounded-xl border border-zinc-700 bg-zinc-900 p-5 shadow-sm">
        <div class="flex flex-col gap-4">
            {{-- Header panel + badge status --}}
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-zinc-100">Periode Perhitungan CKPN Individual</h3>
                    <p class="mt-0.5 text-xs text-zinc-400">Pilih periode, tampilkan data, lalu jalankan perhitungan.</p>
                </div>
                @if($individualPeriod !== '')
                    @if($periodCalculated)
                        <span class="inline-flex w-fit items-center gap-1.5 rounded-full border border-emerald-800/60 bg-emerald-950/60 px-3 py-1 text-xs font-medium text-emerald-300">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                            Sudah dihitung
                        </span>
                    @elseif($periodClassified)
                        <span class="inline-flex w-fit items-center gap-1.5 rounded-full border border-amber-800/60 bg-amber-950/60 px-3 py-1 text-xs font-medium text-amber-300">
                            <span class="h-1.5 w-1.5 rounded-full bg-amber-400"></span>
                            Siap dihitung
                        </span>
                    @else
                        <span class="inline-flex w-fit items-center gap-1.5 rounded-full border border-zinc-700 bg-zinc-800 px-3 py-1 text-xs font-medium text-zinc-300">
                            <span class="h-1.5 w-1.5 rounded-full bg-zinc-400"></span>
                            Belum diklasifikasi
                        </span>
                    @endif
                @endif
            </div>

            {{-- Form: periode + tombol aksi --}}
            <div class="flex flex-col gap-4 border-t border-zinc-800 pt-4 md:flex-row md:items-end md:justify-between">
                <div class="w-full md:w-72">
                    <label for="individual-period" class="mb-1.5 block text-xs font-medium text-zinc-300">Periode Penetapan</label>
                    <select id="individual-period" wire:model.live="individualPeriod"
                            class="h-10 w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 text-sm text-zinc-200 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                        <option value="">Pilih periode</option>
                        @foreach($periods as $p)
                            <option value="{{ $p }}">{{ $p }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex flex-wrap items-end gap-2">
                    @if($individualPeriod !== '')
                        @if($periodClassified)
                            {{-- Tombol Tampil (selalu ada jika sudah diklasifikasi) --}}
                            <button wire:click="tampilkan"
                                    class="inline-flex h-10 items-center gap-1.5 rounded-lg bg-emerald-600 px-4 text-sm font-medium text-white shadow-sm transition hover:bg-emerald-700">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                Tampil
                            </button>
                            {{-- Tombol Hitung --}}
                            <button wire:click="confirmHitung"
                                    class="inline-flex h-10 items-center gap-1.5 rounded-lg bg-amber-500 px-4 text-sm font-medium text-white shadow-sm transition hover:bg-amber-600">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                                </svg>
                                Hitung CKPN Individual
                            </button>
                            {{-- Tombol Hapus Periode (hanya muncul jika sudah ada hasil) --}}
                            @if($periodCalculated)
                                <button wire:click="konfirmasiHapusPeriode"
                                        class="inline-flex h-10 items-center gap-1.5 rounded-lg border border-rose-800/80 bg-rose-950/40 px-4 text-sm font-medium text-rose-300 shadow-sm transition hover:bg-rose-900/50">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                    </svg>
                                    Hapus Periode
                                </button>
                            @endif
                        @else
                            <p class="text-xs text-zinc-400 italic">Klasifikasikan data terlebih dahulu di menu Klasifikasi.</p>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        {{-- Status run (polling saat job berjalan) --}}
        @if($isRunning && $runLogs->count() > 0)
            <div class="mt-3 space-y-1" wire:poll.3s>
                @foreach($runLogs as $log)
                    @php
                        $statusColor = match($log->status) {
                            \App\Enums\RunStatus::Completed  => 'text-emerald-700 bg-emerald-50',
                            \App\Enums\RunStatus::Failed     => 'text-red-700 bg-red-50',
                            \App\Enums\RunStatus::Processing => 'text-blue-700 bg-blue-50',
                            default                          => 'text-zinc-400 bg-zinc-800',
                        };
                    @endphp
                    <div class="flex items-center justify-between rounded-md px-3 py-1.5 text-xs {{ $statusColor }}">
                        <span class="font-medium">{{ $log->usage_type?->label() ?? $log->usage_type }}</span>
                        <span>{{ $log->status->value }}</span>
                        @if($log->error_message)
                            <span class="ml-2 max-w-xs truncate text-red-600" title="{{ $log->error_message }}">
                                {{ Str::limit($log->error_message, 60) }}
                            </span>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Flash message --}}
    @if($flashMessage)
        <div class="mb-4 rounded-lg px-4 py-2.5 text-sm {{ $flashType === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200' }}">
            {{ $flashMessage }}
        </div>
    @endif

    {{-- Panel Info Catatan Perhitungan --}}
    @php $completedLogsIndv = $runLogs->filter(fn($l) => $l->notes); @endphp
    @if($completedLogsIndv->isNotEmpty())
        <div class="mb-4 rounded-xl border border-violet-200 bg-violet-50 p-4 shadow-sm">
            <h3 class="mb-2 text-sm font-semibold text-violet-800">Informasi Perhitungan CKPN Individual</h3>
            <div class="space-y-3">
                @foreach($completedLogsIndv as $log)
                    <div class="rounded-lg border border-violet-100 bg-zinc-900 p-3">
                        <div class="mb-1 flex items-center gap-2">
                            <span class="inline-flex items-center rounded-full bg-violet-100 px-2 py-0.5 text-xs font-semibold text-violet-800">
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

    {{-- Tabel (hanya tampil setelah klik Tampil) --}}
    @if($showTable)

        {{-- Search --}}
        <div class="mb-3">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari periode atau nama debitur…"
                   class="w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-1.5 text-sm text-zinc-100 shadow-sm placeholder-zinc-500 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 sm:w-72" />
        </div>

        {{-- Toolbar bulk delete --}}
        <div class="mb-3 flex items-center gap-3">
            @if(count($selectedIds) > 0)
                <button
                    wire:click="konfirmasiBulkHapus"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-red-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm hover:bg-red-700 transition-colors"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                    </svg>
                    Hapus {{ count($selectedIds) }} Terpilih
                </button>
            @endif
        </div>

        <div class="overflow-hidden rounded-xl border border-zinc-700 bg-zinc-900 shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-800 text-sm">
                    <thead class="bg-zinc-800/50">
                        <tr>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-zinc-400 w-10">
                                <input type="checkbox" wire:model.live="selectAll"
                                       class="rounded border-zinc-700 text-primary-600 focus:ring-primary-500" />
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-400">Periode</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-zinc-400">Nama Debitur</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-zinc-400">Outstanding</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-zinc-400">Nilai Agunan</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-zinc-400">% Biaya Jual</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-zinc-400">Biaya Jual</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-zinc-400">CKPN Amount</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-zinc-400">Kolektibilitas</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-zinc-400">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        @forelse($results as $row)
                            <tr class="hover:bg-zinc-800/50 transition-colors {{ in_array((string) $row->id, $selectedIds) ? 'bg-primary-50' : '' }}">
                                <td class="px-4 py-3 text-center">
                                    <input type="checkbox" wire:model.live="selectedIds" value="{{ $row->id }}"
                                           class="rounded border-zinc-700 text-primary-600 focus:ring-primary-500" />
                                </td>
                                <td class="px-4 py-3 font-mono text-zinc-100">{{ $row->calculation_period }}</td>
                                <td class="px-4 py-3 text-zinc-300">{{ $row->financingAccount?->customer_name ?? '-' }}</td>
                                <td class="px-4 py-3 text-right font-mono text-zinc-100">
                                    {{ number_format((float) $row->outstanding_balance, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-zinc-100">
                                    {{ number_format((float) $row->total_collateral_liquidation_value, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-zinc-400">
                                    {{ number_format((float) $row->selling_cost_rate * 100, 2) }}%
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-zinc-100">
                                    {{ number_format((float) $row->selling_cost_amount, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-semibold text-zinc-100">
                                    {{ number_format((float) $row->ckpn_amount, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 text-center text-zinc-300">{{ $row->collectibility }}</td>
                                <td class="px-4 py-3 text-center">
                                    <button wire:click="konfirmasiHapus({{ $row->id }})"
                                            class="rounded-md p-1.5 text-zinc-500 hover:bg-red-50 hover:text-red-600 transition-colors"
                                            title="Hapus baris ini">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-4 py-10 text-center text-zinc-500">
                                    Belum ada data hasil CKPN Individual untuk periode ini.
                                    @if(! $periodCalculated)
                                        <br><span class="text-xs">Jalankan perhitungan menggunakan tombol "Hitung CKPN Individual" di atas.</span>
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
    @endif

    {{-- Modal Konfirmasi Hitung --}}
    <div x-show="$wire.confirmingAction === 'hitung'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="w-full max-w-sm rounded-xl border border-zinc-800 bg-zinc-900 p-6 shadow-2xl">
            <h3 class="font-semibold text-zinc-100 mb-2">Konfirmasi Perhitungan</h3>
            <p class="text-sm text-zinc-300 mb-4">Yakin ingin menjalankan perhitungan CKPN Individual untuk periode <span class="font-semibold text-white">{{ $individualPeriod }}</span>? Proses akan dimasukkan ke antrean.</p>
            <div class="flex gap-2 justify-end">
                <button wire:click="$set('confirmingAction', '')" class="px-3.5 py-1.5 text-xs font-medium rounded-lg border border-zinc-700 bg-zinc-800 text-zinc-300 hover:bg-zinc-700">Batal</button>
                <button wire:click="jalankanPerhitungan" class="px-3.5 py-1.5 text-xs font-semibold rounded-lg bg-amber-500 text-zinc-950 hover:bg-amber-400">Ya, Jalankan</button>
            </div>
        </div>
    </div>

    {{-- Modal Konfirmasi Hapus (single) --}}
    <div x-show="$wire.confirmingAction === 'hapus'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="w-full max-w-sm rounded-xl border border-zinc-800 bg-zinc-900 p-6 shadow-2xl">
            <h3 class="text-sm font-semibold text-zinc-100">Konfirmasi Hapus</h3>
            <p class="mt-2 text-sm text-zinc-300">Yakin ingin menghapus baris ini? Snapshot yang sudah Completed tidak dapat dihapus.</p>
            <div class="mt-4 flex gap-2">
                <button wire:click="batalHapus" class="flex-1 rounded-lg border border-zinc-700 bg-zinc-800 px-4 py-2 text-xs font-medium text-zinc-300 transition hover:bg-zinc-700">Batal</button>
                <button wire:click="hapus" class="flex-1 rounded-lg bg-rose-600 px-4 py-2 text-xs font-semibold text-white hover:bg-rose-500 transition-colors">Ya, Hapus</button>
            </div>
        </div>
    </div>

    {{-- Modal Konfirmasi Bulk Hapus --}}
    <div x-show="$wire.confirmingAction === 'bulk_hapus'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="w-full max-w-sm rounded-xl border border-zinc-800 bg-zinc-900 p-6 shadow-2xl">
            <h3 class="text-sm font-semibold text-zinc-100">Konfirmasi Hapus Massal</h3>
            <p class="mt-2 text-sm text-zinc-300">Yakin ingin menghapus <span class="font-semibold text-white">{{ count($selectedIds) }}</span> baris terpilih? Baris dengan status Completed tidak dapat dihapus.</p>
            <div class="mt-4 flex gap-2">
                <button wire:click="batalBulkHapus" class="flex-1 rounded-lg border border-zinc-700 bg-zinc-800 px-4 py-2 text-xs font-medium text-zinc-300 transition hover:bg-zinc-700">Batal</button>
                <button wire:click="bulkHapus" class="flex-1 rounded-lg bg-rose-600 px-4 py-2 text-xs font-semibold text-white hover:bg-rose-500 transition-colors">Ya, Hapus</button>
            </div>
        </div>
    </div>

    {{-- Modal Konfirmasi Hapus Periode --}}
    <div x-show="$wire.confirmingAction === 'hapus_periode'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="w-full max-w-sm rounded-xl border border-zinc-800 bg-zinc-900 p-6 shadow-2xl">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-rose-800/60 bg-rose-950/60">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5 text-rose-400">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-zinc-100">Hapus Semua Data Periode</h3>
                    <p class="mt-0.5 text-xs text-zinc-400">Periode: <span class="font-semibold text-zinc-200">{{ $individualPeriod }}</span></p>
                </div>
            </div>
            <p class="mt-3 text-sm text-zinc-300">Semua hasil CKPN Individual untuk periode ini akan dihapus permanen. Anda dapat menghitung ulang setelahnya.</p>
            <div class="mt-4 flex gap-2">
                <button wire:click="$set('confirmingAction', '')" class="flex-1 rounded-lg border border-zinc-700 bg-zinc-800 px-4 py-2 text-xs font-medium text-zinc-300 transition hover:bg-zinc-700">Batal</button>
                <button wire:click="hapusPeriode" class="flex-1 rounded-lg bg-rose-600 px-4 py-2 text-xs font-semibold text-white transition hover:bg-rose-500">Ya, Hapus Semua</button>
            </div>
        </div>
    </div>
</div>
