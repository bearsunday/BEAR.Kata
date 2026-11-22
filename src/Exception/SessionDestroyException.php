<?php

declare(strict_types=1);

namespace BEAR\Kata\Exception;

use RuntimeException;

/**
 * The session could not be destroyed on logout.
 *
 * The user entry and the CSRF token are already gone and the cookie has already been
 * expired by then, so the browser cannot resume the id; what is lost is only the
 * server-side cleanup, and that is reported rather than passed off as a completed logout.
 */
final class SessionDestroyException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Could not destroy the session on logout.');
    }
}
