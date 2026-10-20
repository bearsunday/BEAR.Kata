<?php

declare(strict_types=1);

namespace BEAR\Kata\Alps;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function array_diff;
use function array_unique;
use function array_values;
use function count;
use function file_get_contents;
use function is_array;
use function is_string;
use function json_decode;
use function preg_match_all;
use function sort;

use const JSON_THROW_ON_ERROR;
use const PREG_SET_ORDER;

/**
 * `var/alps/profile.json` is the SSOT (see `kata/alps-profile-ssot.md`), and
 * `#[Alps('id')]` is a resource claiming to implement one of its
 * descriptors. `asd --validate` (`npm run alps:validate`) checks the profile
 * in isolation — it does not check that code references actually resolve
 * into it. This test closes that gap from the code side.
 *
 * The `Cache\*` namespace (`src/Resource/App/Cache/`) is a deliberately
 * isolated showcase for cache attributes (`#[Cacheable]`, `#[DonutCache]`,
 * `#[Embed]` dependency propagation, `UriTagInterface::fromAssoc`) and is
 * out of scope for `profile.json`, whose own `doc` states it "Covers
 * articles, categories, tags, authors and media" — not the cache showcase.
 * `Cache\ArticleTags` spells this out directly: "the showcase deliberately
 * keeps the cache surface self-contained". {@see self::CACHE_SHOWCASE_GAP}
 * is that boundary, enumerated rather than pattern-matched so a *new*
 * reference outside the cache namespace still fails loudly here instead of
 * being silently swallowed by a `Cache*` prefix check.
 */
final class AlpsReferenceIntegrityTest extends TestCase
{
    /**
     * Every `#[Alps]` id declared under `src/Resource/App/Cache/`. Each is a
     * read/write pair for one of the five cache-pattern showcases:
     * `CacheArticlePreview`/`goCacheArticlePreview` (donut-cache),
     * `ArticleTags`/`goCacheArticleTags`/`doUpdateCacheArticleTags`
     * (body-derived dependency via `fromAssoc`),
     * `goCacheAuthor`/`doUpdateCacheAuthor` and `goCacheTag`/`doUpdateCacheTag`
     * (cacheable-leaf), `AuthorProfile`/`goAuthorProfile`
     * (cache-embed-dependency). None of them name an ALPS transition the
     * main CMS application state graph relies on.
     */
    private const array CACHE_SHOWCASE_GAP = [
        'ArticleTags',
        'AuthorProfile',
        'CacheArticlePreview',
        'doUpdateCacheArticleTags',
        'doUpdateCacheAuthor',
        'doUpdateCacheTag',
        'goAuthorProfile',
        'goCacheArticlePreview',
        'goCacheArticleTags',
        'goCacheAuthor',
        'goCacheTag',
    ];

    public function testEveryAlpsReferenceExistsInTheProfile(): void
    {
        $missing = array_diff($this->referencedIds(), $this->profileIds());

        $this->assertSame([], array_values(array_diff($missing, self::CACHE_SHOWCASE_GAP)));
    }

    /**
     * The inverse of the assertion above: an id that leaves the cache
     * showcase (by being added to the profile, or by the resource being
     * deleted) must leave this list too, or the allowlist would silently
     * mask a future unrelated drift.
     */
    public function testCacheShowcaseGapHasNoStaleEntry(): void
    {
        $missing = array_diff($this->referencedIds(), $this->profileIds());

        $this->assertSame([], array_values(array_diff(self::CACHE_SHOWCASE_GAP, $missing)));
    }

    /** A profile or src/ tree that stopped parsing would make the assertions above vacuous. */
    public function testProfileAndReferencesArePopulated(): void
    {
        $this->assertGreaterThan(40, count($this->profileIds()));
        $this->assertGreaterThan(40, count($this->referencedIds()));
    }

    /** @return list<string> */
    private function profileIds(): array
    {
        $profile = json_decode(
            (string) file_get_contents(__DIR__ . '/../../var/alps/profile.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $ids = [];
        if (is_array($profile)) {
            /** @var mixed $descriptors */
            $descriptors = $profile['alps']['descriptor'] ?? [];
            if (is_array($descriptors)) {
                foreach ($descriptors as $descriptor) {
                    if (! is_array($descriptor)) {
                        continue;
                    }

                    /** @var mixed $id */
                    $id = $descriptor['id'] ?? null;
                    if (! is_string($id)) {
                        continue;
                    }

                    $ids[] = $id;
                }
            }
        }

        return $this->normalize($ids);
    }

    /** @return list<string> */
    private function referencedIds(): array
    {
        $ids = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(__DIR__ . '/../../src'),
        );
        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                continue;
            }

            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());
            preg_match_all("/#\[Alps\('([^']+)'\)\]/", $contents, $matches, PREG_SET_ORDER);
            foreach ($matches as [, $id]) {
                $ids[] = $id;
            }
        }

        return $this->normalize($ids);
    }

    /**
     * @param list<string> $values
     *
     * @return list<string>
     */
    private function normalize(array $values): array
    {
        $unique = array_values(array_unique($values));
        sort($unique);

        return $unique;
    }
}
