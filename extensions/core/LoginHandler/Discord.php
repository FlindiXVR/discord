<?php

namespace IPS\discord\extensions\core\LoginHandler;

use IPS\Http\Url;
use IPS\Login;
use IPS\Login\Exception;
use IPS\Login\Handler;
use IPS\Login\Handler\ButtonHandler;
use IPS\Member;

use function defined;

if (! defined('\IPS\SUITE_UNIQUE_KEY')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0').' 403 Forbidden');
    exit;
}

class Discord extends Handler\OAuth2
{
    use ButtonHandler;

    public bool $pkceSupported = false;

    protected array $_cachedUserData = [];

    public static function getTitle(): string
    {
        return 'Discord';
    }

    public function buttonColor(): string
    {
        return '#2b2d31';
    }

    public function buttonIcon(): string
    {
        return 'discord';
    }

    public function buttonText(): string
    {
        return 'Sign In with Discord';
    }

    protected function grantType(): string
    {
        return 'authorization_code';
    }

    protected function scopesToRequest($additional = null): array
    {
        return ['identify', 'email', 'guilds.join'];
    }

    protected function authorizationEndpoint(Login $login): Url
    {
        return Url::external('https://discordapp.com/api/oauth2/authorize');
    }

    protected function tokenEndpoint(): Url
    {
        return Url::external('https://discordapp.com/api/oauth2/token');
    }

    protected function authenticatedUserId(string $accessToken): ?string
    {
        return $this->_userData($accessToken)['id'];
    }

    protected function authenticatedUserName(string $accessToken): ?string
    {
        return $this->_userData($accessToken)['username'];
    }

    protected function authenticatedEmail(string $accessToken): ?string
    {
        return $this->_userData($accessToken)['email'];
    }

    protected function authenticatedAvatarHash(string $accessToken): ?string
    {
        return $this->_userData($accessToken)['avatar'];
    }

    public function userProfileName(Member $member): ?string
    {
        if (! ($link = $this->_link($member)) or ($link['token_expires'] and $link['token_expires'] < time())) {
            throw new Exception('Access token expired. Please reauthenticate.', Exception::INTERNAL_ERROR);
        }

        return $this->authenticatedUserName($link['token_access_token']);
    }

    public function userProfilePhoto(Member $member): ?Url
    {
        if (! ($link = $this->_link($member)) or ($link['token_expires'] and $link['token_expires'] < time())) {
            throw new Exception('Access token expired. Please reauthenticate.', Exception::INTERNAL_ERROR);
        }

        $id = $this->authenticatedUserId($link['token_access_token']);
        $hash = $this->authenticatedAvatarHash($link['token_access_token']);

        if (! $hash || ! $id) {
            return null;
        }

        $format = 'png';
        if (mb_substr($hash, 0, 2) == 'a_') {
            $format = 'gif';
        }

        return Url::external("https://cdn.discordapp.com/avatars/$id/$hash.$format");
    }

    public function userDiscordAccount(Member $member): array
    {
        if (! ($link = $this->_link($member)) or ($link['token_expires'] and $link['token_expires'] < time())) {
            throw new Exception('Access token expired. Please reauthenticate.', Exception::INTERNAL_ERROR);
        }

        return $this->_userData($link['token_access_token']);
    }

    public function userLink(string $identifier, string $username): ?Url
    {
        return null;
    }

    public function link(?Member $member = null): ?array
    {
        return $this->_link($member ?: Member::loggedIn());
    }

    protected function _userData(string $accessToken): array
    {
        if (isset($this->_cachedUserData[$accessToken])) {
            return $this->_cachedUserData[$accessToken];
        }

        $response = Url::external('https://discord.com/api/users/@me')
            ->request()
            ->setHeaders([
                'Authorization' => "Bearer $accessToken",
            ])
            ->get();

        $data = $response->decodeJson();

        if (! $response->isSuccessful()) {
            throw new Exception($data['message'] ?? 'There was an error while retrieving your Discord profile. Please try again.', Exception::INTERNAL_ERROR);
        }

        return $this->_cachedUserData[$accessToken] = $data;
    }
}
