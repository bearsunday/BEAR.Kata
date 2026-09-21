<?php

declare(strict_types=1);

namespace Ray\Csrf\Http;

/**
 * Canonical origin the CSRF defence stack is configured for.
 *
 * Read by `SameOriginInterceptor` only. `value === null` runs without the
 * same-origin gate, for a host with no browser origin to compare against;
 * the token gate is independent and stays on. The consumer resolves the
 * value once (typically from an env var) and binds an instance through
 * `CsrfModule`'s named constructors.
 */
final readonly class AllowedOrigin
{
    public function __construct(public string|null $value = null)
    {
    }
}
