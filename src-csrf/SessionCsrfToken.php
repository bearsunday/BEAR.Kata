<?php

declare(strict_types=1);

namespace Ray\Csrf;

use Override;

use function bin2hex;
use function hash_equals;
use function is_string;
use function random_bytes;
use function session_start;
use function session_status;

use const PHP_SESSION_ACTIVE;

/** @SuppressWarnings("PHPMD.Superglobals") Session adapter boundary. */
final class SessionCsrfToken implements CsrfTokenInterface
{
    public function __construct(private CsrfSessionKey $sessionKey)
    {
    }

    #[Override]
    public function issue(): string
    {
        $this->start();

        $existing = $_SESSION[$this->sessionKey->name] ?? null;
        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $token = bin2hex(random_bytes(32));
        $_SESSION[$this->sessionKey->name] = $token;

        return $token;
    }

    #[Override]
    public function verify(string $candidate): bool
    {
        $this->start();

        $stored = $_SESSION[$this->sessionKey->name] ?? null;
        if (! is_string($stored) || $stored === '' || $candidate === '') {
            return false;
        }

        return hash_equals($stored, $candidate);
    }

    #[Override]
    public function clear(): void
    {
        $this->start();
        unset($_SESSION[$this->sessionKey->name]);
    }

    private function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_start();
    }
}
