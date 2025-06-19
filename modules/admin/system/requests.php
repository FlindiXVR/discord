<?php

namespace IPS\discord\modules\admin\system;

use Exception;
use Illuminate\Http\Client\ConnectionException;
use IPS\DateTime;
use IPS\discord\Action;
use IPS\discord\Discord;
use IPS\discord\Exceptions\DiscordException;
use IPS\discord\InviteRequest;
use IPS\Dispatcher;
use IPS\Dispatcher\Controller;
use IPS\Helpers\Table\Db as TableDb;
use IPS\Http\Url;
use IPS\Member;
use IPS\Output;
use IPS\Request;
use IPS\Theme;
use Throwable;

use function defined;

if (! defined('\IPS\SUITE_UNIQUE_KEY')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0').' 403 Forbidden');
    exit;
}

class requests extends Controller
{
    public static bool $csrfProtected = true;

    /**
     * @throws DiscordException
     * @throws ConnectionException
     */
    public function execute(): void
    {
        Dispatcher::i()->checkAcpPermission('requests_manage');

        if (! Discord::enabled()) {
            throw new DiscordException('Discord not enabled. Please configure Discord first before managing invites.');
        }

        parent::execute();
    }

    protected function manage(): void
    {
        $table = new TableDb(InviteRequest::$databaseTable, Url::internal('app=discord&module=system&controller=requests'));
        $table->include = ['discord_invites_requests_member_id', 'discord_invites_requests_code', 'discord_invites_requests_submitted_at'];
        $table->sortBy = $table->sortBy ?: 'discord_invites_requests_submitted_at';
        $table->sortDirection = $table->sortDirection ?: 'desc';

        $table->tableTemplate = [Theme::i()->getTemplate('tables', 'core', 'admin'), 'table'];
        $table->rowsTemplate = [Theme::i()->getTemplate('tables', 'core', 'admin'), 'rows'];

        $table->quickSearch = 'discord_invites_requests_code';
        $table->simplePagination = true;

        $table->rowButtons = function ($row) {
            $buttons = [];

            $buttons['accept'] = [
                'icon' => 'check',
                'title' => 'discord_invites_requests_approve',
                'link' => Url::internal('app=discord&module=system&controller=requests')->setQueryString([
                    'do' => 'approve',
                    'id' => data_get($row, 'discord_invites_requests_id'),
                ])->csrf(),
            ];

            $buttons['deny'] = [
                'icon' => 'times-circle',
                'title' => 'discord_invites_requests_deny',
                'link' => Url::internal('app=discord&module=system&controller=requests')->setQueryString([
                    'do' => 'deny',
                    'id' => data_get($row, 'discord_invites_requests_id'),
                ])->csrf(),
            ];

            return $buttons;
        };

        $table->parsers = [
            'discord_invites_requests_submitted_at' => function ($value) {
                return DateTime::ts($value)->html();
            },
            'discord_invites_requests_member_id' => function ($value) {
                return Member::load($value)->link();
            },
        ];

        Output::i()->title = Member::loggedIn()->language()->addToStack('discord_invites_requests');
        Output::i()->output = (string) $table;
    }

    /**
     * @throws Exception
     * @throws Throwable
     */
    public function approve(): void
    {
        $request = InviteRequest::load(Request::i()->id);
        $member = Member::load($request->member_id);

        if ($member->member_id) {
            $result = Action::add($member);

            if (! $result) {
                Output::i()->redirect(Url::internal('app=discord&module=system&controller=requests'), 'discord_invites_requests_failed');

                return;
            }

            $request->delete();
        }

        Output::i()->redirect(Url::internal('app=discord&module=system&controller=requests'), 'discord_invites_requests_approved');
    }

    /**
     * @throws Exception
     */
    public function deny(): void
    {
        $request = InviteRequest::load(Request::i()->id);
        $request->delete();

        Output::i()->redirect(Url::internal('app=discord&module=system&controller=requests'), 'discord_invites_requests_denied');
    }
}
