<?php

namespace IPS\discord\extensions\core\FrontNavigation;

use Illuminate\Http\Client\ConnectionException;
use IPS\core\FrontNavigation\FrontNavigationAbstract;
use IPS\discord\Discord;
use IPS\discord\Invite as BaseInvite;
use IPS\Dispatcher;
use IPS\Http\Url;
use IPS\Member as MemberClass;
use IPS\Settings;

use function defined;

if (! defined('\IPS\SUITE_UNIQUE_KEY')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0').' 403 Forbidden');
    exit;
}

class Invite extends FrontNavigationAbstract
{
    public string $defaultIcon = 'discord';

    protected ?string $defaultCode = null;

    public function __construct(array $configuration, int $id, ?string $permissions, string $menuTypes, ?array $icon, ?int $parent = 0)
    {
        parent::__construct($configuration, $id, $permissions, $menuTypes, $icon, $parent);

        $this->defaultCode = Settings::i()->discord_default_invite_code;
    }

    public static function typeTitle(): string
    {
        return MemberClass::loggedIn()->language()->addToStack('frontnavigation_discord');
    }

    /**
     * @throws ConnectionException
     */
    public static function isEnabled(): bool
    {
        return Discord::enabled();
    }

    /**
     * @throws ConnectionException
     */
    public function canAccessContent(): bool
    {
        //        try {
        //            BaseInvite::load($this->defaultCode, 'code');
        //        } catch (\Exception $e) {
        //            return false;
        //        }

        return Discord::enabled() && $this->defaultCode;
    }

    public function title(): string
    {
        return MemberClass::loggedIn()->language()->addToStack('frontnavigation_discord');
    }

    public function link(): Url
    {
        return Url::internal('app=discord&module=system&controller=invite')->setQueryString([
            'code' => $this->defaultCode,
        ]);
    }

    public function active(): bool
    {
        return Dispatcher::i()->application->directory === 'discord';
    }

    public function children(bool $noStore = false): ?array
    {
        return null;
    }
}
