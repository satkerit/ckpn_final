<div class="flex min-h-screen w-full">
    {{-- Panel kiri: branding / hero (tersembunyi di mobile) --}}
    <div class="hidden lg:flex lg:w-1/2 xl:w-3/5 flex-col justify-between relative overflow-hidden bg-gradient-to-br from-zinc-950 via-primary-950/30 to-zinc-950 p-12">
        {{-- Decorative orbs --}}
        <div class="absolute inset-0 pointer-events-none">
            <div class="absolute -top-32 -left-32 w-96 h-96 rounded-full bg-primary-600/20 blur-3xl"></div>
            <div class="absolute bottom-0 right-0 w-80 h-80 rounded-full bg-primary-400/10 blur-3xl"></div>
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-64 h-64 rounded-full bg-primary-500/8 blur-2xl"></div>
        </div>

        {{-- Grid pattern overlay --}}
        <div class="absolute inset-0 opacity-20"
             style="background-image: linear-gradient(rgba(99,102,241,0.08) 1px, transparent 1px), linear-gradient(90deg, rgba(99,102,241,0.08) 1px, transparent 1px); background-size: 40px 40px;"></div>

        {{-- Top: Logo --}}
        <div class="relative z-10">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-primary-400 via-primary-600 to-primary-800 shadow-lg shadow-primary-900/60 ring-1 ring-white/20">
                    <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 15.75V18m-7.5-6.75h.008v.008H8.25v-.008zm0 2.25h.008v.008H8.25V13.5zm0 2.25h.008v.008H8.25v-.008zm0 2.25h.008v.008H8.25V18zm2.498-6.75h.007v.008h-.007v-.008zm0 2.25h.007v.008h-.007V13.5zm0 2.25h.007v.008h-.007v-.008zm0 2.25h.007v.008h-.007V18zm2.504-6.75h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V13.5zm0 2.25h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V18zm2.498-6.75h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V13.5zM8.25 6h7.5v2.25h-7.5V6zM12 2.25c-1.892 0-3.758.11-5.593.322C5.307 2.7 4.5 3.65 4.5 4.757V19.5a2.25 2.25 0 002.25 2.25h10.5a2.25 2.25 0 002.25-2.25V4.757c0-1.108-.806-2.057-1.907-2.185A48.507 48.507 0 0012 2.25z" />
                    </svg>
                </div>
                <span class="text-lg font-bold text-white tracking-tight">CKPN System</span>
            </div>
        </div>

        {{-- Center: headline --}}
        <div class="relative z-10 max-w-md">
            <h2 class="text-4xl font-bold text-white leading-tight tracking-tight mb-4">
                Sistem Perhitungan<br>
                <span class="bg-gradient-to-r from-primary-300 to-primary-500 bg-clip-text text-transparent">CKPN Terpadu</span>
            </h2>
            <p class="text-zinc-400 text-base leading-relaxed">
                Platform analitik risiko pembiayaan berbasis PSAK 414 — kalkulasi PD, LGD, dan CKPN kolektif secara akurat dan teraudit.
            </p>

            {{-- Feature pills --}}
            <div class="mt-8 flex flex-wrap gap-2">
                @foreach(['PD Netflow', 'PD Migration', 'LGD Expected Recoveries', 'LGD Collateral Shortfall', 'CKPN Kolektif'] as $feat)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-zinc-800/80 border border-zinc-700/50 px-3 py-1 text-xs font-medium text-zinc-300">
                    <span class="h-1.5 w-1.5 rounded-full bg-primary-400"></span>
                    {{ $feat }}
                </span>
                @endforeach
            </div>
        </div>

        {{-- Bottom: tagline --}}
        <div class="relative z-10">
            <p class="text-xs text-zinc-600">© {{ date('Y') }} CKPN System · PSAK 414 Compliant</p>
        </div>
    </div>

    {{-- Panel kanan: form login --}}
    <div class="flex flex-1 flex-col items-center justify-center px-6 py-12 lg:px-12 bg-zinc-950">
        {{-- Mobile logo --}}
        <div class="mb-8 flex items-center gap-3 lg:hidden">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-primary-400 via-primary-600 to-primary-800">
                <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 15.75V18m-7.5-6.75h.008v.008H8.25v-.008zm0 2.25h.008v.008H8.25V13.5zm0 2.25h.008v.008H8.25v-.008zm0 2.25h.008v.008H8.25V18zm2.498-6.75h.007v.008h-.007v-.008zm0 2.25h.007v.008h-.007V13.5zm0 2.25h.007v.008h-.007v-.008zm0 2.25h.007v.008h-.007V18zm2.504-6.75h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V13.5zm0 2.25h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V18zm2.498-6.75h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V13.5zM8.25 6h7.5v2.25h-7.5V6zM12 2.25c-1.892 0-3.758.11-5.593.322C5.307 2.7 4.5 3.65 4.5 4.757V19.5a2.25 2.25 0 002.25 2.25h10.5a2.25 2.25 0 002.25-2.25V4.757c0-1.108-.806-2.057-1.907-2.185A48.507 48.507 0 0012 2.25z" />
                </svg>
            </div>
            <span class="text-lg font-bold text-white">CKPN System</span>
        </div>

        <div class="w-full max-w-sm">
            <div class="mb-8">
                <h1 class="text-2xl font-bold text-zinc-100 tracking-tight">Selamat datang</h1>
                <p class="mt-1.5 text-sm text-zinc-400">Masuk ke akun Anda untuk melanjutkan</p>
            </div>

            <form wire:submit="login" class="space-y-5">
                {{-- Email --}}
                <div>
                    <label for="email" class="block text-xs font-medium text-zinc-300 mb-1.5">Email</label>
                    <input wire:model="email" id="email" type="email" autocomplete="email" autofocus
                        class="block w-full rounded-lg border bg-zinc-950 px-3.5 py-2.5 text-sm text-zinc-200 placeholder-zinc-500 shadow-sm transition-colors
                               @error('email') border-rose-500/80 focus:border-rose-500 focus:ring-rose-500/30 @else border-zinc-700 focus:border-primary-500 focus:ring-primary-500/30 @enderror
                               focus:outline-none focus:ring-2"
                        placeholder="nama@institusi.co.id">
                    @error('email')
                        <p class="mt-1.5 text-xs text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Password --}}
                <div>
                    <label for="password" class="block text-xs font-medium text-zinc-300 mb-1.5">Password</label>
                    <input wire:model="password" id="password" type="password" autocomplete="current-password"
                        class="block w-full rounded-lg border bg-zinc-950 px-3.5 py-2.5 text-sm text-zinc-200 placeholder-zinc-500 shadow-sm transition-colors
                               @error('password') border-rose-500/80 focus:border-rose-500 focus:ring-rose-500/30 @else border-zinc-700 focus:border-primary-500 focus:ring-primary-500/30 @enderror
                               focus:outline-none focus:ring-2"
                        placeholder="••••••••">
                    @error('password')
                        <p class="mt-1.5 text-xs text-rose-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Remember --}}
                <div class="flex items-center gap-2">
                    <input wire:model="remember" id="remember" type="checkbox"
                        class="h-3.5 w-3.5 rounded border-zinc-600 bg-zinc-800 text-primary-600 focus:ring-primary-500/30">
                    <label for="remember" class="text-xs text-zinc-300">Ingat saya</label>
                </div>

                {{-- Submit --}}
                <button type="submit"
                    class="w-full rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white
                           hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 focus:ring-offset-zinc-950
                           transition-all disabled:opacity-50 shadow-lg shadow-primary-900/30"
                    wire:loading.attr="disabled">
                    <span wire:loading.remove>Masuk</span>
                    <span wire:loading class="flex items-center justify-center gap-2">
                        <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Memproses...
                    </span>
                </button>
            </form>
        </div>
    </div>
</div>
