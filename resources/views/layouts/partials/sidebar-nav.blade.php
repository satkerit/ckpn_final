@php
    use Illuminate\Support\Facades\Request;

    $nav = [
        ['section' => null, 'items' => [
            ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'home', 'children' => []],
        ]],
        ['section' => 'Data', 'items' => [
            ['label' => 'Data Pembiayaan', 'icon' => 'database', 'children' => [
                ['label' => 'Akun Pembiayaan', 'route' => 'data-pembiayaan.accounts.index'],
                ['label' => 'Data Historis',   'route' => 'data-pembiayaan.periods.index'],
                ['label' => 'Jaminan',          'route' => 'data-pembiayaan.collaterals.index'],
            ]],
            ['label' => 'Upload Data', 'icon' => 'upload', 'children' => [
                ['label' => 'Upload Data',    'route' => 'upload.index'],
                ['label' => 'Riwayat Upload', 'route' => 'upload.batches.index'],
            ]],
        ]],
        ['section' => 'Master Data', 'items' => [
            ['label' => 'Master Data', 'icon' => 'cog', 'children' => [
                ['label' => 'Parameter Kalkulasi', 'route' => 'master-data.parameters.index'],
                ['label' => 'Bucket',              'route' => 'master-data.buckets.index'],
                ['label' => 'Kualitas Aktiva',     'route' => 'master-data.quality-grades.index'],
                ['label' => 'Jenis Jaminan',       'route' => 'master-data.collateral-types.index'],
            ]],
        ]],
        ['section' => 'Kalkulasi', 'items' => [
            ['label' => '1. Periode & Klasifikasi', 'icon' => 'calendar',    'route' => 'kalkulasi.periode-klasifikasi.index', 'children' => []],
            ['label' => '2. Probabilitas Default',  'icon' => 'chart-bar',   'route' => 'kalkulasi.pd.index',                  'children' => []],
            ['label' => '3. Loss Given Default',     'icon' => 'shield-check','route' => 'kalkulasi.lgd.index',                 'children' => []],
            ['label' => '4. CKPN Individual',        'icon' => 'calculator',  'route' => 'kalkulasi.ckpn.individual',           'children' => []],
            ['label' => '5. CKPN Kolektif',          'icon' => 'calculator',  'route' => 'kalkulasi.ckpn.kolektif',             'children' => []],
        ]],
        ['section' => 'Export', 'items' => [
            ['label' => 'Export Data', 'icon' => 'arrow-down-tray', 'route' => 'export.data.index', 'children' => []],
        ]],
        ['section' => 'Laporan', 'items' => [
            ['label' => 'Ringkasan CKPN',  'icon' => 'document-text', 'route' => 'reporting.ckpn-summary', 'children' => []],
            ['label' => 'CKPN Final',       'icon' => 'document-text', 'route' => 'reporting.ckpn-final',   'children' => []],
            ['label' => 'Ringkasan LGD',    'icon' => 'document-text', 'route' => 'reporting.lgd-summary',  'children' => []],
            ['label' => 'Anomali Data',     'icon' => 'document-text', 'route' => 'reporting.anomalies',    'children' => []],
            ['label' => 'Rekonsiliasi',     'icon' => 'arrows-right-left', 'route' => 'kalkulasi.ckpn.index', 'children' => []],
        ]],
        ['section' => 'Sistem', 'items' => [
            ['label' => 'Administrasi', 'icon' => 'users', 'children' => [
                ['label' => 'Manajemen User', 'route' => 'admin.users.index'],
                ['label' => 'Log Kalkulasi',  'route' => 'admin.run-logs.index'],
            ]],
        ]],
    ];
@endphp

@foreach ($nav as $group)
    {{-- Section label --}}
    @if ($group['section'])
        <div class="mb-1 mt-4 px-3 first:mt-0">
            <span class="text-[9px] font-bold uppercase tracking-widest text-zinc-600">
                {{ $group['section'] }}
            </span>
        </div>
    @endif

    @foreach ($group['items'] as $item)
        @if (empty($item['children']))
            {{-- Flat item (Dashboard) --}}
            @php $flatActive = request()->routeIs($item['route']); @endphp
            <a href="{{ route($item['route']) }}"
               class="group relative mb-0.5 flex items-center gap-2.5 rounded-lg px-3 py-2 text-xs font-medium transition-all duration-150
                      {{ $flatActive
                          ? 'bg-gradient-to-r from-primary-600/90 to-primary-500/70 text-white shadow-md shadow-primary-900/40 ring-1 ring-white/10'
                          : 'text-zinc-400 hover:bg-white/6 hover:text-zinc-100' }}">
                {{-- Indikator aktif kiri --}}
                <span class="absolute left-0 top-1/2 h-4 w-[3px] -translate-y-1/2 rounded-r-full bg-primary-300 {{ $flatActive ? '' : 'hidden' }}"></span>
                <span class="{{ $flatActive ? 'text-white' : 'text-zinc-500 group-hover:text-zinc-300' }} transition-colors">
                    @include('layouts.partials.icon', ['name' => $item['icon']])
                </span>
                {{ $item['label'] }}
            </a>
        @else
            {{-- Group with children --}}
            @php
                $isActive = collect($item['children'])->contains(fn($c) => request()->routeIs($c['route']));
            @endphp
            <div x-data="{ open: {{ $isActive ? 'true' : 'false' }} }" class="mb-0.5">
                <button @click="open = !open"
                        class="group flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-xs font-medium transition-all duration-150
                               {{ $isActive
                                   ? 'text-zinc-100'
                                   : 'text-zinc-400 hover:bg-white/6 hover:text-zinc-100' }}">
                    <span class="{{ $isActive ? 'text-indigo-400' : 'text-zinc-600 group-hover:text-zinc-400' }} transition-colors">
                        @include('layouts.partials.icon', ['name' => $item['icon']])
                    </span>
                    <span class="flex-1 text-left">{{ $item['label'] }}</span>
                    {{-- Chevron --}}
                    <svg class="h-3 w-3 shrink-0 text-zinc-600 transition-transform duration-200"
                         :class="open ? 'rotate-180 text-zinc-400' : ''"
                         fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                    </svg>
                </button>

                {{-- Sub-items --}}
                <div x-show="open"
                     x-cloak
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 -translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100 translate-y-0"
                     x-transition:leave-end="opacity-0 -translate-y-1"
                     class="ml-3 mt-0.5 space-y-0.5 border-l border-white/8 pl-3">
                    @foreach ($item['children'] as $child)
                        @php $childActive = request()->routeIs($child['route']); @endphp
                        <a href="{{ route($child['route']) }}"
                           class="group relative flex items-center gap-2 rounded-md px-2.5 py-1.5 text-xs transition-all duration-150
                                  {{ $childActive
                                      ? 'bg-primary-500/15 font-semibold text-primary-200'
                                      : 'text-zinc-500 hover:bg-white/5 hover:text-zinc-300' }}">
                            @if ($childActive)
                                <span class="absolute -left-[13px] top-1/2 h-3 w-[2px] -translate-y-1/2 rounded-full bg-gradient-to-b from-primary-300 to-primary-500"></span>
                            @endif
                            {{ $child['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach
@endforeach
