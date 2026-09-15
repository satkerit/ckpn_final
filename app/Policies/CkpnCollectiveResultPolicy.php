<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CkpnCollectiveResult;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class CkpnCollectiveResultPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CkpnCollectiveResult');
    }

    public function view(AuthUser $authUser, CkpnCollectiveResult $ckpnCollectiveResult): bool
    {
        return $authUser->can('View:CkpnCollectiveResult');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CkpnCollectiveResult');
    }

    public function update(AuthUser $authUser, CkpnCollectiveResult $ckpnCollectiveResult): bool
    {
        return $authUser->can('Update:CkpnCollectiveResult');
    }

    public function delete(AuthUser $authUser, CkpnCollectiveResult $ckpnCollectiveResult): bool
    {
        return $authUser->can('Delete:CkpnCollectiveResult');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CkpnCollectiveResult');
    }

    public function restore(AuthUser $authUser, CkpnCollectiveResult $ckpnCollectiveResult): bool
    {
        return $authUser->can('Restore:CkpnCollectiveResult');
    }

    public function forceDelete(AuthUser $authUser, CkpnCollectiveResult $ckpnCollectiveResult): bool
    {
        return $authUser->can('ForceDelete:CkpnCollectiveResult');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CkpnCollectiveResult');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CkpnCollectiveResult');
    }

    public function replicate(AuthUser $authUser, CkpnCollectiveResult $ckpnCollectiveResult): bool
    {
        return $authUser->can('Replicate:CkpnCollectiveResult');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CkpnCollectiveResult');
    }
}
