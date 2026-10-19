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
    public function testSecureFollowsRequestScheme(): void
    {
        (new SessionCookie())->configure([]);

        $this->assertFalse(session_get_cookie_params()['secure']);
    }
}
