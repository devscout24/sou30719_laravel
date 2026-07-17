<?php

namespace App\Enums;

/**
 * The fixed set of top-level navbar sections. This list is closed by design —
 * admins grant/revoke workspace access to these sections, they do not add new ones.
 */
enum NavSection: string
{
    case AiPal = 'ai_pal';
    case Discovery = 'discovery';
    case Friends = 'friends';
    case Chat = 'chat';

    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
