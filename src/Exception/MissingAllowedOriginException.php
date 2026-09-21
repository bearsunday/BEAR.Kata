<?php

declare(strict_types=1);

namespace BEAR\Kata\Exception;

use RuntimeException;

/**
 * Production was booted without an origin for the same-origin gate to compare against.
 *
 * Raised at boot rather than tolerated, because the alternative is the gate standing down for
 * every request with nothing in the logs to say so. A deployment that genuinely wants no origin
 * check says so in its own context module by installing
 * {@see \BEAR\Csrf\CsrfModule::withoutSameOriginCheck()}.
 */
final class MissingAllowedOriginException extends RuntimeException
{
    public function __construct(string $envName)
    {
        parent::__construct(
            "Missing same-origin configuration: {$envName}. "
            . 'Set it, or install CsrfModule::withoutSameOriginCheck() deliberately.',
        );
    }
}
