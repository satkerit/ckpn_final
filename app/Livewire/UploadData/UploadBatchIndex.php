<?php

declare(strict_types=1);

namespace App\Livewire\UploadData;

use App\Enums\UploadBatchStatus;
use App\Models\FinancingUploadBatch;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Ref: PRD Bab 3 - Riwayat Upload Batch */
#[Layout('layouts.app', ['title' => 'Riwayat Upload'])]
class UploadBatchIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    public string $filterStatus = '';

    public string $filterType = '';

    public string $filterPeriod = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatingFilterType(): void
    {
        $this->resetPage();
    }

    public function updatingFilterPeriod(): void
    {
        $this->resetPage();
    }

    /** Label untuk setiap upload_type */
    public function getUploadTypeLabelsProperty(): array
    {
        return [
            'active_financing' => 'Pembiayaan Aktif',
            'historical_financing' => 'Historis Pembiayaan',
            'collateral' => 'Jaminan',
            'financing_office' => 'Kantor Pembiayaan',
            'collateral_type' => 'Jenis Jaminan',
        ];
    }

    public function render(): View
    {
        $query = FinancingUploadBatch::with('uploadedBy')
            ->orderByDesc('uploaded_at');

        if ($this->filterStatus !== '') {
            $query->where('status', $this->filterStatus);
        }

        if ($this->filterType !== '') {
            $query->where('upload_type', $this->filterType);
        }

        if ($this->filterPeriod !== '') {
            $query->where('period', 'like', "%{$this->filterPeriod}%");
        }

        $query->when($this->search, fn ($q) => $q->where(
            fn ($w) => $w
                ->where('period', 'like', "%{$this->search}%")
                ->orWhere('upload_type', 'like', "%{$this->search}%")
                ->orWhere('status', 'like', "%{$this->search}%")
                ->orWhere('file_name', 'like', "%{$this->search}%")
        ));

        $batches = $query->paginate(20);

        return view('livewire.upload-data.upload-batch-index', [
            'batches' => $batches,
            'statusOptions' => UploadBatchStatus::cases(),
            'typeLabels' => $this->getUploadTypeLabelsProperty(),
        ]);
    }
}
