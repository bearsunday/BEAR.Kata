<?php

declare(strict_types=1);

namespace BEAR\Kata\Auth;

use function in_array;
use function is_string;
use function parse_url;
use function strtolower;

use const PHP_URL_SCHEME;

/**
 * Whether the request reached the public origin over HTTPS.
 *
 * The configured public origin wins over the request scheme: behind a proxy
 * that terminates TLS, PHP sees plain HTTP on every request.
 */
final class RequestScheme
{
    /** @param array<string, mixed> $server */
    public function isHttps(array $server, string|null $publicOrigin): bool
    {
        if ($publicOrigin !== null && strtolower((string) parse_url($publicOrigin, PHP_URL_SCHEME)) === 'https') {
            return true;
        }

        $https = $server['HTTPS'] ?? '';

        return is_string($https) && ! in_array(strtolower($https), ['', 'off', '0'], true);
    }
}
