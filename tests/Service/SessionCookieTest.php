<?php

declare(strict_types=1);

namespace BEAR\Kata\Service;

use BEAR\Kata\Auth\SessionCookie;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

use function ini_get;
use function session_get_cookie_params;

final class SessionCookieTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testHardensCookieBeforeAnySessionStarts(): void
    {
        (new SessionCookie())->configure(['HTTPS' => 'on']);

        $params = session_get_cookie_params();
        $this->assertTrue($params['httponly']);
        $this->assertTrue($params['secure']);
        $this->assertSame('Lax', $params['samesite']);
        $this->assertSame('1', ini_get('session.use_strict_mode'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testEmptyHttpsValueMeansPlainHttp(): void
    {
        (new SessionCookie())->configure(['HTTPS' => '']);

        $this->assertFalse(session_get_cookie_params()['secure']);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testZeroHttpsValueMeansPlainHttp(): void
    {
        (new SessionCookie())->configure(['HTTPS' => '0']);

        $this->assertFalse(session_get_cookie_params()['secure']);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSecureFollowsPublicOriginBehindTlsTerminatingProxy(): void
    {
        (new SessionCookie())->configure([], 'HTTPS://cms.example.com');

        $this->assertTrue(session_get_cookie_params()['secure']);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testPlainPublicOriginDoesNotForceSecure(): void
    {
        (new SessionCookie())->configure([], 'http://localhost:8081');

        $this->assertFalse(session_get_cookie_params()['secure']);
    }
}
