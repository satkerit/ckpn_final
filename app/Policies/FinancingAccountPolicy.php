<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\FinancingAccount;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class FinancingAccountPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:FinancingAccount');
    }

    public function view(AuthUser $authUser, FinancingAccount $financingAccount): bool
    {
        return $authUser->can('View:FinancingAccount');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:FinancingAccount');
    }

    public function update(AuthUser $authUser, FinancingAccount $financingAccount): bool
    {
        return $authUser->can('Update:FinancingAccount');
    }

    public function delete(AuthUser $authUser, FinancingAccount $financingAccount): bool
    {
        return $authUser->can('Delete:FinancingAccount');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:FinancingAccount');
    }

    public function restore(AuthUser $authUser, FinancingAccount $financingAccount): bool
    {
        return $authUser->can('Restore:FinancingAccount');
    }

    public function forceDelete(AuthUser $authUser, FinancingAccount $financingAccount): bool
    {
        return $authUser->can('ForceDelete:FinancingAccount');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:FinancingAccount');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:FinancingAccount');
    }

    public function replicate(AuthUser $authUser, FinancingAccount $financingAccount): bool
    {
        return $authUser->can('Replicate:FinancingAccount');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:FinancingAccount');
    }
}
