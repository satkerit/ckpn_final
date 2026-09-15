<div class="min-h-screen bg-zinc-800/50">
    <div class="mb-6 border-b border-zinc-800 bg-zinc-900 px-6 py-4">
        <h2 class="text-base font-semibold text-zinc-100">Rekonsiliasi Klasifikasi vs Hasil CKPN</h2>
        <p class="mt-0.5 text-xs text-zinc-400">Membandingkan jumlah nasabah &amp; total EAD antara hasil klasifikasi dengan snapshot CKPN per segmen.</p>
    </div>

    <div class="px-6 py-4">
        @if(session('recon_success'))
            <div class="mb-4 rounded-lg border border-emerald-800/60 bg-emerald-950/40 px-4 py-3 text-sm text-emerald-300">
                {{ session('recon_success') }}
            </div>
        @endif

        @if(session('recon_error'))
            <div class="mb-4 rounded-lg border border-rose-800/60 bg-rose-950/40 px-4 py-3 text-sm text-rose-300">
                {{ session('recon_error') }}
            </div>
        @endif

        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-sm font-medium text-zinc-300">Hasil Rekonsiliasi</h3>
            <div class="flex items-center gap-2">
                <select wire:model="selectedReconPeriod"
                    class="rounded-md border border-zinc-700 bg-zinc-950 px-3 py-1.5 text-sm text-zinc-200 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">-- Pilih Periode --</option>
                    @foreach($availablePeriods as $p)
                        <option value="{{ $p }}">{{ $p }}</option>
                    @endforeach
                </select>
                <button wire:click="runReconciliation"
                    class="rounded-md bg-primary-600 px-4 py-1.5 text-sm font-medium text-white hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-1 font-semibold shadow-sm">
                    Jalankan Rekonsiliasi
                </button>
            </div>
        </div>

        @if($reconciliationData->isEmpty())
            <div class="rounded-lg border border-zinc-800 bg-zinc-900 px-6 py-10 text-center text-sm text-zinc-400">
                Belum ada data rekonsiliasi. Masukkan periode dan klik "Jalankan Rekonsiliasi".
            </div>
        @else
            <div class="overflow-x-auto rounded-lg border border-zinc-800 bg-zinc-900">
                <table class="min-w-full divide-y divide-zinc-800 text-sm">
                    <thead class="bg-zinc-800">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-400">Jenis Penggunaan</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-400">Klasifikasi</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-400">Jml Nasabah (Klas.)</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-400">Jml Nasabah (CKPN)</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-400">Selisih Jml</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-400">Total EAD (Klas.)</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-400">Total EAD (CKPN)</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-zinc-400">Selisih EAD</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-zinc-400">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800">
                        @foreach($reconciliationData as $row)
                            @php
                                $pass = $row->is_count_match && $row->is_ead_match;
                                $selisihJml = $row->count_classification - $row->count_ckpn_result;
                                $selisihEad = $row->total_ead_classification - $row->total_ead_ckpn_result;
                            @endphp
                            <tr class="hover:bg-zinc-800/50">
                                <td class="px-4 py-2.5 text-zinc-300">{{ $row->usage_type ?? '(semua)' }}</td>
                                <td class="px-4 py-2.5 text-zinc-300 capitalize">{{ $row->classification }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums text-zinc-300">{{ number_format($row->count_classification) }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums text-zinc-300">{{ number_format($row->count_ckpn_result) }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums {{ $selisihJml != 0 ? 'font-semibold text-rose-400' : 'text-zinc-300' }}">
                                    {{ number_format($selisihJml) }}
                                </td>
                                <td class="px-4 py-2.5 text-right tabular-nums text-zinc-300">{{ number_format($row->total_ead_classification, 2) }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums text-zinc-300">{{ number_format($row->total_ead_ckpn_result, 2) }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums {{ abs($selisihEad) >= 0.01 ? 'font-semibold text-rose-400' : 'text-zinc-300' }}">
                                    {{ number_format($selisihEad, 2) }}
                                </td>
                                <td class="px-4 py-2.5 text-center">
                                    @if($pass)
                                        <span class="inline-flex items-center rounded-full border border-emerald-800/60 bg-emerald-950/40 px-2.5 py-0.5 text-xs font-semibold text-emerald-300">PASS</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full border border-rose-800/60 bg-rose-950/40 px-2.5 py-0.5 text-xs font-semibold text-rose-300">FAIL</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
