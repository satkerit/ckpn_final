<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'CKPN System') }} — @yield('title', 'Dashboard')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>
        [x-cloak] { display: none !important; }
        .nav-slide-enter { animation: navSlideDown 0.18s ease-out; }
        @keyframes navSlideDown {
            from { opacity: 0; transform: translateY(-6px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .sidebar-scrollbar::-webkit-scrollbar { width: 3px; }
        .sidebar-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .sidebar-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 99px; }
    </style>
</head>
<body class="h-full bg-app font-sans text-zinc-100 antialiased">

{{-- Progress bar global — muncul saat ada request Livewire --}}
<div wire:loading.flex class="lw-progress hidden" wire:loading.delay></div>

<div class="flex h-full" x-data="{ sidebarOpen: false }">

    {{-- Mobile overlay --}}
    <div x-show="sidebarOpen"
         x-cloak
         @click="sidebarOpen = false"
         class="fixed inset-0 z-40 bg-black/50 backdrop-blur-sm lg:hidden"></div>

    {{-- Sidebar --}}
    <aside class="bg-sidebar fixed inset-y-0 left-0 z-50 flex w-64 flex-col shadow-2xl shadow-black/30 transition-transform duration-250 ease-in-out lg:translate-x-0"
           :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">

        {{-- Logo / Brand --}}
        <div class="flex h-16 shrink-0 items-center gap-3 border-b border-white/8 px-5">
            <div class="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-primary-400 via-primary-600 to-primary-800 shadow-lg shadow-primary-900/60 ring-1 ring-white/20">
                <svg class="h-4.5 w-4.5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 14.25v2.25m3-4.5v4.5m3-6.75v6.75m3-9v9M6 20.25h12A2.25 2.25 0 0020.25 18V6A2.25 2.25 0 0018 3.75H6A2.25 2.25 0 003.75 6v12A2.25 2.25 0 006 20.25z" />
                </svg>
            </div>
            <div>
                <p class="text-sm font-extrabold tracking-tight text-white">CKPN System</p>
                <p class="text-[10px] font-medium tracking-[0.14em] text-zinc-500 uppercase">Risk Analytics · PSAK 414</p>
            </div>
        </div>

        {{-- Navigation --}}
        <nav class="sidebar-scrollbar flex-1 overflow-y-auto px-3 py-4">
            @include('layouts.partials.sidebar-nav')
        </nav>

        {{-- User footer --}}
        <div class="shrink-0 border-t border-white/8 p-3">
            <div class="flex items-center gap-3 rounded-xl px-2 py-2 transition-colors hover:bg-white/5">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-indigo-400 to-indigo-600 text-xs font-bold text-white shadow-md">
                    {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-xs font-semibold text-zinc-200">{{ auth()->user()->name ?? '' }}</p>
                    <p class="truncate text-[10px] text-zinc-500">{{ auth()->user()->email ?? '' }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            title="Logout"
                            class="flex h-7 w-7 items-center justify-center rounded-lg text-zinc-500 transition-colors hover:bg-red-500/10 hover:text-red-400">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    {{-- Main content area --}}
    <div class="flex min-w-0 flex-1 flex-col lg:pl-64">

        {{-- Top bar --}}
        <header class="sticky top-0 z-30 flex h-14 shrink-0 items-center gap-3 border-b border-zinc-800/80 bg-zinc-900/80 px-4 shadow-sm shadow-black/20 backdrop-blur-md lg:px-6">
            {{-- Mobile hamburger --}}
            <button @click="sidebarOpen = !sidebarOpen"
                    class="flex h-8 w-8 items-center justify-center rounded-lg text-zinc-400 transition-colors hover:bg-zinc-800 lg:hidden">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
            </button>

            {{-- Page title / breadcrumb --}}
            <div class="flex flex-1 items-center gap-2 min-w-0">
                <h1 class="truncate text-sm font-semibold text-zinc-100">@yield('title', 'Dashboard')</h1>
            </div>

            {{-- Right: date + user badge --}}
            <div class="flex shrink-0 items-center gap-3">
                <span class="hidden text-xs text-zinc-400 sm:block">{{ now()->isoFormat('D MMM YYYY') }}</span>
                <div class="flex h-7 w-7 items-center justify-center rounded-full bg-gradient-to-br from-indigo-400 to-indigo-600 text-[11px] font-bold text-white shadow">
                    {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                </div>
            </div>
        </header>

        {{-- Page content --}}
        <main class="flex-1 p-5 lg:p-6">
            @if (session('success'))
                <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 5000)"
                     x-show="show" x-transition:leave="transition ease-in duration-300" x-transition:leave-end="opacity-0 -translate-y-2"
                     class="animate-fade-up mb-4 flex items-center gap-2 rounded-xl border border-emerald-700/60 bg-emerald-950/40 px-4 py-3 text-sm font-medium text-emerald-300 shadow-sm">
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    {{ session('success') }}
                </div>
            @endif
            @if (session('error'))
                <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 8000)"
                     x-show="show" x-transition:leave="transition ease-in duration-300" x-transition:leave-end="opacity-0 -translate-y-2"
                     class="animate-fade-up mb-4 flex items-center gap-2 rounded-xl border border-red-700/60 bg-red-950/40 px-4 py-3 text-sm font-medium text-red-300 shadow-sm">
                    <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                    {{ session('error') }}
                </div>
            @endif
            {{ $slot ?? '' }}
            @yield('content')
        </main>
    </div>
</div>

@livewireScripts

<script>
    {{-- Helper animasi angka naik (count-up) untuk KPI — dipakai via x-data="countUp(N)" --}}
    document.addEventListener('alpine:init', () => {
        Alpine.data('countUp', (target = 0, duration = 900, formatter = null) => ({
            display: '',
            init() {
                this.display = this.format(target);
                if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !Number.isFinite(target)) return;
                const start = performance.now();
                const step = (now) => {
                    const p = Math.min((now - start) / duration, 1);
                    const eased = 1 - Math.pow(1 - p, 3);
                    this.display = this.format(Math.round(target * eased));
                    if (p < 1) requestAnimationFrame(step);
                };
                requestAnimationFrame(step);
            },
            format(n) {
                if (formatter === 'currency') return 'Rp ' + new Intl.NumberFormat('id-ID').format(Number(n) || 0);
                return new Intl.NumberFormat('id-ID').format(Number(n) || 0);
            }
        }));
    });
</script>

{{-- Toast notification container — listen event 'notify' dari $this->dispatch('notify', type, message) --}}
<div
    x-data="{
        toasts: [],
        add(type, message) {
            const id = Date.now();
            this.toasts.push({ id, type, message, visible: false });
            this.$nextTick(() => {
                const t = this.toasts.find(t => t.id === id);
                if (t) t.visible = true;
            });
            setTimeout(() => this.remove(id), 4500);
        },
        remove(id) {
            const t = this.toasts.find(t => t.id === id);
            if (t) {
                t.visible = false;
                setTimeout(() => { this.toasts = this.toasts.filter(t => t.id !== id); }, 350);
            }
        }
    }"
    @notify.window="add($event.detail.type, $event.detail.message)"
    class="fixed bottom-5 right-5 z-[200] flex flex-col gap-2 pointer-events-none"
    style="min-width:18rem;max-width:22rem;"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-show="toast.visible"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 translate-y-4"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-250"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-2"
            :class="{
                'bg-emerald-50 border-emerald-300 text-emerald-800': toast.type === 'success',
                'bg-red-50 border-red-300 text-red-800':             toast.type === 'error',
                'bg-amber-50 border-amber-300 text-amber-800':       toast.type === 'warning',
                'bg-blue-50 border-blue-300 text-blue-800':          toast.type === 'info',
            }"
            class="pointer-events-auto flex items-start gap-3 rounded-xl border px-4 py-3 shadow-lg text-sm"
        >
            {{-- Icon --}}
            <span class="mt-0.5 shrink-0">
                <template x-if="toast.type === 'success'">
                    <svg class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </template>
                <template x-if="toast.type === 'error'">
                    <svg class="h-4 w-4 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                </template>
                <template x-if="toast.type === 'warning'">
                    <svg class="h-4 w-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                    </svg>
                </template>
                <template x-if="toast.type === 'info'">
                    <svg class="h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                    </svg>
                </template>
            </span>
            <span x-text="toast.message" class="flex-1 leading-snug"></span>
            <button @click="remove(toast.id)" class="shrink-0 opacity-50 hover:opacity-100 transition-opacity">
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </template>
</div>

{{-- Global Real-time Progress Bar Component --}}
<livewire:components.progress-bar />

</body>
</html>
