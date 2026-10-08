<?php

namespace App\Enums;

use App\Contracts\HasBadge;
use Carbon\CarbonInterface;

enum LicenseStatus: string implements HasBadge
{
    case Valid = 'valid';
    case ExpiringSoon = 'expiring_soon';
    case Expired = 'expired';

    /** Number of days before expiry at which a licence is flagged. */
    public const WARNING_DAYS = 30;

    public static function fromExpiry(?CarbonInterface $expiry, ?CarbonInterface $on = null): self
    {
        $on = ($on ?? now())->copy()->startOfDay();

        if ($expiry === null || $expiry->lt($on)) {
            return self::Expired;
        }

        return $expiry->lte($on->copy()->addDays(self::WARNING_DAYS)) ? self::ExpiringSoon : self::Valid;
    }

    public function label(): string
    {
        return match ($this) {
            self::Valid => 'Valid',
            self::ExpiringSoon => 'Expiring soon',
            self::Expired => 'Expired',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Valid => 'green',
            self::ExpiringSoon => 'amber',
            self::Expired => 'red',
        };
    }
}
