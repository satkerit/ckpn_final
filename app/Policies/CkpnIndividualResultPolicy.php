<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CkpnIndividualResult;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class CkpnIndividualResultPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CkpnIndividualResult');
    }

    public function view(AuthUser $authUser, CkpnIndividualResult $ckpnIndividualResult): bool
    {
        return $authUser->can('View:CkpnIndividualResult');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CkpnIndividualResult');
    }

    public function update(AuthUser $authUser, CkpnIndividualResult $ckpnIndividualResult): bool
    {
        return $authUser->can('Update:CkpnIndividualResult');
    }

    public function delete(AuthUser $authUser, CkpnIndividualResult $ckpnIndividualResult): bool
    {
        return $authUser->can('Delete:CkpnIndividualResult');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CkpnIndividualResult');
    }

    public function restore(AuthUser $authUser, CkpnIndividualResult $ckpnIndividualResult): bool
    {
        return $authUser->can('Restore:CkpnIndividualResult');
    }

    public function forceDelete(AuthUser $authUser, CkpnIndividualResult $ckpnIndividualResult): bool
    {
        return $authUser->can('ForceDelete:CkpnIndividualResult');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CkpnIndividualResult');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CkpnIndividualResult');
    }

    public function replicate(AuthUser $authUser, CkpnIndividualResult $ckpnIndividualResult): bool
    {
        return $authUser->can('Replicate:CkpnIndividualResult');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CkpnIndividualResult');
    }
}
