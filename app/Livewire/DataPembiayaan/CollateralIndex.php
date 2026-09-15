<?php

declare(strict_types=1);

namespace App\Livewire\DataPembiayaan;

use App\Models\Collateral;
use App\Models\CollateralType;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/** Ref: PRD Bab 10 - Data Jaminan (Collateral) */
#[Layout('layouts.app', ['title' => 'Data Jaminan'])]
class CollateralIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterActive = '';

    public string $filterType = '';

    public ?int $deletingId = null;

    public bool $confirmingDeleteAll = false;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterActive(): void
    {
        $this->resetPage();
    }

    public function updatingFilterType(): void
    {
        $this->resetPage();
    }

    public function konfirmasiHapus(int $id): void
    {
        $this->deletingId = $id;
    }

    public function hapus(): void
    {
        if ($this->deletingId === null) {
            return;
        }

        $collateral = Collateral::find($this->deletingId);

        if ($collateral) {
            $collateral->delete();
            $this->dispatch('notify', type: 'success', message: 'Data jaminan berhasil dihapus.');
        }

        $this->deletingId = null;
    }

    public function konfirmasiHapusSemua(): void
    {
        $this->confirmingDeleteAll = true;
    }

    public function hapusSemuaData(): void
    {
        Collateral::query()->delete();
        $this->confirmingDeleteAll = false;
        $this->resetPage();
        $this->dispatch('notify', type: 'success', message: 'Seluruh data jaminan berhasil dihapus.');
    }

    public function render(): View
    {
        $query = Collateral::with(['financingAccount', 'collateralType'])
            ->orderBy('collateral_code');

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('collateral_code', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhereHas('financingAccount', fn ($a) => $a->where('account_number', 'like', $term)
                        ->orWhere('customer_name', 'like', $term));
            });
        }

        if ($this->filterActive !== '') {
            $query->where('is_active', $this->filterActive === '1');
        }

        if ($this->filterType !== '') {
            $query->where('collateral_type_id', $this->filterType);
        }

        $collaterals = $query->paginate(20);

        // Daftar tipe jaminan untuk filter dropdown
        $collateralTypes = CollateralType::orderBy('name')->get(['id', 'name']);

        return view('livewire.data-pembiayaan.collateral-index', [
            'collaterals' => $collaterals,
            'collateralTypes' => $collateralTypes,
        ]);
    }
}
