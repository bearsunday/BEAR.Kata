# `session-cookie-hardening`

**session cookie の属性と回転を application が決める** · [← 索引に戻る](../index.md)

- **Category:** Runtime / representation
- **Status:** `canonical`
- **Aliases:** session cookie, SessionCookie, session_set_cookie_params, HttpOnly, SameSite, Secure, use_strict_mode, use_only_cookies, session fixation, session_regenerate_id, RequestScheme, TLS termination, reverse proxy, cookieSecure, lazy session start, session_start, cache_limiter, no-store, セッションクッキー, セッション固定化, セッション再生成, Secure属性, 匿名訪問者, セッション開始
- **Manual:** https://bearsunday.github.io/manuals/1.0/en/security.html
- **Use when:** PHP session を使う Page application で、cookie 属性（`HttpOnly` / `SameSite` / `Secure`）を application が決め、ログイン・ログアウトで session id を回したい。

## 例

### cookie 属性は最初の `session_start()` より前に一度だけ

`session_start()` を呼ぶ側は複数ある — session adapter（`NativeAuthSession`）も、
`bear/csrf` の `SessionCsrfStore` も、未開始なら素の `session_start()` を呼ぶ。
どちらが先に走るかは request ごとに変わる。だから属性は呼ぶ側で決めず、
Bootstrap がどの start よりも前に一度決める:

```php
// Bootstrap — Injector を作るより前、どの session_start() よりも前
(new SessionCookie())->configure($server, (string) getenv('CMS_ALLOWED_ORIGIN') ?: null);
```

```php
// SessionCookie
public function configure(array $server, string|null $publicOrigin = null): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        throw new SessionAlreadyActiveException();
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');   // id は cookie 以外から受け取らない
    ini_set('session.use_trans_sid', '0');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $this->scheme->isHttps($server, $publicOrigin),
    ]);
}
```

`use_only_cookies` / `use_trans_sid` は PHP 既定で安全側だが、host の ini に委ねない。URL から id を
受け取る設定が残ると、下の cookie 属性を素通りする経路ができ、「cookie が無ければ session も無い」
という `currentUser()` の前提も崩れる。

PHP は start 後の cookie 設定を警告だけで無視する。`session.auto_start` の host や早すぎる
`session_start()` があると、既定の cookie のまま全 request が成功してしまう。
黙って成立しない形を例外（fail-closed）にする。

`SameSite` は `Lax` であって `Strict` ではない。OAuth callback は cross-site の top-level
navigation で、login state を持つ cookie をそこで受け取る必要がある。

### `Secure` は public origin から決める

proxy が TLS を終端する構成では、PHP の見る request は plain HTTP で `$_SERVER['HTTPS']` が
立たない。request の scheme だけを見ると production の cookie から `Secure` が静かに落ちる。
TLS 終端の事実は設定（public origin）として持ち、request は補助にする:

```php
// RequestScheme — public origin が https なら request を見ずに true
public function isHttps(array $server, string|null $publicOrigin): bool
{
    if ($publicOrigin !== null && strtolower((string) parse_url($publicOrigin, PHP_URL_SCHEME)) === 'https') {
        return true;
    }

    $https = $server['HTTPS'] ?? '';

    return is_string($https) && ! in_array(strtolower($https), ['', 'off', '0'], true);
}
```

same-origin 門に渡す `CMS_ALLOWED_ORIGIN` が既にその事実を持っているので、cookie も同じ値から
決める。外部 SDK が自前の cookie を持つなら、そこにも同じ判定を渡す — Auth0 SDK の
`auth0_session*` / `auth0_transient*` は `Auth0Provider` が同じ `RequestScheme` で `cookieSecure` を決める。

### 認証状態が変わったら session id と token を回す

login 前に発行された session id と CSRF token を認証後まで持ち越さない。固定化された
session や漏れた token が、そのまま admin 権限を得る経路になる:

```php
// NativeAuthSession
public function login(AuthenticatedUser $user, int $authorId): void
{
    $this->start();
    $this->rotate();                 // 先に回す
    $_SESSION[self::USER] = [/* ... */];
}

public function logout(): void
{
    $this->start();
    unset($_SESSION[self::USER], $_SESSION[self::STATE]);   // 先に外す
    $this->rotate();
}

private function rotate(): void
{
    if (! session_regenerate_id(true)) {
        throw new SessionRotationException();
    }

    $this->csrf->clear();            // 次の issue() が新 session に束縛された token を発行する
}
```

順序が型。`login()` は回してから user を書き、回せなければ user を書かずに中断する —
旧 id のまま認証を載せるのは、回転が防ぐはずの fixation そのもの。`logout()` は先に user を
外してから回すので、回せなくても user は残らない。

### cookie を持たない訪問者には session を開始しない

renderer は全 Page で `currentUser()` を呼ぶ。そこで無条件に `session_start()` すると、
公開 Page を見るだけの訪問者全員に cookie と session file が配られる。PHP 既定の
`session.cache_limiter=nocache` はそのとき `Cache-Control: no-store` も送るので、公開 Page が
cache 不能になる — ただしこれは設定で変わり、`Set-Cookie` 自体は shared cache への保存を
防がない。読むものが無い request には開始せず `Visitor` を返す:

```php
public function currentUser(): UserInterface
{
    if (! $this->hasSession()) {
        return new Visitor();
    }

    $this->start();
    // ...
}

private function hasSession(): bool
{
    return session_status() === PHP_SESSION_ACTIVE || isset($_COOKIE[session_name()]);
}
```

この adapter が session を開始するのは、cookie を持参した request と、state 発行・login・logout
の各操作。`bear/csrf` の `SessionCsrfStore` も `csrfField()` の描画と token 検証で開始するので、
公開 Page に form を置けば匿名訪問者にも session が始まる — 遅延開始は `currentUser()` の性質で
あって、application 全体の保証ではない。
shared HTTP cache を置くなら、それとは別に session cookie 付き request の cache 読み書きを bypass し、
個人向け応答には `private` か `no-store` を付ける。`#[CacheableResponse]` の application cache は
HTTP header を見ずに URI で描画済み view を返すので、user 依存の描画を持つ Page には付けない
（[`csrf-same-origin-protection`](./csrf-same-origin-protection.md) の Do not）。cookie 無しで
session を開始しないのはその前提を整える側で、どちらの cache 制御の代わりでもない。

## Naming

| 役割 | 命名 | 例 |
|---|---|---|
| cookie 方針 | `SessionCookie::configure()` | `Bootstrap` が最初の start より前に呼ぶ |
| scheme 判定 | `RequestScheme::isHttps()` | session cookie と外部 SDK の cookie が共有 |
| 回転 | session adapter の private `rotate()` | `login()` / `logout()` の両方から |
| fail-closed 例外 | `Session<Failure>Exception` | `SessionAlreadyActiveException`, `SessionRotationException` |

> 命名規則の全容は [conventions.md](../docs/conventions.md#3-naming) を参照。

## 着手前チェック（Before）

- [ ] `session_start()` を呼ぶ側（session adapter、CSRF store、library）は cookie 属性を決めず、Bootstrap が最初の start より前に一度だけ決めると理解したか。
- [ ] `Secure` を `$_SERVER['HTTPS']` だけで決めない — TLS を終端する proxy の背後では public origin の scheme を正にすると決めたか。
- [ ] `SameSite` は OAuth callback（cross-site top-level navigation）を通す `Lax` にすると決めたか。
- [ ] `login()` は回してから user を書き、回せなければ中断。`logout()` は user を外してから回す — この順序を理解したか。
- [ ] cookie を持たない request には session を開始しない（`Visitor` を返す）と決めたか。

## Source

- [`src/Bootstrap.php`](../src/Bootstrap.php) — 呼び出し位置（Injector より前）
- [`src/Auth/SessionCookie.php`](../src/Auth/SessionCookie.php) — 属性と fail-closed
- [`src/Auth/RequestScheme.php`](../src/Auth/RequestScheme.php) — `Secure` の判定
- [`src/Auth/NativeAuthSession.php`](../src/Auth/NativeAuthSession.php) — `rotate()` と `hasSession()`
- [`src/Provider/Auth0Provider.php`](../src/Provider/Auth0Provider.php) — 外部 SDK の cookie に同じ判定を渡す
- [`src/Exception/SessionAlreadyActiveException.php`](../src/Exception/SessionAlreadyActiveException.php) / [`src/Exception/SessionRotationException.php`](../src/Exception/SessionRotationException.php)

## Tests

- [`tests/Service/SessionCookieTest.php`](../tests/Service/SessionCookieTest.php) — 属性が start 前に決まる。start 後なら拒否する
- [`tests/Service/RequestSchemeTest.php`](../tests/Service/RequestSchemeTest.php) — public origin と `$_SERVER['HTTPS']` の組み合わせ表
- [`tests/Service/NativeAuthSessionTest.php`](../tests/Service/NativeAuthSessionTest.php) — login / logout で id が変わり `clear()` が呼ばれる。回せなければ login が中断する。cookie の無い request では開始しない
- [`tests/Provider/Auth0ProviderTest.php`](../tests/Provider/Auth0ProviderTest.php) — SDK の cookie にも同じ判定が渡る

## Key points

cookie 属性は `session_start()` を呼ぶ側ではなく `Bootstrap` が最初の start より前に一度決め、
active な session への設定は例外にする。`Secure` は request ではなく public origin を正とし、
外部 SDK の cookie にも同じ `RequestScheme` を渡す。`login()` は `session_regenerate_id(true)` と
`CsrfTokenInterface::clear()` を済ませてから user を書き、`logout()` は user を外してから回す。
cookie を持たない request には session を開始しない。

## Do not

- session cookie を PHP 既定のまま使わない — 既定は `SameSite` 無指定・`HttpOnly` off。
- `session_start()` の後に cookie 属性を設定しない — 警告だけで無視され、全 request が成功し続ける。だから `SessionAlreadyActiveException`。
- request header で `Secure` を決めない — `X-Forwarded-Proto` は誰でも付けられる。TLS 終端の事実は設定（public origin）として持つ。
- 回転に失敗したまま user を書かない — 旧 id に認証を載せるのは fixation そのもの。`login()` は中断する。
- cookie を持たない訪問者の公開 Page 描画で session を開始しない — 不要な cookie と session file が増え、PHP 既定では応答が `no-store` になる。
- `Set-Cookie` や PHP 既定の `no-store` を cache 制御の代わりにしない — shared cache を使うなら session cookie 付き request の bypass と、個人向け応答の `private` / `no-store` を明示する。

## マスター確認（After）

- [ ] `session_get_cookie_params()` が `HttpOnly` / `SameSite=Lax`、public origin が https なら `Secure` — `SessionCookieTest` 相当で green。
- [ ] active な session に対する `configure()` が例外になる — 同テストで green。
- [ ] login / logout で session id が変わり CSRF token が `clear()` される。回せなければ login が中断する — `NativeAuthSessionTest` 相当で green。
- [ ] cookie の無い request で `currentUser()` が session を開始しない — 同テストで green。
- [ ] 外部 SDK の cookie にも同じ `Secure` 判定が渡る — `Auth0ProviderTest` 相当で green。

## See also

- [`csrf-same-origin-protection`](./csrf-same-origin-protection.md) — この session に住む CSRF token。回転時に `clear()` される側
- [`admin-session-login`](./admin-session-login.md) — `login()` / `logout()` を呼ぶ login → callback → logout の flow
- [`admin-auth-boundary`](./admin-auth-boundary.md) — `currentUser()` が返す `Visitor` / `AdminUser` を型で検査する側
- [`auth-oauth-flow`](./auth-oauth-flow.md) — callback が cross-site navigation である理由（`SameSite=Lax` の根拠）
- [`page-resource-test`](./page-resource-test.md) — test では `FakeAuthSession` に差し替え、PHP session に触れない
