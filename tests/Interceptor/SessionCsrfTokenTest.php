<?php

declare(strict_types=1);

namespace BEAR\Kata\Interceptor;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Ray\Csrf\CsrfSessionKey;
use Ray\Csrf\SessionCsrfToken;

use function ini_set;
use function session_start;
use function session_status;

use const PHP_SESSION_ACTIVE;

/**
 * Which session slot holds the token is part of the contract, not an
 * implementation detail: a consumer sharing a session with an existing system
 * has to name the slot, and interoperability breaks silently if it cannot.
 */
final class SessionCsrfTokenTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testTokenIsStoredUnderTheConfiguredKey(): void
    {
        $this->startSession();
        $token = new SessionCsrfToken(new CsrfSessionKey('cms_token'));

        $issued = $token->issue();

        $this->assertSame($issued, $_SESSION['cms_token'] ?? null);
        $this->assertArrayNotHasKey('ray_csrf_token', $_SESSION);
        $this->assertTrue($token->verify($issued));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testDefaultKeyIsUsedWhenNoneIsConfigured(): void
    {
        $this->startSession();

        $issued = (new SessionCsrfToken(new CsrfSessionKey()))->issue();

        $this->assertSame($issued, $_SESSION['ray_csrf_token'] ?? null);
    }

    /** A token issued under one key must not satisfy a gate reading another. */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testTokensDoNotLeakAcrossKeys(): void
    {
        $this->startSession();
        $issued = (new SessionCsrfToken(new CsrfSessionKey('area_a')))->issue();

        $other = new SessionCsrfToken(new CsrfSessionKey('area_b'));

        $this->assertFalse($other->verify($issued));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testClearRemovesOnlyTheConfiguredKey(): void
    {
        $this->startSession();
        $_SESSION['unrelated'] = 'keep';
        $token = new SessionCsrfToken(new CsrfSessionKey('cms_token'));
        $token->issue();

        $token->clear();

        $this->assertArrayNotHasKey('cms_token', $_SESSION);
        $this->assertSame('keep', $_SESSION['unrelated']);
    }

    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_cookies', '0');
        session_start();
        $_SESSION = [];
    }
}
