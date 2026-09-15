<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CalculationRunLog;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class CalculationRunLogPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CalculationRunLog');
    }

    public function view(AuthUser $authUser, CalculationRunLog $calculationRunLog): bool
    {
        return $authUser->can('View:CalculationRunLog');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CalculationRunLog');
    }

    public function update(AuthUser $authUser, CalculationRunLog $calculationRunLog): bool
    {
        return $authUser->can('Update:CalculationRunLog');
    }

    public function delete(AuthUser $authUser, CalculationRunLog $calculationRunLog): bool
    {
        return $authUser->can('Delete:CalculationRunLog');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CalculationRunLog');
    }

    public function restore(AuthUser $authUser, CalculationRunLog $calculationRunLog): bool
    {
        return $authUser->can('Restore:CalculationRunLog');
    }

    public function forceDelete(AuthUser $authUser, CalculationRunLog $calculationRunLog): bool
    {
        return $authUser->can('ForceDelete:CalculationRunLog');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CalculationRunLog');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CalculationRunLog');
    }

    public function replicate(AuthUser $authUser, CalculationRunLog $calculationRunLog): bool
    {
        return $authUser->can('Replicate:CalculationRunLog');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CalculationRunLog');
    }
}
