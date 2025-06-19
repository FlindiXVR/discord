<?php

namespace IPS\discord\modules\admin\system;

use DomainException;
use Exception;
use IPS\Db;
use IPS\discord\Api\Api;
use IPS\discord\Api\Guild;
use IPS\discord\Discord;
use IPS\discord\DiscordMember;
use IPS\discord\Enums\MemberListenerEvent;
use IPS\discord\Exceptions\DiscordException;
use IPS\discord\Invite;
use IPS\discord\InviteRequest;
use IPS\discord\License;
use IPS\discord\Utils;
use IPS\Dispatcher;
use IPS\Dispatcher\Controller;
use IPS\Helpers\Form;
use IPS\Helpers\MultipleRedirect;
use IPS\Helpers\Wizard;
use IPS\Http\Url;
use IPS\Log;
use IPS\Member;
use IPS\Output;
use IPS\Request;
use IPS\Settings;
use Throwable;

use function count;
use function defined;
use function is_array;

if (! defined('\IPS\SUITE_UNIQUE_KEY')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0').' 403 Forbidden');
    exit;
}

class config extends Controller
{
    public static bool $csrfProtected = true;

    public function execute(): void
    {
        Dispatcher::i()->checkAcpPermission('config_manage');

        parent::execute();
    }

    protected function manage(): void
    {
        $wizard = new Wizard([
            'discord_config_wizard_step_license' => function () {
                $form = new Form('discord_config_wizard_step_license', 'next');
                $form->add( new Form\Text('discord_license_key', 'Nulled by nullforums.net', true, [ 'disabled' => TRUE ], function ($value) {

                    Settings::i()->changeValues([
                        'discord_license_instance_id' => $value,
                    ]);
                }));

                if ($values = $form->values()) {
                    $form->saveAsSettings($values);

                    return $values;
                }

                return $form;
            },
            'discord_config_wizard_step1' => function () {
                $handler = Discord::handler();

                $settings = $handler->settings;

                $form = new Form('discord_config_wizard_step1_form', 'next');

                if (! Discord::enabled(
                    ignoreLicenseKey: true,
                    ignoreBotToken: true
                )) {
                    $form->error = Member::loggedIn()->language()->addToStack('discord_config_wizard_error_login_handler');
                }

                $form->add(new Form\Text('discord_client_id', $settings['client_id'], true, [
                    'disabled' => true,
                ]));
                $form->add(new Form\Text('discord_client_secret', $settings['client_secret'], true, [
                    'disabled' => true,
                ]));
                $form->add(new Form\Text('discord_bot_token', Settings::i()->discord_bot_token ?: null, true, [], function ($botToken) {
                    try {
                        $api = new Api;
                        $api->asBot($botToken)->request(
                            url: 'oauth2/applications/@me',
                            alwaysThrow: true
                        );
                    } catch (Exception $e) {
                        throw new DomainException('The bot token you provided is not valid.');
                    }
                }));

                if ($values = $form->values()) {
                    $form->saveAsSettings($values);

                    return $values;
                }

                return $form;
            },
            'discord_config_wizard_step2' => function (array $data) {
                $guilds = Discord::getGuilds() ?? [];

                $mappedGuilds = [];
                foreach ($guilds as $guild) {
                    $mappedGuilds[$guild['id']] = $guild['name'];
                }

                $form = new Form('discord_config_wizard_step2_form', 'next');

                $form->add(new Form\YesNo('discord_server_integration', Settings::i()->discord_server_integration ?: true, true));
                $form->add(new Form\Select('discord_server', Settings::i()->discord_server ?: null, true, [
                    'multiple' => false,
                    'options' => $mappedGuilds,
                ]));

                if ($values = $form->values()) {
                    $form->saveAsSettings($values);

                    return $values;
                }

                $addServerUrl = Url::external('https://discordapp.com/api/oauth2/authorize')->setQueryString([
                    'scope' => 'bot',
                    'client_id' => $data['discord_client_id'],
                    'permissions' => array_sum(Discord::$botPermissions),
                ]);

                $form->addButton('discord_config_wizard_step2_add_server', 'link', $addServerUrl, 'ipsButton ipsButton--secondary', [
                    'target' => '_blank',
                ]);

                return $form;
            },
            'discord_config_wizard_step3' => function ($data) {
                $roles = Guild::getRoles(data_get($data, 'discord_server', [])) ?? [];

                $mappedRoles = [];
                foreach ($roles as $role) {
                    $mappedRoles[$role['id']] = $role['name'];
                }

                $form = new Form('discord_config_wizard_step3_form', 'next');

                $form->add(new Form\Select('discord_role', Settings::i()->discord_role ?: null, true, [
                    'multiple' => false,
                    'options' => $mappedRoles,
                ]));

                if ($values = $form->values()) {
                    $form->saveAsSettings($values);

                    return $values;
                }

                return $form;
            },
            'discord_config_wizard_step4' => function () {
                $form = new Form('discord_config_wizard_step4_form');

                $form->addHtml("<div class=' i-padding_3'><div class='ipsTitle ipsTitle--h1 i-text-align_center'>All Done!</div><div class='i-text-align_center i-padding-top_2'>Your configuration has been successfully saved.</div></div>");
                $form->actionButtons = [];

                return $form;
            },
        ], Url::internal('app=discord&modules=system&controller=config'));

        Output::i()->sidebar['actions']['clear'] = [
            'primary' => false,
            'icon' => 'trash',
            'title' => 'discord_configuration_clear',
            'link' => Url::internal('app=discord&module=system&controller=config')->setQueryString([
                'do' => 'clear',
            ]),
        ];

        Output::i()->sidebar['actions']['sync'] = [
            'primary' => false,
            'icon' => 'arrows-rotate',
            'title' => 'discord_configuration_sync',
            'link' => Url::internal('app=discord&module=system&controller=config')->setQueryString([
                'do' => 'sync',
                'state' => 'start',
            ]),
        ];

        Output::i()->title = Member::loggedIn()->language()->addToStack('discord_config_wizard');
        Output::i()->output = $wizard;
    }

    /**
     * @throws Exception
     */
    public function clear(): void
    {
        Request::i()->confirmedDelete('discord_confirm_clear_title', 'discord_confirm_clear_message', 'discord_confirm_clear');

        $licenseKey = Settings::i()->discord_license_key;
        $instanceId = Settings::i()->discord_license_instance_id;

        if ($licenseKey && $instanceId) {
            License::i()->deactivate($licenseKey, $instanceId);
        }

        Db::i()->delete(Invite::$databaseTable);
        Db::i()->delete(InviteRequest::$databaseTable);

        Settings::i()->changeValues([
            'discord_automation_sync_limit' => 1000,
            'discord_automation_sync_member_events' => collect(MemberListenerEvent::cases())
                ->map(fn (MemberListenerEvent $event) => $event->value)
                ->toArray(),
            'discord_automation_sync_nicknames' => 1,
            'discord_automation_sync_unassigned_roles' => 'keep',
            'discord_bot_token' => null,
            'discord_default_invite_code' => null,
            'discord_group_roles' => null,
            'discord_license_instance_id' => null,
            'discord_license_key' => null,
            'discord_role' => null,
            'discord_server' => null,
            'discord_server_integration' => 1,
            'discord_widget_settings' => null,
        ]);

        Output::i()->redirect(Url::internal('app=discord&module=system&controller=config'), 'discord_confirm_clear_success');
    }

    /**
     * @throws Exception
     * @throws DiscordException
     * @throws Throwable
     */
    public function sync(): void
    {
        if (! Discord::enabled()) {
            throw new DiscordException('Discord not enabled. Please configure Discord first before managing invites.');
        }

        // Start the sync by first performing these initial API calls to make sure the bot has access to everything it needs
        if (Request::i()->state === 'start') {
            $guild = Guild::getGuild(query: [
                'with_counts' => true,
            ]);

            $members = Guild::getMembers();
            $member = Guild::getMember(Settings::i()->discord_client_id);

            if (blank($guild) || blank($members) || blank($member)) {
                throw new DiscordException('The bot does not have the appropriate permissions to perform the requested action. Please consult the documentation for assistance.');
            }

            // We passed so start the sync
            Output::i()->redirect(Url::internal('app=discord&module=system&controller=config')->setQueryString([
                'do' => 'sync',
                'state' => 'process',
            ]));
        }

        // Run the actual sync process
        if (Request::i()->state === 'process') {
            $redirect = new MultipleRedirect(
                url: Url::internal('app=discord&module=system&controller=config')->setQueryString([
                    'do' => 'sync',
                    'state' => 'process',
                ]),
                callback: function ($data) {

                    // First iteration so set our data array with all the initial values
                    if (! is_array($data)) {
                        $guild = Guild::getGuild(query: [
                            'with_counts' => true,
                        ]);

                        $count = data_get($guild, 'approximate_member_count');
                        $ownerId = data_get($guild, 'owner_id');

                        $data = [
                            'checked' => 0,
                            'last_checked_id' => 0,
                            'members' => $count,
                            'synced' => 0,
                            'owner_id' => $ownerId,
                            'iterations' => 0,
                            'assignments' => Utils::getRoleAssignments(),
                        ];

                        Log::debug('Mass sync start: '.json_encode($data, JSON_PRETTY_PRINT));

                        return [$data, Member::loggedIn()->language()->addToStack('discord_sync_setup'), 1];
                    }

                    // Increment the iteration
                    $data['iterations']++;

                    // Get all the discord members for the current iteration using the after query parameter
                    $limit = Settings::i()->discord_automation_sync_limit ?: 1000;
                    $members = Guild::getMembers(query: [
                        'limit' => $limit,
                        'after' => data_get($data, 'last_checked_id', 0),
                    ]);

                    // Loop through all the discord members
                    foreach ($members as $user) {
                        // Get their discord user ID
                        $userId = data_get($user, 'user.id');

                        // If the member is the server owner, just skip so we don't overwrite any sensitive owner/admin data
                        if ($userId === data_get($data, 'owner_id')) {
                            $data['checked']++;
                            $data['last_checked_id'] = $userId;

                            continue;
                        }

                        // Load the member using their discord user ID
                        $member = DiscordMember::load($userId, 'token_identifier');

                        // We couldn't find a member
                        if (! $member->member_id) {
                            $data['checked']++;
                            $data['last_checked_id'] = $userId;

                            continue;
                        }

                        // Get their name
                        $name = data_get($user, 'nick', data_get($user, 'user.username'));

                        // Get their current rules and roles they should have based on the groups they are assigned
                        $existingRoles = data_get($user, 'roles', []) ?? [];
                        $assignedRoles = collect(Utils::computeRolesToSync(
                            member: $member,
                            existingRoles: $existingRoles
                        ));

                        // If the roles do not match
                        $payload = [];
                        if (collect($existingRoles)->diff($assignedRoles)->isNotEmpty()) {
                            $payload['roles'] = $assignedRoles->toArray();
                        }

                        // If the nickname doesn't match and we are updating those
                        if ($name !== $member->real_name && Settings::i()->discord_automation_sync_nicknames) {
                            $payload['nick'] = $member->real_name;
                        }

                        // If we have something to update
                        if (filled($payload)) {
                            // Update the member with the new data
                            $response = Guild::updateMember($member, $payload);

                            // Increment our sync count
                            if (filled($response)) {
                                $data['synced']++;
                            }
                        }

                        // Increment our check count and set who we last checked
                        $data['checked']++;
                        $data['last_checked_id'] = $userId;
                    }

                    // If we do not have any members or the API has returned fewer members than the limit, we should be done
                    if (is_null($members) || count($members) < $limit) {
                        Log::debug('Mass sync finish: '.json_encode($data, JSON_PRETTY_PRINT));

                        return null;
                    }

                    // Calculate the current progress
                    $progress = abs(((int) data_get($data, 'checked') / (int) data_get($data, 'members', 1)) * 100);

                    // Set the ongoing sync message
                    $message = Member::loggedIn()->language()->addToStack('discord_sync', false, ['sprintf' => [data_get($data, 'checked'), data_get($data, 'members'), data_get($data, 'synced')]]);

                    return [$data, $message, $progress];
                },
                finished: function () {
                    Output::i()->redirect(Url::internal('app=discord&module=system&controller=config')->setQueryString([
                        'do' => 'sync',
                        'state' => 'done',
                    ]), 'discord_confirm_sync_success');
                }
            );

            Output::i()->output = $redirect;
        } elseif (Request::i()->state === 'done') {
            Output::i()->redirect(Url::internal('app=discord&module=system&controller=config'), 'discord_confirm_sync_success');
        } else {
            Output::i()->error('page_not_found', '1sbr108/5', 404);
        }
    }
}
