<?php

namespace IPS\discord\modules\admin\system;

use Illuminate\Http\Client\ConnectionException;
use IPS\discord\Action;
use IPS\discord\Discord;
use IPS\discord\Exceptions\DiscordException;
use IPS\discord\Request;
use IPS\Dispatcher;
use IPS\Dispatcher\Controller;
use IPS\Http\Url;
use IPS\Member;
use IPS\Output;
use Throwable;

use function defined;

if (! defined('\IPS\SUITE_UNIQUE_KEY')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0').' 403 Forbidden');
    exit;
}

class manage extends Controller
{
    public static bool $csrfProtected = true;

    /**
     * @throws DiscordException
     * @throws ConnectionException
     */
    public function execute(): void
    {
        Dispatcher::i()->checkAcpPermission('manage_manage');

        if (! Discord::enabled()) {
            throw new DiscordException('Discord not enabled. Please configure Discord first before managing members.');
        }

        parent::execute();
    }

    /**
     * @throws DiscordException
     * @throws Throwable
     * @throws ConnectionException
     */
    protected function sync(): void
    {
        if (! Request::i()->has('id')) {
            throw new DiscordException('No member ID provided.');
        }

        $member = Member::load(Request::i()->input('id'));

        if (blank($member->member_id)) {
            return;
        }

        $result = Action::sync($member);

        if (! $result) {
            throw new DiscordException('There was an error syncing the member. Please check the logs.');
        }

        Output::i()->redirect(Url::internal('app=core&module=members&controller=members')->setQueryString([
            'do' => 'view',
            'id' => $member->member_id,
        ]), 'discord_manage_sync_success');
    }

    /**
     * @throws DiscordException
     * @throws Throwable
     * @throws ConnectionException
     */
    protected function add(): void
    {
        if (! Request::i()->has('id')) {
            throw new DiscordException('No member ID provided.');
        }

        $member = Member::load(Request::i()->input('id'));

        if (blank($member->member_id)) {
            return;
        }

        $result = Action::add($member);

        if (! $result) {
            throw new DiscordException('There was an error adding the member to the server. Please check the logs.');
        }

        Output::i()->redirect(Url::internal('app=core&module=members&controller=members')->setQueryString([
            'do' => 'view',
            'id' => $member->member_id,
        ]), 'discord_manage_add_success');
    }

    /**
     * @throws DiscordException
     * @throws Throwable
     * @throws ConnectionException
     */
    protected function remove(): void
    {
        if (! Request::i()->has('id')) {
            throw new DiscordException('No member ID provided.');
        }

        $member = Member::load(Request::i()->input('id'));

        if (blank($member->member_id)) {
            return;
        }

        $result = Action::remove($member);

        if (! $result) {
            throw new DiscordException('There was an error removing the member from the server. Please check the logs.');
        }

        Output::i()->redirect(Url::internal('app=core&module=members&controller=members')->setQueryString([
            'do' => 'view',
            'id' => $member->member_id,
        ]), 'discord_manage_remove_success');
    }
}
