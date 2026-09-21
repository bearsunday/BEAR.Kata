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
 * One end-to-end wiring check that `#[SameOrigin]` actually fires when the
 * resource is invoked through the DI container — the unit tests in
 * `SameOriginInterceptorTest` exercise the algorithm directly, but say
 * nothing about whether the attribute is matched, the interceptor is
 * bound, or the fake bindings reach the runtime. This test asserts the
 * full pipeline rejects a cross-site POST.
 *
 * Targets `page://self/admin/logout` — the smallest Page/Admin onPost
 * surface — so the test is about the gate, not about admin business
 * logic. Other annotated onPost methods (`Article`, `ArticleDelete`,
 * `ArticleConfirm`) share the same wiring path; covering one is enough
 * to prove the bind.
 */
final class SameOriginWiringTest extends TestCase
{
    public function testCrossSitePostOnAdminPageThrowsForbidden(): void
    {
        $injector = Injector::getOverrideInstance(
            'html-test-hal-api-app',
            new CrossSiteOriginOverrideModule(new FakeUserModule(new Visitor())),
        );
        $resource = $injector->getInstance(ResourceInterface::class);

        $this->expectException(ForbiddenException::class);
        $resource->post('page://self/admin/logout');
    }
}
