<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PdNetflowResult;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PdNetflowResultPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PdNetflowResult');
    }

    public function view(AuthUser $authUser, PdNetflowResult $pdNetflowResult): bool
    {
        return $authUser->can('View:PdNetflowResult');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PdNetflowResult');
    }

    public function update(AuthUser $authUser, PdNetflowResult $pdNetflowResult): bool
    {
        return $authUser->can('Update:PdNetflowResult');
    }

    public function delete(AuthUser $authUser, PdNetflowResult $pdNetflowResult): bool
    {
        return $authUser->can('Delete:PdNetflowResult');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:PdNetflowResult');
    }

    public function restore(AuthUser $authUser, PdNetflowResult $pdNetflowResult): bool
    {
        return $authUser->can('Restore:PdNetflowResult');
    }

    public function forceDelete(AuthUser $authUser, PdNetflowResult $pdNetflowResult): bool
    {
        return $authUser->can('ForceDelete:PdNetflowResult');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PdNetflowResult');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PdNetflowResult');
    }

    public function replicate(AuthUser $authUser, PdNetflowResult $pdNetflowResult): bool
    {
        return $authUser->can('Replicate:PdNetflowResult');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PdNetflowResult');
    }
}
