<?php

declare(strict_types=1);

namespace BEAR\Kata\Interceptor;

use BEAR\Csrf\Http\AllowedOrigin;
use BEAR\Csrf\Http\RequestOriginInterface;
use BEAR\Kata\Fake\FakeRequestOrigin;
use Override;
use Ray\Di\AbstractModule;

/**
 * Forces `SameOriginInterceptor` to actually evaluate rather than the
 * usual short-circuit. Scoped to `SameOriginWiringTest`.
 */
final class CrossSiteOriginOverrideModule extends AbstractModule
{
    public function __construct(AbstractModule|null $module = null)
    {
        parent::__construct($module);
    }

    #[Override]
    protected function configure(): void
    {
        $this->bind(AllowedOrigin::class)
            ->toInstance(new AllowedOrigin('https://cms.example.com'));
        $this->bind(RequestOriginInterface::class)
            ->toInstance(new FakeRequestOrigin(fetchSite: 'cross-site'));
    }
}
