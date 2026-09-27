<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

/** Ref: PRD FR-14 — manajemen user & role */
#[Layout('layouts.app', ['title' => 'Manajemen User'])]
class UserIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterRole = '';

    // Modal state
    public bool $showModal = false;

    public bool $showDeleteConfirm = false;

    public ?int $editingId = null;

    public ?int $deletingId = null;

    public ?string $deletingUserName = null;

    // Form fields
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public array $selectedRoles = [];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterRole(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->filterRole = '';
        $this->resetPage();
    }

    protected function rules(): array
    {
        $emailRule = $this->editingId
            ? ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)]
            : ['required', 'email', 'max:255', 'unique:users,email'];

        $passwordRule = $this->editingId
            ? ['nullable', 'string', 'min:8']
            : ['required', 'string', 'min:8'];

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => $emailRule,
            'password' => $passwordRule,
            'selectedRoles' => ['array'],
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'Nama pengguna wajib diisi.',
            'email.required' => 'Alamat email wajib diisi.',
            'email.email' => 'Format alamat email tidak valid.',
            'email.unique' => 'Alamat email ini sudah digunakan.',
            'password.required' => 'Kata sandi wajib diisi untuk user baru.',
            'password.min' => 'Kata sandi minimal terdiri dari 8 karakter.',
        ];
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->editingId = null;
        $this->showModal = true;
    }

    public function openEdit(int $id): void
    {
        $user = User::findOrFail($id);
        $this->editingId = $id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->selectedRoles = $user->roles->pluck('name')->toArray();
        $this->showModal = true;
        $this->resetErrorBag();
    }

    public function save(): void
    {
        // Ref: AGENTS.md Bab 5.5 — otorisasi lewat Policy, bukan hardcode role
        $this->authorize($this->editingId ? 'update' : 'create', User::class);

        $this->validate();

        if ($this->editingId) {
            $user = User::findOrFail($this->editingId);
            $data = ['name' => $this->name, 'email' => $this->email];
            if ($this->password !== '') {
                $data['password'] = Hash::make($this->password);
            }
            $user->update($data);
            $user->syncRoles($this->selectedRoles);
            $action = 'diperbarui';
        } else {
            $user = User::create([
                'name' => $this->name,
                'email' => $this->email,
                'password' => Hash::make($this->password),
            ]);
            $user->syncRoles($this->selectedRoles);
            $action = 'dibuat';
        }

        $this->closeModal();
        $this->dispatch('notify', type: 'success', message: "User {$user->name} berhasil {$action}.");
    }

    public function openDelete(int $id): void
    {
        $user = User::findOrFail($id);
        $this->deletingId = $id;
        $this->deletingUserName = $user->name;
        $this->showDeleteConfirm = true;
    }

    public function delete(): void
    {
        if ($this->deletingId === null) {
            return;
        }

        // Jangan hapus diri sendiri
        if ($this->deletingId === auth()->id()) {
            $this->dispatch('notify', type: 'error', message: 'Anda tidak dapat menghapus akun Anda sendiri.');
            $this->closeModal();

            return;
        }

        $user = User::findOrFail($this->deletingId);
        $this->authorize('delete', $user);

        $userName = $user->name;
        $user->delete();

        $this->dispatch('notify', type: 'success', message: "User {$userName} berhasil dihapus.");
        $this->closeModal();
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->showDeleteConfirm = false;
        $this->deletingId = null;
        $this->deletingUserName = null;
        $this->resetForm();
        $this->resetErrorBag();
    }

    private function resetForm(): void
    {
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->selectedRoles = [];
    }

    public function render(): View
    {
        $records = User::query()
            ->with('roles')
            ->when(
                $this->search,
                fn ($q) => $q->where(function ($sub) {
                    $sub->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%');
                })
            )
            ->when(
                $this->filterRole !== '',
                fn ($q) => $q->whereHas('roles', fn ($rq) => $rq->where('name', $this->filterRole))
            )
            ->orderBy('name')
            ->paginate(15);

        $allRoles = Role::orderBy('name')->get();

        // User stats untuk header cards
        $stats = (object) [
            'total' => User::count(),
            'admins' => User::role('super_admin')->count(),
            'analysts' => User::role('risk_analyst')->count(),
            'approvers' => User::role('approver')->count(),
        ];

        return view('livewire.admin.user-index', compact('records', 'allRoles', 'stats'));
    }
}
