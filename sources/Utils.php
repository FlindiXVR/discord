<?php

namespace IPS\discord;

use Illuminate\Support\Collection;
use IPS\discord\Api\Guild;
use IPS\Member;
use IPS\Settings;

class Utils
{
    public static function resolveMemberId(Member|string|null $member = null): ?string
    {
        $result = match (true) {
            is_null($member) => Member::loggedIn(),
            default => $member
        };

        if ($result instanceof Member) {
            return Discord::getProfile($member, 'id');
        }

        return $result;
    }

    public static function computeRolesToSync(Member $member, ?array $groupsIds = null, ?array $existingRoles = null): array
    {
        $finalRoles = collect();

        // Add the base role that everyone should have
        if ($baseRole = Settings::i()->discord_role) {
            $finalRoles->push($baseRole);
        }

        // Get the saved groups roles
        $groupRoles = Utils::getRoleAssignments();

        // What to do with unassigned roles
        $unassignedRolesAction = Settings::i()->discord_automation_sync_unassigned_roles ?: 'remove';

        // If we want to keep any roles they are assigned that are not assigned to an IC group
        if ($unassignedRolesAction === 'keep') {
            // Get the users existing roles
            $existingRoles = collect($existingRoles ?? data_get(Guild::getMember($member), 'roles', []) ?? []);

            // Add their existing roles
            $finalRoles->push(...$existingRoles);

            // Now remove any existing roles that are currently tied to IC groups so that we can start fresh when adding IC assigned roles below
            $finalRoles = $finalRoles->diff($groupRoles->flatten());
        }

        // Now add the roles they should be a part of from their IC group memberships
        // Check for null, because we want to also be able to take all roles away if group id's is empty
        if (is_null($groupsIds)) {
            $memberGroupRoles = collect($member->groups)->map(fn ($id) => data_get($groupRoles, $id));
        } else {
            // Use the group ids that were explicitly given
            $memberGroupRoles = collect($groupsIds)->map(fn ($id) => data_get($groupRoles, $id));
        }

        // Now push on the roles we calculated above
        $finalRoles->push(...$memberGroupRoles);

        return $finalRoles->flatten()->filter()->unique()->values()->toArray();
    }

    public static function getRoleAssignments(): Collection
    {
        return collect(json_decode(Settings::i()->discord_group_roles ?? '', true))
            ->reject(fn ($id) => $id === -1);
    }
}
