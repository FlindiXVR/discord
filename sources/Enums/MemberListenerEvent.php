<?php

namespace IPS\discord\Enums;

enum MemberListenerEvent: string
{
    case LOGIN = 'login';
    case PROFILE_UPDATE = 'profile_update';
    case SPAMMER = 'spammer';
    case DELETE = 'delete';

    public function getLabel(): ?string
    {
        return match ($this) {
            MemberListenerEvent::LOGIN => 'On Login',
            MemberListenerEvent::PROFILE_UPDATE => 'On Profile Update',
            MemberListenerEvent::SPAMMER => 'Marked As A Spammer',
            MemberListenerEvent::DELETE => 'When Deleted',
        };
    }
}
