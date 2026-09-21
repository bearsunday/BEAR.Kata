<?php

declare(strict_types=1);

namespace BEAR\Kata\Fake;

use BEAR\Csrf\Http\RequestOriginInterface;
use Override;

final readonly class FakeRequestOrigin implements RequestOriginInterface
{
    public function __construct(
        private string|null $fetchSite = null,
        private string|null $origin = null,
        private string|null $referer = null,
    ) {
    }

    #[Override]
    public function fetchSite(): string|null
    {
        return $this->fetchSite;
    }

    #[Override]
    public function origin(): string|null
    {
        return $this->origin;
    }

    #[Override]
    public function referer(): string|null
    {
        return $this->referer;
    }
}
