<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PdMigrationResult;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PdMigrationResultPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PdMigrationResult');
    }

    public function view(AuthUser $authUser, PdMigrationResult $pdMigrationResult): bool
    {
        return $authUser->can('View:PdMigrationResult');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PdMigrationResult');
    }

    public function update(AuthUser $authUser, PdMigrationResult $pdMigrationResult): bool
    {
        return $authUser->can('Update:PdMigrationResult');
    }

    public function delete(AuthUser $authUser, PdMigrationResult $pdMigrationResult): bool
    {
        return $authUser->can('Delete:PdMigrationResult');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:PdMigrationResult');
    }

    public function restore(AuthUser $authUser, PdMigrationResult $pdMigrationResult): bool
    {
        return $authUser->can('Restore:PdMigrationResult');
    }

    public function forceDelete(AuthUser $authUser, PdMigrationResult $pdMigrationResult): bool
    {
        return $authUser->can('ForceDelete:PdMigrationResult');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PdMigrationResult');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PdMigrationResult');
    }

    public function replicate(AuthUser $authUser, PdMigrationResult $pdMigrationResult): bool
    {
        return $authUser->can('Replicate:PdMigrationResult');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PdMigrationResult');
    }
}
