<?php

declare(strict_types=1);

namespace BEAR\Kata\Interceptor;

use BEAR\Csrf\Exception\ForbiddenException;
use BEAR\Kata\Auth\Visitor;
use BEAR\Kata\Fake\FakeUserModule;
use BEAR\Kata\Injector;
use BEAR\Resource\ResourceInterface;
use PHPUnit\Framework\TestCase;

/**
 * One end-to-end wiring check that `#[CsrfToken]` actually fires when
 * the resource is invoked through the DI container — mirrors
 * `SameOriginWiringTest` in shape and intent. The unit tests in
 * `CsrfTokenInterceptorTest` cover the algorithm; this proves the
 * attribute is matched, the interceptor is bound, and the fake
 * override reaches the runtime.
 *
 * Targets `page://self/admin/logout` — same minimal surface
 * (`onPost` does only `session->logout()`, no DB / no admin user
 * check) as the SameOrigin wiring test. Other annotated `onPost`
 * methods share the same wiring path, so one case is enough.
 */
final class CsrfTokenWiringTest extends TestCase
{
    public function testMissingCsrfTokenOnAdminPostThrowsForbidden(): void
    {
        $injector = Injector::getOverrideInstance(
            'html-test-hal-api-app',
            new MissingCsrfTokenOverrideModule(new FakeUserModule(new Visitor())),
        );
        $resource = $injector->getInstance(ResourceInterface::class);

        $this->expectException(ForbiddenException::class);
        $this->expectExceptionMessage('CSRF token missing.');
        $resource->post('page://self/admin/logout');
    }
}
