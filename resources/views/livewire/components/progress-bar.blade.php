{{-- Animated Real-time Progress Bar Component --}}
<div x-cloak 
     x-show="@entangle('isVisible')" 
     x-transition
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
    
    {{-- Container --}}
    <div class="w-full max-w-md mx-auto px-4">
        <div class="rounded-2xl bg-gradient-to-br from-zinc-900 to-zinc-800 border border-zinc-700 shadow-2xl overflow-hidden">
            
            {{-- Header --}}
            <div class="px-6 py-5 border-b border-zinc-700 bg-gradient-to-r from-zinc-800 to-zinc-900">
                <h3 class="text-lg font-semibold text-white truncate">
                    {{ $title }}
                </h3>
            </div>

            {{-- Content --}}
            <div class="px-6 py-6 space-y-5">
                
                {{-- Progress Bar --}}
                <div class="space-y-2">
                    {{-- Animated Bar Container --}}
                    <div class="relative h-3 rounded-full bg-zinc-700/50 overflow-hidden shadow-inner">
                        {{-- Gradient Bar --}}
                        <div class="absolute inset-y-0 left-0 rounded-full transition-all duration-500 ease-out"
                             :style="`width: ${$wire.percentage}%; background: linear-gradient(90deg, #3b82f6, #8b5cf6, #3b82f6); background-size: 200% 100%;`"
                             :class="{'animate-pulse': !$wire.isCompleted && $wire.percentage < 100}">
                        </div>
                        
                        {{-- Shimmer Effect --}}
                        @if (!$isCompleted && $percentage > 0)
                            <div class="absolute inset-y-0 left-0 rounded-full opacity-50"
                                 style="background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent); animation: shimmer 2s infinite;"
                                 :style="`width: ${$wire.percentage}%;`">
                            </div>
                        @endif
                    </div>

                    {{-- Percentage Display --}}
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-zinc-400">{{ $statusText }}</span>
                        <span class="text-2xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-purple-400">
                            {{ $percentage }}%
                        </span>
                    </div>
                </div>

                {{-- Status Information --}}
                @if ($infoText)
                    <div class="rounded-lg bg-zinc-800/50 border border-zinc-700 px-3 py-2">
                        <p class="text-xs text-zinc-300">{{ $infoText }}</p>
                    </div>
                @endif

                {{-- Details Panel --}}
                @if (!empty($details))
                    <div class="rounded-lg bg-zinc-800/30 border border-zinc-700/50 p-4 space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                            @foreach ($details as $key => $value)
                                <div class="flex flex-col space-y-1">
                                    <span class="text-xs font-medium text-zinc-400 uppercase tracking-wide">
                                        {{ str_replace('_', ' ', $key) }}
                                    </span>
                                    <span class="text-sm font-semibold text-zinc-100">
                                        {{ is_array($value) ? json_encode($value) : $value }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Real-time Stats --}}
                @if (!$isCompleted && $percentage > 0)
                    <div class="rounded-lg bg-gradient-to-r from-blue-950/40 to-purple-950/40 border border-blue-700/30 px-4 py-3">
                        <div class="grid grid-cols-3 gap-3 text-center">
                            <div>
                                <p class="text-xs text-zinc-400">Waktu Berjalan</p>
                                <p class="text-sm font-bold text-blue-300">
                                    {{ sprintf('%02d:%02d', intdiv($elapsedSeconds, 60), $elapsedSeconds % 60) }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-zinc-400">Kecepatan</p>
                                <p class="text-sm font-bold text-purple-300">
                                    {{ $elapsedSeconds > 0 ? number_format($percentage / $elapsedSeconds, 2) : '0' }}%/s
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-zinc-400">Estimasi Selesai</p>
                                <p class="text-sm font-bold text-emerald-300">
                                    @if ($elapsedSeconds > 0 && $percentage > 0 && $percentage < 100)
                                        {{ sprintf('%02d:%02d', intdiv(intval(($elapsedSeconds / $percentage) * (100 - $percentage)), 60), intval(($elapsedSeconds / $percentage) * (100 - $percentage)) % 60) }}
                                    @else
                                        --:--
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Error State --}}
                @if ($hasError)
                    <div class="rounded-lg bg-rose-950/40 border border-rose-700/60 px-4 py-3">
                        <p class="text-sm text-rose-300">
                            <span class="font-semibold">Error:</span> {{ $errorMessage }}
                        </p>
                    </div>
                @endif

                {{-- Completion State --}}
                @if ($isCompleted)
                    <div class="flex items-center justify-center gap-2 pt-2">
                        @if ($hasError)
                            <svg class="h-5 w-5 text-rose-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"/>
                            </svg>
                            <span class="text-sm font-medium text-rose-300">Gagal</span>
                        @else
                            <svg class="h-5 w-5 text-emerald-400 animate-bounce" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                            </svg>
                            <span class="text-sm font-medium text-emerald-300">Selesai</span>
                        @endif
                    </div>
                @endif

            </div>

            {{-- Footer Actions --}}
            @if ($isCompleted)
                <div class="px-6 py-4 border-t border-zinc-700 bg-zinc-800/30 flex gap-3">
                    <button wire:click="close"
                            class="flex-1 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-primary-500">
                        Tutup
                    </button>
                </div>
            @endif

            {{-- Shimmer Animation --}}
            <style>
                [x-cloak] {
                    display: none !important;
                }
                @keyframes shimmer {
                    0% { left: -100%; }
                    100% { left: 100%; }
                }
            </style>
        </div>
    </div>

</div>
