<?php

declare(strict_types=1);

namespace BEAR\Kata\Module;

use BEAR\Kata\Exception\MissingAllowedOriginException;
use BEAR\Package\Context\ProdModule as PackageProdModule;
use BEAR\QueryRepository\StorageRedisDsnModule;
use Override;
use Ray\Di\AbstractModule;

use function getenv;

/**
 * Production context overlay.
 *
 * BEAR.Package's ProdModule installs compiled DI, production error/logging
 * bindings, and local QueryRepository cache storage. This project-level module
 * keeps that default path, then optionally swaps the QueryRepository storage
 * to Redis when CMS_REDIS_DSN is configured.
 *
 * It also refuses to boot without an allowed origin. {@see AppModule} picks between the two
 * CsrfModule constructors from CMS_ALLOWED_ORIGIN, and in development the absent case is the
 * right answer — there is no browser origin to compare against. In production the same absence
 * would stand the same-origin gate down for every request, silently, so it is an error here
 * rather than a default.
 */
final class ProdModule extends AbstractModule
{
    private const string ALLOWED_ORIGIN_ENV = 'CMS_ALLOWED_ORIGIN';

    #[Override]
    protected function configure(): void
    {
        $allowedOrigin = getenv(self::ALLOWED_ORIGIN_ENV);
        if ($allowedOrigin === false || $allowedOrigin === '') {
            throw new MissingAllowedOriginException(self::ALLOWED_ORIGIN_ENV);
        }

        $this->install(new PackageProdModule());

        $redisDsn = getenv('CMS_REDIS_DSN');
        if ($redisDsn === false || $redisDsn === '') {
            return;
        }

        $this->install(new StorageRedisDsnModule((string) $redisDsn));
    }
}
