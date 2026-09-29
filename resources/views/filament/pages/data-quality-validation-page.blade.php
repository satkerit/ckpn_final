<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        @if ($validationResults)
            <div class="grid grid-cols-4 gap-4">
                <!-- Total Snapshots -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="text-sm font-semibold text-blue-900">Total Snapshots</div>
                    <div class="text-3xl font-bold text-blue-600">{{ $validationResults['total_snapshots'] }}</div>
                </div>

                <!-- Total Anomalies -->
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <div class="text-sm font-semibold text-yellow-900">Total Anomalies</div>
                    <div class="text-3xl font-bold text-yellow-600">{{ $validationResults['total_anomalies'] }}</div>
                </div>

                <!-- Critical -->
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <div class="text-sm font-semibold text-red-900">🚨 Critical</div>
                    <div class="text-3xl font-bold text-red-600">{{ $validationResults['critical_count'] }}</div>
                </div>

                <!-- Warnings -->
                <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                    <div class="text-sm font-semibold text-orange-900">⚠️ Warnings</div>
                    <div class="text-3xl font-bold text-orange-600">{{ $validationResults['warning_count'] }}</div>
                </div>
            </div>

            @if ($validationResults['anomalies']->isNotEmpty())
                <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                    <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                        <h3 class="font-semibold text-gray-900">Anomalies Detected</h3>
                    </div>

                    <div class="divide-y divide-gray-200">
                        @foreach ($validationResults['anomalies'] as $item)
                            <div class="px-6 py-4">
                                <div class="flex items-start justify-between mb-2">
                                    <div>
                                        <span class="inline-block px-2 py-1 text-xs font-semibold rounded
                                            @if ($item['type'] === 'lgd_er')
                                                bg-blue-100 text-blue-800
                                            @else
                                                bg-green-100 text-green-800
                                            @endif
                                        ">
                                            {{ strtoupper($item['type']) }}
                                        </span>

                                        <span class="ml-2 text-sm text-gray-600">
                                            Office: <strong>{{ $item['office_code'] ?? '(Global)' }}</strong>
                                        </span>
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    @foreach ($item['anomalies'] as $key => $anomaly)
                                        <div class="flex items-start gap-2">
                                            <span class="inline-block px-2 py-1 text-xs font-semibold rounded mt-0.5
                                                @if ($anomaly['severity'] === 'critical')
                                                    bg-red-100 text-red-800
                                                @elseif ($anomaly['severity'] === 'warning')
                                                    bg-yellow-100 text-yellow-800
                                                @else
                                                    bg-blue-100 text-blue-800
                                                @endif
                                            ">
                                                {{ strtoupper($anomaly['severity']) }}
                                            </span>
                                            <span class="text-sm text-gray-700">{{ $anomaly['message'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="bg-green-50 border border-green-200 rounded-lg p-6 text-center">
                    <div class="text-lg font-semibold text-green-900">✅ No Anomalies Detected</div>
                    <div class="text-sm text-green-700">All snapshots for this period passed validation.</div>
                </div>
            @endif
        @endif
    </div>
</x-filament-panels::page>
