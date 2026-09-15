{{-- resources/views/livewire/data-pembiayaan/financing-account-index.blade.php --}}
{{-- Ref: PRD Bab 5 - Data Akun Pembiayaan --}}
<div>
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-base font-semibold text-zinc-100">Data Akun Pembiayaan</h2>
            <p class="mt-1 text-sm text-zinc-400">Daftar seluruh akun pembiayaan yang tersimpan di sistem.</p>
        </div>
        <a href="{{ route('upload.index') }}" wire:navigate
           class="inline-flex items-center gap-1.5 text-xs text-primary-600 hover:text-primary-700 font-medium">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
            </svg>
            Upload Data
        </a>
    </div>

    {{-- Search & Filters --}}
    <div class="mb-4 flex flex-wrap gap-3">
        <div class="relative flex-1 min-w-[200px]">
            <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
            </svg>
            <input
                type="text"
                wire:model.live.debounce.400ms="search"
                placeholder="Cari no. kontrak / nama nasabah..."
                class="w-full rounded-lg border border-zinc-700 bg-zinc-950 pl-8 pr-3 py-2 text-xs text-zinc-200 placeholder-zinc-500 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
            />
        </div>

        <select wire:model.live="filterUsage"
                class="rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-xs text-zinc-200 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
            <option value="">Semua Jenis Penggunaan</option>
            @foreach ($usageTypes as $ut)
                <option value="{{ $ut->value }}">{{ $ut->label() }}</option>
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
                        <th class="px-5 py-3 text-left text-xs font-semibold text-zinc-400 uppercase tracking-wide">No. Kontrak</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-zinc-400 uppercase tracking-wide">Nama Nasabah</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-zinc-400 uppercase tracking-wide">Jenis Penggunaan</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-zinc-400 uppercase tracking-wide">Kode Produk</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-zinc-400 uppercase tracking-wide">Kode Akad</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-zinc-400 uppercase tracking-wide">Kode Kantor</th>
                        <th class="px-5 py-3 text-center text-xs font-semibold text-zinc-400 uppercase tracking-wide">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800">
                    @forelse ($accounts as $account)
                        <tr class="hover:bg-zinc-800/50 transition-colors">
                            <td class="px-5 py-3 font-mono text-xs text-zinc-100">{{ $account->account_number }}</td>
                            <td class="px-5 py-3 text-zinc-300">{{ $account->customer_name }}</td>
                            <td class="px-5 py-3 text-zinc-400 text-xs">
                                {{ $account->usage_type?->label() ?? '-' }}
                            </td>
                            <td class="px-5 py-3 text-zinc-400 text-xs font-mono">{{ $account->product_code ?? '-' }}</td>
                            <td class="px-5 py-3 text-zinc-400 text-xs font-mono">{{ $account->akad_code ?? '-' }}</td>
                            <td class="px-5 py-3 text-zinc-400 text-xs font-mono">{{ $account->office_code ?? '-' }}</td>
                            <td class="px-5 py-3 text-center">
                                @if ($account->is_active)
                                    <span class="inline-flex rounded-full bg-emerald-950/40 text-emerald-300">Aktif</span>
                                @else
                                    <span class="inline-flex rounded-full bg-zinc-800 px-2 py-0.5 text-xs font-medium text-zinc-400">Nonaktif</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-10 text-center text-zinc-500 text-sm">
                                @if ($search || $filterUsage || $filterActive)
                                    Tidak ada data yang sesuai dengan filter.
                                @else
                                    Belum ada data akun pembiayaan.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if ($accounts->hasPages())
            <div class="border-t border-zinc-800 px-5 py-3">
                {{ $accounts->links() }}
            </div>
        @endif
    </div>

    {{-- Count info --}}
    <p class="mt-3 text-xs text-zinc-500">
        Menampilkan {{ $accounts->firstItem() ?? 0 }}–{{ $accounts->lastItem() ?? 0 }}
        dari {{ number_format($accounts->total()) }} akun
    </p>
</div>
