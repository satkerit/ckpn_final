<?php

declare(strict_types=1);

namespace App\Livewire\MasterData;

use App\Models\Bucket;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app', ['title' => 'Master Bucket'])]
class BucketIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $sortField = 'bucket_order';

    public string $sortDirection = 'asc';

    // Modal state
    public bool $showModal = false;

    public bool $showDeleteConfirm = false;

    public ?int $editingId = null;

    public ?int $deletingId = null;

    // Form fields
    public string $code = '';

    public string $label = '';

    public string $min_days_overdue = '';

    public string $max_days_overdue = '';

    public string $bucket_order = '';

    public bool $is_default_bucket = false;

    protected function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20'],
            'label' => ['required', 'string', 'max:100'],
            'min_days_overdue' => ['required', 'integer', 'min:0'],
            'max_days_overdue' => ['nullable', 'integer', 'min:0', 'gte:min_days_overdue'],
            'bucket_order' => ['required', 'integer', 'min:1'],
            'is_default_bucket' => ['boolean'],
        ];
    }

    protected function messages(): array
    {
        return [
            'code.required' => 'Kode bucket wajib diisi.',
            'code.max' => 'Kode bucket maksimal 20 karakter.',
            'label.required' => 'Label bucket wajib diisi.',
            'label.max' => 'Label bucket maksimal 100 karakter.',
            'min_days_overdue.required' => 'Min hari tunggakan wajib diisi.',
            'min_days_overdue.min' => 'Min hari tunggakan minimal 0.',
            'max_days_overdue.min' => 'Max hari tunggakan minimal 0.',
            'max_days_overdue.gte' => 'Max hari tunggakan harus >= min hari tunggakan.',
            'bucket_order.required' => 'Urutan bucket wajib diisi.',
            'bucket_order.min' => 'Urutan bucket minimal 1.',
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
        $record = Bucket::findOrFail($id);
        $this->editingId = $id;
        $this->code = $record->code;
        $this->label = $record->label;
        $this->min_days_overdue = (string) $record->min_days_overdue;
        $this->max_days_overdue = $record->max_days_overdue !== null ? (string) $record->max_days_overdue : '';
        $this->bucket_order = (string) $record->bucket_order;
        $this->is_default_bucket = (bool) $record->is_default_bucket;
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'code' => $this->code,
            'label' => $this->label,
            'min_days_overdue' => (int) $this->min_days_overdue,
            'max_days_overdue' => $this->max_days_overdue !== '' ? (int) $this->max_days_overdue : null,
            'bucket_order' => (int) $this->bucket_order,
            'is_default_bucket' => $this->is_default_bucket,
        ];

        if ($this->editingId) {
            Bucket::findOrFail($this->editingId)->update($data);
        } else {
            Bucket::create($data);
        }

        $action = $this->editingId ? 'diperbarui' : 'ditambahkan';
        $this->closeModal();
        $this->dispatch('notify', type: 'success', message: "Bucket berhasil {$action}.");
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingId = $id;
        $this->showDeleteConfirm = true;
    }

    public function delete(): void
    {
        if ($this->deletingId) {
            Bucket::findOrFail($this->deletingId)->delete();
            $this->dispatch('notify', type: 'success', message: 'Bucket berhasil dihapus.');
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
        $this->min_days_overdue = '';
        $this->max_days_overdue = '';
        $this->bucket_order = '';
        $this->is_default_bucket = false;
    }

    public function render(): View
    {
        $allowedSortFields = ['code', 'label', 'min_days_overdue', 'max_days_overdue', 'bucket_order'];
        $sortField = in_array($this->sortField, $allowedSortFields, true) ? $this->sortField : 'bucket_order';

        $records = Bucket::query()
            ->when($this->search, fn ($q) => $q
                ->where('code', 'like', '%'.$this->search.'%')
                ->orWhere('label', 'like', '%'.$this->search.'%')
            )
            ->orderBy($sortField, $this->sortDirection)
            ->paginate(15);

        return view('livewire.master-data.bucket-index', compact('records'));
    }
}
