<?php

namespace IPS\discord\modules\admin\system;

use Illuminate\Http\Client\ConnectionException;
use IPS\discord\Discord;
use IPS\discord\Enums\MemberListenerEvent;
use IPS\discord\Exceptions\DiscordException;
use IPS\Dispatcher;
use IPS\Dispatcher\Controller;
use IPS\Helpers\Form;
use IPS\Member;
use IPS\Output;
use IPS\Settings;

use function defined;

if (! defined('\IPS\SUITE_UNIQUE_KEY')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0').' 403 Forbidden');
    exit;
}

class automation extends Controller
{
    /**
     * @throws DiscordException
     * @throws ConnectionException
     */
    public function execute(): void
    {
        Dispatcher::i()->checkAcpPermission('automation_manage');

        if (! Discord::enabled()) {
            throw new DiscordException('Discord not enabled. Please configure Discord first before managing invites.');
        }

        parent::execute();
    }

    protected function manage(): void
    {
        $form = new Form;

        $form->addHeader('discord_automation_sync');
        $form->add(new Form\YesNo('discord_automation_sync_nicknames', Settings::i()->discord_automation_sync_nicknames ?: true, true));
        $form->add(new Form\Select('discord_automation_sync_unassigned_roles', Settings::i()->discord_automation_sync_unassigned_roles ?: null, true, [
            'options' => [
                'keep' => 'Keep Roles Assigned To User',
                'remove' => 'Remove Roles From User',
            ],
        ]));
        $form->add(new Form\Text('discord_automation_sync_limit', Settings::i()->discord_automation_sync_limit ?: 1000, true));
        $form->add(new Form\Select('discord_automation_sync_member_events', json_decode(Settings::i()->discord_automation_sync_member_events ?? '') ?? null, true, [
            'multiple' => true,
            'options' => collect(MemberListenerEvent::cases())
                ->mapWithKeys(fn (MemberListenerEvent $event) => [$event->value => $event->getLabel()])
                ->toArray(),
        ]));

        if ($values = $form->values()) {
            if ($memberEvents = data_get($values, 'discord_automation_sync_member_events')) {
                data_set($values, 'discord_automation_sync_member_events', json_encode(array_values($memberEvents)));
            }

            $form->saveAsSettings($values);
        }

        Output::i()->title = Member::loggedIn()->language()->addToStack('discord_automation');
        Output::i()->output = $form;
    }
}
