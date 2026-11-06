<?php

declare(strict_types=1);

namespace BEAR\Kata\Service;

use BEAR\Csrf\CsrfTokenInterface;
use BEAR\Kata\Auth\AdminUser;
use BEAR\Kata\Auth\AuthenticatedUser;
use BEAR\Kata\Auth\NativeAuthSession;
use BEAR\Kata\Auth\Visitor;
use BEAR\Kata\Exception\SessionDestroyException;
use BEAR\Kata\Exception\SessionRotationException;
use Override;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use SessionHandlerInterface;

use function restore_error_handler;
use function session_id;
use function session_name;
use function session_set_save_handler;
use function session_start;
use function session_status;
use function set_error_handler;

use const PHP_SESSION_ACTIVE;
use const PHP_SESSION_NONE;

/**
 * Runs in its own process: PHPUnit has already written to stdout in the main
 * one, and session_start() refuses to run once headers are sent.
 */
final class NativeAuthSessionTest extends TestCase
{
    private NativeAuthSession $session;
    private CsrfTokenInterface $csrf;
    private int $tokenClears = 0;

    #[Override]
    protected function setUp(): void
    {
        $this->csrf = new class ($this->tokenClears) implements CsrfTokenInterface {
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
        };
        $this->session = new NativeAuthSession($this->csrf);
    }

    /** @SuppressWarnings("PHPMD.Superglobals") The cookie is the input under test. */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testVisitorWithoutSessionCookieStartsNoSession(): void
    {
        $this->assertInstanceOf(Visitor::class, $this->session->currentUser());
        $this->assertSame(PHP_SESSION_NONE, session_status());
    }

    /**
     * The cookie is the only thing the next request carries: the session is
     * closed and the array emptied before currentUser() is asked again.
     *
     * @SuppressWarnings("PHPMD.Superglobals") The cookie is the input under test.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testSessionCookieResumesTheStoredUser(): void
    {
        $this->session->login(new AuthenticatedUser(id: 'sub-1', email: 'a@example.com', name: 'A'), 7);
        $id = session_id();
        session_write_close();
        $_SESSION = [];
        $_COOKIE[session_name()] = $id;

        $user = (new NativeAuthSession($this->csrf))->currentUser();

        $this->assertSame(PHP_SESSION_ACTIVE, session_status());
        $this->assertSame($id, session_id());
        $this->assertInstanceOf(AdminUser::class, $user);
        $this->assertSame(7, $user->authorId);
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

    /** @SuppressWarnings("PHPMD.Superglobals") The cookie is the state under test. */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testLogoutDestroysTheSessionAndForgetsTheCookie(): void
    {
        $this->session->login(new AuthenticatedUser(id: 'sub-1', email: 'a@example.com', name: 'A'), 7);
        $_COOKIE[session_name()] = session_id();

        $this->session->logout();

        $this->assertSame(PHP_SESSION_NONE, session_status());
        $this->assertSame(2, $this->tokenClears);
        $this->assertArrayNotHasKey(session_name(), $_COOKIE);
        $this->assertSame([], $_SESSION);
        // Nothing later in the request restarts a session for the signed-out visitor.
        $this->assertInstanceOf(Visitor::class, $this->session->currentUser());
        $this->assertSame(PHP_SESSION_NONE, session_status());
    }

    /**
     * The handler refuses the second destroy: the first is login's regenerate,
     * the second is logout. The user and the cookie are already gone by then.
     *
     * @SuppressWarnings("PHPMD.Superglobals") The cookie is the state under test.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testLogoutReportsAFailedDestroyAfterForgettingTheUser(): void
    {
        session_set_save_handler(new class implements SessionHandlerInterface {
            private int $destroys = 0;

            #[Override]
            public function open(string $path, string $name): bool
            {
                return true;
            }

            #[Override]
            public function close(): bool
            {
                return true;
            }

            #[Override]
            public function read(string $id): string
            {
                return '';
            }

            #[Override]
            public function write(string $id, string $data): bool
            {
                return true;
            }

            #[Override]
            public function destroy(string $id): bool
            {
                return ++$this->destroys < 2;
            }

            #[Override]
            public function gc(int $maxLifetime): int
            {
                return 0;
            }
        });
        $this->session->login(new AuthenticatedUser(id: 'sub-1', email: 'a@example.com', name: 'A'), 7);
        $_COOKIE[session_name()] = session_id();
        set_error_handler(static fn (): bool => true); // PHP warns before returning false

        try {
            $this->session->logout();
            $this->fail('logout() must report a failed destroy');
        } catch (SessionDestroyException) {
            $this->assertSame(2, $this->tokenClears);
            $this->assertArrayNotHasKey(session_name(), $_COOKIE);
            $this->assertArrayNotHasKey('cms_user', $_SESSION);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * session_regenerate_id(true) fails when the old session cannot be destroyed.
     * The login must then abort rather than store the user under the old id.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testLoginAbortsWhenTheSessionIdCannotBeRotated(): void
    {
        session_set_save_handler(new class implements SessionHandlerInterface {
            #[Override]
            public function open(string $path, string $name): bool
            {
                return true;
            }

            #[Override]
            public function close(): bool
            {
                return true;
            }

            #[Override]
            public function read(string $id): string
            {
                return '';
            }

            #[Override]
            public function write(string $id, string $data): bool
            {
                return true;
            }

            #[Override]
            public function destroy(string $id): bool
            {
                return false;
            }

            #[Override]
            public function gc(int $maxLifetime): int
            {
                return 0;
            }
        });
        set_error_handler(static fn (): bool => true); // PHP warns before returning false

        try {
            $this->session->login(new AuthenticatedUser(id: 'sub-1', email: 'a@example.com', name: 'A'), 7);
            $this->fail('login() must not continue after a failed rotation');
        } catch (SessionRotationException) {
            $this->assertSame(0, $this->tokenClears);
            $this->assertInstanceOf(Visitor::class, $this->session->currentUser());
        } finally {
            restore_error_handler();
        }
    }
}
