<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-zinc-800 pb-5">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex h-6 w-6 items-center justify-center rounded-lg bg-primary-950 border border-primary-800/60 text-primary-400">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/>
                    </svg>
                </span>
                <h1 class="text-lg font-bold text-zinc-100">Manajemen Pengguna</h1>
            </div>
            <p class="mt-1 text-xs text-zinc-400">Ref: PRD FR-14 — Kelola akun akses pengguna, penetapan peran/role, dan hak otorisasi sistem.</p>
        </div>

        <button wire:click="openCreate"
            class="inline-flex items-center gap-2 rounded-xl bg-primary-600 px-4 py-2.5 text-xs font-semibold text-white shadow-lg shadow-primary-950/50 hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-400 focus:ring-offset-2 focus:ring-offset-zinc-950 transition-all active:scale-[0.98]">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
            <span>Tambah Pengguna Baru</span>
        </button>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {{-- Total Users --}}
        <div class="rounded-2xl border border-zinc-800 bg-zinc-900/90 p-4 shadow-sm backdrop-blur">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-zinc-400">Total Pengguna</span>
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-zinc-800 text-zinc-300">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/>
                    </svg>
                </span>
            </div>
            <p class="mt-2 font-mono text-2xl font-extrabold text-zinc-100">{{ $stats->total }}</p>
            <p class="mt-1 text-[11px] text-zinc-500">Akun terdaftar dalam sistem</p>
        </div>

        {{-- Super Admin --}}
        <div class="rounded-2xl border border-purple-900/40 bg-zinc-900/90 p-4 shadow-sm backdrop-blur">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-purple-300">Super Admin</span>
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-purple-950 border border-purple-800/60 text-purple-400">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z"/>
                    </svg>
                </span>
            </div>
            <p class="mt-2 font-mono text-2xl font-extrabold text-purple-200">{{ $stats->admins }}</p>
            <p class="mt-1 text-[11px] text-zinc-500">Akses penuh konfigurasi</p>
        </div>

        {{-- Risk Analysts --}}
        <div class="rounded-2xl border border-blue-900/40 bg-zinc-900/90 p-4 shadow-sm backdrop-blur">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-blue-300">Risk Analyst</span>
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-950 border border-blue-800/60 text-blue-400">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 0 0 6 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0 1 18 16.5h-2.25m-7.5 0h7.5m-7.5 0-1 3m8.5-3 1 3m0 0 .5 1.5m-.5-1.5h-9.5m0 0-.5 1.5M9 11.25v1.5M12 9v3.75m3-6v6"/>
                    </svg>
                </span>
            </div>
            <p class="mt-2 font-mono text-2xl font-extrabold text-blue-200">{{ $stats->analysts }}</p>
            <p class="mt-1 text-[11px] text-zinc-500">Eksekusi kalkulasi PD/LGD</p>
        </div>

        {{-- Approvers --}}
        <div class="rounded-2xl border border-emerald-900/40 bg-zinc-900/90 p-4 shadow-sm backdrop-blur">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-emerald-300">Approver CKPN</span>
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-950 border border-emerald-800/60 text-emerald-400">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                </span>
            </div>
            <p class="mt-2 font-mono text-2xl font-extrabold text-emerald-200">{{ $stats->approvers }}</p>
            <p class="mt-1 text-[11px] text-zinc-500">Otorisasi hasil akhir CKPN</p>
        </div>
    </div>

    {{-- Filter & Search Toolbar --}}
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 rounded-2xl border border-zinc-800 bg-zinc-900/70 p-3 backdrop-blur">
        <div class="flex flex-wrap items-center gap-3 flex-1">
            {{-- Search Box --}}
            <div class="relative min-w-[240px] flex-1 sm:max-w-xs">
                <svg class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
                </svg>
                <input wire:model.live.debounce.300ms="search"
                    type="text"
                    placeholder="Cari nama atau email pengguna…"
                    class="w-full rounded-xl border border-zinc-700/80 bg-zinc-950 py-2 pl-9 pr-8 text-xs text-zinc-100 placeholder-zinc-500 shadow-inner transition-colors focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"/>
                @if ($search !== '')
                    <button wire:click="$set('search', '')"
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-zinc-500 hover:text-zinc-300">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                @endif
            </div>

            {{-- Role Filter --}}
            <div class="w-48">
                <select wire:model.live="filterRole"
                    class="w-full rounded-xl border border-zinc-700/80 bg-zinc-950 px-3 py-2 text-xs text-zinc-200 shadow-inner transition-colors focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    <option value="">Semua Peran (Role)</option>
                    @foreach ($allRoles as $r)
                        <option value="{{ $r->name }}">{{ $r->name }}</option>
                    @endforeach
                </select>
            </div>

            @if ($search !== '' || $filterRole !== '')
                <button wire:click="resetFilters"
                    class="inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-medium text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200 transition-colors">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/>
                    </svg>
                    <span>Reset Filter</span>
                </button>
            @endif
        </div>

        <div class="text-xs text-zinc-500 text-right">
            Menampilkan <span class="font-semibold text-zinc-300">{{ $records->total() }}</span> pengguna
        </div>
    </div>

    {{-- Users Table --}}
    <div class="overflow-hidden rounded-2xl border border-zinc-800 bg-zinc-900 shadow-xl shadow-black/20">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-zinc-800 text-xs">
                <thead class="bg-zinc-950/70">
                    <tr>
                        <th scope="col" class="px-5 py-3.5 text-left font-semibold text-zinc-300">Pengguna</th>
                        <th scope="col" class="px-5 py-3.5 text-left font-semibold text-zinc-300">Email</th>
                        <th scope="col" class="px-5 py-3.5 text-left font-semibold text-zinc-300">Peran / Hak Akses</th>
                        <th scope="col" class="px-5 py-3.5 text-left font-semibold text-zinc-300">Tanggal Terdaftar</th>
                        <th scope="col" class="px-5 py-3.5 text-right font-semibold text-zinc-300">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-800/80">
                    @forelse ($records as $user)
                        <tr class="group hover:bg-zinc-800/40 transition-colors">
                            {{-- Nama & Avatar --}}
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-primary-600 via-primary-700 to-indigo-800 font-bold text-white shadow-md ring-1 ring-white/15">
                                        {{ strtoupper(substr($user->name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-semibold text-zinc-100 group-hover:text-white transition-colors">
                                                {{ $user->name }}
                                            </span>
                                            @if ($user->id === auth()->id())
                                                <span class="inline-flex items-center rounded-md bg-primary-950/90 border border-primary-800/60 px-2 py-0.5 text-[10px] font-bold text-primary-300">
                                                    Akun Anda
                                                </span>
                                            @endif
                                        </div>
                                        <p class="text-[11px] text-zinc-500 font-mono">ID: #{{ $user->id }}</p>
                                    </div>
                                </div>
                            </td>

                            {{-- Email --}}
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-1.5 font-mono text-zinc-300">
                                    <svg class="h-3.5 w-3.5 text-zinc-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/>
                                    </svg>
                                    <span>{{ $user->email }}</span>
                                </div>
                            </td>

                            {{-- Roles --}}
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap gap-1.5">
                                    @forelse ($user->roles as $role)
                                        @php
                                            $roleClass = match($role->name) {
                                                'super_admin' => 'bg-purple-950/80 border-purple-800/70 text-purple-200',
                                                'risk_analyst' => 'bg-blue-950/80 border-blue-800/70 text-blue-200',
                                                'approver' => 'bg-emerald-950/80 border-emerald-800/70 text-emerald-200',
                                                default => 'bg-zinc-800 border-zinc-700 text-zinc-200',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center gap-1 rounded-lg border px-2.5 py-1 text-[11px] font-semibold {{ $roleClass }}">
                                            <span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>
                                            {{ $role->name }}
                                        </span>
                                    @empty
                                        <span class="text-zinc-500 italic">Tidak ada role</span>
                                    @endforelse
                                </div>
                            </td>

                            {{-- Created Date --}}
                            <td class="px-5 py-4">
                                <span class="font-mono text-zinc-400">{{ $user->created_at->format('d M Y, H:i') }}</span>
                            </td>

                            {{-- Actions --}}
                            <td class="px-5 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    {{-- Edit Button --}}
                                    <button wire:click="openEdit({{ $user->id }})"
                                        title="Edit Pengguna"
                                        class="inline-flex items-center gap-1.5 rounded-xl border border-zinc-700 bg-zinc-800 px-3 py-1.5 text-xs font-semibold text-zinc-200 shadow-sm hover:border-zinc-600 hover:bg-zinc-700 focus:outline-none focus:ring-2 focus:ring-primary-500 transition-all active:scale-[0.97]">
                                        <svg class="h-3.5 w-3.5 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/>
                                        </svg>
                                        <span>Edit</span>
                                    </button>

                                    {{-- Delete Button --}}
                                    @if ($user->id !== auth()->id())
                                        <button wire:click="openDelete({{ $user->id }})"
                                            title="Hapus Pengguna"
                                            class="inline-flex items-center gap-1.5 rounded-xl border border-rose-900/60 bg-rose-950/40 px-3 py-1.5 text-xs font-semibold text-rose-300 shadow-sm hover:border-rose-700 hover:bg-rose-900/60 hover:text-rose-100 focus:outline-none focus:ring-2 focus:ring-rose-500 transition-all active:scale-[0.97]">
                                            <svg class="h-3.5 w-3.5 text-rose-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
                                            </svg>
                                            <span>Hapus</span>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-14 text-center">
                                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-zinc-800/80 text-zinc-500">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                                    </svg>
                                </div>
                                <p class="mt-3 text-sm font-semibold text-zinc-300">Tidak ada data pengguna ditemukan</p>
                                <p class="mt-1 text-xs text-zinc-500">Coba ubah kata kunci pencarian atau bersihkan filter yang aktif.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination footer --}}
        @if ($records->hasPages())
            <div class="border-t border-zinc-800 bg-zinc-950/60 px-5 py-3.5">
                {{ $records->links() }}
            </div>
        @endif
    </div>

    {{-- Modal Create / Edit User --}}
    @if ($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6" x-data>
        {{-- Backdrop --}}
        <div class="fixed inset-0 bg-zinc-950/80 backdrop-blur-md transition-opacity" wire:click="closeModal"></div>

        {{-- Modal Box --}}
        <div class="relative w-full max-w-lg rounded-2xl border border-zinc-700/80 bg-zinc-900 shadow-2xl ring-1 ring-white/10 overflow-hidden transform transition-all">
            {{-- Modal Header --}}
            <div class="flex items-center justify-between border-b border-zinc-800 px-6 py-4 bg-zinc-950/50">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary-950 border border-primary-800/70 text-primary-400">
                        @if ($editingId)
                            <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/>
                            </svg>
                        @else
                            <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.645-6.374-1.766Z"/>
                            </svg>
                        @endif
                    </div>
                    <div>
                        <h2 class="text-sm font-bold text-zinc-100">
                            {{ $editingId ? 'Edit Data Pengguna' : 'Tambah Pengguna Baru' }}
                        </h2>
                        <p class="text-[11px] text-zinc-400">
                            {{ $editingId ? 'Perbarui informasi profil dan penugasan role pengguna.' : 'Buat akun pengguna baru dan tetapkan hak aksesnya.' }}
                        </p>
                    </div>
                </div>

                <button wire:click="closeModal"
                    class="rounded-xl p-1.5 text-zinc-400 hover:bg-zinc-800 hover:text-zinc-200 transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Form Body --}}
            <div class="px-6 py-5 space-y-4 max-h-[75vh] overflow-y-auto sidebar-scrollbar">
                {{-- Nama Lengkap --}}
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-zinc-200">
                        Nama Lengkap <span class="text-rose-400">*</span>
                    </label>
                    <div class="relative">
                        <input wire:model="name"
                            type="text"
                            placeholder="Contoh: Muhammad Rizky"
                            class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3.5 py-2.5 text-xs text-zinc-100 placeholder-zinc-500 shadow-inner transition-colors focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 @error('name') border-rose-500 ring-1 ring-rose-500/30 @enderror"/>
                    </div>
                    @error('name')
                        <p class="mt-1 flex items-center gap-1 text-[11px] font-medium text-rose-400">
                            <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
                            </svg>
                            <span>{{ $message }}</span>
                        </p>
                    @enderror
                </div>

                {{-- Email --}}
                <div>
                    <label class="mb-1.5 block text-xs font-semibold text-zinc-200">
                        Alamat Email <span class="text-rose-400">*</span>
                    </label>
                    <div class="relative">
                        <input wire:model="email"
                            type="email"
                            placeholder="nama@perusahaan.co.id"
                            class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3.5 py-2.5 text-xs text-zinc-100 placeholder-zinc-500 shadow-inner font-mono transition-colors focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 @error('email') border-rose-500 ring-1 ring-rose-500/30 @enderror"/>
                    </div>
                    @error('email')
                        <p class="mt-1 flex items-center gap-1 text-[11px] font-medium text-rose-400">
                            <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
                            </svg>
                            <span>{{ $message }}</span>
                        </p>
                    @enderror
                </div>

                {{-- Password --}}
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-semibold text-zinc-200">
                            Kata Sandi {{ $editingId ? '' : '*' }}
                        </label>
                        @if ($editingId)
                            <span class="text-[10px] text-zinc-400 font-normal">Kosongkan jika tidak ingin mengubah sandi</span>
                        @endif
                    </div>
                    <input wire:model="password"
                        type="password"
                        placeholder="{{ $editingId ? '•••••••• (Tetap gunakan sandi saat ini)' : 'Minimal 8 karakter unik' }}"
                        class="w-full rounded-xl border border-zinc-700 bg-zinc-950 px-3.5 py-2.5 text-xs text-zinc-100 placeholder-zinc-500 shadow-inner transition-colors focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500/20 @error('password') border-rose-500 ring-1 ring-rose-500/30 @enderror"/>
                    @error('password')
                        <p class="mt-1 flex items-center gap-1 text-[11px] font-medium text-rose-400">
                            <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
                            </svg>
                            <span>{{ $message }}</span>
                        </p>
                    @enderror
                </div>

                {{-- Roles Selection --}}
                <div>
                    <label class="mb-2 block text-xs font-semibold text-zinc-200">
                        Hak Akses / Peran (Role)
                    </label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        @forelse ($allRoles as $role)
                            @php
                                $isChecked = in_array($role->name, $selectedRoles);
                                $roleDesc = match($role->name) {
                                    'super_admin' => 'Akses penuh sistem & master data',
                                    'risk_analyst' => 'Hitung PD, LGD & buat simulasi CKPN',
                                    'approver' => 'Persetujuan & validasi akhir CKPN',
                                    'viewer' => 'Hanya melihat laporan & ringkasan',
                                    default => 'Hak akses peran ' . $role->name,
                                };
                            @endphp
                            <label class="relative flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition-all {{ $isChecked ? 'border-primary-500 bg-primary-950/40 ring-1 ring-primary-500/30' : 'border-zinc-800 bg-zinc-950/60 hover:border-zinc-700 hover:bg-zinc-800/50' }}">
                                <input type="checkbox"
                                    wire:model="selectedRoles"
                                    value="{{ $role->name }}"
                                    class="mt-0.5 rounded border-zinc-700 bg-zinc-900 text-primary-600 focus:ring-primary-500 focus:ring-offset-zinc-900"/>
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-bold {{ $isChecked ? 'text-primary-300' : 'text-zinc-200' }}">
                                        {{ $role->name }}
                                    </p>
                                    <p class="mt-0.5 text-[10px] text-zinc-400 line-clamp-2 leading-relaxed">
                                        {{ $roleDesc }}
                                    </p>
                                </div>
                            </label>
                        @empty
                            <div class="col-span-2 rounded-xl border border-zinc-800 bg-zinc-950 p-4 text-center text-xs text-zinc-500">
                                Belum ada role terdaftar di sistem.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Modal Footer --}}
            <div class="flex items-center justify-end gap-3 border-t border-zinc-800 bg-zinc-950/60 px-6 py-4">
                <button wire:click="closeModal"
                    type="button"
                    class="rounded-xl border border-zinc-700 bg-zinc-800 px-4 py-2.5 text-xs font-semibold text-zinc-300 hover:bg-zinc-700 hover:text-white transition-colors">
                    Batal
                </button>
                <button wire:click="save"
                    type="button"
                    wire:loading.attr="disabled"
                    wire:target="save"
                    class="inline-flex items-center gap-2 rounded-xl bg-primary-600 px-5 py-2.5 text-xs font-bold text-white shadow-lg shadow-primary-950/60 hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-400 transition-all disabled:opacity-50">
                    <svg wire:loading.remove wire:target="save" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                    </svg>
                    <svg wire:loading wire:target="save" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span wire:loading.remove wire:target="save">
                        {{ $editingId ? 'Simpan Perubahan' : 'Buat Pengguna' }}
                    </span>
                    <span wire:loading wire:target="save">Memproses…</span>
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Modal Delete Confirmation --}}
    @if ($showDeleteConfirm)
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6" x-data>
        {{-- Backdrop --}}
        <div class="fixed inset-0 bg-zinc-950/80 backdrop-blur-md transition-opacity" wire:click="closeModal"></div>

        {{-- Dialog Box --}}
        <div class="relative w-full max-w-md rounded-2xl border border-rose-900/60 bg-zinc-900 p-6 shadow-2xl ring-1 ring-white/10 overflow-hidden">
            <div class="flex items-start gap-4">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-rose-950/90 border border-rose-800/80 text-rose-400 shadow-lg shadow-rose-950/50">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-zinc-100">Konfirmasi Hapus Pengguna</h3>
                    <p class="mt-1.5 text-xs text-zinc-400 leading-relaxed">
                        Apakah Anda yakin ingin menghapus akun <span class="font-bold text-zinc-200">{{ $deletingUserName }}</span>? Pengguna ini tidak akan dapat login lagi ke dalam sistem. Tindakan ini bersifat permanen.
                    </p>
                </div>
            </div>

            <div class="mt-6 flex items-center justify-end gap-3 border-t border-zinc-800 pt-4">
                <button wire:click="closeModal"
                    type="button"
                    class="rounded-xl border border-zinc-700 bg-zinc-800 px-4 py-2 text-xs font-semibold text-zinc-300 hover:bg-zinc-700 hover:text-white transition-colors">
                    Batal
                </button>
                <button wire:click="delete"
                    type="button"
                    wire:loading.attr="disabled"
                    wire:target="delete"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-rose-600 px-4 py-2 text-xs font-bold text-white shadow-lg shadow-rose-950/60 hover:bg-rose-500 focus:outline-none focus:ring-2 focus:ring-rose-400 transition-all disabled:opacity-50">
                    <svg wire:loading wire:target="delete" class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span wire:loading.remove wire:target="delete">Ya, Hapus Pengguna</span>
                    <span wire:loading wire:target="delete">Menghapus…</span>
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
