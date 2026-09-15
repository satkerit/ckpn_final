<?php

declare(strict_types=1);

namespace App\Livewire\Kalkulasi;

use App\Services\CkpnReconciliationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app', ['title' => 'Rekonsiliasi CKPN'])]
class HasilCkpnIndex extends Component
{
    public Collection $reconciliationData;

    /** Daftar periode yang tersedia untuk rekonsiliasi (dari snapshot klasifikasi). */
    public array $availablePeriods = [];

    public string $selectedReconPeriod = '';

    protected CkpnReconciliationService $reconciliationService;

    public function boot(CkpnReconciliationService $reconciliationService): void
    {
        $this->reconciliationService = $reconciliationService;
    }

    public function mount(): void
    {
        $this->reconciliationData = collect();
        $this->availablePeriods = DB::table('ckpn_period_classifications')
            ->distinct()
            ->orderBy('period', 'desc')
            ->pluck('period')
            ->all();
        $this->selectedReconPeriod = $this->availablePeriods[0] ?? '';
    }

    public function runReconciliation(): void
    {
        $period = $this->selectedReconPeriod;

        if ($period === '') {
            session()->flash('recon_error', 'Pilih periode yang akan direkonsiliasi terlebih dahulu.');

            return;
        }

        $this->reconciliationData = $this->reconciliationService->runReconciliation($period);

        session()->flash('recon_success', "Rekonsiliasi periode {$period} selesai.");
    }

    public function render(): View
    {
        return view('livewire.kalkulasi.hasil-ckpn-index', [
            'reconciliationData' => $this->reconciliationData,
            'availablePeriods' => $this->availablePeriods,
        ]);
    }
}
