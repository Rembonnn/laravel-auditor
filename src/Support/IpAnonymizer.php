<?php

declare(strict_types=1);

namespace Rembon\LaravelAuditor\Support;

/**
 * Masks an IP address: IPv4 keeps its /24 network, IPv6 its /48 network.
 */
final class IpAnonymizer
{
    public static function anonymize(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        $packed = @inet_pton($ip);

        if ($packed === false) {
            return null;
        }

        $keepBytes = strlen($packed) === 4 ? 3 : 6;
        $masked = substr($packed, 0, $keepBytes).str_repeat("\0", strlen($packed) - $keepBytes);

        return (string) inet_ntop($masked);
    }
}
