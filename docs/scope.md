# Scope: what's in, what's not

A snapshot of what this reference codebase demonstrates and where the
known gaps are. The codebase is a **reference CMS** — its purpose is
to show canonical patterns (BEAR.Sunday + ALPS + Ray.MediaQuery + BDR),
not to be feature-complete. Some omissions are deliberate contrasts;
others are deferred until upstream is ready. Every claim is grounded
in the current `1.x` HEAD — if you find a discrepancy, that's a doc bug.

## In scope (implemented)

### Domain & data

| Item | Notes |
|------|-------|
| 5 entities | `Article`, `Author`, `Category`, `Tag`, `Media` — all `final readonly class` with public properties |
| Status enum | `ArticleStatus` (`draft` / `published`) |
| Fake data | 50 records / entity, deterministic (`mt_srand(42)`), referential integrity. Regenerable via `composer fake` |

### App resources (HAL+JSON)

| Resource | Verbs | Notes |
|----------|-------|-------|
| `app://self/article` | GET / POST / PUT / DELETE | Full CRUD; POST/PUT accept `tagIds` (tri-state `null` / `[]` / list) |
| `app://self/article-publish` | POST | State-transition resource for draft → published; optional `publishedAt`, 404 on missing article, 409 when already published |
| `app://self/articles` | GET | Filter (`status`, `categoryId`, `tagId`, `authorId`) + page/perPage; omitted `status` returns all lifecycle states |
| `app://self/author` | GET / POST / PUT | No DELETE; no `authors` collection (asymmetric — see "By design") |
| `app://self/category` / `categories` | GET / POST / PUT / DELETE | |
| `app://self/tag` / `tags` | GET / POST / DELETE | |
| `app://self/media` | GET / POST / DELETE | No `media` collection (asymmetric — see "By design") |
| `app://self/media-upload` | POST | `#[InputFile]` image upload; stores runtime files under `CMS_UPLOAD_DIR` or `var/tmp/uploads` and records Media metadata |
| `app://self/auth` | GET / POST | OAuth flow: GET returns authorization URL, POST exchanges `{code, state}` |
| `app://self/crawl/author` | GET | `linkCrawl` root for the author → articles → tags companion graph |
| `app://self/crawl/articles` | GET | Article summary list with nested DataLoader-backed `tagList` crawl link |
| `app://self/crawl/tags` | GET | Tag-list row contract used by the DataLoader and standalone reads |
| `app://self/cache/articlepreview` | GET | Explicit `#[DonutCache]` HAL preview example; scalar-only because donut-hole placeholders are string-renderer oriented |
| `app://self/cache/author` | GET / PUT | Cache showcase leaf — user-zero-code (`#[Cacheable]` only) |
| `app://self/cache/authorprofile` | GET | Cache showcase parent — `#[Embed]`-only automatic dependency (single-child, zero cache code; since `bear/query-repository` 1.16) |
| `app://self/cache/tag` | GET / PUT | Cache showcase leaf — user-zero-code (`#[Cacheable]` only) |
| `app://self/cache/articletags` | GET / PUT | Cache showcase parent — one-line `fromAssoc` for body-derived variable-length dependency set; PUT is the showcase's own write entry point (main `app://self/article` writes are intentionally out of scope) |

There is no `app://self/` entry point at the App layer; `Page/Index`
serves as the public HTML entry. (Discoverability via HAL `_links` is
demonstrated from each top-level resource.)

### Page resources (Qiq HTML)

| Surface | Resources | Verbs |
|---------|-----------|-------|
| Public read-only | `Index`, `Article`, `ArticleList`, `Author`, `AuthorList`, `Category`, `CategoryList`, `Tag`, `TagList` | GET only; `ArticleList` always restricts results to published articles |
| Admin write | `Page/Admin/Article` (create / edit form) | GET, POST |
| Admin write | `Page/Admin/ArticleConfirm` (publish preview / confirm form) | GET, POST |
| Admin write | `Page/Admin/ArticleDelete` (confirm form) | GET, POST |
| Admin read | `Page/Admin/ArticleList` | GET only |

Admin pages wrap the App resources via `$this->resource->post/put/delete(...)`.
PRG: create/update success redirects with `?saved=created|updated`, delete
success redirects with `?deleted=1`, and publish success redirects to the
public article URL.

### Resource variations (reading material, not API)

`src/Resource/App/Variations/` — three alternative implementations of the
Article GET to compare entity vs array, declarative vs programmatic query, and
MediaQuery vs raw PDO. It also contains `MediaStream`, a separate transfer-mode
variation that demonstrates `BEAR.Streamer` without changing canonical
`Media::onGet()`. Run `composer demo:variations`.

### Hypermedia

| Feature | Where | Notes |
|---------|-------|-------|
| `_links` | `#[Link]` attributes (RFC 6570 templates) | Rels follow ALPS Choreography names (`goArticleList`, `goAuthor`, …) |
| `_embedded` | `#[Embed]` + `addQuery()` for parametric embeds; manual array build inside `onGet` for ID-after-fetch cases | |
| `linkCrawl` | `src/Resource/App/Crawl/*` + `ArticleTagsDataLoader` | `ResourceInterface::crawl('app://self/crawl/author', 'author-tree', ['id' => 1])` traverses author → articles → tags and batches tags with one MediaQuery call |
| ALPS profile | `var/alps/profile.json` (single source of truth) | HTML/SVG rendering via `composer doc` (npm ASD) |

### Validation

| Layer | Mechanism | Coverage |
|-------|-----------|----------|
| Response body | `#[JsonSchema(schema: '...')]` | Canonical App GET resources (`Article`, `Articles`, `Auth`, `Author`, `Category`, `Categories`, `Tag`, `Tags`, `Media`, crawl companion resources, and the cache showcase) plus `Auth::onPost` (separate `auth_response.json` for string subject id). DELETE methods return without body validation |
| Request params | `#[JsonSchema(params: '...')]` | POST and PUT on the resources above (DELETE takes only `int $id`, no params schema) |
| Input DTO | `#[Input]` + `Ray\InputQuery` | `ArticleCreateInput`, `ArticleUpdateInput`, `AuthExchangeInput`. Author / Category / Tag / Media remain scalar by intentional contrast — see "By design" |
| File upload | `#[InputFile]` + `Koriym\FileUpload` | `MediaUpload::onPost()` validates image MIME/extension/size and persists Media metadata |
| Native array DTO inputs | `array` / `array|null` via Ray.InputQuery 1.1 → malformed shapes become `ParameterException` (→ 400) | See `conventions.md` §4 "Native array DTO inputs" |

### Auth

| Item | Notes |
|------|-------|
| `AuthInterface` | Backend abstraction |
| `GoogleAuthProvider` | `league/oauth2-google`; default provider |
| `Auth0AuthProvider` | `auth0/auth0-php`; selected with `CMS_AUTH_PROVIDER=auth0` |
| `FakeAuthProvider` | Test/fake context |
| `AuthenticatedUser` | `final readonly`; keeps `id` as a backward-compatible subject alias and exposes `provider` + `subject` |
| `AuthIdentity` | `(provider, subject) -> authorId` mapping for stable external identities; email is first-login fallback only |
| `Auth` resource | Auth flow shape with response schema |
| `AuthSessionInterface` | Session-backed current-user, OAuth state, login, and logout boundary |
| `AdminUserInterface` | Authenticated admin identity carrying author ownership; enforced in `Page/Admin/*` through `AdminGuard` |
| Practical Google guide | `docs/auth-google.md` / `docs/ja/auth-google.md` show OAuth client setup, `.env`, callback, identity mapping, logout, and env-gated authorization URL smoke |

Note: `Page/Admin/*` is now behind an `AdminGuard` check backed by `UserInterface` / `AdminUserInterface`, with CSRF protection on admin form posts via the `BEAR\Csrf` `#[SameOrigin]` + `#[CsrfToken]` interceptors.

### Persistence & migrations

| Item | Notes |
|------|-------|
| Doctrine Migrations | `var/db/migrations/` |
| Seed | `bin/seed.php` loads the fake into a real backend |
| SQL files | `var/db/sql/<entity>_<verb>.sql` — column order matches `__construct` (PDO::FETCH_FUNC) |
| Read SQL contract | Single `#[DbQuery]` interceptor routes by return type to `getRow` / `getRowList`; `exec()` is unused |
| Backends | MySQL (Malt or docker compose), SQLite (CI / no-docker fallback) |

### Testing

| Suite | Target | Backend |
|-------|--------|---------|
| `tests/Resource/App` | App API | `FakeSqlQuery` (no DB) |
| `tests/Resource/Page` | Page HTML | `FakeSqlQuery` via `html-test-hal-api-app` |
| `tests/Hypermedia` | HAL link/embed contract | Fake |
| `tests/Smoke` | Lifecycle, SQL, MediaQuery, and Resource GET/schema smoke | Fake |
| `tests/Entity` | Entity invariants | n/a |
| `tests/Integration/<Entity>MySQLTest.php` | 5 entities (Article, Author, Category, Tag, Media) | Real MySQL (auto-skips when unreachable) |
| `tests/params` | SQL, query, resource, and JSON-Schema params validation | n/a |

`composer test` runs the full suite. `tests/Smoke/ResourceSmokeTest.php` walks canonical GET-able App resources and validates their rendered output against the declared JSON Schema using fake fixture arguments from `tests/params/resource_args.php`. `composer demo` is a 7-section walkthrough that auto-detects malt → docker → sqlite for the real-DB section.

### CLI & tooling

| Command | Purpose |
|---------|---------|
| `composer fake` | Regenerate `var/fake/*.json` |
| `composer schema` | Regenerate `var/json_schema/*.json` |
| `composer semantic` | Both of the above |
| `composer cli` | `bear-cli-gen` — generates `bin/cli/*` from `#[Cli]`-annotated resources |
| `composer serve` / `serve:api` | HTML / API HTTP servers |
| `composer demo` | End-to-end walkthrough |
| `composer demo:cache` | QueryRepository cache showcase (hermetic, in-memory ArrayAdapter) |
| `composer doc` | apidoc + OpenAPI + llms.txt + term index + documentation audit + ALPS HTML/SVG |
| `composer compile` | bear.compile production graph |

Generated commands exist for `article-show` and `article-list` (under `bin/cli/`). Expanding generation to the other entities and to the write-side methods is intentionally out of scope — see "By design".

### Reference patterns shown

Patterns the codebase deliberately demonstrates (each appears in at least one place, not necessarily everywhere):

| Pattern | Where |
|---------|-------|
| `FetchInjectionFactory` (DI into hydrated entity) | `Page/Article` injects `MarkdownRendererInterface` |
| Input DTO via `#[Input]` + `Ray\InputQuery` | `Article` (POST/PUT), `Auth` (POST) — contrasted against scalar `onPost` on Author/Category/Tag/Media |
| Tri-state optional collection input | `tagIds` on `ArticleCreateInput` / `ArticleUpdateInput` |
| Ray.MediaQuery pager | `ArticleQueryInterface::list()` / `PagesInterface` |
| Ray.MediaQuery SELECT result class | `ArticleSelectionQueryInterface::list()` / `ArticleSelection` |
| Ray.MediaQuery DML metadata result | `Samples\ArticleAffectedRowsCommandInterface` / `AffectedRows` |
| Natural-key `by<Key>` post-INSERT lookup | `Article::onPost` → `bySlug`; same idea for `byEmail` / `byFilename` |
| Manual `_embedded` build for ID-after-fetch | `Article::onGet` (`author`, `category`, `tagList`) |
| BEAR.Async opt-in embed parallelization | `bin/async.php` overlays `ParallelRuntimeModule`; Article's `author` / `category` / `tagList` embeds are the reference graph |
| Three Article GET implementation variations | `src/Resource/App/Variations/` (`composer demo:variations`) |
| Stream transfer response | `Variations\MediaStream` uses `BEAR.Streamer` and an open file handle body |
| File upload boundary | `MediaUpload` demonstrates `#[InputFile]`, `FileUpload`, `ErrorFileUpload`, and runtime storage configuration |
| `linkCrawl` + DataLoader batching | `Crawl\Author` → `Crawl\Articles` → `Crawl\Tags`; `ArticleTagsDataLoader` collapses per-article tag reads into `TagQueryInterface::listByArticles()` |
| QueryRepository cache — user-zero-code leaf | `Cache\Author`, `Cache\Tag` (`#[Cacheable]` only; reflection-pinned) |
| QueryRepository cache — `#[Embed]`-only parent (single-child, auto-merged) | `Cache\AuthorProfile`; reflection-pinned to zero manual cache code (since `bear/query-repository` 1.16.0) |
| QueryRepository cache — one-line `fromAssoc` parent (N-child, body-derived) | `Cache\ArticleTags`; reflection-pinned to exactly one `fromAssoc` call |
| QueryRepository cache — explicit `#[DonutCache]` | `Cache\ArticlePreview`; scalar HAL preview, no invented clock/random demo |
| Application import companion | `examples/import/ImportedCatalog`; `ImportAppModule` mounts `app://catalog/status` alongside `app://self/*` in a focused test |
| Production/security operating reference | `src/Module/ProdModule.php` plus `docs/production.md`; BEAR.Package prod module, optional `CMS_REDIS_DSN` QueryRepository storage, compile artifacts, and SAST/taint commands |
| Practical Google auth reference | `docs/auth-google.md`; Google OAuth setup, callback, identity mapping, logout, common failures, and env-gated smoke |
| Reader/admin article visibility split | Public `Page/ArticleList` enforces `published`; admin `Page/Admin/ArticleList` can show all, draft, or published author-owned articles |
| PRG redirect on admin write | `Page/Admin/Article`, `Page/Admin/ArticleConfirm`, and `Page/Admin/ArticleDelete` redirect 303 after successful writes |

### Documentation surface

`README.md` → `docs/{en,ja}/reading-guide.md` → `docs/architecture.md` → `docs/conventions.md` → `docs/resources.md` → `docs/production.md` → `docs/auth-google.md` → `docs/alps.md` → `docs/journal/*`. Conventions is the canonical rulebook for new code.

---

## Historical: resolved upstream blockers

These were once blockers that prevented the canonical pattern from being shown; they are listed here so the journal trail makes sense (they're already reflected in the In-scope tables above).

| # | Item | Closed by |
|---|------|-----------|
| R1 | DTO recognition by `JsonSchemaInterceptor` ([BEAR.Resource#356](https://github.com/bearsunday/BEAR.Resource/issues/356)) | BEAR.Resource 1.31.1 — `Article` / `Auth` re-attached `#[JsonSchema]` |
| R2 | OpenAPI generator skipped DTO methods ([BEAR.ApiDoc#81](https://github.com/bearsunday/BEAR.ApiDoc/issues/81)) | BEAR.ApiDoc 1.9.1 |
| R3 | `JsonSchema` body validation on cache hit ([BEAR.Resource#355](https://github.com/bearsunday/BEAR.Resource/issues/355)) | BEAR.Resource 1.31.1 — unblocked the cache showcase under `src/Resource/App/Cache/*` (`composer demo:cache`); main-resource rollout remains D1 |
| R4 | Typed-array DTO field × validation order pitfall ([decisions P8 #46](journal/decisions-to-consult.md)) | BEAR.Resource 1.x-dev / Ray.InputQuery 1.1 native `array` / `array|null` DTO inputs; `conventions.md` §4 |
| R7 | Admin write/delete failure propagation (CodeRabbit feedback on PR #18) | Commit `0d7f98d` — `Page/Admin/Article` and `Page/Admin/ArticleDelete` propagate 4xx codes back instead of redirecting |

---

## Historical: resolved project gaps

These were once listed as deferred project work. They are now implemented,
and remain here only so older journal entries make sense.

| # | Item | Closed by |
|---|------|-----------|
| D2 | Auth boundary for `Page/Admin/*` | `UserInterface` / `AdminUserInterface`, providers, `AdminGuard`, Google OAuth session login, author-scoped ownership, and CSRF form protection |
| D11 | Application import companion example | `examples/import/ImportedCatalog` plus `ImportAppExampleTest` demonstrate `ImportAppModule` without adding artificial CMS behavior |
| D12 | `linkCrawl` / DataLoader companion | `src/Resource/App/Crawl/*`, `ArticleTagsDataLoader`, and `CrawlDataLoaderTest` demonstrate author → articles → tags traversal with one batched tag query |
| D13 | Production/security operating reference | `docs/production.md`, `docs/ja/production.md`, `composer security:sast`, and `composer security:taint` document the opt-in production/security path without adding Redis/DAST/OAuth requirements to default tests |
| D14 | Practical Google auth reference | `docs/auth-google.md`, `docs/ja/auth-google.md`, and `GoogleAuthProviderSmokeTest` make Google the canonical admin login path while keeping Auth0/OIDC secondary |
| D7 | `Articles` collection `totalCount` | MediaQuery `PagesInterface::total` is exposed as `totalCount` in the collection body |
| D8 | `#[Pager]` / `PagesInterface` adoption decision | Article collection reads use Ray.MediaQuery `#[Pager]`; fake uses Pagerfanta `ArrayAdapter` |

---

## By design (intentional omissions)

These aren't bugs or backlog — they're deliberate choices that keep the reference focused.

| Item | Why this way |
|------|--------------|
| Scalar `onPost` / `onPut` on Author, Category, Tag, Media | Kept scalar so a reader sees both styles side-by-side. `Article` and `Auth` show the `#[Input]` + DTO style; the others show plain scalar parameters. Migrating all four would erase the contrast |
| No `authors` or `media` list resource | The two collections that exist (`articles`, `categories`, `tags`) are enough to demonstrate the list pattern, filtering, and pagination. Adding more would be repetition |
| Generated CLI beyond `article-show` / `article-list` | `article-show` and `article-list` already demonstrate the `#[Cli]` / `#[Option]` pattern in full. Generating the same wrappers for the other entities and the write-side methods is the repetition this reference deliberately avoids — the same rationale as "No `authors` or `media` list resource" above. `Article::onPost` / `onPut` could not be CLI-exposed in any case: they take an `#[Input]` DTO and `bear/cli` only maps `#[Option]` scalar parameters, so that scalar-vs-DTO boundary is documented here rather than demonstrated with generated files |
| No `app://self/` entry point | `Page/Index` is the public HTML entry; HAL discoverability is shown via per-resource `_links` |
| JS-enhanced admin (HTMX or similar) | Out of demonstration scope; the patterns to demonstrate are server-side. An optional add-on would not change App-layer code |
| Applying `#[Cacheable]` to the main `Article` resource | `Article` composes three embeds (`author`, `category`, `tagList`) and `tagList` is itself a body-derived variable-length list. Mixing `#[Embed]`-driven composition and `fromAssoc()`-driven cross-resource invalidation on the same response is exercised by the `Cache\*` showcase as the canonical pattern; leaving the main `Article` untouched keeps the principal resource side-by-side comparable against the showcase rather than entangling the two demos |
| PSR-7 request context demo | Deprioritized by design. A diagnostics resource would be easy but unused in this CMS; add one only when a concrete header/cookie/client-IP use emerges |
| OAuth provider zoo | Google and Auth0/OIDC intentionally cover the two useful shapes: social login and generic tenant-backed identity provider. Adding more providers would mostly repeat configuration mechanics |
| Other-language connection | Keep out of the CMS mainline. This is better as a companion BEAR.Thrift or isolated `examples/` demo than as an artificial CMS feature |

## Deferred / not built

Drawn from `architecture.md` "What was intentionally not built", `journal/handoff.md` "Known gaps", and `journal/decisions-to-consult.md` P4. Recovery column tells you what unblocking each one looks like.

| # | Item | Why deferred | Recovery / next step |
|---|------|--------------|----------------------|
| D1 | Entity-level cache rollout and article-list query-string variants | **Partial — canonical list reads/writes are covered.** `Articles` / `Categories` carry class-level `#[CacheableResponse]`; `Article` / `Category` writes carry `#[Purge(uri: 'app://self/{collection}')]`. Query-string variants (for example `?categoryId=3`) are distinct cache entries and are deliberately left as application policy rather than a reference implementation. Two other intentional exclusions remain: (1) entity resources skip class-level caching because `DonutCommandInterceptor` re-runs `onGet` on deleted entities and mutates `204 → 404`; (2) `Tags` skips caching because it is embedded in `Article` via `#[Embed(rel: 'tagList')]` — when the html context materialises the embed, the donut pipeline calls `(string) $ro` and `CmsQiqRenderer` has no App-template, throws, and breaks the ETag chain | Pipeline verified in `tests/Resource/App/CacheTest.php` (asserts `try-donut-view` / `put-donut` / `save-etag` / `purge-query-repository` in `RepositoryLogger`). Add query-string variant invalidation only when a real CMS workflow needs cached filter variants; revisit entity-level caching once the delete-mutation upstream behavior is clarified |
| D3 | Async Docker CI smoke | Runtime containers exist, but CI does not yet build ext-parallel and run `composer parallel:demo` | Add a focused GitHub Actions job once image build time and caching are acceptable |
| D5 | Real OAuth integration tests | Needs Google/Auth0 creds + callback URLs | env-gated tests that skip unless provider env vars are set |
| D9 | phpstan baseline (1 entry) | Upstream OAuth provider arg-type widening | Wait for upstream relaxation, then drop the entry |
| D10 | Migration to `bearsunday/coding-standard` | Drafted as [coding-standard-roadmap/003](journal/coding-standard-roadmap/003-myvendor-cms-adopts-bearsunday-cs.md); blocked on the package's v0.1 + 001 (`@input-param` expansion) landing | After upstream lands, swap composer dependency and run the migration playbook in 003 |

---

## Sources

The information above is consolidated from these existing journal entries; this doc is the menu, those are the conversation behind each line item.

- [`docs/architecture.md`](architecture.md) "What was intentionally *not* built" — design-decision framing
- [`docs/journal/handoff.md`](journal/handoff.md) "Known gaps (deferred deliberately)" — operational view with recovery instructions
- [`docs/journal/decisions-to-consult.md`](journal/decisions-to-consult.md) P4 "黙ってスコープから落とした項目" / P8 "後追いで埋めたい穴" — original deferral rationale
- [`docs/journal/auth-boundary-plan.md`](journal/auth-boundary-plan.md) — auth boundary design (D2)
- [`docs/journal/coding-standard-roadmap/`](journal/coding-standard-roadmap/) — coding-standard package roadmap (D10)
