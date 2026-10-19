<?php

declare(strict_types=1);

namespace BEAR\Kata\Interceptor;

use BEAR\Csrf\Attribute\CsrfToken;
use BEAR\Csrf\Attribute\SameOrigin;
use PHPUnit\Framework\TestCase;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;

use function array_map;
use function array_merge;
use function basename;
use function class_exists;
use function glob;
use function sprintf;

/**
 * Enforces that every unsafe HTTP verb on `Page/Admin/*` carries both
 * `#[SameOrigin]` and `#[CsrfToken]` — closes the "you added a new
 * admin POST and forgot the attribute" gap that the wiring tests
 * can't catch. The wiring tests prove the interceptors fire when the
 * attributes are present; this one proves the attributes are
 * actually present everywhere they need to be.
 *
 * Discovers admin Page resources from disk and walks the unsafe verbs
 * (`onPost`, `onPut`, `onDelete`, `onPatch`). A new admin Page or a
 * new unsafe method is picked up automatically — no test edit
 * required to keep coverage in sync.
 *
 * Two intentional exclusions:
 *
 *  - GET handlers (`onGet`) are safe by HTTP semantics; CSRF
 *    interceptors don't belong on them.
 *  - The `Callback` page (OAuth provider callback) takes a `code` /
 *    `state` payload on a GET URL the provider redirects to — its
 *    state matching is the OAuth replay defence, not CSRF. It
 *    currently exposes only `onGet`, so it doesn't surface here
 *    anyway, but the docblock pins the reasoning.
 */
final class AdminPageCsrfAttributeCoverageTest extends TestCase
{
    private const string ADMIN_PAGE_DIR = __DIR__ . '/../../src/Resource/Page/Admin';
    private const string ADMIN_PAGE_NAMESPACE = 'BEAR\\Kata\\Resource\\Page\\Admin\\';
    private const array UNSAFE_VERBS = ['onPost', 'onPut', 'onDelete', 'onPatch'];

    /** @dataProvider unsafeAdminMethodProvider */
    public function testUnsafeAdminMethodHasSameOriginAndCsrfToken(string $class, string $method): void
    {
        $names = $this->attributeNames(new ReflectionMethod($class, $method));

        $this->assertContains(
            SameOrigin::class,
            $names,
            sprintf(
                '%s::%s is an unsafe admin verb but is missing #[SameOrigin]. '
                . 'Either annotate it or move the resource out of Page/Admin if the gate genuinely should not apply.',
                $class,
                $method,
            ),
        );

        $this->assertContains(
            CsrfToken::class,
            $names,
            sprintf(
                '%s::%s is an unsafe admin verb but is missing #[CsrfToken]. '
                . 'Either annotate it or revisit the threat model for this surface.',
                $class,
                $method,
            ),
        );
    }

    /** @return iterable<string, array{class-string, string}> */
    public static function unsafeAdminMethodProvider(): iterable
    {
        $files = glob(self::ADMIN_PAGE_DIR . '/*.php') ?: [];
        foreach ($files as $file) {
            $class = self::ADMIN_PAGE_NAMESPACE . basename($file, '.php');
            if (! class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            foreach (self::UNSAFE_VERBS as $verb) {
                if (! $reflection->hasMethod($verb)) {
                    continue;
                }

                yield sprintf('%s::%s', $class, $verb) => [$class, $verb];
            }
        }
    }

    /** @return list<string> */
    private function attributeNames(ReflectionMethod $method): array
    {
        $attributes = array_merge(
            $method->getAttributes(SameOrigin::class),
            $method->getAttributes(CsrfToken::class),
        );

        return array_map(static fn (ReflectionAttribute $a): string => $a->getName(), $attributes);
    }
}
