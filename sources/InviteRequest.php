<?php

namespace IPS\discord;

use IPS\Http\Url;
use IPS\Member;
use IPS\Node\Model;

/**
 * @property-read string $id;
 * @property int $member_id;
 * @property string $code;
 * @property int $submitted_at;
 */
class InviteRequest extends Model
{
    protected static array $multitons = [];

    public static ?array $ownerTypes = ['member' => 'member_id'];

    public static bool $modalForms = true;

    public static string $nodeTitle = 'discord_invites_requests';

    protected static array $databaseIdFields = ['discord_invites_requests_id', 'discord_invites_requests_code', 'discord_invites_requests_member_id'];

    public static ?string $databaseTable = 'discord_invites_requests';

    public static string $databaseColumnId = 'id';

    public static string $databasePrefix = 'discord_invites_requests_';

    protected function get__title(): string
    {
        return Member::load($this->member_id)->name;
    }

    public function url(): Url|string|null
    {
        return Url::internal('app=discord&module=system&controller=invites', 'front', 'invite')->setQueryString([
            'code' => $this->code,
        ]);
    }
}
