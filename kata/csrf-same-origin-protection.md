# `csrf-same-origin-protection`

**`bear/csrf` をAdmin境界に組み込む** · [← 索引に戻る](../index.md)

- **Category:** Runtime / representation
- **Status:** `canonical`
- **Aliases:** CSRF, CsrfToken, SameOrigin, interceptor, AOP, form protection, synchronizer token, シンクロナイザートークン, CSRF対策, BEAR.Csrf, _csrf_token, Sec-Fetch-Site
- **Manual:** https://bearsunday.github.io/manuals/1.0/en/security.html
- **Library:** [`bear/csrf`](https://github.com/bearsunday/BEAR.Csrf) — 仕組みはこちら。この kata は組み込み側だけを扱う。
- **Use when:** Admin Page Resourceのwrite操作をCSRF攻撃とCross-Site Origin攻撃から保護したい。

## 例

CSRF の仕組み — synchronizer token、`hash_equals`、`Sec-Fetch-Site` / `Origin` / `Referer`
の優先順位 — は [`bear/csrf`](https://github.com/bearsunday/BEAR.Csrf) が持つ。この kata が扱うのは
**それを BEAR application の境界にどう据えるか**だけで、実装は再掲しない。

### どちらの門を arm するか

二つの門は独立している。token 門は常時 on。same-origin 門は比較する相手 (browser origin)
を持つ host でしか意味がないので、named constructor で明示的に選ぶ:

```php
// AppModule
$allowedOrigin = (string) getenv('CMS_ALLOWED_ORIGIN') ?: null;
$this->install($allowedOrigin === null
    ? CsrfModule::withoutSameOriginCheck()
    : CsrfModule::withSameOriginCheck($allowedOrigin));
```

`withoutSameOriginCheck()` は「書かなければ起きない」選択である点が要。ただしこれは
**module API の話**で、composition root が env から導出している以上、値の欠落はここでは
防げない。prod で落とすのは `ProdModule` の仕事:

```php
// ProdModule — 値が無ければ boot に失敗する
if ($allowedOrigin === null) {
    throw new MissingAllowedOriginException('CMS_ALLOWED_ORIGIN');
}
```

### どこに attribute を付けるか

**Page/Admin に付け、App resource には付けない。** App resource は CLI・seed・他 resource
からの内部requestで到達する。そこに browser 前提の門を置くと、攻撃を防ぐのではなく
正当な呼び出しを塞ぐ。browser が触る境界は Page であって App ではない:

```php
#[SameOrigin]
#[CsrfToken]
public function onPost(string $title, string $body): static
```

method 引数に token を取らないことに注意。CSRF は transport の関心であって resource の
意味論ではない。interceptor が header → `uri->query` → `$_POST` の順に拾う。

### token を view にどう渡すか

Resource は hidden field の存在を知らない。renderer が `CsrfTokenInterface` から発行して
template 変数に載せる:

```php
// CmsQiqRenderer — 全 template に渡る共通変数
return [
    'user' => $this->session->currentUser(),
    'csrfToken' => $this->csrf->issue(),
    'csrfTokenField' => $this->csrfTokenField->name,
];
```

field 名を template に直書きせず `CsrfTokenField` から渡すのは、wire 名を変えたときに
form と interceptor が一緒に動くようにするため。

### session cookie は application が決める

`SessionCsrfStore` も `NativeAuthSession` も、未開始なら素の `session_start()` を呼ぶ。
どちらが先に走るかは request ごとに変わるので、cookie 属性は呼ぶ側で決めず、
最初の `session_start()` より前に一度だけ決める:

```php
// Bootstrap — どの session_start() よりも前
(new SessionCookie())->configure($server, (string) getenv('CMS_ALLOWED_ORIGIN') ?: null);
```

session が既に active なら `configure()` は `SessionAlreadyActiveException` を投げる。PHP は
start 後の cookie 設定を警告だけで無視するので、`session.auto_start` の host や早すぎる
`session_start()` があると既定の cookie のまま全 request が成功してしまう。黙って成立しない形を
例外にする。

`SameSite` は `Lax` であって `Strict` ではない。OAuth callback は cross-site の top-level
navigation で、login state を持つ cookie をそこで受け取る必要がある。

`Secure` は request が HTTPS か、`CMS_ALLOWED_ORIGIN` の scheme が `https` なら付く。proxy が TLS を終端する構成では
PHP の見る request は plain HTTP で `$_SERVER['HTTPS']` が立たないため、request の scheme だけを
見ると production の cookie から `Secure` が静かに落ちる。same-origin 門に渡す public origin は
その事実を既に持っているので、cookie も同じ値から決める。Auth0 SDK 自身の cookie
(`auth0_session*` / `auth0_transient*`) も、`Auth0Provider` が同じ `RequestScheme` で `cookieSecure` を決める。

### 認証状態が変わったら session id と token を回す

login 前に発行された session id と token を認証後まで持ち越さない。固定化された
session や漏れた token が、そのまま admin 権限を得る経路になる:

```php
// NativeAuthSession — login() と logout() の両方
if (! session_regenerate_id(true)) {
    throw new SessionRotationException();   // 旧 id のまま user を書かない
}

$this->csrf->clear();   // 次の issue() が新 session に束縛された token を発行する
```

`login()` は回してから user を書く。回せなかったら user を書かずに中断する — 旧 id のまま
認証を載せるのは、回転が防ぐはずの fixation そのもの。`logout()` は先に user を外してから
回すので、回せなくても user は残らない。

### 付け忘れを test で塞ぐ

ライブラリは「attribute が付いた method」しか守れない。**付け忘れは consumer 側の穴**で、
library には検出しようがない。`src/Resource/Page` 以下を再帰的に歩き、unsafe verb を
reflection で列挙して突き合わせる。サブディレクトリに置いた resource も黙って外れない:

```php
// PageCsrfAttributeCoverageTest
foreach ($this->unsafeMethods() as [$class, $method]) {
    $attributes = array_map(
        static fn (ReflectionAttribute $a): string => $a->getName(),
        (new ReflectionMethod($class, $method))->getAttributes(),
    );
    $this->assertContains(CsrfToken::class, $attributes);
    $this->assertContains(SameOrigin::class, $attributes);
}
```

この kata で最も価値があるのはここ。正しいライブラリでも consumer のために保証できない
ことを検査している。

### host の前提

同梱の `SessionCsrfStore` は `$_SESSION` に依存する — process が request を所有する host
(PHP-FPM、built-in server、CLI) が前提。Swoole のような coroutine host では worker 単位で
token が共有され防御が成立しないため、`CoroutineUnsafeStoreException` で拒否される
([BEAR.Csrf#7](https://github.com/bearsunday/BEAR.Csrf/issues/7))。並行 host に載せるなら
`CsrfTokenInterface` を request scope の store に束縛し直す。

same-origin 門は `Sec-Fetch-Site` / `Origin` / `Referer` のどれも無い request を 403 にする
(fail-closed)。現行 browser は POST に必ず `Origin` を付けるので、落ちるのは browser 以外の
client と、ヘッダを剥がす proxy の背後だけ。その経路を正当に通すなら
`withoutSameOriginCheck()` で門ごと外すのであって、interceptor に例外を足すのではない。

## Naming

attribute → interceptor → module の対応が名前で追える:

| 役割 | 命名 | 例 |
|---|---|---|
| Marker attribute | `<Concern>`（`TARGET_METHOD`） | `CsrfToken`, `SameOrigin` |
| Interceptor | `<Attribute名>Interceptor` | `CsrfTokenInterceptor`, `SameOriginInterceptor` |
| 束ねるModule | `<Concern>Module` | `CsrfModule` |
| hidden field名 | `_csrf_token`（`CsrfModule` 第2引数の既定値） | template変数 `$csrfTokenField` |

> 命名規則の全容は [conventions.md](../docs/conventions.md#3-naming) を参照。

## 着手前チェック（Before）

- [ ] CSRF の実装ではなく、`bear/csrf` の**組み込み**が主題だと理解したか。token 生成・`hash_equals`・origin 解析は library 側にある。
- [ ] 門を掛けるのは browser が触る Page/Admin であって、CLI や内部requestから到達する App resource ではないと理解したか。
- [ ] token 門と same-origin 門は独立で、後者は `withSameOriginCheck()` / `withoutSameOriginCheck()` のどちらを呼ぶかで決まると理解したか。prod での欠落は `ProdModule` が boot時に落とす。
- [ ] attribute の付け忘れは library が検出できない consumer 側の穴で、application が test で塞ぐと理解したか。
- [ ] session cookie の属性 (`SameSite` / `HttpOnly` / `Secure`) は最初の `session_start()` より前に application が決め、login / logout で session id と token を回すと理解したか。
- [ ] 同梱 store が前提とする host (request が process を所有する) を確認したか。

## Source

application が所有する部分のみ。library は [`bear/csrf`](https://github.com/bearsunday/BEAR.Csrf):

- [`src/Module/AppModule.php`](../src/Module/AppModule.php) — どちらの門を arm するか
- [`src/Module/ProdModule.php`](../src/Module/ProdModule.php) — prod の fail-closed
- [`src/Bootstrap.php`](../src/Bootstrap.php) / [`src/Auth/SessionCookie.php`](../src/Auth/SessionCookie.php) — 最初の `session_start()` より前の cookie 属性
- [`src/Auth/RequestScheme.php`](../src/Auth/RequestScheme.php) — `Secure` の判定。session cookie と Auth0 SDK の cookie が共有する
- [`src/Exception/MissingAllowedOriginException.php`](../src/Exception/MissingAllowedOriginException.php)
- [`src/Renderer/CmsQiqRenderer.php`](../src/Renderer/CmsQiqRenderer.php) — token と field 名の view 供給
- [`src/Resource/Page/Admin/Article.php`](../src/Resource/Page/Admin/Article.php) — attribute の置き場
- [`src/Auth/NativeAuthSession.php`](../src/Auth/NativeAuthSession.php) — login/logout での session id 再生成と token 破棄
- [`src/Exception/SessionRotationException.php`](../src/Exception/SessionRotationException.php) / [`src/Exception/SessionAlreadyActiveException.php`](../src/Exception/SessionAlreadyActiveException.php) — 回転と cookie 設定の fail-closed

## Tests

- [`tests/Interceptor/CsrfTokenWiringTest.php`](../tests/Interceptor/CsrfTokenWiringTest.php) — 宣言が injector 経由で実際に interception になるか
- [`tests/Interceptor/SameOriginWiringTest.php`](../tests/Interceptor/SameOriginWiringTest.php)
- [`tests/Interceptor/PageCsrfAttributeCoverageTest.php`](../tests/Interceptor/PageCsrfAttributeCoverageTest.php) — 付け忘れ検出
- [`tests/Interceptor/ProdCsrfBootTest.php`](../tests/Interceptor/ProdCsrfBootTest.php) — origin 未設定で prod が boot に失敗する
- [`tests/Service/NativeAuthSessionTest.php`](../tests/Service/NativeAuthSessionTest.php) — login / logout で session id が変わり `clear()` が呼ばれる。回せなければ login が中断する
- [`tests/Service/SessionCookieTest.php`](../tests/Service/SessionCookieTest.php) — cookie 属性が session 開始前に決まる。開始後なら拒否する
- [`tests/Service/RequestSchemeTest.php`](../tests/Service/RequestSchemeTest.php) — public origin と `$_SERVER['HTTPS']` からの `Secure` 判定
- [`tests/Provider/Auth0ProviderTest.php`](../tests/Provider/Auth0ProviderTest.php) — Auth0 SDK の cookie にも同じ判定が渡る

interceptor 単体の挙動 (signal 優先順位、malformed origin、token 比較) は library 側の
test が持つ。ここで再実装すると、同じ契約の記述が二つになって必ずずれる。

## Key points

門の位置と網羅が application の責任、仕組みは library の責任。`#[CsrfToken]` / `#[SameOrigin]`
は browser が触る Page/Admin の unsafe verb にのみ付け、method 引数には token を取らない。
hidden field は renderer が `$csrfToken` / `$csrfTokenField` として全 template に供給し、
login / logout では `session_regenerate_id(true)` と `CsrfTokenInterface::clear()`。cookie 属性は
`Bootstrap` が最初の `session_start()` より前に決める。`PageCsrfAttributeCoverageTest` が付け忘れを
reflection で検出する — library には見えない穴で、ここが application 側で最も効く一手。

## Do not

- CSRF の仕組みを自前で書かない — token 生成も `hash_equals` も origin 解析も `bear/csrf` にある。security code の複製は、複製された分だけ review を受けない実装が増えるということ。
- App resource に門を付けない — CLI・seed・内部requestから到達するので、攻撃ではなく正当な呼び出しを塞ぐ。browser 境界は Page。
- 設定値の欠落で防御が静かに外れる形にしない — 「検証しない」は `withoutSameOriginCheck()` という**書かなければ起きない**選択として表す。ただし module API がそう書けても composition root が env から導出するなら欠落は防げない。落とすのは `ProdModule` の役割。
- attribute の付け忘れを library に期待しない — 付いていない method は interceptor の視界に入らない。網羅は application の test で保証する。
- session cookie を PHP 既定のまま使わない — 既定は `SameSite` 無指定・`HttpOnly` off。`session_start()` を呼ぶ側 (`NativeAuthSession`、`SessionCsrfStore`) は属性を決めず、`Bootstrap` が最初の start より前に決める。
- 公開 Page を cache するなら token も user も eager に埋めない — renderer は全 template に `issue()` と `currentUser()` の結果を渡す。`#[CacheableResponse]` な公開 Page と同居すると、最初の訪問者の token が cache された HTML で配られる。この kata の form は admin のみで cache しない。公開 form と cache を同居させる consumer は template 側の遅延発行にする。
- **coroutine hostにそのまま持ち込まない** — 同梱の `SessionCsrfStore` は `$_SESSION` 依存で、Swooleのようにworker内で複数requestが並行する環境ではtokenがworker単位で共有され防御が成立しない([BEAR.Csrf#7](https://github.com/bearsunday/BEAR.Csrf/issues/7)、未解決)。並行hostでは `CsrfTokenInterface` をrequest scopeのstoreに束縛し直す。

## マスター確認（After）

- [ ] 全 Page write method に attribute が付いていることを coverage test で green。属性を1つ外すと落ちることを確認した。
- [ ] login / logout で session id が変わり `clear()` が呼ばれることを `NativeAuthSessionTest` で green。cookie は `HttpOnly` / `SameSite=Lax`、public origin が https なら `Secure`。
- [ ] wiring test が injector 経由で interception の成立を示している。
- [ ] prod context で `CMS_ALLOWED_ORIGIN` 未設定なら boot に失敗する。
- [ ] renderer が全 template に token と field 名を供給し、logout で `clear()` される。

green が意味するのは「この構成で組み込み側の義務が満たされている」ことであって、
CSRF 防御そのものの証明ではない。後者は `bear/csrf` の test が持つ。

## See also

- [`admin-prg-form`](./admin-prg-form.md) — この2 attributeが守るAdmin form POST（PRG）
- [`admin-confirm-page`](./admin-confirm-page.md) — 確認画面のPOSTも同じ2 attributeで保護する
- [`admin-auth-boundary`](./admin-auth-boundary.md) — 同じAdmin Pageを守るもう一つの境界（認証401 / 認可403）
- [`admin-session-login`](./admin-session-login.md) — tokenを保持するsessionの確立とlogout
- [`aop-validation-valid`](./aop-validation-valid.md) — attribute + interceptorでmethodにgateを掛ける同型（validation）
- [`rate-limit-interceptor`](./rate-limit-interceptor.md) — attribute + interceptorでmethodにgateを掛ける同型（rate limit）
