<div class="min-h-screen">
    <div class="mb-6 border-b border-zinc-800 bg-zinc-900/60 rounded-xl px-4 py-2 sm:px-6 shadow-sm">
        <nav class="-mb-px flex flex-wrap gap-x-2 gap-y-1">
            <button wire:click="$set('activeTab', 'netflow')"
                class="border-b-2 px-5 py-3 text-sm font-semibold transition-all
                    {{ $activeTab === 'netflow' ? 'border-primary-500 text-primary-400 bg-primary-950/20 rounded-t-lg' : 'border-transparent text-zinc-400 hover:border-zinc-700 hover:text-zinc-200' }}">
                PD Netflow
            </button>
            <button wire:click="$set('activeTab', 'migration')"
                class="border-b-2 px-5 py-3 text-sm font-semibold transition-all
                    {{ $activeTab === 'migration' ? 'border-primary-500 text-primary-400 bg-primary-950/20 rounded-t-lg' : 'border-transparent text-zinc-400 hover:border-zinc-700 hover:text-zinc-200' }}">
                PD Migration
            </button>
        </nav>
    </div>
    <div>
        @if($activeTab === 'netflow')
            @livewire('pd-netflow.pd-netflow-result-index')
        @endif
        @if($activeTab === 'migration')
            @livewire('pd-migration.pd-migration-result-index')
        @endif
    </div>
</div>
