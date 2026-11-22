<?php

declare(strict_types=1);

namespace BEAR\Kata\Exception;

use RuntimeException;

/**
 * The session id could not be regenerated on an authentication change.
 *
 * Raised from login, before the user is stored: keeping the pre-login id is exactly the
 * fixation the rotation exists to prevent. Logout does not rotate; it destroys the session
 * and reports its own failure as {@see SessionDestroyException}.
 */
final class SessionRotationException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Could not regenerate the session id on an authentication change.');
    }
}
