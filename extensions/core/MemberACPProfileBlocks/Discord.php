<?php

namespace IPS\discord\extensions\core\MemberACPProfileBlocks;

use ErrorException;
use Illuminate\Http\Client\ConnectionException;
use IPS\core\MemberACPProfile\Block;
use IPS\discord\Api\Guild;
use IPS\discord\Discord as BaseDiscord;
use IPS\discord\InviteRequest;
use IPS\Theme;
use Throwable;

use function defined;

if (! defined('\IPS\SUITE_UNIQUE_KEY')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0').' 403 Forbidden');
    exit;
}

class Discord extends Block
{
    public static string $displayTab = 'core_Main';

    public static string $displayColumn = 'left';

    /**
     * @throws Throwable
     * @throws ErrorException
     * @throws ConnectionException
     */
    public function output(): string
    {
        if (! BaseDiscord::enabledForMember($this->member)) {
            return '';
        }

        $guildRoles = collect(Guild::getRoles());
        $member = Guild::getMember($this->member);
        $roles = collect(data_get($member, 'roles'))->map(fn ($id) => data_get($guildRoles->firstWhere('id', $id), 'name'))->filter()->sort();
        $currentlyInServer = filled($member);
        $invite = collect(InviteRequest::loadByOwner($this->member))->first();

        return (string) Theme::i()->getTemplate('memberprofile', 'discord', 'admin')->discord(
            nick: data_get($member, 'nick', 'Not In Server'),
            id: data_get($member, 'user.id', 'Not In Server'),
            member: $this->member,
            invite: $invite->id,
            roles: $roles->isEmpty() ? 'No Roles Assigned' : $roles->implode(', '),
            status: $currentlyInServer,
            actions: [
                'sync' => $currentlyInServer,
                'add' => ! $currentlyInServer,
                'remove' => $currentlyInServer,
                'approve' => filled($invite),
                'deny' => filled($invite),
            ]
        );
    }
}
