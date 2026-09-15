<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CalculationParameter;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class CalculationParameterPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:CalculationParameter');
    }

    public function view(AuthUser $authUser, CalculationParameter $calculationParameter): bool
    {
        return $authUser->can('View:CalculationParameter');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:CalculationParameter');
    }

    public function update(AuthUser $authUser, CalculationParameter $calculationParameter): bool
    {
        return $authUser->can('Update:CalculationParameter');
    }

    public function delete(AuthUser $authUser, CalculationParameter $calculationParameter): bool
    {
        return $authUser->can('Delete:CalculationParameter');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:CalculationParameter');
    }

    public function restore(AuthUser $authUser, CalculationParameter $calculationParameter): bool
    {
        return $authUser->can('Restore:CalculationParameter');
    }

    public function forceDelete(AuthUser $authUser, CalculationParameter $calculationParameter): bool
    {
        return $authUser->can('ForceDelete:CalculationParameter');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:CalculationParameter');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:CalculationParameter');
    }

    public function replicate(AuthUser $authUser, CalculationParameter $calculationParameter): bool
    {
        return $authUser->can('Replicate:CalculationParameter');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:CalculationParameter');
    }
}
