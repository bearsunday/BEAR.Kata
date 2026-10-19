<?php

declare(strict_types=1);

namespace BEAR\Kata\Exception;

use RuntimeException;

/**
 * The session id could not be regenerated on an authentication change.
 *
 * On login this aborts before the user is stored, because keeping the pre-login id is
 * exactly the fixation the rotation exists to prevent. On logout the user entry is already
 * removed by then; only the id rotation and the token reset are lost.
 */
final class SessionRotationException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Could not regenerate the session id on an authentication change.');
    }
}
