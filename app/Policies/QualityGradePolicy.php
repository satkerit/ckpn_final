<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\QualityGrade;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class QualityGradePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:QualityGrade');
    }

    public function view(AuthUser $authUser, QualityGrade $qualityGrade): bool
    {
        return $authUser->can('View:QualityGrade');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:QualityGrade');
    }

    public function update(AuthUser $authUser, QualityGrade $qualityGrade): bool
    {
        return $authUser->can('Update:QualityGrade');
    }

    public function delete(AuthUser $authUser, QualityGrade $qualityGrade): bool
    {
        return $authUser->can('Delete:QualityGrade');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:QualityGrade');
    }

    public function restore(AuthUser $authUser, QualityGrade $qualityGrade): bool
    {
        return $authUser->can('Restore:QualityGrade');
    }

    public function forceDelete(AuthUser $authUser, QualityGrade $qualityGrade): bool
    {
        return $authUser->can('ForceDelete:QualityGrade');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:QualityGrade');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:QualityGrade');
    }

    public function replicate(AuthUser $authUser, QualityGrade $qualityGrade): bool
    {
        return $authUser->can('Replicate:QualityGrade');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:QualityGrade');
    }
}
