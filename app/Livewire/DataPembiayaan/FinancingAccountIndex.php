<?php

declare(strict_types=1);

namespace App\Livewire\DataPembiayaan;

use App\Enums\UsageType;
use App\Models\FinancingAccount;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/** Ref: PRD Bab 5 - Data Akun Pembiayaan */
#[Layout('layouts.app', ['title' => 'Data Akun Pembiayaan'])]
class FinancingAccountIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterUsage = '';

    public string $filterActive = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterUsage(): void
    {
        $this->resetPage();
    }

    public function updatingFilterActive(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $query = FinancingAccount::query()->orderBy('account_number');

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('account_number', 'like', $term)
                    ->orWhere('customer_name', 'like', $term);
            });
        }

        if ($this->filterUsage !== '') {
            $query->where('usage_type', (int) $this->filterUsage);
        }

        if ($this->filterActive !== '') {
            $query->where('is_active', $this->filterActive === '1');
        }

        $accounts = $query->paginate(20);

        return view('livewire.data-pembiayaan.financing-account-index', [
            'accounts' => $accounts,
            'usageTypes' => UsageType::cases(),
        ]);
    }
}
