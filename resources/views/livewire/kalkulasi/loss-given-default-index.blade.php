<div class="min-h-screen">
    <div class="mb-6 border-b border-zinc-800 bg-zinc-900/60 rounded-xl px-4 py-2 sm:px-6 shadow-sm">
        <nav class="-mb-px flex gap-2">
            <button wire:click="$set('activeTab', 'er')"
                class="border-b-2 px-5 py-3 text-sm font-semibold transition-all
                    {{ $activeTab === 'er' ? 'border-primary-500 text-primary-400 bg-primary-950/20 rounded-t-lg' : 'border-transparent text-zinc-400 hover:border-zinc-700 hover:text-zinc-200' }}">
                Expected Recoveries
            </button>
            <button wire:click="$set('activeTab', 'cs')"
                class="border-b-2 px-5 py-3 text-sm font-semibold transition-all
                    {{ $activeTab === 'cs' ? 'border-primary-500 text-primary-400 bg-primary-950/20 rounded-t-lg' : 'border-transparent text-zinc-400 hover:border-zinc-700 hover:text-zinc-200' }}">
                Collateral Shortfall
            </button>
            <button wire:click="$set('activeTab', 'final')"
                class="border-b-2 px-5 py-3 text-sm font-semibold transition-all
                    {{ $activeTab === 'final' ? 'border-primary-500 text-primary-400 bg-primary-950/20 rounded-t-lg' : 'border-transparent text-zinc-400 hover:border-zinc-700 hover:text-zinc-200' }}">
                LGD Final
            </button>
        </nav>
    </div>
    <div>
        @if($activeTab === 'er')
            @livewire('lgd.lgd-er-result-index')
        @endif
        @if($activeTab === 'cs')
            @livewire('lgd.lgd-cs-result-index')
        @endif
        @if($activeTab === 'final')
            @livewire('lgd.lgd-final-result-index')
        @endif
    </div>
</div>
