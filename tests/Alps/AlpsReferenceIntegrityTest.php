<?php

declare(strict_types=1);

namespace BEAR\Kata\Alps;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function array_flip;
use function array_unique;
use function array_values;
use function count;
use function dirname;
use function file_get_contents;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function json_decode;
use function ksort;
use function preg_match_all;
use function realpath;
use function sort;
use function str_starts_with;
use function strlen;
use function substr;

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
 * {@see self::CACHE_SHOWCASE_GAP} is that boundary, enumerated rather than
 * pattern-matched so a *new* reference outside the cache namespace still
 * fails loudly here instead of being silently swallowed by a `Cache*` prefix
 * check. An entry explains a reference only when every file declaring that id
 * sits under {@see self::CACHE_SHOWCASE_DIR}; the same id used by a mainline
 * resource is still reported.
 */
final class AlpsReferenceIntegrityTest extends TestCase
{
    private const string CACHE_SHOWCASE_DIR = 'src/Resource/App/Cache/';

    private const string ALPS_REFERENCE = '/#\[Alps\(\s*(?:id\s*:\s*)?(["\'])([^"\']+)\1\s*\)\]/';

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
        $profileIds = array_flip($this->profileIds());
        $unexplained = [];
        foreach ($this->referencedIdsByFile() as $id => $files) {
            if (isset($profileIds[$id])) {
                continue;
            }

            if (in_array($id, self::CACHE_SHOWCASE_GAP, true) && $this->allInCacheShowcase($files)) {
                continue;
            }

            $unexplained[$id] = $files;
        }

        $this->assertSame(
            [],
            $unexplained,
            'Every #[Alps] id must exist in var/alps/profile.json. An allowlisted id is explained only when '
            . 'it is declared solely under ' . self::CACHE_SHOWCASE_DIR . '.',
        );
    }

    /**
     * The inverse of the assertion above: an id that leaves the cache
     * showcase — by being added to the profile, by the resource being deleted,
     * or by appearing under a mainline resource — must leave this list too, or
     * the allowlist would silently mask a future unrelated drift.
     */
    public function testCacheShowcaseGapHasNoStaleEntry(): void
    {
        $profileIds = array_flip($this->profileIds());
        $referenced = $this->referencedIdsByFile();

        foreach (self::CACHE_SHOWCASE_GAP as $id) {
            $this->assertArrayHasKey($id, $referenced, $id . ' is no longer referenced; drop it from CACHE_SHOWCASE_GAP.');
            $this->assertArrayNotHasKey($id, $profileIds, $id . ' now resolves in the profile; drop it from CACHE_SHOWCASE_GAP.');
            $this->assertTrue(
                $this->allInCacheShowcase($referenced[$id]),
                $id . ' is also declared outside ' . self::CACHE_SHOWCASE_DIR . ': ' . implode(', ', $referenced[$id]),
            );
        }
    }

    /** A profile or src/ tree that stopped parsing would make the assertions above vacuous. */
    public function testProfileAndReferencesArePopulated(): void
    {
        $this->assertGreaterThan(40, count($this->profileIds()));
        $this->assertGreaterThan(40, count($this->referencedIdsByFile()));
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

    /** @return array<string, list<string>> id => declaring files, relative to the repository root */
    private function referencedIdsByFile(): array
    {
        $root = (string) realpath(dirname(__DIR__, 2));
        $byId = [];
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

            $path = substr((string) realpath($file->getPathname()), strlen($root) + 1);
            $contents = (string) file_get_contents($file->getPathname());
            preg_match_all(self::ALPS_REFERENCE, $contents, $matches, PREG_SET_ORDER);
            foreach ($matches as [, , $id]) {
                $byId[$id][] = $path;
            }
        }

        ksort($byId);
        foreach ($byId as $id => $files) {
            $byId[$id] = $this->normalize($files);
        }

        return $byId;
    }

    /** @param list<string> $files */
    private function allInCacheShowcase(array $files): bool
    {
        foreach ($files as $file) {
            if (! str_starts_with($file, self::CACHE_SHOWCASE_DIR)) {
                return false;
            }
        }

        return $files !== [];
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
