# CSRF defence design

This note records the cross-site request defence layered into the admin
surface. Lives next to
[`validation-layer-design.md`](validation-layer-design.md) and
[`auth-boundary-plan.md`](auth-boundary-plan.md) — together they cover
admin-side request handling end to end.

The choreography comes from
[Issue #37](https://github.com/bearsunday/MyVendor.Cms/issues/37) (Form /
Confirmation / CSRF strategy) and the Codex review pass that followed.

**Status: 実装はこのrepositoryを離れた。** ここに記録された設計は
[`ray/csrf`](https://github.com/ray-di/Ray.Csrf) として切り出され、`src-csrf/` は削除された。
以下で `src-csrf/` を指す記述は、インキュベーション期間の設計史として読むこと。
package 化にあたり、application統合で見つかった4件の欠陥
([#4](https://github.com/ray-di/Ray.Csrf/issues/4) /
[#5](https://github.com/ray-di/Ray.Csrf/issues/5) /
[#6](https://github.com/ray-di/Ray.Csrf/issues/6) /
[#7](https://github.com/ray-di/Ray.Csrf/issues/7)) を修正した。#7 は未解決で、
同梱 store は coroutine host で例外を投げて拒否する。

---

## Goal

Reject browser-driven unsafe HTTP verbs that originate from outside the
deployment's own origin. The classic CSRF concern: a cookie-authenticated
admin visiting an unrelated page should not have their cookies
weaponised to issue `POST /admin/articledelete?id=1` from
`https://evil.example`.

The defence is **not** a substitute for identity:
- Identity comes from the session cookie (see
  [`auth-boundary-plan.md`](auth-boundary-plan.md)).
- This layer says "the browser that sent this request was on our site
  when it was sent" — nothing more.

Both checks have to pass before a write reaches the resource method.

---

## Architecture

Two attributes layer the defence:

| Attribute | What it requires | Where |
|---|---|---|
| `#[SameOrigin]` | Browser-emitted same-origin signals (`Sec-Fetch-Site`, `Origin`, `Referer`) match the configured allowed origin. | All Page/Admin `onPost` methods. |
| `#[CsrfToken]` | A per-session token submitted as `_csrf_token` in the form body matches the one stored in session. | Destructive / session-changing onPost methods: `Article`, `ArticleDelete`, `ArticleConfirm`, `Logout`. |

The two layers stack — `#[SameOrigin]` defends the bulk of cookie-driven
CSRF cheaply; `#[CsrfToken]` adds belt-and-braces protection on the
operations where a same-origin compromise (XSS in a sibling subdomain,
sloppy `SameSite` defaults on a related origin, etc.) would do the most
damage.

Naming follows the @NaokiTsuchiya note in Issue #37: the attribute
describes **what the resource requires**, not **the attack it defends
against**. `#[SameOrigin]` reads as a precondition; `#[Csrf]` would
read as a defence implementation detail.

---

## `#[SameOrigin]` runtime

### Wiring

`CsrfModule` (`src-csrf/CsrfModule.php`, namespace `Ray\Csrf`) binds
both interceptor pointcuts and the typed value classes they depend
on (`AllowedOrigin`, `CsrfTokenField`). `AppModule` installs the
module after the auth bindings, passing the operator-controlled
`CMS_ALLOWED_ORIGIN` env value through the constructor.
`FakeModule` rebinds the interfaces to in-memory fakes
(`BEAR\Kata\Fake\Fake*`) so tests / CLI / fake-app don't have to
script HTTP headers.

`RequestOriginInterface` and `AllowedOrigin` are deliberately
separate. The first models inbound HTTP headers (untrusted, client-
controlled); the second is server configuration (trusted, set by
the operator). Conflating them would let one fake script both
sides and obscure that they're different concerns.

The library lives in `src-csrf/` as an upstream-candidate package —
see `src-csrf/README.md` for the donation plan. Consumer `use`
statements already address `Ray\Csrf\` so the namespace is the
migration contract.

### Detection algorithm

In priority order:

1. **`Sec-Fetch-Site`** (Fetch Metadata, RFC-adjacent). It's a
   forbidden request header — browser-generated, not settable from
   JS — so a `same-origin` value is authoritative. Anything else
   (`same-site`, `cross-site`, `none`, or an unknown literal) is
   rejected as `ForbiddenException` (403). Unknown values are
   treated as cross-site rather than fallthrough; a surprising
   value is more likely an attack than a new browser literal we
   should trust.

2. **`Origin`**. Compared as a *canonical origin* (scheme + host
   lowercased per RFC 3986, default ports for the scheme collapsed,
   no path / query / fragment / userinfo). Mismatch → 403.
   Malformed (parse failure, `Origin: null`, anything with a path
   beyond `/`) → `BadRequestException` (400). The 400/403 split
   reflects RFC semantics: 400 is "your request is wrong", 403 is
   "we won't process this request".

3. **`Referer`**. Same canonical comparison after extracting the URL's
   origin component. Used only when both `Sec-Fetch-Site` and
   `Origin` are absent.

4. **All three absent**. With `AllowedOrigin->value` non-null, the
   request is rejected as 403 (fail-closed). With `value === null`
   the gate short-circuits at the top of the interceptor and never
   reaches this branch.

### Short-circuit mode — 撤回

当初は `AllowedOrigin->value === null` が **両方** の門
(`SameOriginInterceptor` と `CsrfTokenInterceptor`) を無効化していた。
「production HTTP は強制、それ以外は素通り」という mental model を
config 値ひとつに畳む意図だったが、これは誤りだった。二つの門は
独立した防御であり、片方が不要な状況はもう片方が不要な理由にならない。
origin を比較する相手がいないCLI/testでも、token の検証は成立する。

現在は `AllowedOrigin` を読むのは `SameOriginInterceptor` だけで、
`value === null` は same-origin 門のみを外す。token 門は常に on で、
受理可否は束縛された `CsrfTokenInterface` が決める — CSRFが主題でない
テストは寛容な実装を束縛すればよく、門ごと外す必要がない。

**Production gotcha — resolved.** `null` means "no origin to compare
against", which is the right answer for CLI and development but would
stand the same-origin gate down for every request in production. It is
therefore no longer reachable by omission: `CsrfModule`'s constructor is
private and the choice is made by calling `withSameOriginCheck()` or
`withoutSameOriginCheck()`, and `ProdModule` throws
`MissingAllowedOriginException` at boot when `CMS_ALLOWED_ORIGIN` is
unset. The token gate never depended on this value in the first place —
that coupling was removed at the same time, so a missing origin can no
longer disable token verification as a side effect.

### What this layer doesn't do

- **Session cookie flags** (`SameSite=Lax`, `Secure`, `HttpOnly`)
  belong on the cookie issuer, not on the request gate. The
  interceptor reads inbound headers; cookie flags are an outbound
  concern, set by the auth session layer. They're complementary
  defences — neither replaces the other.
- **`X-CSRF-Token` header support** for JavaScript-driven writes.
  The current surface is server-rendered forms, so synchroniser tokens
  are submitted as hidden fields.

---

## Why on Page/Admin, not on App

`#[SameOrigin]` is attached only to `Page/Admin/*::onPost`. The App
resources (`app://self/article` etc.) deliberately stay unguarded:

- App resources are routinely invoked from the CLI, from seeds, from
  Page-layer composition — contexts where there is no HTTP request
  and no headers to check. An interceptor that assumed an HTTP
  request would break those callers.
- The Page layer is the public surface for browsers. Putting the gate
  there matches the threat: a CSRF attack reaches the server through
  a browser submitting to a Page resource, not through a process
  calling `$resource->post('app://self/article')` directly.

This is the Codex review's most important pre-implementation
correction — the original sketch put `#[SameOrigin]` on the App
methods and would have broken every internal use of those resources.

---

## Test coverage

- **`tests-csrf/Http/AllowedOriginTest`,
  `tests-csrf/Http/CsrfTokenFieldTest`,
  `tests-csrf/Exception/ForbiddenExceptionTest`** — pure value-class
  / exception unit tests on the library surface. Migrate alongside
  the source when `ray/csrf-module` ships.
- **`tests/Interceptor/SameOriginInterceptorTest`** — 18 cases over
  the algorithm: allowed-origin null short-circuit, every
  `Sec-Fetch-Site` literal (including the explicit reject for
  unknown values), `Origin` match / mismatch / canonicalisation
  (default port, case), `Origin` malformed / `null`-literal /
  with-path, `Referer` match / mismatch / malformed,
  all-signals-missing fail-closed, malformed-allowed-origin
  configuration fail-closed.
- **`tests/Interceptor/SameOriginWiringTest`** — one end-to-end
  case: a cross-site POST through the DI container (with
  `#[SameOrigin]`-annotated `Page/Admin/Logout::onPost`) raises
  `ForbiddenException`. Proves the attribute matches, the
  interceptor is bound, and the fake overrides reach the runtime.
  Other annotated `onPost` methods share the wiring path, so one
  case is enough.

The unit and wiring split is intentional: unit tests are hermetic and
fast; the wiring test is the safety net that catches a stale module
binding before CI does.

---

## Library packaging

`src-csrf/` (namespace `Ray\Csrf`) and its companion `tests-csrf/`
hold the CSRF stack as a self-contained upstream candidate. Nothing
in `src-csrf/` references `BEAR\Kata` — the library depends only
on `ray/aop`, `ray/di`, and `bear/resource`.

When `ray/csrf-module` is published (target: koriym/ray.csrf or
similar Ray.* vendor), migration is:

1. Delete `src-csrf/` and `tests-csrf/`.
2. Remove `Ray\\Csrf\\` from `composer.json` `autoload` and
   `autoload-dev`.
3. Remove `src-csrf` / `tests-csrf` from `phpcs.xml`, `phpstan.neon`,
   `psalm.xml`, the `phpmd` composer script, and the PHPUnit
   `<source>` section. Drop the `csrf` testsuite from
   `phpunit.xml.dist`.
4. Add `ray/csrf-module` to `composer.json` `require`.
5. Clear the DI cache: `rm -rf var/tmp/*-hal-app var/tmp/*-hal-api-app`.

Consumer `use` statements (already addressing `Ray\Csrf\…`) stay
unchanged. See `src-csrf/README.md` for the surface and rationale.

---

## Out of scope

- `ProdModule` boot-time fail-closed when `CMS_ALLOWED_ORIGIN` is
  unset — see `docs/scope.md` Tier 2.
- `X-CSRF-Token` header support for future JavaScript-driven writes.
- Rendering 403 as a styled HTML error page through `HtmlModule`'s
  error pipeline. The interceptor throws `ForbiddenException`; the
  default error pipeline turns that into a 4xx response. A polished
  HTML 403 page belongs with the broader Page-layer error UX work.
- Extracting the interceptor into a `ray/csrf-module` package. Per
  the Codex review, package extraction waits until the in-app
  shape settles.
