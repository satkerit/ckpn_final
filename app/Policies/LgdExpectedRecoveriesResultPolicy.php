<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LgdExpectedRecoveriesResult;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class LgdExpectedRecoveriesResultPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LgdExpectedRecoveriesResult');
    }

    public function view(AuthUser $authUser, LgdExpectedRecoveriesResult $lgdExpectedRecoveriesResult): bool
    {
        return $authUser->can('View:LgdExpectedRecoveriesResult');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LgdExpectedRecoveriesResult');
    }

    public function update(AuthUser $authUser, LgdExpectedRecoveriesResult $lgdExpectedRecoveriesResult): bool
    {
        return $authUser->can('Update:LgdExpectedRecoveriesResult');
    }

    public function delete(AuthUser $authUser, LgdExpectedRecoveriesResult $lgdExpectedRecoveriesResult): bool
    {
        return $authUser->can('Delete:LgdExpectedRecoveriesResult');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:LgdExpectedRecoveriesResult');
    }

    public function restore(AuthUser $authUser, LgdExpectedRecoveriesResult $lgdExpectedRecoveriesResult): bool
    {
        return $authUser->can('Restore:LgdExpectedRecoveriesResult');
    }

    public function forceDelete(AuthUser $authUser, LgdExpectedRecoveriesResult $lgdExpectedRecoveriesResult): bool
    {
        return $authUser->can('ForceDelete:LgdExpectedRecoveriesResult');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:LgdExpectedRecoveriesResult');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:LgdExpectedRecoveriesResult');
    }

    public function replicate(AuthUser $authUser, LgdExpectedRecoveriesResult $lgdExpectedRecoveriesResult): bool
    {
        return $authUser->can('Replicate:LgdExpectedRecoveriesResult');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:LgdExpectedRecoveriesResult');
    }
}
