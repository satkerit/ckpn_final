<div class="space-y-6">
{{-- Stats cards --}}
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    {{-- Periode Aktif --}}
    <div class="rounded-xl bg-zinc-900 p-5 shadow-sm border border-zinc-800">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-zinc-400">Periode Aktif</p>
                <p class="mt-1 text-2xl font-bold text-zinc-100">{{ $activePeriods }}</p>
                <p class="mt-1 text-xs text-zinc-500">Draft / In Progress</p>
            </div>
            <div class="flex h-10 w-10 items-center justify-center rounded-lg {{ $activePeriods > 0 ? 'bg-amber-100 text-amber-600' : 'bg-zinc-800 text-zinc-500' }}">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
        </div>
    </div>

    {{-- Periode Approved --}}
    <div class="rounded-xl bg-zinc-900 p-5 shadow-sm border border-zinc-800">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-zinc-400">Periode Approved</p>
                <p class="mt-1 text-2xl font-bold text-zinc-100">{{ $approvedPeriods }}</p>
                <p class="mt-1 text-xs text-zinc-500">Total disetujui</p>
            </div>
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                </svg>
            </div>
        </div>
    </div>

    {{-- Anomali --}}
    <div class="rounded-xl bg-zinc-900 p-5 shadow-sm border border-zinc-800">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-zinc-400">Anomali Belum Resolved</p>
                <p class="mt-1 text-2xl font-bold text-zinc-100">{{ $unresolvedAnomalies }}</p>
                <p class="mt-1 text-xs text-zinc-500">Perlu ditindaklanjuti</p>
            </div>
            <div class="flex h-10 w-10 items-center justify-center rounded-lg {{ $unresolvedAnomalies > 0 ? 'bg-red-950 text-red-300' : 'bg-emerald-100 text-emerald-600' }}">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                </svg>
            </div>
        </div>
    </div>

    {{-- CKPN Total --}}
    <div class="rounded-xl bg-zinc-900 p-5 shadow-sm border border-zinc-800">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-xs font-medium text-zinc-400">CKPN Total Terakhir</p>
                <p class="mt-1 text-lg font-bold text-zinc-100">Rp {{ number_format($totalCkpn, 0, ',', '.') }}</p>
                <p class="mt-1 text-xs text-zinc-500">{{ $lastPeriod ? 'Periode '.$lastPeriod : 'Belum ada data' }}</p>
            </div>
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-950 text-primary-300">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75" />
                </svg>
            </div>
        </div>
    </div>
</div>

{{-- Recent Periods Table --}}
<div class="mt-6 rounded-xl bg-zinc-900 shadow-sm border border-zinc-800">
    <div class="border-b border-zinc-800 px-5 py-4">
        <h2 class="text-sm font-semibold text-zinc-100">Periode CKPN Terkini</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-xs">
            <thead>
                <tr class="border-b border-zinc-800 bg-zinc-800/50">
                    <th class="px-5 py-3 text-left font-medium text-zinc-400">Periode</th>
                    <th class="px-5 py-3 text-left font-medium text-zinc-400">Status</th>
                    <th class="px-5 py-3 text-left font-medium text-zinc-400">Dibuat Oleh</th>
                    <th class="px-5 py-3 text-left font-medium text-zinc-400">Disetujui Oleh</th>
                    <th class="px-5 py-3 text-left font-medium text-zinc-400">Tgl Approval</th>
                    <th class="px-5 py-3 text-left font-medium text-zinc-400">Catatan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-zinc-800">
                @forelse ($recentPeriods as $period)
                    <tr class="hover:bg-zinc-800/50 transition-colors">
                        <td class="px-5 py-3 font-medium text-zinc-100">{{ $period->period }}</td>
                        <td class="px-5 py-3">
                            @php
                                $badgeClass = match($period->status->value ?? $period->status) {
                                    'draft'       => 'bg-zinc-800 text-zinc-300',
                                    'in_progress' => 'bg-amber-100 text-amber-700',
                                    'completed'   => 'bg-emerald-100 text-emerald-700',
                                    'approved'    => 'bg-primary-100 text-primary-700',
                                    default       => 'bg-zinc-800 text-zinc-300',
                                };
                            @endphp
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $badgeClass }}">
                                {{ $period->status->value ?? $period->status }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-zinc-300">{{ $period->createdBy?->name ?? '-' }}</td>
                        <td class="px-5 py-3 text-zinc-300">{{ $period->approvedBy?->name ?? '-' }}</td>
                        <td class="px-5 py-3 text-zinc-300">{{ $period->approved_at ? $period->approved_at->format('d/m/Y H:i') : '-' }}</td>
                        <td class="px-5 py-3 text-zinc-400 max-w-xs truncate">{{ $period->notes ?? '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-5 py-8 text-center text-zinc-500">Belum ada data periode</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
</div>{{-- end single root --}}
