<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\QueryTemplate;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class QueryTemplatePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:QueryTemplate');
    }

    public function view(AuthUser $authUser, QueryTemplate $queryTemplate): bool
    {
        return $authUser->can('View:QueryTemplate');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:QueryTemplate');
    }

    public function update(AuthUser $authUser, QueryTemplate $queryTemplate): bool
    {
        return $authUser->can('Update:QueryTemplate');
    }

    public function delete(AuthUser $authUser, QueryTemplate $queryTemplate): bool
    {
        return $authUser->can('Delete:QueryTemplate');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:QueryTemplate');
    }

    public function restore(AuthUser $authUser, QueryTemplate $queryTemplate): bool
    {
        return $authUser->can('Restore:QueryTemplate');
    }

    public function forceDelete(AuthUser $authUser, QueryTemplate $queryTemplate): bool
    {
        return $authUser->can('ForceDelete:QueryTemplate');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:QueryTemplate');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:QueryTemplate');
    }

    public function replicate(AuthUser $authUser, QueryTemplate $queryTemplate): bool
    {
        return $authUser->can('Replicate:QueryTemplate');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:QueryTemplate');
    }
}
