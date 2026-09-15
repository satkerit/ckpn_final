<?php

declare(strict_types=1);

namespace App\Livewire\MasterData;

use App\Models\QualityGrade;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app', ['title' => 'Master Quality Grade'])]
class QualityGradeIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $sortField = 'collectibility_number';

    public string $sortDirection = 'asc';

    // Modal state
    public bool $showModal = false;

    public bool $showDeleteConfirm = false;

    public ?int $editingId = null;

    public ?int $deletingId = null;

    // Form fields
    public string $code = '';

    public string $label = '';

    public string $collectibility_number = '';

    public bool $is_npl = false;

    protected function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20'],
            'label' => ['required', 'string', 'max:100'],
            'collectibility_number' => ['required', 'integer', 'min:1'],
            'is_npl' => ['boolean'],
        ];
    }

    protected function messages(): array
    {
        return [
            'code.required' => 'Kode wajib diisi.',
            'code.max' => 'Kode maksimal 20 karakter.',
            'label.required' => 'Label wajib diisi.',
            'label.max' => 'Label maksimal 100 karakter.',
            'collectibility_number.required' => 'Nomor kolektibilitas wajib diisi.',
            'collectibility_number.min' => 'Nomor kolektibilitas minimal 1.',
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
        $record = QualityGrade::findOrFail($id);
        $this->editingId = $id;
        $this->code = $record->code;
        $this->label = $record->label;
        $this->collectibility_number = (string) $record->collectibility_number;
        $this->is_npl = (bool) $record->is_npl;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'code' => $this->code,
            'label' => $this->label,
            'collectibility_number' => (int) $this->collectibility_number,
            'is_npl' => $this->is_npl,
        ];

        if ($this->editingId) {
            QualityGrade::findOrFail($this->editingId)->update($data);
        } else {
            QualityGrade::create($data);
        }

        $action = $this->editingId ? 'diperbarui' : 'ditambahkan';
        $this->closeModal();
        $this->dispatch('notify', type: 'success', message: "Quality grade berhasil {$action}.");
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;
        $this->showDeleteConfirm = true;
    }

    public function delete(): void
    {
        if ($this->deletingId) {
            QualityGrade::findOrFail($this->deletingId)->delete();
            $this->dispatch('notify', type: 'success', message: 'Quality grade berhasil dihapus.');
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
        $this->label = '';
        $this->collectibility_number = '';
        $this->is_npl = false;
    }

    public function render(): View
    {
        $allowedSortFields = ['code', 'label', 'collectibility_number'];
        $sortField = in_array($this->sortField, $allowedSortFields, true) ? $this->sortField : 'collectibility_number';

        $records = QualityGrade::query()
            ->when(
                $this->search,
                fn ($q) => $q
                    ->where('code', 'like', '%'.$this->search.'%')
                    ->orWhere('label', 'like', '%'.$this->search.'%')
            )
            ->orderBy($sortField, $this->sortDirection)
            ->paginate(15);

        return view('livewire.master-data.quality-grade-index', compact('records'));
    }
}
