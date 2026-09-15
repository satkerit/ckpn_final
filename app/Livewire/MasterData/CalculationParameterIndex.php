<?php

declare(strict_types=1);

namespace App\Livewire\MasterData;

use App\Enums\UsageType;
use App\Models\CalculationParameter;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app', ['title' => 'Parameter Kalkulasi'])]
class CalculationParameterIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $sortField = 'parameter_key';

    public string $sortDirection = 'asc';

    // Modal state
    public bool $showModal = false;

    public bool $showDeleteConfirm = false;

    public ?int $editingId = null;

    public ?int $deletingId = null;

    // Form fields
    public string $usage_type = '';

    public string $parameter_key = '';

    public string $parameter_value = '';

    public string $description = '';

    protected function rules(): array
    {
        return [
            'usage_type' => ['nullable', 'integer', 'in:1,2,3'],
            'parameter_key' => ['required', 'string', 'max:100'],
            'parameter_value' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function messages(): array
    {
        return [
            'usage_type.required' => 'Usage type wajib diisi.',
            'usage_type.in' => 'Usage type tidak valid.',
            'parameter_key.required' => 'Parameter key wajib diisi.',
            'parameter_key.max' => 'Parameter key maksimal 100 karakter.',
            'parameter_value.required' => 'Nilai parameter wajib diisi.',
            'parameter_value.max' => 'Nilai parameter maksimal 255 karakter.',
            'description.max' => 'Deskripsi maksimal 500 karakter.',
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
        $record = CalculationParameter::findOrFail($id);
        $this->editingId = $id;
        // usage_type null = parameter global (berlaku semua usage type)
        $this->usage_type = (string) ($record->usage_type?->value ?? '');
        $this->parameter_key = $record->parameter_key;
        $this->parameter_value = $record->parameter_value;
        $this->description = $record->description ?? '';
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'usage_type' => $this->usage_type === '' ? null : (int) $this->usage_type,
            'parameter_key' => $this->parameter_key,
            'parameter_value' => $this->parameter_value,
            'description' => $this->description ?: null,
        ];

        if ($this->editingId) {
            CalculationParameter::findOrFail($this->editingId)->update($data);
        } else {
            CalculationParameter::create($data);
        }

        $action = $this->editingId ? 'diperbarui' : 'ditambahkan';
        $this->closeModal();
        $this->dispatch('notify', type: 'success', message: "Parameter berhasil {$action}.");
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;
        $this->showDeleteConfirm = true;
    }

    public function delete(): void
    {
        if ($this->deletingId) {
            CalculationParameter::findOrFail($this->deletingId)->delete();
            $this->dispatch('notify', type: 'success', message: 'Parameter berhasil dihapus.');
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
        $this->usage_type = '';
        $this->parameter_key = '';
        $this->parameter_value = '';
        $this->description = '';
    }

    public function render(): View
    {
        $allowedSortFields = ['parameter_key', 'usage_type', 'parameter_value', 'created_at'];
        $sortField = in_array($this->sortField, $allowedSortFields, true) ? $this->sortField : 'parameter_key';

        $records = CalculationParameter::query()
            ->when(
                $this->search,
                fn ($q) => $q
                    ->where('parameter_key', 'like', '%'.$this->search.'%')
                    ->orWhere('parameter_value', 'like', '%'.$this->search.'%')
                    ->orWhere('description', 'like', '%'.$this->search.'%')
            )
            ->orderBy($sortField, $this->sortDirection)
            ->paginate(15);

        $usageTypes = UsageType::cases();

        return view('livewire.master-data.calculation-parameter-index', compact('records', 'usageTypes'));
    }
}
