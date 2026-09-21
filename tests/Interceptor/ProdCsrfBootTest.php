<?php

declare(strict_types=1);

namespace BEAR\Kata\Interceptor;

use BEAR\Kata\Exception\MissingAllowedOriginException;
use BEAR\Kata\Module\ProdModule;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Ray\Di\AbstractModule;
use Ray\Di\Injector;

use function getenv;
use function putenv;

/**
 * The same-origin gate is armed from an env var, so an unset one runs production without it —
 * silently, because every request still succeeds. ProdModule turns that into a boot failure.
 * Nothing else in the suite covers this: the other CSRF tests run in a test context.
 */
final class ProdCsrfBootTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testProductionRefusesToBootWithoutAnAllowedOrigin(): void
    {
        putenv('CMS_ALLOWED_ORIGIN');

        $this->expectException(MissingAllowedOriginException::class);
        $this->expectExceptionMessage('CMS_ALLOWED_ORIGIN');

        $this->configure(new ProdModule());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testProductionRefusesAnEmptyAllowedOrigin(): void
    {
        putenv('CMS_ALLOWED_ORIGIN=');

        $this->expectException(MissingAllowedOriginException::class);

        $this->configure(new ProdModule());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testProductionBootsWithAnAllowedOrigin(): void
    {
        putenv('CMS_ALLOWED_ORIGIN=https://cms.example.com');

        $this->configure(new ProdModule());

        $this->assertSame('https://cms.example.com', getenv('CMS_ALLOWED_ORIGIN'));
    }

    /** Runs configure() without booting the whole application graph. */
    private function configure(AbstractModule $module): void
    {
        new Injector($module);
    }
}
