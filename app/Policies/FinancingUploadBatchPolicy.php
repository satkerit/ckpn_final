<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\FinancingUploadBatch;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class FinancingUploadBatchPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:FinancingUploadBatch');
    }

    public function view(AuthUser $authUser, FinancingUploadBatch $financingUploadBatch): bool
    {
        return $authUser->can('View:FinancingUploadBatch');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:FinancingUploadBatch');
    }

    public function update(AuthUser $authUser, FinancingUploadBatch $financingUploadBatch): bool
    {
        return $authUser->can('Update:FinancingUploadBatch');
    }

    public function delete(AuthUser $authUser, FinancingUploadBatch $financingUploadBatch): bool
    {
        return $authUser->can('Delete:FinancingUploadBatch');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:FinancingUploadBatch');
    }

    public function restore(AuthUser $authUser, FinancingUploadBatch $financingUploadBatch): bool
    {
        return $authUser->can('Restore:FinancingUploadBatch');
    }

    public function forceDelete(AuthUser $authUser, FinancingUploadBatch $financingUploadBatch): bool
    {
        return $authUser->can('ForceDelete:FinancingUploadBatch');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:FinancingUploadBatch');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:FinancingUploadBatch');
    }

    public function replicate(AuthUser $authUser, FinancingUploadBatch $financingUploadBatch): bool
    {
        return $authUser->can('Replicate:FinancingUploadBatch');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:FinancingUploadBatch');
    }
}
