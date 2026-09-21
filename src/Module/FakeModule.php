<?php

declare(strict_types=1);

namespace BEAR\Kata\Module;

use BEAR\Kata\Auth\AuthInterface;
use BEAR\Kata\Auth\AuthSessionInterface;
use BEAR\Kata\Fake\FakeAdminAuthSessionProvider;
use BEAR\Kata\Fake\FakeAuthProvider;
use BEAR\Kata\Fake\FakeCsrfToken;
use BEAR\Kata\Fake\FakeRequestOrigin;
use BEAR\Kata\Fake\FakeRequestToken;
use BEAR\Kata\Fake\FakeSqlQuery;
use Ray\Csrf\CsrfTokenInterface;
use Ray\Csrf\Http\AllowedOrigin;
use Ray\Csrf\Http\RequestOriginInterface;
use Ray\Csrf\Http\RequestTokenInterface;
use Ray\Di\AbstractModule;
use Ray\Di\Scope;
use Ray\MediaQuery\SqlQueryInterface;

/**
 * Swaps real-infra interfaces with deterministic in-memory fakes.
 *
 * Use context `fake-hal-api-app` (or `cli-fake-hal-api-app`) to run the app
 * without a real database / OAuth provider, backed by var/fake/*.json and
 * a fixed user identity.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects") composition root
 */
final class FakeModule extends AbstractModule
{
    protected function configure(): void
    {
        $this->bind(SqlQueryInterface::class)->to(FakeSqlQuery::class)->in(Scope::SINGLETON);
        $this->bind(AuthInterface::class)->to(FakeAuthProvider::class)->in(Scope::SINGLETON);
        $this->bind(AuthSessionInterface::class)->toProvider(FakeAdminAuthSessionProvider::class)->in(Scope::SINGLETON);

        // Force the CSRF gates off — fake / test runs don't drive HTTP, so
        // there's no Sec-Fetch-Site / Origin / Referer / _csrf_token to
        // script. Tests that exercise the gates rebind AllowedOrigin (and
        // the relevant header / body fake) via overrideModule().
        $this->bind(AllowedOrigin::class)->toInstance(new AllowedOrigin(null));
        $this->bind(RequestOriginInterface::class)->to(FakeRequestOrigin::class)->in(Scope::SINGLETON);
        $this->bind(CsrfTokenInterface::class)->to(FakeCsrfToken::class)->in(Scope::SINGLETON);
        $this->bind(RequestTokenInterface::class)->to(FakeRequestToken::class)->in(Scope::SINGLETON);
    }
}
