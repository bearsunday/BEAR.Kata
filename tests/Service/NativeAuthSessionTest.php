<?php

declare(strict_types=1);

namespace BEAR\Kata\Service;

use BEAR\Csrf\CsrfTokenInterface;
use BEAR\Kata\Auth\AdminUser;
use BEAR\Kata\Auth\AuthenticatedUser;
use BEAR\Kata\Auth\NativeAuthSession;
use BEAR\Kata\Auth\Visitor;
use Override;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

use function session_id;
use function session_start;

/**
 * Runs in its own process: PHPUnit has already written to stdout in the main
 * one, and session_start() refuses to run once headers are sent.
 */
final class NativeAuthSessionTest extends TestCase
{
    private NativeAuthSession $session;
    private int $tokenClears = 0;

    #[Override]
    protected function setUp(): void
    {
        $this->session = new NativeAuthSession(new class ($this->tokenClears) implements CsrfTokenInterface {
            public function __construct(private int &$clears)
            {
            }

            #[Override]
            public function issue(): string
            {
                return 'token';
            }

            #[Override]
            public function verify(string $candidate): bool
            {
                return $candidate === 'token';
            }

            #[Override]
            public function clear(): void
            {
                $this->clears++;
            }
        });
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testLoginRotatesSessionIdAndClearsToken(): void
    {
        session_start();
        $before = session_id();

        $this->session->login(new AuthenticatedUser(id: 'sub-1', email: 'a@example.com', name: 'A'), 7);

        $this->assertNotSame($before, session_id());
        $this->assertSame(1, $this->tokenClears);
        $user = $this->session->currentUser();
        $this->assertInstanceOf(AdminUser::class, $user);
        $this->assertSame(7, $user->authorId);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testLogoutRotatesSessionIdAndClearsToken(): void
    {
        $this->session->login(new AuthenticatedUser(id: 'sub-1', email: 'a@example.com', name: 'A'), 7);
        $loggedIn = session_id();

        $this->session->logout();

        $this->assertNotSame($loggedIn, session_id());
        $this->assertSame(2, $this->tokenClears);
        $this->assertInstanceOf(Visitor::class, $this->session->currentUser());
    }
}
