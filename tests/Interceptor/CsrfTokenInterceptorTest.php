<?php

declare(strict_types=1);

namespace BEAR\Kata\Interceptor;

use BEAR\Kata\Fake\FakeCsrfToken;
use BEAR\Kata\Fake\FakeRequestBodyToken;
use PHPUnit\Framework\TestCase;
use Ray\Aop\ReflectiveMethodInvocation;
use Ray\Csrf\CsrfTokenInterface;
use Ray\Csrf\Exception\ForbiddenException;
use Ray\Csrf\Interceptor\CsrfTokenInterceptor;

/**
 * Unit tests for `CsrfTokenInterceptor` — covers the verification
 * algorithm directly. End-to-end wiring is `CsrfTokenWiringTest`.
 */
final class CsrfTokenInterceptorTest extends TestCase
{
    public const string PROCEED_SENTINEL = 'proceeded';

    public function testTokenGateDoesNotDependOnOriginConfiguration(): void
    {
        // The two gates are independent defences. This used to short-circuit whenever no
        // allowed origin was configured, which meant one unset environment variable disabled
        // token checking as well — the deployment with the weakest configuration got the
        // weakest protection, silently. Whether an origin is configured is now the
        // same-origin gate's business alone; see CsrfModule's two named constructors.
        $interceptor = new CsrfTokenInterceptor(
            new FakeCsrfToken('session-token'),
            new FakeRequestBodyToken(null),
        );

        $this->expectException(ForbiddenException::class);
        $interceptor->invoke($this->invocation());
    }

    public function testAcceptanceIsDecidedByTheBoundTokenImplementation(): void
    {
        // A missing token reaches verify() as '' instead of being rejected before the port is
        // consulted, so a context can bind an implementation that accepts token-less requests —
        // a fake for tests whose subject is not CSRF, or a warn-only period during a migration.
        // The interceptor must honour that answer rather than overrule it; this assertion is
        // impossible to write if the missing case short-circuits.
        $permissive = new class implements CsrfTokenInterface {
            public function issue(): string
            {
                return 'irrelevant';
            }

            public function verify(string $candidate): bool
            {
                return true;
            }

            public function clear(): void
            {
            }
        };

        $interceptor = new CsrfTokenInterceptor($permissive, new FakeRequestBodyToken(null));

        $this->assertSame(self::PROCEED_SENTINEL, $interceptor->invoke($this->invocation()));
    }

    public function testMatchingTokenProceeds(): void
    {
        $interceptor = new CsrfTokenInterceptor(
            new FakeCsrfToken('session-token'),
            new FakeRequestBodyToken('session-token'),
        );

        $this->assertSame(self::PROCEED_SENTINEL, $interceptor->invoke($this->invocation()));
    }

    public function testMissingSubmittedTokenThrowsForbidden(): void
    {
        $interceptor = new CsrfTokenInterceptor(
            new FakeCsrfToken('session-token'),
            new FakeRequestBodyToken(null),
        );

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('CSRF token missing.');
        $interceptor->invoke($this->invocation());
    }

    public function testMismatchedTokenThrowsForbidden(): void
    {
        $interceptor = new CsrfTokenInterceptor(
            new FakeCsrfToken('session-token'),
            new FakeRequestBodyToken('different-token'),
        );

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('CSRF token invalid.');
        $interceptor->invoke($this->invocation());
    }

    public function testEmptyTokensVerifyAsMismatchedNotMatch(): void
    {
        // Defence: a session that issued an empty token (impossible via
        // SessionCsrfToken, but exercised by FakeCsrfToken to pin the
        // contract) must not pretend an empty submitted value matches.
        $interceptor = new CsrfTokenInterceptor(
            new FakeCsrfToken(''),
            new FakeRequestBodyToken('any-value'),
        );

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('CSRF token invalid.');
        $interceptor->invoke($this->invocation());
    }

    /** @return ReflectiveMethodInvocation<object> */
    private function invocation(): ReflectiveMethodInvocation
    {
        $target = new CsrfTokenInterceptorTestTarget();

        return $this->makeInvocation($target);
    }

    /** @return ReflectiveMethodInvocation<object> */
    private function makeInvocation(object $target): ReflectiveMethodInvocation
    {
        return new ReflectiveMethodInvocation($target, 'onPost', []);
    }
}
