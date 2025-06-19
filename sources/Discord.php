<?php

namespace IPS\discord;

use Illuminate\Http\Client\ConnectionException;
use IPS\discord\Api\Api;
use IPS\discord\Exceptions\DiscordException;
use IPS\discord\extensions\core\LoginHandler\Discord as DiscordHandler;
use IPS\Login\Exception;
use IPS\Login\Handler;
use IPS\Member;
use IPS\Patterns\Singleton;
use IPS\Settings;
use Throwable;

class Discord extends Singleton
{
    public static array $botPermissions = [
        'CREATE_INSTANT_INVITE' => 0x00000001,
        'KICK_MEMBERS' => 0x00000002,
        'MANAGE_CHANNELS' => 0x00000010,
        'MANAGE_GUILD' => 0x00000020,
        'VIEW_AUDIT_LOG' => 0x00000080,
        'MANAGE_NICKNAMES' => 0x08000000,
        'MANAGE_ROLES' => 0x10000000,
        'MANAGE_WEBHOOKS' => 0x20000000,
        'MANAGE_EMOJIS' => 0x40000000,
    ];

    /**
     * @throws Throwable
     * @throws DiscordException
     * @throws ConnectionException
     */
    public static function getGuilds(): mixed
    {
        $api = new Api;
        $response = $api->asBot()->request(
            url: 'users/@me/guilds'
        );

        if (blank($response)) {
            return null;
        }

        return $response;
    }

    public static function getProfile(?Member $member = null, ?string $key = null, mixed $default = null): string|array|null
    {
        $member = $member ?? Member::loggedIn();

        $handler = Discord::handler();

        if (blank($handler)) {
            return null;
        }

        try {
            $profile = $handler->userDiscordAccount($member);
        } catch (Exception $e) {
            return null;
        }

        if (blank($profile)) {
            return null;
        }

        if (filled($key) && isset($profile[$key])) {
            return data_get($profile, $key, value($default));
        }

        return $profile;
    }

    /**
     * @throws ConnectionException
     */
    public static function enabled(bool $ignoreLicenseKey = true, bool $ignoreBotToken = false): bool
    {
        $results = Discord::handler()
            && Settings::i()->discord_client_id
            && Settings::i()->discord_client_secret;

        if (! $ignoreLicenseKey) {
            $results = $results && License::i()->active();
        }

        if (! $ignoreBotToken) {
            $results = $results && Settings::i()->discord_bot_token;
        }

        return $results;
    }

    /**
     * @throws ConnectionException
     */
    public static function enabledForMember(Member $member): bool
    {
        return Discord::enabled()
            && filled(Discord::getProfile($member));
    }

    public static function handler(): ?DiscordHandler
    {
        $handler = Handler::findMethod(extensions\core\LoginHandler\Discord::class);

        if (blank($handler)) {
            return null;
        }

        return $handler;
    }
}
