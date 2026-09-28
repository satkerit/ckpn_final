<?php

declare(strict_types=1);

namespace App\Livewire\Ckpn;

use App\Domain\Ckpn\Preview\CkpnPreviewResult;
use App\Domain\Ckpn\Preview\CkpnPreviewService;
use App\Exports\CkpnPreviewExport;
use App\Models\CalculationGeneralSetting;
use App\Models\CkpnCollectiveResult;
use App\Models\CkpnIndividualResult;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Preview kalkulasi CKPN read-only (PD x LGD x EAD) per periode,
 * dipilah penelaahan Individual (Top-N outstanding) vs Kolektif.
 */
#[Layout('layouts.app', ['title' => 'Preview CKPN'])]
class CkpnPreviewIndex extends Component
{
    public string $period = '';

    public string $search = '';

    public int $topN = 10;

    public int $kolPage = 1;

    /** Jumlah baris kolektif per halaman (preview — bukan snapshot). */
    public int $perPage = 20;

    public function mount(): void
    {
        $this->topN = max(1, CalculationGeneralSetting::intValue('ckpn_individual_top_n_outstanding', 10));

        $this->period = (string) (self::ckpnResultPeriods()->first() ?? '');
    }

    public function updatingTopN(): void
    {
        $this->kolPage = 1;
    }

    public function updatingPeriod(): void
    {
        $this->kolPage = 1;
    }

    public function updatedTopN(): void
    {
        $this->topN = min(200, max(1, $this->topN));
    }

    public function prevKolPage(): void
    {
        if ($this->kolPage > 1) {
            $this->kolPage--;
        }
    }

    public function nextKolPage(): void
    {
        $this->kolPage++;
    }

    public function exportExcel(): BinaryFileResponse
    {
        $result = app(CkpnPreviewService::class)->build($this->period, $this->topN);

        $filename = sprintf('ckpn_preview_nasabah_%s.xlsx', $this->period);

        return Excel::download(new CkpnPreviewExport($result), $filename);
    }

    /** Periode yang sudah ada snapshot hasil CKPN (individual atau kolektif), desc. */
    private static function ckpnResultPeriods(): Collection
    {
        $individual = CkpnIndividualResult::query()
            ->select('calculation_period as period')
            ->distinct();

        return CkpnCollectiveResult::query()
            ->select('calculation_period as period')
            ->distinct()
            ->union($individual)
            ->orderByDesc('period')
            ->limit(24)
            ->pluck('period');
    }

    public function render(): View
    {
        $periods = self::ckpnResultPeriods();

        $result = null;
        $collectivePage = null;

        if ($this->period !== '') {
            $result = app(CkpnPreviewService::class)->build($this->period, $this->topN);

            if ($this->search !== '') {
                $term = strtolower($this->search);
                $filteredIndividual = $result->individual->filter(fn ($r) => str_contains(strtolower($r->accountNumber), $term) || str_contains(strtolower($r->customerName), $term))->values();
                $filteredCollective = $result->collective->filter(fn ($r) => str_contains(strtolower($r->accountNumber), $term) || str_contains(strtolower($r->customerName), $term))->values();
                $result = new CkpnPreviewResult(
                    period: $result->period,
                    topN: $result->topN,
                    individual: $filteredIndividual,
                    collective: $filteredCollective,
                    totalEad: (float) $filteredIndividual->sum(fn ($r) => $r->ead) + (float) $filteredCollective->sum(fn ($r) => $r->ead),
                    totalCkpnIndividual: (float) $filteredIndividual->sum(fn ($r) => $r->ckpnAmount),
                    totalCkpnCollective: (float) $filteredCollective->sum(fn ($r) => $r->ckpnAmount),
                );
            }

            $collectivePage = $this->paginateCollective($result);
        }

        return view('livewire.ckpn.ckpn-preview-index', [
            'result' => $result,
            'periods' => $periods,
            'collectivePage' => $collectivePage,
        ]);
    }

    /** Potong collection kolektif jadi halaman manual (read-only preview, tanpa DB pagination). */
    private function paginateCollective(CkpnPreviewResult $result): LengthAwarePaginator
    {
        $total = $result->collective->count();
        $lastPage = max(1, (int) ceil($total / $this->perPage));
        $this->kolPage = min(max(1, $this->kolPage), $lastPage);

        return new LengthAwarePaginator(
            $result->collective->forPage($this->kolPage, $this->perPage)->values(),
            $total,
            $this->perPage,
            $this->kolPage,
            ['pageName' => 'kolPage'],
        );
    }
}
