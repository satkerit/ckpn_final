<div>
    {{-- Header --}}
    <div class="mb-6">
        <h2 class="text-base font-semibold text-zinc-100">Parameter Kalkulasi</h2>
        <p class="mt-0.5 text-xs text-zinc-400">
            Penentuan kolom tabel (EAD/PD/CKPN), rentang data PD &amp; LGD, dan segmentasi bertingkat.
            Ref: PRD Bab 5, 7, 8, 9, 10, 15
        </p>
    </div>

    {{-- Notifikasi --}}
    @if($flashMessage !== '')
        <div class="mb-4 flex items-start gap-2 rounded-lg border px-4 py-3 text-sm
            {{ $flashType === 'error' ? 'border-rose-800/60 bg-rose-950/40 text-rose-300' : 'border-emerald-800/60 bg-emerald-950/40 text-emerald-300' }}">
            <span class="flex-1">{{ $flashMessage }}</span>
            <button wire:click="$set('flashMessage','')" class="text-xs opacity-70 transition hover:opacity-100">Tutup</button>
        </div>
    @endif

    {{-- Sub-tab --}}
    <div class="mb-4 flex flex-wrap gap-1 rounded-lg border border-zinc-700 bg-zinc-900 p-1">
        @foreach([
            'column_config' => 'Kolom Tabel',
            'data_range' => 'Rentang Data PD/LGD',
            'segmentation' => 'Segmentasi Bertingkat',
            'general' => 'Parameter Umum',
        ] as $key => $label)
            <button wire:click="$set('activeSubTab','{{ $key }}')"
                    class="rounded-md px-3 py-1.5 text-xs font-medium transition
                        {{ $activeSubTab === $key ? 'bg-zinc-800 text-zinc-100 shadow-sm' : 'text-zinc-400 hover:text-zinc-200' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if($activeSubTab !== 'segmentation')
        <div class="mb-3">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Cari parameter…"
                   class="w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-1.5 text-sm text-zinc-100 shadow-sm placeholder-zinc-500 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 sm:w-72" />
        </div>
    @endif

    {{-- ============================================================ --}}
    {{-- SUB-TAB: KOLOM TABEL --}}
    {{-- ============================================================ --}}
    @if($activeSubTab === 'column_config')
        <div class="mb-4 flex items-center justify-between">
            <p class="text-xs text-zinc-400">Kolom tabel sumber per POKPBY / akad_code untuk EAD, dasar PD, dan dasar CKPN.</p>
            <button wire:click="tambahColumnConfig"
                    class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-blue-600 px-3 text-xs font-medium text-white shadow-sm transition hover:bg-blue-700">
                + Tambah Kolom
            </button>
        </div>

        <div class="overflow-hidden rounded-xl border border-zinc-700 bg-zinc-900 shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-800 text-sm">
                    <thead class="bg-zinc-800/50">
                        <tr>
                            <th class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold text-zinc-400">Topik</th>
                            <th class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold text-zinc-400">POKPBY</th>
                            <th class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold text-zinc-400">Label</th>
                            <th class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold text-zinc-400">Tabel Sumber</th>
                            <th class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold text-zinc-400">Kolom</th>
                            <th class="whitespace-nowrap px-4 py-3 text-center text-xs font-semibold text-zinc-400">Jatuh Tempo</th>
                            <th class="whitespace-nowrap px-4 py-3 text-center text-xs font-semibold text-zinc-400">Status</th>
                            <th class="whitespace-nowrap px-4 py-3 text-right text-xs font-semibold text-zinc-400">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        @forelse($columnConfigs as $cfg)
                            <tr class="transition-colors hover:bg-zinc-800/50">
                                <td class="whitespace-nowrap px-4 py-3 text-zinc-300">{{ $cfg->methodLabel() }}</td>
                                <td class="whitespace-nowrap px-4 py-3 font-mono text-zinc-100">{{ $cfg->pokpby_code }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-zinc-400">{{ $cfg->pokpby_label ?? '-' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-zinc-400">{{ $cfg->source_table }}</td>
                                <td class="whitespace-nowrap px-4 py-3 font-mono text-emerald-300">{{ $cfg->column_name }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-center">
                                    @if($cfg->require_maturity)
                                        <span class="rounded-full bg-amber-950/40 px-2 py-0.5 text-xs font-medium text-amber-300">Ya</span>
                                    @else
                                        <span class="text-xs text-zinc-500">Tidak</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-center">
                                    <button wire:click="toggleColumnConfigStatus({{ $cfg->id }})"
                                            class="rounded-full px-2 py-0.5 text-xs font-medium transition
                                                {{ $cfg->is_active ? 'bg-emerald-950/40 text-emerald-300 hover:bg-emerald-900/60' : 'bg-zinc-800 text-zinc-400 hover:bg-zinc-700' }}">
                                        {{ $cfg->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </button>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    <button wire:click="editColumnConfig({{ $cfg->id }})" class="text-xs font-medium text-blue-400 transition hover:text-blue-300">Ubah</button>
                                    <button wire:click="konfirmasiHapus({{ $cfg->id }}, 'column_config')" class="ml-3 text-xs font-medium text-rose-400 transition hover:text-rose-300">Hapus</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-10 text-center text-zinc-500">Belum ada konfigurasi kolom.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($columnConfigs->hasPages())
                <div class="border-t border-zinc-800 px-4 py-3">{{ $columnConfigs->links() }}</div>
            @endif
        </div>
    @endif

    {{-- ============================================================ --}}
    {{-- SUB-TAB: RENTANG DATA PD/LGD --}}
    {{-- ============================================================ --}}
    @if($activeSubTab === 'data_range')
        <div class="mb-4 flex items-center justify-between">
            <p class="text-xs text-zinc-400">Rentang data per metode. Baris global (segmen kosong) berlaku sebagai fallback; baris bersegmen menimpa global.</p>
            <button wire:click="tambahDataRange"
                    class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-blue-600 px-3 text-xs font-medium text-white shadow-sm transition hover:bg-blue-700">
                + Tambah Rentang
            </button>
        </div>

        <div class="overflow-hidden rounded-xl border border-zinc-700 bg-zinc-900 shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-800 text-sm">
                    <thead class="bg-zinc-800/50">
                        <tr>
                            <th class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold text-zinc-400">Metode</th>
                            <th class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold text-zinc-400">Parameter</th>
                            <th class="whitespace-nowrap px-4 py-3 text-right text-xs font-semibold text-zinc-400">Nilai</th>
                            <th class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold text-zinc-400">Segmen</th>
                            <th class="whitespace-nowrap px-4 py-3 text-center text-xs font-semibold text-zinc-400">Status</th>
                            <th class="whitespace-nowrap px-4 py-3 text-right text-xs font-semibold text-zinc-400">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        @forelse($dataRanges as $range)
                            <tr class="transition-colors hover:bg-zinc-800/50">
                                <td class="whitespace-nowrap px-4 py-3 text-zinc-300">{{ $range->methodLabel() }}</td>
                                <td class="px-4 py-3 text-zinc-400">
                                    {{ $rangeCatalog[$range->range_key]['label'] ?? $range->range_key }}
                                    <span class="ml-1 font-mono text-xs text-zinc-600">{{ $range->range_key }}</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-zinc-100">
                                    {{ $range->range_value }}
                                    <span class="ml-1 text-xs text-zinc-500">{{ $range->range_unit }}</span>
                                </td>
                                <td class="px-4 py-3 text-xs text-zinc-400">{{ $range->segmentLabel() }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-center">
                                    <button wire:click="toggleDataRangeStatus({{ $range->id }})"
                                            class="rounded-full px-2 py-0.5 text-xs font-medium transition
                                                {{ $range->is_active ? 'bg-emerald-950/40 text-emerald-300 hover:bg-emerald-900/60' : 'bg-zinc-800 text-zinc-400 hover:bg-zinc-700' }}">
                                        {{ $range->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </button>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    <button wire:click="editDataRange({{ $range->id }})" class="text-xs font-medium text-blue-400 transition hover:text-blue-300">Ubah</button>
                                    <button wire:click="konfirmasiHapus({{ $range->id }}, 'data_range')" class="ml-3 text-xs font-medium text-rose-400 transition hover:text-rose-300">Hapus</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-zinc-500">Belum ada rentang data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($dataRanges->hasPages())
                <div class="border-t border-zinc-800 px-4 py-3">{{ $dataRanges->links() }}</div>
            @endif
        </div>
    @endif

    {{-- ============================================================ --}}
    {{-- SUB-TAB: SEGMENTASI BERTINGKAT --}}
    {{-- ============================================================ --}}
    @if($activeSubTab === 'segmentation')
        <div class="mb-4 flex items-center justify-between">
            <p class="text-xs text-zinc-400">Urutan level segmentasi dari terluar ke terdalam. Nilai segmen dapat ditambahkan per level.</p>
            <button wire:click="tambahSegmentation"
                    class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-blue-600 px-3 text-xs font-medium text-white shadow-sm transition hover:bg-blue-700">
                + Tambah Level
            </button>
        </div>

        <div class="space-y-3">
            @forelse($segmentationLevels as $level)
                <div class="rounded-xl border border-zinc-700 bg-zinc-900 p-4 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-zinc-800 font-mono text-sm font-semibold text-zinc-300">
                                {{ $level->level_order }}
                            </span>
                            <div>
                                <p class="text-sm font-medium text-zinc-100">{{ $level->label }}</p>
                                <p class="font-mono text-xs text-zinc-500">{{ $level->segment_type->value }}</p>
                            </div>
                            <button wire:click="toggleSegmentationStatus({{ $level->id }})"
                                    class="rounded-full px-2 py-0.5 text-xs font-medium transition
                                        {{ $level->is_active ? 'bg-emerald-950/40 text-emerald-300 hover:bg-emerald-900/60' : 'bg-zinc-800 text-zinc-400 hover:bg-zinc-700' }}">
                                {{ $level->is_active ? 'Aktif' : 'Nonaktif' }}
                            </button>
                            @if($level->allow_global_fallback)
                                <span class="rounded-full bg-sky-950/40 px-2 py-0.5 text-xs font-medium text-sky-300">Fallback Global</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-3">
                            <button wire:click="editSegmentation({{ $level->id }})" class="text-xs font-medium text-blue-400 transition hover:text-blue-300">Ubah</button>
                            <button wire:click="konfirmasiHapus({{ $level->id }}, 'segmentation')" class="text-xs font-medium text-rose-400 transition hover:text-rose-300">Hapus</button>
                        </div>
                    </div>

                    @if($level->values->isNotEmpty())
                        <div class="mt-3 flex flex-wrap gap-1.5 border-t border-zinc-800 pt-3">
                            @foreach($level->values as $value)
                                <span class="rounded-md bg-zinc-800 px-2 py-0.5 font-mono text-xs text-zinc-300">{{ $value->value }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>
            @empty
                <div class="rounded-xl border-2 border-dashed border-zinc-700 bg-zinc-900 px-6 py-12 text-center text-sm text-zinc-500">
                    Belum ada level segmentasi.
                </div>
            @endforelse
        </div>
    @endif

    {{-- ============================================================ --}}
    {{-- SUB-TAB: PARAMETER UMUM --}}
    {{-- ============================================================ --}}
    @if($activeSubTab === 'general')
        <div class="mb-4">
            <p class="text-xs text-zinc-400">Parameter umum berskala tunggal (Top-N, batas NPL, metode PD default, biaya penjualan jaminan, dsb.).</p>
        </div>

        <div class="overflow-hidden rounded-xl border border-zinc-700 bg-zinc-900 shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-800 text-sm">
                    <thead class="bg-zinc-800/50">
                        <tr>
                            <th class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold text-zinc-400">Kategori</th>
                            <th class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold text-zinc-400">Label</th>
                            <th class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold text-zinc-400">Key</th>
                            <th class="whitespace-nowrap px-4 py-3 text-right text-xs font-semibold text-zinc-400">Nilai</th>
                            <th class="whitespace-nowrap px-4 py-3 text-right text-xs font-semibold text-zinc-400">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        @forelse($generalSettings as $set)
                            <tr class="transition-colors hover:bg-zinc-800/50">
                                <td class="whitespace-nowrap px-4 py-3 text-xs uppercase text-zinc-400">{{ $set->category }}</td>
                                <td class="px-4 py-3 text-zinc-200">
                                    {{ $set->label }}
                                    @if($set->description)
                                        <p class="mt-0.5 text-xs text-zinc-500">{{ $set->description }}</p>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-zinc-500">{{ $set->setting_key }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-emerald-300">{{ $set->setting_value }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    <button wire:click="editSetting({{ $set->id }})" class="text-xs font-medium text-blue-400 transition hover:text-blue-300">Ubah</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-10 text-center text-zinc-500">Belum ada parameter umum.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ============================================================ --}}
    {{-- MODAL: COLUMN CONFIG --}}
    {{-- ============================================================ --}}
    @if($showColumnConfigModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" wire:click="$set('showColumnConfigModal', false)"></div>
            <div class="relative w-full max-w-lg rounded-xl border border-zinc-800 bg-zinc-900 p-6 shadow-2xl">
                <h3 class="text-sm font-semibold text-zinc-100">{{ $editingColumnConfigId ? 'Ubah' : 'Tambah' }} Konfigurasi Kolom</h3>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-zinc-400">Topik</label>
                        <select wire:model="colMethod" class="h-10 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 text-sm text-zinc-100 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                            @foreach($parameterMethods as $pm)
                                <option value="{{ $pm->value }}">{{ $pm->label() }}</option>
                            @endforeach
                        </select>
                        @error('colMethod') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-zinc-400">POKPBY / Akad Code</label>
                        <input type="text" wire:model="colPokpbyCode" placeholder="mis. 10"
                               class="h-10 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 text-sm text-zinc-100 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                        @error('colPokpbyCode') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-zinc-400">Label POKPBY</label>
                        <input type="text" wire:model="colPokpbyLabel" placeholder="mis. Khusus (tgkmdl)"
                               class="h-10 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 text-sm text-zinc-100 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-zinc-400">Tabel Sumber</label>
                        <select wire:model.live="colSourceTable" class="h-10 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 text-sm text-zinc-100 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                            @foreach(array_keys($availableColumns) as $table)
                                <option value="{{ $table }}">{{ $table }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-xs font-medium text-zinc-400">Kolom</label>
                        <select wire:model="colColumnName" class="h-10 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 text-sm text-zinc-100 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                            @foreach($availableColumns[$colSourceTable] ?? [] as $col)
                                <option value="{{ $col }}">{{ $col }}</option>
                            @endforeach
                        </select>
                        @error('colColumnName') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-xs font-medium text-zinc-400">Catatan</label>
                        <input type="text" wire:model="colNotes"
                               class="h-10 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 text-sm text-zinc-100 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                    </div>
                    <label class="flex items-center gap-2 text-sm text-zinc-300">
                        <input type="checkbox" wire:model="colRequireMaturity" class="h-4 w-4 rounded border-zinc-600 bg-zinc-800 text-primary-600" />
                        Wajib sudah jatuh tempo
                    </label>
                    <label class="flex items-center gap-2 text-sm text-zinc-300">
                        <input type="checkbox" wire:model="colIsActive" class="h-4 w-4 rounded border-zinc-600 bg-zinc-800 text-primary-600" />
                        Aktif
                    </label>
                </div>
                <div class="mt-6 flex gap-2">
                    <button wire:click="$set('showColumnConfigModal', false)" class="flex-1 rounded-lg border border-zinc-700 bg-zinc-800/80 px-4 py-2 text-xs font-medium text-zinc-300 transition hover:bg-zinc-700/60">Batal</button>
                    <button wire:click="simpanColumnConfig" class="flex-1 rounded-lg bg-blue-600 px-4 py-2 text-xs font-medium text-white transition hover:bg-blue-500">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ============================================================ --}}
    {{-- MODAL: DATA RANGE --}}
    {{-- ============================================================ --}}
    @if($showDataRangeModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" wire:click="$set('showDataRangeModal', false)"></div>
            <div class="relative w-full max-w-2xl rounded-xl border border-zinc-800 bg-zinc-900 p-6 shadow-2xl">
                <h3 class="text-sm font-semibold text-zinc-100">{{ $editingDataRangeId ? 'Ubah' : 'Tambah' }} Rentang Data</h3>
                
                {{-- Guidance Card --}}
                @php
                    $guidance = $rangeCatalog[$rangeKey]['guidance'] ?? null;
                    $recommendedValues = $rangeCatalog[$rangeKey]['recommended_values'] ?? null;
                @endphp
                @if($guidance || $recommendedValues)
                    <div class="mb-4 mt-3 rounded-lg border border-sky-800/40 bg-sky-950/30 p-3">
                        @if($guidance)
                            <p class="text-xs text-sky-300">📌 <strong>Panduan:</strong> {{ $guidance }}</p>
                        @endif
                        @if($recommendedValues)
                            <p class="mt-1.5 text-xs text-sky-300"><strong>Nilai Rekomendasi:</strong> {{ implode(', ', $recommendedValues) }}</p>
                        @endif
                    </div>
                @endif
                
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-zinc-400">Metode</label>
                        <select wire:model.live="rangeMethod" class="h-10 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 text-sm text-zinc-100 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                            @foreach($calculationMethods as $cm)
                                <option value="{{ $cm->value }}">{{ $cm->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-zinc-400">Parameter</label>
                        <select wire:model.live="rangeKey" class="h-10 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 text-sm text-zinc-100 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                            @foreach($this->rangeCatalogForMethod($rangeMethod) as $key => $meta)
                                <option value="{{ $key }}">{{ $meta['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-zinc-400">Nilai</label>
                        <input type="text" wire:model="rangeValue"
                               class="h-10 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 text-sm text-zinc-100 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                        @error('rangeValue') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-zinc-400">Satuan</label>
                        <input type="text" wire:model="rangeUnit"
                               class="h-10 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 text-sm text-zinc-100 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                    </div>
                    <div class="sm:col-span-2 border-t border-zinc-800 pt-3">
                        <p class="text-xs text-zinc-500">Segmentasi bertingkat — kosongkan untuk nilai global (fallback).</p>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-zinc-400">Kode Kantor</label>
                        <input type="text" wire:model="rangeOfficeCode" placeholder="mis. 001"
                               class="h-10 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 text-sm text-zinc-100 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-zinc-400">Jenis Penggunaan</label>
                        <select wire:model="rangeUsageType" class="h-10 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 text-sm text-zinc-100 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                            <option value="">Semua</option>
                            @foreach($usageTypes as $ut)
                                <option value="{{ $ut->value }}">{{ $ut->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-zinc-400">Akad Code</label>
                        <input type="text" wire:model="rangeAkadCode" placeholder="mis. 03"
                               class="h-10 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 text-sm text-zinc-100 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                    </div>
                    <label class="flex items-center gap-2 self-end text-sm text-zinc-300">
                        <input type="checkbox" wire:model="rangeIsActive" class="h-4 w-4 rounded border-zinc-600 bg-zinc-800 text-primary-600" />
                        Aktif
                    </label>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-xs font-medium text-zinc-400">Catatan</label>
                        <input type="text" wire:model="rangeNotes"
                               class="h-10 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 text-sm text-zinc-100 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                    </div>
                    <div class="sm:col-span-2 border-t border-zinc-800 pt-3">
                        <p class="text-xs text-zinc-500">Justifikasi & Rekomendasi — opsional, untuk audit trail dokumentasi.</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-xs font-medium text-zinc-400">Alasan Perubahan (Justifikasi)</label>
                        <textarea wire:model="rangeJustificationNotes" rows="3"
                               class="w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-sm text-zinc-100 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
                               placeholder="Contoh: Perubahan dari 36 ke 60 bulan untuk meningkatkan stabilitas PD sesuai rekomendasi Risk Committee."></textarea>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-xs font-medium text-zinc-400">Nilai Rekomendasi (CSV)</label>
                        <input type="text" wire:model="rangeRecommendationValues" placeholder="mis. 12,24,36,48,60"
                               class="h-10 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 text-sm text-zinc-100 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                        <p class="mt-1 text-xs text-zinc-500">Nilai alternatif yang disarankan untuk parameter ini.</p>
                    </div>
                </div>
                <div class="mt-6 flex gap-2">
                    <button wire:click="$set('showDataRangeModal', false)" class="flex-1 rounded-lg border border-zinc-700 bg-zinc-800/80 px-4 py-2 text-xs font-medium text-zinc-300 transition hover:bg-zinc-700/60">Batal</button>
                    <button wire:click="simpanDataRange" class="flex-1 rounded-lg bg-blue-600 px-4 py-2 text-xs font-medium text-white transition hover:bg-blue-500">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ============================================================ --}}
    {{-- MODAL: SEGMENTATION LEVEL --}}
    {{-- ============================================================ --}}
    @if($showSegmentationModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" wire:click="$set('showSegmentationModal', false)"></div>
            <div class="relative w-full max-w-md rounded-xl border border-zinc-800 bg-zinc-900 p-6 shadow-2xl">
                <h3 class="text-sm font-semibold text-zinc-100">{{ $editingSegmentationId ? 'Ubah' : 'Tambah' }} Level Segmentasi</h3>
                <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-zinc-400">Urutan Level</label>
                        <input type="number" min="1" max="10" wire:model="segLevelOrder"
                               class="h-10 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 text-sm text-zinc-100 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                        @error('segLevelOrder') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-zinc-400">Jenis Segmen</label>
                        <select wire:model="segType" class="h-10 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 text-sm text-zinc-100 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                            @foreach($segmentTypes as $st)
                                <option value="{{ $st->value }}">{{ $st->label() }}</option>
                            @endforeach
                        </select>
                        @error('segType') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-xs font-medium text-zinc-400">Label</label>
                        <input type="text" wire:model="segLabel"
                               class="h-10 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 text-sm text-zinc-100 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                        @error('segLabel') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                    </div>
                    <label class="flex items-center gap-2 text-sm text-zinc-300">
                        <input type="checkbox" wire:model="segIsActive" class="h-4 w-4 rounded border-zinc-600 bg-zinc-800 text-primary-600" />
                        Aktif
                    </label>
                    <label class="flex items-center gap-2 text-sm text-zinc-300">
                        <input type="checkbox" wire:model="segAllowGlobalFallback" class="h-4 w-4 rounded border-zinc-600 bg-zinc-800 text-primary-600" />
                        Izinkan fallback global
                    </label>
                </div>
                <div class="mt-6 flex gap-2">
                    <button wire:click="$set('showSegmentationModal', false)" class="flex-1 rounded-lg border border-zinc-700 bg-zinc-800/80 px-4 py-2 text-xs font-medium text-zinc-300 transition hover:bg-zinc-700/60">Batal</button>
                    <button wire:click="simpanSegmentation" class="flex-1 rounded-lg bg-blue-600 px-4 py-2 text-xs font-medium text-white transition hover:bg-blue-500">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ============================================================ --}}
    {{-- MODAL: GENERAL SETTING --}}
    {{-- ============================================================ --}}
    @if($showSettingModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" wire:click="$set('showSettingModal', false)"></div>
            <div class="relative w-full max-w-md rounded-xl border border-zinc-800 bg-zinc-900 p-6 shadow-2xl">
                <h3 class="text-sm font-semibold text-zinc-100">Ubah Parameter Umum</h3>
                <p class="mt-1 font-mono text-xs text-zinc-500">{{ $settingKey }}</p>
                <div class="mt-4 space-y-4">
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-zinc-400">Label</label>
                        <input type="text" wire:model="settingLabel"
                               class="h-10 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 text-sm text-zinc-100 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                        @error('settingLabel') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-zinc-400">Nilai</label>
                        <input type="text" wire:model="settingValue"
                               class="h-10 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 text-sm text-zinc-100 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                        @error('settingValue') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-zinc-400">Deskripsi</label>
                        <input type="text" wire:model="settingDescription"
                               class="h-10 w-full rounded-lg border border-zinc-700 bg-zinc-900 px-3 text-sm text-zinc-100 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500" />
                    </div>
                </div>
                <div class="mt-6 flex gap-2">
                    <button wire:click="$set('showSettingModal', false)" class="flex-1 rounded-lg border border-zinc-700 bg-zinc-800/80 px-4 py-2 text-xs font-medium text-zinc-300 transition hover:bg-zinc-700/60">Batal</button>
                    <button wire:click="simpanSetting" class="flex-1 rounded-lg bg-blue-600 px-4 py-2 text-xs font-medium text-white transition hover:bg-blue-500">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ============================================================ --}}
    {{-- MODAL: KONFIRMASI HAPUS --}}
    {{-- ============================================================ --}}
    @if($deletingId !== null)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" wire:click="$set('deletingId', null)"></div>
            <div class="relative w-full max-w-sm rounded-xl border border-zinc-800 bg-zinc-900 p-6 shadow-2xl">
                <div class="flex items-start gap-3">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-rose-500/30 bg-rose-950/60 text-rose-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-zinc-100">Konfirmasi Hapus</h3>
                        <p class="mt-1 text-xs text-zinc-400">Yakin ingin menghapus parameter ini? Tindakan tidak dapat dibatalkan.</p>
                    </div>
                </div>
                <div class="mt-5 flex gap-2">
                    <button wire:click="$set('deletingId', null)" class="flex-1 rounded-lg border border-zinc-700 bg-zinc-800/80 px-4 py-2 text-xs font-medium text-zinc-300 transition hover:bg-zinc-700/60">Batal</button>
                    <button wire:click="hapus" class="flex-1 rounded-lg bg-rose-600 px-4 py-2 text-xs font-medium text-white transition hover:bg-rose-500">Ya, Hapus</button>
                </div>
            </div>
        </div>
    @endif
</div>
