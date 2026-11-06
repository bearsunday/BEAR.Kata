<?php

declare(strict_types=1);

namespace BEAR\Kata\Auth;

use BEAR\Csrf\CsrfTokenInterface;
use BEAR\Kata\Exception\SessionDestroyException;
use BEAR\Kata\Exception\SessionRotationException;

use function bin2hex;
use function hash_equals;
use function is_array;
use function is_int;
use function is_string;
use function random_bytes;
use function session_destroy;
use function session_get_cookie_params;
use function session_name;
use function session_regenerate_id;
use function session_start;
use function session_status;
use function setcookie;
use function time;

use const PHP_SESSION_ACTIVE;

/** @SuppressWarnings("PHPMD.Superglobals") Session adapter boundary. */
final class NativeAuthSession implements AuthSessionInterface
{
    private const string USER = 'cms_user';
    private const string STATE = 'cms_oauth_state';

    public function __construct(private readonly CsrfTokenInterface $csrf)
    {
    }

    public function currentUser(): UserInterface
    {
        if (! $this->hasSession()) {
            return new Visitor();
        }

        $this->start();
        $user = $_SESSION[self::USER] ?? null;
        if (! is_array($user)) {
            return new Visitor();
        }

        if (! $this->hasUserShape($user)) {
            return new Visitor();
        }

        return new AdminUser(
            id: $user['id'],
            email: $user['email'],
            name: $user['name'],
            authorId: $user['authorId'],
        );
    }

    public function issueState(): string
    {
        $this->start();
        $state = bin2hex(random_bytes(16));
        $_SESSION[self::STATE] = $state;

        return $state;
    }

    public function consumeState(string $state): bool
    {
        $this->start();
        $expected = $_SESSION[self::STATE] ?? null;
        unset($_SESSION[self::STATE]);

        return is_string($expected) && hash_equals($expected, $state);
    }

    public function login(AuthenticatedUser $user, int $authorId): void
    {
        $this->start();
        $this->rotate();
        $_SESSION[self::USER] = [
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'authorId' => $authorId,
        ];
    }

    /**
     * Logout leaves nothing behind: no user, no token, no session, no cookie.
     * Rotating into an empty session instead would hand the browser a new id,
     * and every later public page would start a session for it again.
     */
    public function logout(): void
    {
        $this->start();
        unset($_SESSION[self::USER], $_SESSION[self::STATE]);
        $this->csrf->clear();
        $this->destroy();
    }

    /**
     * A request without a session cookie has no session to read. Starting one
     * anyway would hand every anonymous visitor a cookie and a session file, and
     * PHP's default cache limiter would mark every public page no-store.
     */
    private function hasSession(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE || isset($_COOKIE[session_name()]);
    }

    private function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_start();
    }

    /**
     * A session id and token minted before the authentication change must not
     * survive it: a fixated session or a leaked token would otherwise carry over.
     */
    private function rotate(): void
    {
        if (! session_regenerate_id(true)) {
            throw new SessionRotationException();
        }

        $this->csrf->clear();
    }

    /**
     * The cookie is expired before the server-side destroy, and dropped from
     * $_COOKIE so nothing later in this request resumes or restarts a session.
     */
    private function destroy(): void
    {
        $name = session_name();
        $params = session_get_cookie_params();
        setcookie($name, '', [
            'expires' => time() - 3600,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'],
        ]);
        unset($_COOKIE[$name]);
        $_SESSION = [];
        if (! session_destroy()) {
            throw new SessionDestroyException();
        }
    }

    /** @param array<array-key, mixed> $user */
    private function hasUserShape(array $user): bool
    {
        return isset($user['id'], $user['email'], $user['name'], $user['authorId'])
            && is_string($user['id'])
            && is_string($user['email'])
            && is_string($user['name'])
            && is_int($user['authorId']);
    }
}
