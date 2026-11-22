<?php

declare(strict_types=1);

namespace BEAR\Kata\Auth;

use BEAR\Kata\Exception\SessionAlreadyActiveException;

use function ini_set;
use function session_set_cookie_params;
use function session_status;

use const PHP_SESSION_ACTIVE;

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
    public function __construct(private readonly RequestScheme $scheme = new RequestScheme())
    {
    }

    /** @param array<string, mixed> $server */
    public function configure(array $server, string|null $publicOrigin = null): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            throw new SessionAlreadyActiveException();
        }

        ini_set('session.use_strict_mode', '1');
        // The id travels in the cookie and nowhere else: an id accepted from the
        // URL would bypass the cookie attributes set below, and a request without
        // the cookie is what NativeAuthSession treats as having no session.
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => $this->scheme->isHttps($server, $publicOrigin),
        ]);
    }
}
