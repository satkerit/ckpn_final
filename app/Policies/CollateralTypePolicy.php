<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CollateralType;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class CollateralTypePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CollateralType');
    }

    public function view(AuthUser $authUser, CollateralType $collateralType): bool
    {
        return $authUser->can('View:CollateralType');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CollateralType');
    }

    public function update(AuthUser $authUser, CollateralType $collateralType): bool
    {
        return $authUser->can('Update:CollateralType');
    }

    public function delete(AuthUser $authUser, CollateralType $collateralType): bool
    {
        return $authUser->can('Delete:CollateralType');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CollateralType');
    }

    public function restore(AuthUser $authUser, CollateralType $collateralType): bool
    {
        return $authUser->can('Restore:CollateralType');
    }

    public function forceDelete(AuthUser $authUser, CollateralType $collateralType): bool
    {
        return $authUser->can('ForceDelete:CollateralType');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CollateralType');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CollateralType');
    }

    public function replicate(AuthUser $authUser, CollateralType $collateralType): bool
    {
        return $authUser->can('Replicate:CollateralType');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CollateralType');
    }
}
