<?php

declare(strict_types=1);

namespace BEAR\Kata\Exception;

use RuntimeException;

/**
 * The session id could not be regenerated on an authentication change.
 *
 * Raised instead of continuing, because storing the authenticated user under the
 * pre-login id is exactly the fixation the rotation exists to prevent.
 */
final class SessionRotationException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Could not regenerate the session id; the authentication change was not applied.');
    }
}
