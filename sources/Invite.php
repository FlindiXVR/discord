<?php

namespace IPS\discord;

use DomainException;
use IPS\DateTime;
use IPS\Db;
use IPS\Helpers\Form\Date;
use IPS\Helpers\Form\Text;
use IPS\Helpers\Form\YesNo;
use IPS\Http\Url;
use IPS\Node\Model;
use IPS\Node\Permissions;
use IPS\Settings;
use UnderflowException;

/**
 * @property-read string $id;
 * @property string $code;
 * @property int $position;
 * @property int $expiration;
 * @property int $approval_required
 */
class Invite extends Model implements Permissions
{
    protected static array $multitons = [];

    public static bool $modalForms = true;

    public static string $nodeTitle = 'discord_invites';

    protected static array $databaseIdFields = ['discord_invites_id', 'discord_invites_code'];

    public static ?string $databaseTable = 'discord_invites';

    public static ?string $databaseColumnOrder = 'order';

    public static string $databaseColumnId = 'id';

    public static string $databasePrefix = 'discord_invites_';

    public static ?string $permApp = 'discord';

    public static ?string $permType = 'invites';

    public static string $permissionLangPrefix = 'discord_invite_';

    public static array $permissionMap = [
        'view' => 'view',
        'bypass' => 2,
    ];

    protected function get__title(): string
    {
        return $this->code;
    }

    protected function get__description(): ?string
    {
        return filled($this->expiration) && $this->expiration !== -1
            ? 'Expires '.DateTime::ts($this->expiration)->relative()
            : 'Does Not Expire';
    }

    protected function get__icon(): ?string
    {
        if ($this->approval_required) {
            return 'lock';
        }

        return null;
    }

    protected function get__badge(): ?array
    {
        $defaultCode = Settings::i()->discord_default_invite_code;

        if ($defaultCode === $this->code) {
            return [
                0 => 'ipsBadge ipsBadge--neutral i-float_end i-margin-end_icon',
                1 => 'discord_default_invite_code',
            ];
        }

        return null;
    }

    public function form(&$form): void
    {
        $form->add(new Text('discord_invites_code', $this->id ? $this->code : null, true, [
            'disabled' => ! $this->_new,
        ], function ($code) {
            if (! $this->id) {
                try {
                    Db::i()->select('discord_invites_code', static::$databaseTable, ['discord_invites_code=?', $code], null, [0, 1])->first();
                    throw new DomainException('discord_invites_code_unique');
                } catch (UnderflowException $e) {
                }
            }
        }));

        $form->add(new Date('discord_invites_expiration', $this->id ? $this->expiration : -1, false, [
            'time' => true,
            'unlimited' => -1,
            'unlimitedLang' => 'never',
            'min' => DateTime::create(),
        ], function ($value) {
            if ($value instanceof DateTime && $value < new DateTime) {
                throw new DomainException('discord_invites_expiration_past');
            }
        }));

        $form->add(new YesNo('discord_invites_approval_required', $this->id ? $this->approval_required : false));
        $form->add(new YesNo('discord_default_invite_code', Settings::i()->discord_default_invite_code === $this->code && ! blank($this->code) ?: false));
    }

    public function formatFormValues($values): array
    {
        $expiration = data_get($values, 'discord_invites_expiration');

        if ($expiration != -1 && $expiration instanceof DateTime) {
            data_set($values, 'discord_invites_expiration', $expiration->getTimestamp());
        }

        if (data_get($values, 'discord_default_invite_code', false)) {
            Settings::i()->changeValues([
                'discord_default_invite_code' => data_get($values, 'discord_invites_code', $this->code),
            ]);
        }

        data_forget($values, 'discord_default_invite_code');

        return parent::formatFormValues($values);
    }

    public function getButtons($url, $subnode = false): array
    {
        return array_merge(['url' => [
            'icon' => 'link',
            'title' => 'discord_invite_url',
            'link' => $this->url(),
            'target' => '_blank',
        ]], parent::getButtons($url, $subnode));
    }

    public function url(): Url|string|null
    {
        return Url::internal('app=discord&module=system&controller=invite', 'front', 'discord_invite')->setQueryString([
            'code' => $this->code,
        ]);
    }
}
