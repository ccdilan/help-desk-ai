<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Discovering = 'discovering';
    case Review = 'review';
    case SignedOff = 'signed_off';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Discovering => 'Discovering',
            self::Review => 'Review',
            self::SignedOff => 'Signed off',
        };
    }
}
