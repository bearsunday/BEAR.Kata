<?php

declare(strict_types=1);

namespace BEAR\Kata\Auth;

use BEAR\Kata\Exception\SessionAlreadyActiveException;

use function in_array;
use function ini_set;
use function is_string;
use function parse_url;
use function session_set_cookie_params;
use function session_status;
use function strtolower;

use const PHP_SESSION_ACTIVE;
use const PHP_URL_SCHEME;

/**
 * Session cookie settings the application owns. Applied before the first
 * session_start() so every starter — NativeAuthSession or the csrf store —
 * gets the same cookie; which one runs first no longer matters.
 *
 * SameSite is Lax, not Strict: the OAuth callback is a cross-site top-level
 * navigation and must still carry the cookie holding the login state.
 *
 * Secure follows the public origin when one is configured. Behind a proxy
 * that terminates TLS, PHP never sees HTTPS on the request, so the request
 * scheme alone would leave a production cookie without the flag.
 */
final class SessionCookie
{
    /** @param array<string, mixed> $server */
    public function configure(array $server, string|null $publicOrigin = null): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            throw new SessionAlreadyActiveException();
        }

        ini_set('session.use_strict_mode', '1');
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => $this->isHttps($server, $publicOrigin),
        ]);
    }

    /** @param array<string, mixed> $server */
    private function isHttps(array $server, string|null $publicOrigin): bool
    {
        if ($publicOrigin !== null && strtolower((string) parse_url($publicOrigin, PHP_URL_SCHEME)) === 'https') {
            return true;
        }

        $https = $server['HTTPS'] ?? '';

        return is_string($https) && ! in_array(strtolower($https), ['', 'off', '0'], true);
    }
}
