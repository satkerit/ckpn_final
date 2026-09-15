<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\FinancingAccountPeriod;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class FinancingAccountPeriodPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:FinancingAccountPeriod');
    }

    public function view(AuthUser $authUser, FinancingAccountPeriod $financingAccountPeriod): bool
    {
        return $authUser->can('View:FinancingAccountPeriod');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:FinancingAccountPeriod');
    }

    public function update(AuthUser $authUser, FinancingAccountPeriod $financingAccountPeriod): bool
    {
        return $authUser->can('Update:FinancingAccountPeriod');
    }

    public function delete(AuthUser $authUser, FinancingAccountPeriod $financingAccountPeriod): bool
    {
        return $authUser->can('Delete:FinancingAccountPeriod');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:FinancingAccountPeriod');
    }

    public function restore(AuthUser $authUser, FinancingAccountPeriod $financingAccountPeriod): bool
    {
        return $authUser->can('Restore:FinancingAccountPeriod');
    }

    public function forceDelete(AuthUser $authUser, FinancingAccountPeriod $financingAccountPeriod): bool
    {
        return $authUser->can('ForceDelete:FinancingAccountPeriod');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:FinancingAccountPeriod');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:FinancingAccountPeriod');
    }

    public function replicate(AuthUser $authUser, FinancingAccountPeriod $financingAccountPeriod): bool
    {
        return $authUser->can('Replicate:FinancingAccountPeriod');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:FinancingAccountPeriod');
    }
}
