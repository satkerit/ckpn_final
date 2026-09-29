<div class="min-w-0 text-zinc-100">
    {{-- Header --}}
    <div class="mb-6 overflow-hidden rounded-2xl border border-zinc-800 bg-zinc-950 px-5 py-6 text-white shadow-lg shadow-black/20 sm:px-7">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-sky-300">Risk analytics / PD Netflow</p>
                <h2 class="mt-2 text-2xl font-semibold tracking-tight">Detail Breakdown PD Netflow</h2>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-zinc-300">Pivot outstanding, transition rate, compound flow loss per kantor, akad, bucket, dan periode.</p>
            </div>
            <a href="{{ route('kalkulasi.pd.index') }}"
               class="inline-flex items-center justify-center gap-2 rounded-lg border border-white/20 bg-white/10 px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-white/20">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
                </svg>
                Kembali ke Hasil PD
            </a>
        </div>
    </div>

    {{-- Filter Panel --}}
    <div class="mb-6 rounded-xl border border-zinc-800 bg-zinc-900 p-5 shadow-sm">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
            <div class="sm:w-52">
                <label class="mb-1 block text-xs font-medium text-zinc-400">Run Log</label>
                <select wire:model="filterRunLogId" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm text-zinc-200 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">-- Pilih Run Log --</option>
                    @foreach($runLogs as $log)
                        <option value="{{ $log->id }}">RunLog #{{ $log->id }} - {{ $log->period }} - {{ $log->run_type }} - {{ $log->usage_type?->label() ?? '-' }} - {{ $log->office_code ?? 'all' }} - {{ $log->akad_code ?? 'all' }}</option>
                    @endforeach
                </select>
            </div>

            <div class="sm:w-48">
                <label class="mb-1 block text-xs font-medium text-zinc-400">Jenis Penggunaan</label>
                <select wire:model="filterUsageType" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm text-zinc-200 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">-- Semua --</option>
                    @foreach($usageTypes as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>

            <div class="sm:w-40">
                <label class="mb-1 block text-xs font-medium text-zinc-400">Kode Kantor</label>
                <select wire:model="filterOfficeCode" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm text-zinc-200 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">-- Semua --</option>
                    @foreach($officeCodes as $code)
                        <option value="{{ $code }}">{{ $code }}</option>
                    @endforeach
                </select>
            </div>

            <div class="sm:w-40">
                <label class="mb-1 block text-xs font-medium text-zinc-400">Kode Akad</label>
                <select wire:model="filterAkadCode" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm text-zinc-200 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">-- Semua --</option>
                    @foreach($akadCodes as $code)
                        <option value="{{ $code }}">{{ $code }}</option>
                    @endforeach
                </select>
            </div>

            <div class="sm:w-36">
                <label class="mb-1 block text-xs font-medium text-zinc-400">Bucket</label>
                <select wire:model="filterBucketId" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm text-zinc-200 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">-- Semua --</option>
                    @foreach($buckets as $bucket)
                        <option value="{{ $bucket->id }}">{{ $bucket->code }} — {{ $bucket->label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="sm:w-48">
                <label class="mb-1 block text-xs font-medium text-zinc-400">Periode</label>
                <select wire:model="filterPeriod" class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm text-zinc-200 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">-- Semua --</option>
                    @foreach($periods as $period)
                        <option value="{{ $period }}">{{ $period }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex gap-2 items-center flex-wrap">
                <button wire:click="exportExcel" wire:loading.attr="disabled" wire:target="exportExcel"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-4 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-emerald-700 transition-colors disabled:opacity-60">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    Export Excel
                </button>
            </div>
        </div>
    </div>

    {{-- Results Table --}}
    <div class="overflow-hidden rounded-xl border border-zinc-800 bg-zinc-900 shadow-sm">
        <div class="flex items-center justify-between px-4 py-3 border-b border-zinc-800">
            <div>
                <h3 class="text-sm font-semibold text-zinc-100">Detail Breakdown PD Netflow</h3>
                <p class="mt-1 text-xs text-zinc-400">Outstanding, transition rate, compound rate per kantor + akad + bucket + periode.</p>
            </div>
            <span class="text-xs text-zinc-500">{{ $records->total() }} record</span>
        </div>

        @if($records->isEmpty())
            <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-700 bg-zinc-900 py-16 text-center">
                <svg class="h-10 w-10 text-zinc-600" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 01-1.125-1.125M3.375 19.5h1.5C5.496 19.5 6 18.996 6 18.375m-3.75.125v-13.5A1.125 1.125 0 013.375 3.75h13.5A1.125 1.125 0 0118 4.875v13.5M6 18.375a1.125 1.125 0 001.125 1.125H18M6 18.375V6.375" />
                </svg>
                <p class="mt-3 text-sm font-medium text-zinc-400">Belum ada data</p>
                <p class="mt-1 text-xs text-zinc-500">Pilih Run Log atau filter untuk menampilkan detail breakdown.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead class="bg-zinc-800">
                        <tr>
                            <th class="sticky left-0 z-10 bg-zinc-800 px-3 py-2.5 text-left font-semibold whitespace-nowrap min-w-[60px]">RunLog</th>
                            <th class="px-3 py-2.5 text-left font-semibold whitespace-nowrap">Periode</th>
                            <th class="px-3 py-2.5 text-left font-semibold whitespace-nowrap">Jenis Penggunaan</th>
                            <th class="px-3 py-2.5 text-left font-semibold whitespace-nowrap">Kantor</th>
                            <th class="px-3 py-2.5 text-left font-semibold whitespace-nowrap">Akad</th>
                            <th class="px-3 py-2.5 text-left font-semibold whitespace-nowrap">Bucket</th>
                            <th class="px-3 py-2.5 text-center font-semibold whitespace-nowrap">Periode Data</th>
                            <th class="px-3 py-2.5 text-right font-semibold whitespace-nowrap">Outstanding</th>
                            <th class="px-3 py-2.5 text-right font-semibold whitespace-nowrap">Transition Rate</th>
                            <th class="px-3 py-2.5 text-right font-semibold whitespace-nowrap">Compound Rate</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        @forelse($records as $record)
                            <tr class="hover:bg-zinc-800/50 transition-colors">
                                <td class="sticky left-0 z-10 bg-zinc-900 px-3 py-2 font-mono text-zinc-300 whitespace-nowrap hover:bg-zinc-800">#{{ $record->calculation_run_log_id }}</td>
                                <td class="px-3 py-2 font-mono text-zinc-300 whitespace-nowrap">{{ $record->calculationRunLog->period ?? '-' }}</td>
                                <td class="px-3 py-2 text-zinc-300 whitespace-nowrap">{{ $record->usage_type ? \App\Enums\UsageType::from($record->usage_type)->label() : '-' }}</td>
                                <td class="px-3 py-2 text-zinc-300 whitespace-nowrap">{{ $record->office_code ?? 'Konsolidasi' }}</td>
                                <td class="px-3 py-2 text-zinc-300 whitespace-nowrap">{{ $record->akad_code ?? 'Konsolidasi' }}</td>
                                <td class="px-3 py-2 font-mono text-zinc-100 whitespace-nowrap">{{ $record->bucket->code ?? 'B'.$record->from_bucket_id }}</td>
                                <td class="px-3 py-2 text-center font-mono text-zinc-300 whitespace-nowrap">{{ $record->period }}</td>
                                <td class="px-3 py-2 text-right font-mono text-zinc-300 tabular-nums whitespace-nowrap">
                                    {{ $record->outstanding_balance > 0 ? number_format($record->outstanding_balance, 0, ',', '.') : '-' }}
                                </td>
                                <td class="px-3 py-2 text-right font-mono text-zinc-300 tabular-nums whitespace-nowrap">
                                    {{ $record->transition_rate !== null ? number_format(min(1, $record->transition_rate) * 100, 2).'%' : '-' }}
                                </td>
                                <td class="px-3 py-2 text-right font-mono text-zinc-300 tabular-nums whitespace-nowrap">
                                    {{ $record->compound_rate !== null ? number_format(min(1, $record->compound_rate) * 100, 2).'%' : '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-4 py-10 text-center text-zinc-500">Belum ada data detail breakdown.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($records->hasPages())
                <div class="border-t border-zinc-800 px-4 py-3">
                    {{ $records->links() }}
                </div>
            @endif
        @endif
    </div>
</div>