<?php

declare(strict_types=1);

namespace Ray\Csrf;

/**
 * Session array key holding the issued CSRF token (default `ray_csrf_token`).
 *
 * Configurable because an application adopting this library rarely owns the
 * session namespace alone: it may already store a token under a key fixed by
 * an existing system, or run several independently-protected areas in one
 * session. A hardcoded key forces such a consumer to reimplement
 * CsrfTokenInterface merely to rename a string.
 */
final readonly class CsrfSessionKey
{
    public function __construct(public string $name = 'ray_csrf_token')
    {
    }
}
