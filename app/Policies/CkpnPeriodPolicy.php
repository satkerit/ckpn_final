<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CkpnPeriod;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class CkpnPeriodPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CkpnPeriod');
    }

    public function view(AuthUser $authUser, CkpnPeriod $ckpnPeriod): bool
    {
        return $authUser->can('View:CkpnPeriod');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CkpnPeriod');
    }

    public function update(AuthUser $authUser, CkpnPeriod $ckpnPeriod): bool
    {
        return $authUser->can('Update:CkpnPeriod');
    }

    public function delete(AuthUser $authUser, CkpnPeriod $ckpnPeriod): bool
    {
        return $authUser->can('Delete:CkpnPeriod');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CkpnPeriod');
    }

    public function restore(AuthUser $authUser, CkpnPeriod $ckpnPeriod): bool
    {
        return $authUser->can('Restore:CkpnPeriod');
    }

    public function forceDelete(AuthUser $authUser, CkpnPeriod $ckpnPeriod): bool
    {
        return $authUser->can('ForceDelete:CkpnPeriod');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CkpnPeriod');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CkpnPeriod');
    }

    public function replicate(AuthUser $authUser, CkpnPeriod $ckpnPeriod): bool
    {
        return $authUser->can('Replicate:CkpnPeriod');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CkpnPeriod');
    }
}
