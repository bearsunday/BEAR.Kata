# `admin-session-login`

**セッションOAuthログインフロー（login → callback → logout）** · [← 索引に戻る](../index.md)

- **Category:** HTML / Page
- **Status:** `showcase`
- **Aliases:** session login, OAuth callback, login flow, state parameter, identity mapping, AuthSessionInterface, AuthorIdentityResolver, ログイン, セッション認証, コールバック
- **Manual:** https://bearsunday.github.io/manuals/1.0/en/security.html
- **Use when:** OAuth providerでログインし、session-backedな管理画面ユーザーを確立したい。

## 例

### Login

state発行 → 302 で provider の authorization URL へ:

```php
public function onGet(): static
{
    $state = $this->session->issueState();
    $this->code = 302;
    $this->headers['Location'] = $this->auth->getAuthorizationUrl($state);
    $this->body = [];

    return $this;
}
```

### Callback

state検証失敗/認証失敗は401（provider内部を漏らさない）、author未解決は403、成功で `login()` → 303:

```php
public function onGet(string $code = '', string $state = ''): static
{
    if ($code === '' || ! $this->session->consumeState($state)) {
        $this->code = Code::UNAUTHORIZED;
        $this->body = ['message' => 'Authentication failed'];

        return $this;
    }

    try {
        $user = $this->auth->authenticate($code, $state);
    } catch (Throwable) {
        $this->code = Code::UNAUTHORIZED;
        $this->body = ['message' => 'Authentication failed'];

        return $this;
    }

    $authorId = $this->identity->resolveAuthorId($user);
    if ($authorId === null) {
        $this->code = Code::FORBIDDEN;
        $this->body = ['message' => 'Forbidden'];

        return $this;
    }

    $this->session->login($user, $authorId);
    $this->code = 303;
    $this->headers['Location'] = '/admin/index';
    $this->body = [];

    return $this;
}
```

### Logout

GETは405、POSTは `#[SameOrigin]` + `#[CsrfToken]` で守って303:

```php
public function onGet(): static
{
    $this->code = 405;
    $this->body = ['message' => 'Method not allowed'];

    return $this;
}

#[SameOrigin]
#[CsrfToken]
public function onPost(): static
{
    $this->session->logout();
    $this->code = 303;
    $this->headers['Location'] = '/';
    $this->body = [];

    return $this;
}
```

### State（one-time消費）

`issueState()` が発行し、`consumeState()` は取り出しと同時に破棄して `hash_equals` で比較する:

```php
public function issueState(): string
{
    $this->start();
    $state = bin2hex(random_bytes(16));
    $_SESSION[self::STATE] = $state;

    return $state;
}

public function consumeState(string $state): bool
{
    $this->start();
    $expected = $_SESSION[self::STATE] ?? null;
    unset($_SESSION[self::STATE]);

    return is_string($expected) && hash_equals($expected, $state);
}
```

### login / logout（session の確立と破棄）

認証状態が変わる 2 箇所は順序が型 — `login()` は session id を回してから user を書き、`logout()` は user を外してから回す。回転と CSRF token の `clear()` は [`session-cookie-hardening`](./session-cookie-hardening.md):

```php
public function login(AuthenticatedUser $user, int $authorId): void
{
    $this->start();
    $this->rotate();
    $_SESSION[self::USER] = [
        'id' => $user->id,
        'email' => $user->email,
        'name' => $user->name,
        'authorId' => $authorId,
    ];
}

public function logout(): void
{
    $this->start();
    unset($_SESSION[self::USER], $_SESSION[self::STATE]);
    $this->rotate();
}
```

### Identity mapping

恒久キーは `(provider, subject)`。未マッピング時のみemailでauthorを引き、mappingを1回だけ `add` する。unique制約違反（並行ログイン）は既存mappingを読み直して吸収する:

```php
public function resolveAuthorId(AuthenticatedUser $user): int|null
{
    $identity = $this->identity->byProviderSubject($user->provider, $user->subject);
    if ($identity !== null) {
        return $identity->authorId;
    }

    $author = $this->author->byEmail($user->email);
    if ($author === null) {
        return null;
    }

    try {
        $this->identityCmd->add($user->provider, $user->subject, $author->id, $user->email, $user->name);
    } catch (PdoPerformException $e) {
        if (! $this->isUniqueIdentityViolation($e)) {
            throw $e;
        }

        $identity = $this->identity->byProviderSubject($user->provider, $user->subject);
        if ($identity !== null) {
            return $identity->authorId;
        }

        throw $e;
    }

    return $author->id;
}
```

## Naming

login / callback / logout は別Page Resourceに分け、URLと1対1にする（`page://self/admin/login` 等）。identityのRead/Writeはinterfaceを分ける:

| 役割 | 名前 | 例 |
|---|---|---|
| Session境界 | `AuthSessionInterface`（実装 `NativeAuthSession`） | `issueState()` / `consumeState()` / `login()` / `logout()` |
| Identity read | `by<NaturalKey>` | `AuthIdentityQueryInterface::byProviderSubject()`, `AuthorQueryInterface::byEmail()` |
| Identity write | 動詞 `add` | `AuthIdentityCommandInterface::add()` ↔ `auth_identity_add.sql` |

> 命名規則の全容は [conventions.md](../docs/conventions.md#3-naming) を参照。

## 着手前チェック（Before）

- [ ] login（state発行→302）/ callback（state検証→token交換→session確立）/ logout（user を外して session id を回す）を別Page Resourceに分けると決めたか。
- [ ] CSRF/replay対策として `issueState()` → `consumeState()`（one-time消費 + `hash_equals` 比較）のstate検証を入れると決めたか。
- [ ] 外部identityを `(provider, subject) → authorId` でマッピングし、emailは初回ログインのfallbackのみと理解したか。
- [ ] logoutはGETでなくPOST（`#[SameOrigin]` + `#[CsrfToken]`）にすると決めたか。
- [ ] `login()` は session id を回してから user を書き、`logout()` は user を外してから回す（回転の中身は [`session-cookie-hardening`](./session-cookie-hardening.md)）と理解したか。

## Source

- [`src/Resource/Page/Admin/Login.php`](../src/Resource/Page/Admin/Login.php)
- [`src/Resource/Page/Admin/Callback.php`](../src/Resource/Page/Admin/Callback.php)
- [`src/Resource/Page/Admin/Logout.php`](../src/Resource/Page/Admin/Logout.php)
- [`src/Auth/NativeAuthSession.php`](../src/Auth/NativeAuthSession.php)
- [`src/Auth/AuthorIdentityResolver.php`](../src/Auth/AuthorIdentityResolver.php)

## Tests

- [`tests/Resource/Page/Admin/LoginTest.php`](../tests/Resource/Page/Admin/LoginTest.php)
- [`tests/Service/AuthorIdentityResolverTest.php`](../tests/Service/AuthorIdentityResolverTest.php)
- [`tests/Service/NativeAuthSessionTest.php`](../tests/Service/NativeAuthSessionTest.php) — login / logout での session id 再生成と token 破棄

## Key points

Loginは `issueState()` → 302（authorization URL）。Callbackはstate検証失敗/認証失敗で401（provider内部を漏らさない）、author未解決は403、成功で `session->login()` → 303 `/admin/index`。identity mappingは初回のみ `auth_identity_add` され、同一subjectの再ログインで重複作成しない。LogoutはGET=405、POST=303。`login()` / `logout()` はどちらも `session_regenerate_id(true)` と CSRF token の `clear()` を行い、認証前の session id と token を持ち越さない。

## Do not

- emailを恒久的なアカウントキーにしない — 恒久キーは `(provider, subject)`。emailはprovider側で変更・再割当されうるため、初回ログインでauthorを引き当てるfallbackにだけ使う。

## マスター確認（After）

- [ ] login→callbackの正常系で303が返り、admin pageが200になる。
- [ ] 同一subjectの再ログインでidentity mappingが重複作成されないことを `LoginTest.php` 相当で green。

## See also

- [`admin-auth-boundary`](./admin-auth-boundary.md) — 確立したsessionを型付きユーザー境界で検査する側
- [`auth-oauth-flow`](./auth-oauth-flow.md) — OAuth認証フローをResourceで示す（App側）
- [`csrf-same-origin-protection`](./csrf-same-origin-protection.md) — logout POSTを守る `#[SameOrigin]` + `#[CsrfToken]` のbind
- [`session-cookie-hardening`](./session-cookie-hardening.md) — この flow が確立する session の cookie 属性と、`login()` / `logout()` の回転
- [`admin-prg-form`](./admin-prg-form.md) — POST → 303 リダイレクトのPRG（admin form側）
- [`db-read-by-natural-key`](./db-read-by-natural-key.md) — `byProviderSubject` / `byEmail` の自然キー読み
- [`page-resource-test`](./page-resource-test.md) — Page Resourceのテストの型（`LoginTest` の土台）
