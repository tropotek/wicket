<?php

namespace App\Support;

use DateTimeImmutable;
use OTPHP\TOTP as Rfc6238;
use Psr\Clock\ClockInterface;

/**
 * Current TOTP code for a base32 seed (RFC 6238, the authenticator-app default: SHA1, 6 digits, 30 s).
 * Time comes from Laravel's clock, so tests can travel.
 */
class Totp
{
    /** @return array{code: string, period: int, remaining: int} */
    public function current(string $seed): array
    {
        $clock = new class implements ClockInterface
        {
            public function now(): DateTimeImmutable
            {
                return now()->toDateTimeImmutable();
            }
        };

        $totp = Rfc6238::createFromSecret($seed, $clock);
        $period = $totp->getPeriod();

        return [
            'code' => $totp->now(),
            'period' => $period,
            'remaining' => $period - (now()->timestamp % $period),
        ];
    }
}
