<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\FinancingOffice;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class FinancingOfficePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:FinancingOffice');
    }

    public function view(AuthUser $authUser, FinancingOffice $financingOffice): bool
    {
        return $authUser->can('View:FinancingOffice');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:FinancingOffice');
    }

    public function update(AuthUser $authUser, FinancingOffice $financingOffice): bool
    {
        return $authUser->can('Update:FinancingOffice');
    }

    public function delete(AuthUser $authUser, FinancingOffice $financingOffice): bool
    {
        return $authUser->can('Delete:FinancingOffice');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:FinancingOffice');
    }

    public function restore(AuthUser $authUser, FinancingOffice $financingOffice): bool
    {
        return $authUser->can('Restore:FinancingOffice');
    }

    public function forceDelete(AuthUser $authUser, FinancingOffice $financingOffice): bool
    {
        return $authUser->can('ForceDelete:FinancingOffice');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:FinancingOffice');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:FinancingOffice');
    }

    public function replicate(AuthUser $authUser, FinancingOffice $financingOffice): bool
    {
        return $authUser->can('Replicate:FinancingOffice');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:FinancingOffice');
    }
}
