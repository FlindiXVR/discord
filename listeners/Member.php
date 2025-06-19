<?php

namespace IPS\discord\listeners;

use Illuminate\Http\Client\ConnectionException;
use IPS\calendar\Event;
use IPS\Content as ContentClass;
use IPS\Content\Reaction;
use IPS\Db;
use IPS\discord\Action;
use IPS\discord\Api\Guild;
use IPS\discord\Discord;
use IPS\discord\Enums\MemberListenerEvent;
use IPS\Events\ListenerType\MemberListenerType;
use IPS\Http\Url;
use IPS\Member as MemberClass;
use IPS\Member\Club as Club;
use IPS\Settings;
use Throwable;

use function defined;

if (! defined('\IPS\SUITE_UNIQUE_KEY')) {
    header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0').' 403 Forbidden');
    exit;
}

class Member extends MemberListenerType
{
    public function onCreateAccount(MemberClass $member): void {}

    public function onValidate(MemberClass $member): void {}

    /**
     * @throws Throwable
     * @throws ConnectionException
     */
    public function onLogin(MemberClass $member): void
    {
        if (! Discord::enabledForMember($member)) {
            return;
        }

        $events = json_decode(Settings::i()->discord_automation_sync_member_events ?? '', true);

        if (! in_array(MemberListenerEvent::LOGIN->value ?? [], $events)) {
            return;
        }

        if (Guild::getMember($member)) {
            Action::sync($member);
        }
    }

    public function onLogout(MemberClass $member, Url $returnUrl): void {}

    /**
     * @throws Throwable
     * @throws ConnectionException
     */
    public function onProfileUpdate(MemberClass $member, array $changes): void
    {
        if (! Discord::enabledForMember($member)) {
            return;
        }

        $events = json_decode(Settings::i()->discord_automation_sync_member_events ?? '', true);

        if (! in_array(MemberListenerEvent::PROFILE_UPDATE->value, $events)) {
            return;
        }

        $select = collect(Db::i()->select(['member_group_id', 'mgroup_others'], MemberClass::$databaseTable, ['member_id=?', $member->member_id]))->filter()->first();
        $groups = collect(data_get($select, 'member_group_id'));

        if ($others = data_get($select, 'mgroup_others')) {
            $groups = $groups->merge(collect(explode(',', $others)));
        }

        match (true) {
            ! is_null($groups) => Action::sync(
                member: $member,
                groupsIds: $groups->filter()->unique()->toArray()
            ),
            array_key_exists('name', $changes) => Action::sync(
                member: $member,
                ignoreRoles: true
            ),
            default => null
        };
    }

    /**
     * @throws Throwable
     * @throws ConnectionException
     */
    public function onSetAsSpammer(MemberClass $member): void
    {
        if (! Discord::enabledForMember($member)) {
            return;
        }

        $events = json_decode(Settings::i()->discord_automation_sync_member_events ?? '', true);

        if (! in_array(MemberListenerEvent::SPAMMER->value, $events)) {
            return;
        }

        Action::remove($member);
    }

    public function onUnSetAsSpammer(MemberClass $member): void {}

    public function onMerge(MemberClass $member, MemberClass $member2): void {}

    /**
     * @throws Throwable
     * @throws ConnectionException
     */
    public function onDelete(MemberClass $member): void
    {
        if (! Discord::enabledForMember($member)) {
            return;
        }

        $events = json_decode(Settings::i()->discord_automation_sync_member_events ?? '', true);

        if (! in_array(MemberListenerEvent::DELETE->value, $events)) {
            return;
        }

        Action::remove($member);
    }

    public function onEmailChange(MemberClass $member, string $new, string $old): void {}

    public function onPassChange(MemberClass $member, string $new): void {}

    public function onJoinClub(MemberClass $member, Club $club): void {}

    public function onLeaveClub(MemberClass $member, Club $club): void {}

    public function onEventRsvp(MemberClass $member, Event $event, int $response): void {}

    public function onReact(MemberClass $member, ContentClass $content, Reaction $reaction): void {}

    public function onUnreact(MemberClass $member, ContentClass $content): void {}

    public function onFollow(MemberClass $member, object $object, bool $isAnonymous): void {}

    public function onUnfollow(MemberClass $member, object $object): void {}
}
