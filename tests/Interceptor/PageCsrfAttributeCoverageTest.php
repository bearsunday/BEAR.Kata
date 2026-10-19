<?php

declare(strict_types=1);

namespace BEAR\Kata\Interceptor;

use BEAR\Csrf\Attribute\CsrfToken;
use BEAR\Csrf\Attribute\SameOrigin;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use SplFileInfo;

use function array_map;
use function array_merge;
use function assert;
use function class_exists;
use function sort;
use function sprintf;
use function str_replace;
use function strlen;
use function substr;

/**
 * Enforces that every unsafe HTTP verb on a Page resource carries both
 * `#[SameOrigin]` and `#[CsrfToken]` — closes the "you added a new
 * POST and forgot the attribute" gap that the wiring tests can't
 * catch. The wiring tests prove the interceptors fire when the
 * attributes are present; this one proves the attributes are
 * actually present everywhere they need to be.
 *
 * Walks `src/Resource/Page` recursively — the browser boundary is Page,
 * not just Page/Admin, and a resource in a subdirectory must not fall
 * out of coverage silently. A new Page or a new unsafe method is picked
 * up automatically — no test edit required to keep coverage in sync.
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
final class PageCsrfAttributeCoverageTest extends TestCase
{
    private const string PAGE_DIR = __DIR__ . '/../../src/Resource/Page';
    private const string PAGE_NAMESPACE = 'BEAR\\Kata\\Resource\\Page\\';
    private const array UNSAFE_VERBS = ['onPost', 'onPut', 'onDelete', 'onPatch'];

    /** @dataProvider unsafePageMethodProvider */
    public function testUnsafePageMethodHasSameOriginAndCsrfToken(string $class, string $method): void
    {
        $names = $this->attributeNames(new ReflectionMethod($class, $method));

        $this->assertContains(
            SameOrigin::class,
            $names,
            sprintf(
                '%s::%s is an unsafe Page verb but is missing #[SameOrigin]. '
                . 'Either annotate it or move the resource out of Page if the gate genuinely should not apply.',
                $class,
                $method,
            ),
        );

        $this->assertContains(
            CsrfToken::class,
            $names,
            sprintf(
                '%s::%s is an unsafe Page verb but is missing #[CsrfToken]. '
                . 'Either annotate it or revisit the threat model for this surface.',
                $class,
                $method,
            ),
        );
    }

    /**
     * Discovery that finds nothing would make the data-provider test pass
     * with zero cases; pin the directory and the class derivation here.
     */
    public function testDiscoveryFindsTheAdminWriteMethods(): void
    {
        $found = [];
        foreach (self::unsafePageMethodProvider() as [$class, $method]) {
            $found[] = sprintf('%s::%s', $class, $method);
        }

        $this->assertContains('BEAR\\Kata\\Resource\\Page\\Admin\\Article::onPost', $found);
        $this->assertContains('BEAR\\Kata\\Resource\\Page\\Admin\\Logout::onPost', $found);
    }

    /** @return iterable<string, array{class-string, string}> */
    public static function unsafePageMethodProvider(): iterable
    {
        foreach (self::pageClasses() as $class) {
            $reflection = new ReflectionClass($class);
            foreach (self::UNSAFE_VERBS as $verb) {
                if (! $reflection->hasMethod($verb)) {
                    continue;
                }

                yield sprintf('%s::%s', $class, $verb) => [$class, $verb];
            }
        }
    }

    /** @return list<class-string> */
    private static function pageClasses(): array
    {
        $classes = [];
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(self::PAGE_DIR, RecursiveDirectoryIterator::SKIP_DOTS),
        );
        foreach ($files as $file) {
            assert($file instanceof SplFileInfo);
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen(self::PAGE_DIR) + 1, -strlen('.php'));
            $class = self::PAGE_NAMESPACE . str_replace('/', '\\', $relative);
            if (! class_exists($class)) {
                continue;
            }

            $classes[] = $class;
        }

        sort($classes);

        return $classes;
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
