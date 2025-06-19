<?php

namespace IPS\discord\Api;

use Illuminate\Http\Client\ConnectionException;
use IPS\discord\Utils;
use IPS\Member;
use IPS\Settings;
use Throwable;

class Guild extends Api
{
    /**
     * @throws Throwable
     * @throws ConnectionException
     */
    public static function getGuild(?string $id = null, ?array $query = null)
    {
        $gid = $id ?? Settings::i()->discord_server;

        $api = new Api;

        $response = $api->asBot()->request(
            url: "guilds/$gid",
            query: $query
        );

        if (blank($response)) {
            return null;
        }

        return $response;
    }

    /**
     * @throws Throwable
     * @throws ConnectionException
     */
    public static function getRoles(?string $id = null): mixed
    {
        $gid = $id ?? Settings::i()->discord_server;

        $api = new Api;

        $response = $api->asBot()->request(
            url: "guilds/$gid/roles"
        );

        if (blank($response)) {
            return null;
        }

        return collect($response)->filter(function ($role) use ($gid) {
            return (int) $role['managed'] !== 1 && (string) $role['id'] !== (string) $gid;
        })->toArray();
    }

    /**
     * @throws Throwable
     * @throws ConnectionException
     */
    public static function getMembers(?string $id = null, ?array $query = null)
    {
        $gid = $id ?? Settings::i()->discord_server;

        $api = new Api;

        $response = $api->asBot()->request(
            url: "guilds/$gid/members",
            query: $query
        );

        if (blank($response)) {
            return null;
        }

        return $response;
    }

    /**
     * @throws Throwable
     * @throws ConnectionException
     */
    public static function getMember(Member|string $member, ?string $id = null)
    {
        $gid = $id ?? Settings::i()->discord_server;
        $mid = Utils::resolveMemberId($member);

        $api = new Api;

        $response = $api->asBot()->request(
            url: "guilds/$gid/members/$mid"
        );

        if (blank($response)) {
            return null;
        }

        return $response;
    }

    /**
     * @throws Throwable
     * @throws ConnectionException
     */
    public static function addMember(Member|string $member, array $data, ?string $id = null)
    {
        $gid = $id ?? Settings::i()->discord_server;
        $mid = Utils::resolveMemberId($member);

        $api = new Api;

        $response = $api->withMember($member)->asBot()->request(
            url: "guilds/$gid/members/$mid",
            method: 'PUT',
            body: $data,
            mergeMemberAccessToken: true
        );

        if (blank($response)) {
            return null;
        }

        return $response;
    }

    /**
     * @throws Throwable
     * @throws ConnectionException
     */
    public static function updateMember(Member|string $member, array $data, ?string $id = null)
    {
        $gid = $id ?? Settings::i()->discord_server;
        $mid = Utils::resolveMemberId($member);

        $api = new Api;

        $response = $api->asBot()->request(
            url: "guilds/$gid/members/$mid",
            method: 'PATCH',
            body: $data
        );

        if (blank($response)) {
            return null;
        }

        return $response;
    }

    /**
     * @throws Throwable
     * @throws ConnectionException
     */
    public static function removeMember(Member|string $member, ?string $id = null)
    {
        $gid = $id ?? Settings::i()->discord_server;
        $mid = Utils::resolveMemberId($member);

        $api = new Api;

        $response = $api->asBot()->request(
            url: "guilds/$gid/members/$mid",
            method: 'DELETE'
        );

        if (blank($response)) {
            return null;
        }

        return $response;
    }
}
