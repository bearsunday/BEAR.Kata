<?php

declare(strict_types=1);

namespace BEAR\Kata\Auth;

use function ini_set;
use function session_set_cookie_params;

/**
 * Session cookie settings the application owns. Applied before the first
 * session_start() so every starter — NativeAuthSession or the csrf store —
 * gets the same cookie; which one runs first no longer matters.
 *
 * SameSite is Lax, not Strict: the OAuth callback is a cross-site top-level
 * navigation and must still carry the cookie holding the login state.
 */
final class SessionCookie
{
    /** @param array<string, mixed> $server */
    public function configure(array $server): void
    {
        ini_set('session.use_strict_mode', '1');
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => ($server['HTTPS'] ?? 'off') !== 'off',
        ]);
    }
}
