<x-filament-panels::page>
    <div class="space-y-6">
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
            <h3 class="font-semibold text-blue-900 mb-2">ℹ️ Batch Calculation</h3>
            <p class="text-blue-800 text-sm">
                Jalankan beberapa metode perhitungan sekaligus untuk satu periode. Semua job dijalankan <strong>paralel</strong> via queue asynchronous.
                Pantau progress di <strong>Job Monitor</strong>.
            </p>
        </div>

        {{ $this->form }}
    </div>
</x-filament-panels::page>
