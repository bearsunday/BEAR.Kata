<?php

declare(strict_types=1);

namespace BEAR\Kata\Service;

use BEAR\Kata\Auth\RequestScheme;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RequestSchemeTest extends TestCase
{
    /** @param array<string, mixed> $server */
    #[DataProvider('schemeProvider')]
    public function testIsHttps(array $server, string|null $publicOrigin, bool $expected): void
    {
        $this->assertSame($expected, (new RequestScheme())->isHttps($server, $publicOrigin));
    }

    /** @return iterable<string, array{array<string, mixed>, string|null, bool}> */
    public static function schemeProvider(): iterable
    {
        yield 'HTTPS on' => [['HTTPS' => 'on'], null, true];
        yield 'HTTPS 1' => [['HTTPS' => '1'], null, true];
        yield 'no HTTPS' => [[], null, false];
        yield 'empty HTTPS' => [['HTTPS' => ''], null, false];
        yield 'HTTPS 0' => [['HTTPS' => '0'], null, false];
        yield 'HTTPS off' => [['HTTPS' => 'off'], null, false];
        yield 'HTTPS OFF' => [['HTTPS' => 'OFF'], null, false];
        yield 'non-string HTTPS' => [['HTTPS' => 1], null, false];
        yield 'https origin behind TLS-terminating proxy' => [['HTTPS' => 'off'], 'https://cms.example.com', true];
        yield 'origin scheme is case-insensitive' => [[], 'HTTPS://cms.example.com', true];
        yield 'plain origin does not override HTTPS request' => [['HTTPS' => 'on'], 'http://localhost:8081', true];
        yield 'plain origin over plain request' => [[], 'http://localhost:8081', false];
    }
}
