<?php

declare(strict_types=1);

namespace BEAR\Kata\Exception;

use LogicException;

/**
 * The session cookie was about to be configured, but a session was already running.
 *
 * PHP ignores cookie parameters set after session_start(), so the cookie would go out
 * with the ini defaults while every request kept succeeding. Raised instead, so a
 * session.auto_start host or an early session_start() is noticed at the first request.
 */
final class SessionAlreadyActiveException extends LogicException
{
    public function __construct()
    {
        parent::__construct('Session cookie settings must be applied before the first session_start().');
    }
}
