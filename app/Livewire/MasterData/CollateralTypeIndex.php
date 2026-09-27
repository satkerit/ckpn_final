<?php

declare(strict_types=1);

namespace App\Livewire\MasterData;

use App\Models\CollateralType;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app', ['title' => 'Master Jenis Agunan'])]
class CollateralTypeIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $sortField = 'code';

    public string $sortDirection = 'asc';

    // Modal state
    public bool $showModal = false;

    public bool $showDeleteConfirm = false;

    public ?int $editingId = null;

    public ?int $deletingId = null;

    // Form fields
    public string $code = '';

    public string $name = '';

    public string $liquidation_discount_rate = '';

    public bool $is_active = true;

    protected function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:150'],
            'liquidation_discount_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'is_active' => ['boolean'],
        ];
    }

    protected function messages(): array
    {
        return [
            'code.required' => 'Kode wajib diisi.',
            'code.max' => 'Kode maksimal 20 karakter.',
            'name.required' => 'Nama jenis agunan wajib diisi.',
            'name.max' => 'Nama maksimal 150 karakter.',
            'liquidation_discount_rate.required' => 'Discount rate wajib diisi.',
            'liquidation_discount_rate.numeric' => 'Discount rate harus berupa angka.',
            'liquidation_discount_rate.min' => 'Discount rate minimal 0.',
            'liquidation_discount_rate.max' => 'Discount rate maksimal 1 (100%).',
        ];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function sort(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->editingId = null;
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $record = CollateralType::findOrFail($id);
        $this->editingId = $id;
        $this->code = $record->code;
        $this->name = $record->name;
        $this->liquidation_discount_rate = (string) $record->liquidation_discount_rate;
        $this->is_active = (bool) $record->is_active;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize($this->editingId ? 'update' : 'create', CollateralType::class);

        $this->validate();

        $data = [
            'code' => $this->code,
            'name' => $this->name,
            'liquidation_discount_rate' => (float) $this->liquidation_discount_rate,
            'is_active' => $this->is_active,
        ];

        if ($this->editingId) {
            CollateralType::findOrFail($this->editingId)->update($data);
        } else {
            CollateralType::create($data);
        }

        $action = $this->editingId ? 'diperbarui' : 'ditambahkan';
        $this->closeModal();
        $this->dispatch('notify', type: 'success', message: "Jenis agunan berhasil {$action}.");
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;
        $this->showDeleteConfirm = true;
    }

    public function delete(): void
    {
        if ($this->deletingId) {
            $type = CollateralType::findOrFail($this->deletingId);
            $this->authorize('delete', $type);
            $type->delete();
            $this->dispatch('notify', type: 'success', message: 'Jenis agunan berhasil dihapus.');
        }
        $this->showDeleteConfirm = false;
        $this->deletingId = null;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->showDeleteConfirm = false;
        $this->editingId = null;
        $this->deletingId = null;
        $this->resetForm();
        $this->resetErrorBag();
    }

    private function resetForm(): void
    {
        $this->code = '';
        $this->name = '';
        $this->liquidation_discount_rate = '';
        $this->is_active = true;
    }

    public function render(): View
    {
        $allowedSortFields = ['code', 'name', 'liquidation_discount_rate', 'is_active'];
        $sortField = in_array($this->sortField, $allowedSortFields, true) ? $this->sortField : 'code';

        $records = CollateralType::query()
            ->when($this->search, fn ($q) => $q
                ->where('code', 'like', '%'.$this->search.'%')
                ->orWhere('name', 'like', '%'.$this->search.'%')
            )
            ->orderBy($sortField, $this->sortDirection)
            ->paginate(15);

        return view('livewire.master-data.collateral-type-index', compact('records'));
    }
}
