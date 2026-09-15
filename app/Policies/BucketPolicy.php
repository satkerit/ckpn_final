<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Bucket;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class BucketPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Bucket');
    }

    public function view(AuthUser $authUser, Bucket $bucket): bool
    {
        return $authUser->can('View:Bucket');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Bucket');
    }

    public function update(AuthUser $authUser, Bucket $bucket): bool
    {
        return $authUser->can('Update:Bucket');
    }

    public function delete(AuthUser $authUser, Bucket $bucket): bool
    {
        return $authUser->can('Delete:Bucket');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:Bucket');
    }

    public function restore(AuthUser $authUser, Bucket $bucket): bool
    {
        return $authUser->can('Restore:Bucket');
    }

    public function forceDelete(AuthUser $authUser, Bucket $bucket): bool
    {
        return $authUser->can('ForceDelete:Bucket');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Bucket');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Bucket');
    }

    public function replicate(AuthUser $authUser, Bucket $bucket): bool
    {
        return $authUser->can('Replicate:Bucket');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Bucket');
    }
}
