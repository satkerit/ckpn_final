<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LgdCollateralShortfallResult;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class LgdCollateralShortfallResultPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LgdCollateralShortfallResult');
    }

    public function view(AuthUser $authUser, LgdCollateralShortfallResult $lgdCollateralShortfallResult): bool
    {
        return $authUser->can('View:LgdCollateralShortfallResult');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LgdCollateralShortfallResult');
    }

    public function update(AuthUser $authUser, LgdCollateralShortfallResult $lgdCollateralShortfallResult): bool
    {
        return $authUser->can('Update:LgdCollateralShortfallResult');
    }

    public function delete(AuthUser $authUser, LgdCollateralShortfallResult $lgdCollateralShortfallResult): bool
    {
        return $authUser->can('Delete:LgdCollateralShortfallResult');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:LgdCollateralShortfallResult');
    }

    public function restore(AuthUser $authUser, LgdCollateralShortfallResult $lgdCollateralShortfallResult): bool
    {
        return $authUser->can('Restore:LgdCollateralShortfallResult');
    }

    public function forceDelete(AuthUser $authUser, LgdCollateralShortfallResult $lgdCollateralShortfallResult): bool
    {
        return $authUser->can('ForceDelete:LgdCollateralShortfallResult');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:LgdCollateralShortfallResult');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:LgdCollateralShortfallResult');
    }

    public function replicate(AuthUser $authUser, LgdCollateralShortfallResult $lgdCollateralShortfallResult): bool
    {
        return $authUser->can('Replicate:LgdCollateralShortfallResult');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:LgdCollateralShortfallResult');
    }
}
