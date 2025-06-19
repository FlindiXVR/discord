<?php

namespace IPS\discord\modules\admin\system;

use Illuminate\Http\Client\ConnectionException;
use IPS\discord\Discord;
use IPS\discord\Exceptions\DiscordException;
use IPS\Dispatcher;
use IPS\Node\Controller;

use function defined;

if (! defined('\IPS\SUITE_UNIQUE_KEY')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0').' 403 Forbidden');
    exit;
}

class invites extends Controller
{
    public static bool $csrfProtected = true;

    protected string $nodeClass = 'IPS\discord\Invite';

    /**
     * @throws DiscordException
     * @throws ConnectionException
     */
    public function execute(): void
    {
        Dispatcher::i()->checkAcpPermission('invites_manage');

        if (! Discord::enabled()) {
            throw new DiscordException('Discord not enabled. Please configure Discord first before managing invites.');
        }

        parent::execute();
    }
}
