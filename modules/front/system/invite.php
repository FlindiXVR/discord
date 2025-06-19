<?php

namespace IPS\discord\modules\front\system;

use ErrorException;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use IPS\DateTime;
use IPS\discord\Action;
use IPS\discord\Api\Guild;
use IPS\discord\Discord;
use IPS\discord\Exceptions\DiscordException;
use IPS\discord\Invite as InviteModel;
use IPS\discord\InviteRequest;
use IPS\discord\Request;
use IPS\Dispatcher\Controller;
use IPS\Http\Url;
use IPS\Member;
use IPS\Output;
use IPS\Request as IpsRequest;
use IPS\Session;
use IPS\Settings;
use IPS\Theme;
use Throwable;
use UnderflowException;

use function defined;

if (! defined('\IPS\SUITE_UNIQUE_KEY')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0').' 403 Forbidden');
    exit;
}

class invite extends Controller
{
    protected ?string $code = null;

    protected ?InviteModel $invite = null;

    protected ?Member $member = null;

    protected ?array $profile = null;

    public function execute(): void
    {
        $this->member = Member::loggedIn();

        try {
            $this->runChecks();
        } catch (DiscordException|Exception $exception) {
            Output::i()->error($exception->getMessage(), 403);
        }

        parent::execute();
    }

    /**
     * @throws Throwable
     * @throws ConnectionException
     * @throws ErrorException
     */
    protected function manage(): void
    {
        Output::i()->metaTags['robots'] = 'noindex';
        Output::i()->sidebar['enabled'] = false;
        Output::i()->bodyClasses[] = 'ipsLayout_minimal';

        $discordLoginRequired = Discord::getProfile($this->member);
        $guild = Guild::getGuild(query: [
            'with_counts' => true,
        ]);

        $expiration = filled($this->invite->expiration) && $this->invite->expiration !== -1
            ? 'Expires '.DateTime::ts($this->invite->expiration)->html()
            : 'Does Not Expire';

        $approvalRequired = $this->invite->approval_required && ! $this->invite->can('bypass');
        $alreadyRequested = false;
        if ($approvalRequired) {
            $alreadyRequested = filled(InviteRequest::loadByOwner($this->member));
        }

        Output::i()->title = Member::loggedIn()->language()->addToStack('frontnavigation_discord');
        Output::i()->output = Theme::i()->getTemplate('system', 'discord', 'front')->processInvite(
            approvalRequired: $approvalRequired,
            alreadyRequested: $alreadyRequested,
            discordLoginRequired: $discordLoginRequired,
            communityName: Settings::i()->board_name,
            guildName: data_get($guild, 'name'),
            expiration: $expiration,
            totalMembers: data_get($guild, 'approximate_member_count'),
            onlineMembers: data_get($guild, 'approximate_presence_count'),
            requestApprovalUrl: Url::internal('app=discord&module=system&controller=invite')->setQueryString([
                'do' => 'request',
                'code' => $this->code,
            ])->csrf(),
            acceptUrl: Url::internal('app=discord&module=system&controller=invite')->setQueryString([
                'do' => 'accept',
                'code' => $this->code,
            ])->csrf(),
            cancelUrl: Url::internal('')
        );
    }

    /**
     * @throws DiscordException
     * @throws Throwable
     */
    public function request(): void
    {
        if (Request::i()->method() !== 'POST') {
            throw new DiscordException('This URL only accepts a POST request.');
        }

        Session::i()->csrfCheck();

        if (filled(InviteRequest::loadByOwner($this->member))) {
            throw new DiscordException('You already have a pending request.');
        }

        $request = new InviteRequest;
        $request->member_id = $this->member->member_id;
        $request->code = $this->code;
        $request->submitted_at = DateTime::create()->getTimestamp();
        $request->save();

        Output::i()->redirect(Url::internal(''), 'discord_invite_approval_requested');
    }

    /**
     * @throws DiscordException
     * @throws Throwable
     */
    public function accept(): void
    {
        if (Request::i()->method() !== 'POST') {
            throw new DiscordException('This URL only accepts a POST request.');
        }

        Session::i()->csrfCheck();

        $approvalRequired = $this->invite->approval_required;

        if ($approvalRequired) {
            throw new DiscordException('You must request approval to accept this invite.');
        }

        $result = Action::add($this->member);

        if (! $result) {
            Output::i()->redirect(Url::internal(''), 'discord_invite_failed');

            return;
        }

        Output::i()->redirect(Url::internal(''), 'discord_invite_success');
    }

    /**
     * @throws Throwable
     * @throws ConnectionException
     * @throws DiscordException
     */
    private function runChecks(): void
    {
        if (is_null($this->member->member_id)) {
            throw new DiscordException('You must be logged in to access this page.');
        }

        if (! $this->code = IpsRequest::i()->code) {
            throw new DiscordException('No invite code provided.');
        }

        try {
            $this->invite = InviteModel::load($this->code, 'discord_invites_code');
        } catch (UnderflowException $e) {
            throw new DiscordException('The invite code provided is not valid.');
        }

        if (! $this->invite->can('view')) {
            throw new DiscordException('You do not have permission to use this invite code.');
        }

        if ($this->profile = Guild::getMember($this->member)) {
            throw new DiscordException('You are already a member of our server.');
        }
    }
}
