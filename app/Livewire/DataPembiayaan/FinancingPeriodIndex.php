<?php

declare(strict_types=1);

namespace App\Livewire\DataPembiayaan;

use App\Exports\FinancingPeriodExport;
use App\Models\FinancingAccountPeriod;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Ref: PRD Bab 15 - Historis Data Pembiayaan per Periode */
#[Layout('layouts.app', ['title' => 'Historis Pembiayaan'])]
class FinancingPeriodIndex extends Component
{
    use WithPagination;

    public string $filterPeriod = '';

    public string $filterAccount = '';

    public string $filterStatus = '';

    public function updatingFilterPeriod(): void
    {
        $this->resetPage();
    }

    public function updatingFilterAccount(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    /** Export daftar pembiayaan sesuai filter aktif ke Excel. */
    public function exportSourceExcel(): BinaryFileResponse
    {
        $filename = 'daftar-pembiayaan'
            .($this->filterPeriod !== '' ? '-'.$this->filterPeriod : '')
            .'.xlsx';

        return Excel::download(
            new FinancingPeriodExport($this->filterPeriod, $this->filterAccount, $this->filterStatus),
            $filename,
        );
    }

    public function render(): View
    {
        $query = FinancingAccountPeriod::with('financingAccount')
            ->orderByDesc('period')
            ->orderBy('financing_account_id');

        if ($this->filterPeriod !== '') {
            $query->where('period', $this->filterPeriod);
        }

        if ($this->filterAccount !== '') {
            $term = '%'.$this->filterAccount.'%';
            $query->whereHas('financingAccount', function ($q) use ($term) {
                $q->where('account_number', 'like', $term)
                    ->orWhere('customer_name', 'like', $term);
            });
        }

        if ($this->filterStatus !== '') {
            $query->where('financing_status', $this->filterStatus);
        }

        $periods = $query->paginate(20);

        // Daftar periode unik untuk dropdown filter
        $availablePeriods = FinancingAccountPeriod::select('period')
            ->distinct()
            ->orderByDesc('period')
            ->pluck('period');

        return view('livewire.data-pembiayaan.financing-period-index', [
            'periods' => $periods,
            'availablePeriods' => $availablePeriods,
        ]);
    }
}
