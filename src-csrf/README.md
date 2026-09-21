# Ray\Csrf — upstream candidate

CSRF defence stack for BEAR.Sunday applications. Lives in this
repository under `src-csrf/` until it is published as the
`ray/csrf` Composer package.

## Surface

| Component | Role |
|---|---|
| `Ray\Csrf\Attribute\SameOrigin` | Marker for unsafe verbs requiring same-origin headers. |
| `Ray\Csrf\Attribute\CsrfToken` | Marker for unsafe verbs requiring a synchroniser token. |
| `Ray\Csrf\Http\RequestOriginInterface` / `ServerRequestOrigin` | Reads `Sec-Fetch-Site` / `Origin` / `Referer` from the request. |
| `Ray\Csrf\Http\RequestBodyTokenInterface` / `ServerRequestBodyToken` | Reads the submitted token from the form body. |
| `Ray\Csrf\Http\AllowedOrigin` | Configured canonical origin. `value === null` runs without the same-origin gate, for a host with no browser origin to compare against. The token gate is unaffected — the two are independent. |
| `Ray\Csrf\Http\CsrfTokenField` | Wire-protocol field name (default `_csrf_token`). |
| `Ray\Csrf\CsrfSessionKey` | Session slot holding the issued token (default `ray_csrf_token`). Configurable so an application sharing a session with an existing system can name the slot. |
| `Ray\Csrf\CsrfTokenInterface` / `SessionCsrfToken` | Server-side token issue / verify / clear. |
| `Ray\Csrf\Interceptor\SameOriginInterceptor` | Same-origin gate fired by `#[SameOrigin]`. |
| `Ray\Csrf\Interceptor\CsrfTokenInterceptor` | Synchroniser-token gate fired by `#[CsrfToken]`. |
| `Ray\Csrf\CsrfModule` | One-stop DI wiring. Private constructor; build it with `withSameOriginCheck()` or `withoutSameOriginCheck()` so that running without the origin gate is a deliberate, greppable call rather than an omitted argument. |
| `Ray\Csrf\Exception\ForbiddenException` | Thrown by both gates on policy failure; extends `BEAR\Resource\Exception\BadRequestException` so the 4xx pipeline serves the response. |

## Consumer integration

```php
// AppModule
$allowedOrigin = ((string) getenv('CMS_ALLOWED_ORIGIN')) ?: null;
$this->install($allowedOrigin === null
    ? \Ray\Csrf\CsrfModule::withoutSameOriginCheck()
    : \Ray\Csrf\CsrfModule::withSameOriginCheck($allowedOrigin));
```

In production the absent case is an error rather than a default: `ProdModule` throws
`MissingAllowedOriginException` at boot when `CMS_ALLOWED_ORIGIN` is unset.

Annotate the Page/Admin write methods that need protection:

```php
#[SameOrigin]
#[CsrfToken]
public function onPost(): static { /* … */ }
```

Render the hidden field from the template (the renderer exposes
`csrfToken` and `csrfTokenField` once `CsrfTokenInterface` and
`CsrfTokenField` are injected):

```php
<input type="hidden" name="{{h $csrfTokenField }}" value="{{h $csrfToken }}">
```

Clear the token on logout from the consumer's auth session:

```php
$this->csrf->clear();
```

See `docs/journal/csrf-design.md` for the algorithm, rationale, and
test layout.

## Upstream migration

When `ray/csrf` is published:

1. Delete `src-csrf/` and `tests-csrf/`.
2. Remove the `Ray\\Csrf\\` entries from `composer.json` `autoload`
   and `autoload-dev`.
3. Remove `src-csrf` / `tests-csrf` from `phpcs.xml`, `phpstan.neon`,
   `psalm.xml`, the `phpmd` composer script paths, and the PHPUnit
   `<source>` section.
4. Drop the `csrf` testsuite from `phpunit.xml.dist`.
5. Add `ray/csrf` to `composer.json` `require`.
6. Clear the DI cache: `rm -rf var/tmp/*-hal-app var/tmp/*-hal-api-app`.

The shared namespace is not by itself a drop-in guarantee: the two
copies evolved apart while both were maintained. As of `ray/csrf`
`9cf4ae6` the token-source port differs — this copy has
`RequestBodyTokenInterface` / `ServerRequestBodyToken` reading `$_POST`,
upstream has an invocation-aware `RequestTokenInterface` with header,
`uri->query` and `$_POST` adapters tried in that order. Code naming the
port has to move with it; code naming only the attributes, `CsrfModule`,
`CsrfTokenInterface` and the exceptions does not.

Check the upstream README for the current surface before migrating
rather than assuming this directory describes it.
