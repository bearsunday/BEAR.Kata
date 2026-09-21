<?php

declare(strict_types=1);

namespace BEAR\Kata\Fake;

use BEAR\Csrf\CsrfTokenInterface;
use Override;

use function hash_equals;

final readonly class FakeCsrfToken implements CsrfTokenInterface
{
    public const string DEFAULT_TOKEN = 'fake-csrf-token';

    public function __construct(private string $token = self::DEFAULT_TOKEN)
    {
    }

    #[Override]
    public function issue(): string
    {
        return $this->token;
    }

    #[Override]
    public function verify(string $candidate): bool
    {
        return $candidate !== '' && hash_equals($this->token, $candidate);
    }

    #[Override]
    public function clear(): void
    {
        // No-op: the fake's token is constructor-fixed.
    }
}
