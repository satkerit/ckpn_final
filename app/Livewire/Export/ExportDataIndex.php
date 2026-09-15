<?php

declare(strict_types=1);

namespace App\Livewire\Export;

use App\Enums\UsageType;
use App\Jobs\CsvExportJob;
use App\Models\ExportJob;
use App\Models\FinancingAccountPeriod;
use App\Models\LgdCollateralShortfallResult;
use App\Models\LgdExpectedRecoveriesResult;
use App\Models\PdNetflowResult;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Halaman terpusat untuk export data sumber 4 kalkulasi CKPN */
#[Layout('layouts.app', ['title' => 'Export Data'])]
class ExportDataIndex extends Component
{
    public string $filterPeriode = '';

    public string $filterUsageType = '';

    public ?int $exportJobId = null;

    public string $exportStatus = '';

    public string $exportFilename = '';

    public string $exportError = '';

    public function exportDaftarPembiayaan(): void
    {
        $this->queueExport('financing');
    }

    public function exportHistoryPembiayaan(): void
    {
        $this->queueExport('financing_history');
    }

    public function exportPdNetflow(): void
    {
        $this->queueExport('pd_netflow');
    }

    public function exportLgdEr(): void
    {
        $this->queueExport('lgd_er');
    }

    public function exportLgdCs(): void
    {
        $this->queueExport('lgd_cs');
    }

    public function pollExportStatus(): void
    {
        if ($this->exportJobId !== null) {
            $job = ExportJob::find($this->exportJobId);
            $this->exportStatus = $job?->status ?? 'failed';
            $this->exportFilename = $job?->filename ?? '';
            $this->exportError = $job?->error_message ?? '';
        }
    }

    private function queueExport(string $type): void
    {
        if ($this->filterPeriode === '') {
            $this->addError('filterPeriode', 'Pilih periode kalkulasi terlebih dahulu.');

            return;
        }

        $job = ExportJob::create([
            'type' => $type,
            'status' => 'pending',
            'params' => ['periode' => $this->filterPeriode, 'usage_type' => $this->filterUsageType],
        ]);
        $this->exportJobId = $job->id;
        CsvExportJob::dispatch($job->id, $type, $job->params);
    }

    public function render(): View
    {
        // Kumpulkan daftar periode dari semua sumber agar dropdown lengkap
        $periodePembiayaan = FinancingAccountPeriod::query()
            ->select('period')
            ->distinct()
            ->orderByDesc('period')
            ->pluck('period');

        $periodePdNetflow = PdNetflowResult::query()
            ->select('calculation_period')
            ->distinct()
            ->orderByDesc('calculation_period')
            ->pluck('calculation_period');

        $periodeLgdEr = LgdExpectedRecoveriesResult::query()
            ->select('calculation_period')
            ->distinct()
            ->orderByDesc('calculation_period')
            ->pluck('calculation_period');

        $periodeLgdCs = LgdCollateralShortfallResult::query()
            ->select('calculation_period')
            ->distinct()
            ->orderByDesc('calculation_period')
            ->pluck('calculation_period');

        // Gabungkan semua periode unik untuk dropdown filter global
        $allPeriodes = $periodePembiayaan
            ->merge($periodePdNetflow)
            ->merge($periodeLgdEr)
            ->merge($periodeLgdCs)
            ->unique()
            ->sortDesc()
            ->values();

        $usageTypes = UsageType::cases();

        return view('livewire.export.export-data-index', [
            'allPeriodes' => $allPeriodes,
            'usageTypes' => $usageTypes,
        ]);
    }
}
