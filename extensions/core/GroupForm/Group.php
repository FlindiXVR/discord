<?php

namespace IPS\discord\extensions\core\GroupForm;

use Illuminate\Http\Client\ConnectionException;
use IPS\discord\Api\Guild;
use IPS\Extensions\GroupFormAbstract;
use IPS\Helpers\Form;
use IPS\Member\Group as SystemGroup;
use IPS\Settings;
use Throwable;

use function defined;

if (! defined('\IPS\SUITE_UNIQUE_KEY')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0').' 403 Forbidden');
    exit;
}

class Group extends GroupFormAbstract
{
    /**
     * @throws Throwable
     * @throws ConnectionException
     */
    public function process(Form $form, SystemGroup $group): void
    {
        $roles = Guild::getRoles();

        $mappedRoles = [];
        foreach ($roles as $role) {
            $mappedRoles[$role['id']] = data_get($role, 'name');
        }

        $roles = json_decode(Settings::i()->discord_group_roles ?? '', true);

        $form->add(new Form\Select('group_discord_role_id', data_get($roles, $group->g_id, -1) ?? -1, true, [
            'multiple' => true,
            'options' => $mappedRoles,
            'unlimited' => -1,
            'unlimitedLang' => 'group_discord_role_none',
        ]));
    }

    public function save(array $values, SystemGroup $group): void
    {
        $roles = json_decode(Settings::i()->discord_group_roles ?? '', true);
        $roles[$group->g_id] = data_get($values, 'group_discord_role_id');

        Settings::i()->changeValues([
            'discord_group_roles' => json_encode($roles),
        ]);
    }

    public function canDelete(SystemGroup $group): bool
    {
        return true;
    }
}
