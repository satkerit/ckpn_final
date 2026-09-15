<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RiskSegment;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class RiskSegmentPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:RiskSegment');
    }

    public function view(AuthUser $authUser, RiskSegment $riskSegment): bool
    {
        return $authUser->can('View:RiskSegment');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RiskSegment');
    }

    public function update(AuthUser $authUser, RiskSegment $riskSegment): bool
    {
        return $authUser->can('Update:RiskSegment');
    }

    public function delete(AuthUser $authUser, RiskSegment $riskSegment): bool
    {
        return $authUser->can('Delete:RiskSegment');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:RiskSegment');
    }

    public function restore(AuthUser $authUser, RiskSegment $riskSegment): bool
    {
        return $authUser->can('Restore:RiskSegment');
    }

    public function forceDelete(AuthUser $authUser, RiskSegment $riskSegment): bool
    {
        return $authUser->can('ForceDelete:RiskSegment');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:RiskSegment');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:RiskSegment');
    }

    public function replicate(AuthUser $authUser, RiskSegment $riskSegment): bool
    {
        return $authUser->can('Replicate:RiskSegment');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:RiskSegment');
    }
}
