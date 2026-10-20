<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

class OrgUserPolicy
{
    /**
     * Determine whether the user can view any staff users in the organization.
     */
    public function viewAny(User $actor, ?Organization $organization = null): bool
    {
        if ($actor->isSuperAdmin()) {
            return true;
        }

        if ($actor->isOrganizationAdmin() && $actor->organization_id) {
            return $organization ? (int)$organization->id === (int)$actor->organization_id : true;
        }

        return false;
    }

    /**
     * Determine whether the user can view a specific user's details and branch history.
     */
    public function view(User $actor, User $target): bool
    {
        if ($actor->isSuperAdmin()) {
            return true;
        }

        if ($actor->isOrganizationAdmin() && $actor->organization_id) {
            // Cannot view other Org Admins or Super Admins
            if ($target->isSuperAdmin()) {
                return false;
            }
            if ($target->isOrganizationAdmin() && $target->id !== $actor->id) {
                return false;
            }

            $targetOrgId = $target->organization_id ?: $target->branch?->organization_id;
            return (int)$targetOrgId === (int)$actor->organization_id;
        }

        return false;
    }

    /**
     * Determine whether the user can move a specific staff user to another branch.
     */
    public function move(User $actor, User $target): bool
    {
        if ($actor->isSuperAdmin()) {
            return !$target->isSuperAdmin() && $target->id !== $actor->id;
        }

        if ($actor->isOrganizationAdmin() && $actor->organization_id) {
            // Check Phase 6 delegation toggle
            $org = $actor->organization;
            if ($org && !$org->canManageStaff()) {
                return false;
            }

            // Cannot move self, other Org Admins, or Super Admins
            if ($target->id === $actor->id || $target->isSuperAdmin() || $target->isOrganizationAdmin()) {
                return false;
            }

            $targetOrgId = $target->organization_id ?: $target->branch?->organization_id;
            return (int)$targetOrgId === (int)$actor->organization_id;
        }

        return false;
    }

    /**
     * Determine whether the user can disable a specific staff user.
     */
    public function disable(User $actor, User $target): bool
    {
        if ($actor->isSuperAdmin()) {
            return !$target->isSuperAdmin() && $target->id !== $actor->id;
        }

        if ($actor->isOrganizationAdmin() && $actor->organization_id) {
            // Check Phase 6 delegation toggle
            $org = $actor->organization;
            if ($org && !$org->canManageStaff()) {
                return false;
            }

            // Cannot disable self, other Org Admins, or Super Admins
            if ($target->id === $actor->id || $target->isSuperAdmin() || $target->isOrganizationAdmin()) {
                return false;
            }

            $targetOrgId = $target->organization_id ?: $target->branch?->organization_id;
            return (int)$targetOrgId === (int)$actor->organization_id;
        }

        return false;
    }

    /**
     * Determine whether the user can re-enable a specific staff user.
     */
    public function enable(User $actor, User $target): bool
    {
        return $this->disable($actor, $target);
    }
}
