<?php

declare(strict_types=1);

namespace BEAR\Kata\Interceptor;

use BEAR\Csrf\Http\AllowedOrigin;
use BEAR\Csrf\Http\RequestOriginInterface;
use BEAR\Csrf\Http\RequestTokenInterface;
use BEAR\Kata\Fake\FakeRequestOrigin;
use BEAR\Kata\Fake\FakeRequestToken;
use Override;
use Ray\Di\AbstractModule;

/**
 * Forces `CsrfTokenInterceptor` to actually evaluate (non-null
 * AllowedOrigin) and scripts a missing submitted token to trip the
 * gate. Also scripts `Sec-Fetch-Site: same-origin` so the stacked
 * `SameOriginInterceptor` proceeds and lets the CSRF gate be the
 * first to reject. Scoped to `CsrfTokenWiringTest`.
 */
final class MissingCsrfTokenOverrideModule extends AbstractModule
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
            ->toInstance(new FakeRequestOrigin(fetchSite: 'same-origin'));
        $this->bind(RequestTokenInterface::class)
            ->toInstance(new FakeRequestToken(null));
    }
}
