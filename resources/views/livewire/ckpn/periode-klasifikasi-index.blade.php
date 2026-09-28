<div class="min-h-screen bg-zinc-950">
    <div class="mb-6 border-b border-zinc-700 bg-zinc-900">
        <nav class="-mb-px flex gap-1 px-6">
            <button wire:click="$set('activeTab', 'periode')"
                class="border-b-2 px-4 py-3 text-sm font-medium transition-colors
                    {{ $activeTab === 'periode' ? 'border-primary-500 text-primary-600' : 'border-transparent text-zinc-400 hover:border-zinc-700 hover:text-zinc-300' }}">
                Periode CKPN
            </button>
            <button wire:click="$set('activeTab', 'klasifikasi')"
                class="border-b-2 px-4 py-3 text-sm font-medium transition-colors
                    {{ $activeTab === 'klasifikasi' ? 'border-primary-500 text-primary-600' : 'border-transparent text-zinc-400 hover:border-zinc-700 hover:text-zinc-300' }}">
                Klasifikasi Nasabah
            </button>
            <button wire:click="$set('activeTab', 'parameter')"
                class="border-b-2 px-4 py-3 text-sm font-medium transition-colors
                    {{ $activeTab === 'parameter' ? 'border-primary-500 text-primary-600' : 'border-transparent text-zinc-400 hover:border-zinc-700 hover:text-zinc-300' }}">
                Parameter Kalkulasi
            </button>
        </nav>
    </div>
    <div>
        @if($activeTab === 'periode')
            @livewire('ckpn.ckpn-period-index')
        @endif
        @if($activeTab === 'klasifikasi')
            @livewire('ckpn.ckpn-classification-index')
        @endif
        @if($activeTab === 'parameter')
            @livewire('ckpn.calculation-parameter-index')
        @endif
    </div>
</div>
