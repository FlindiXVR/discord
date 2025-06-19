<?php

namespace IPS\discord;

use Illuminate\Http\Client\ConnectionException;
use IPS\discord\Api\Guild;
use IPS\Member;
use IPS\Patterns\Singleton;
use IPS\Settings;
use Throwable;

class Action extends Singleton
{
    /**
     * @throws Throwable
     * @throws ConnectionException
     */
    public static function add(Member $member, bool $shouldSync = true): bool
    {
        $data = [];
        if (Settings::i()->discord_automation_sync_nicknames) {
            $data['nick'] = $member->real_name;
        }

        $response = Guild::addMember($member, $data);

        if (blank($response)) {
            return false;
        }

        if ($shouldSync) {
            Action::sync($member);
        }

        return true;
    }

    /**
     * @throws Throwable
     * @throws ConnectionException
     */
    public static function remove(Member $member): bool
    {
        Guild::removeMember($member);

        return true;
    }

    /**
     * @throws Throwable
     * @throws ConnectionException
     */
    public static function sync(Member $member, ?array $groupsIds = null, bool $ignoreRoles = false): bool
    {
        $data = [];
        if (Settings::i()->discord_automation_sync_nicknames) {
            $data['nick'] = $member->real_name;
        }

        if (! $ignoreRoles) {
            $roles = Utils::computeRolesToSync($member, $groupsIds);

            $data['roles'] = $roles;
        }

        if (blank($data)) {
            return false;
        }

        $response = Guild::updateMember($member, $data);

        if (blank($response)) {
            return false;
        }

        return true;
    }
}
