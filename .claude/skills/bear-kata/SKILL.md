---
name: bear-kata
description: BEAR.Sunday実装の「型(Kata)」をソース索引から引く。「BEAR.Sundayで〜を実装したい」時に、どの正規形(canonical)ソースを真似し、どのテストで振る舞いを確認し、実装後にどうマスターを検証するかを案内する。Use when user says "〜をkataに従って実装してください", "kataに従って実装", "kata に従って", "BEAR.Sundayで〜を実装したい", "Kata", "ソース索引", "source index", "どのソースを見れば", "pager/HAL embed/streaming/PRG/Cacheable/CSRF/session cookie/OAuth/ファイルアップロード/イベントソーシング/defer/conditional request/content negotiation/PATCH/OPTIONS/form validation/FakeSqlQuery を実装", or asks which BEAR.Sunday reference implementation to copy/follow for a feature.
user-invocable: true
---

# bear-kata — BEAR.Sunday 実装の型を引く

このスキルは BEAR.Kata の **ソース索引** [`index.md`](index.md) を入口に、「これを実装したい」という intent から、真似してよい正規形ソース・確認すべきテスト・マスター確認チェックリストへ最短で到達させる。

索引は武道の「型(Kata)」の集まり。各 Kata は **着手前チェック → ソース → テスト → 実装 → マスター確認** を備える。

## いつ使うか

- 「BEAR.Sundayで○○を実装したい」（一覧/ページング、HAL link/embed、POST/PUT/DELETE、ストリーム応答、キャッシュ、PRGフォーム、CLI、OAuth認証、CSRF保護、ファイルアップロード、イベントソーシング、defer実行、エラーハンドリング、状態遷移 等）。
- 「どのソースを真似すればいい？」「このパターンの正規形は？」。
- 既存実装が型に従っているかレビューしたい。

## 手順（6ステップ）

1. **INTENT を言語化する。** ユーザーがやりたいことを1文にする。例:「記事一覧をページングして返したい」。
2. **ROUTE — 索引で Kata を引く。** [`index.md`](index.md) を開き、`Aliases`（`pager`, `#[Embed]`, `streaming`, `PRG`, `CSRF`, `OAuth`, `#[Defer]`, `event sourcing`, `FakeSqlQuery` 等）でマッチする Kata を選ぶ。冒頭の一覧テーブルからも引ける。
3. **Status を確認する。**
   - `canonical` … 最初に真似する正規形。コピー可。
   - `showcase` … 特定機能の実例。
   - `comparison-only` … 比較理解用。**デフォルト実装にコピーしない。**
   - `support` … テスト/Fake/生成物。
   - `manual-only` … 型の記述のみ。**公式マニュアルを一次資料に、「近いKata」の型を流用して移植する。**
   - `external` … 型の記述のみ。**`Reference:` の外部リポジトリを参照実装として読み、自プロジェクトで再実装する（コードはコピーしない）。**
4. **着手前チェック（Before）を読む。** 書き始める前に守るべき型と前提（命名・分離・契約）を確認する。
5. **READ → OBSERVE。** `Source` を読んで `Key points` と `Do not` を把握し、`Tests` で期待される振る舞い（status / body / link / embed）を確認する。`manual-only` / `external` にはこのリポジトリ内の `Source` / `Tests` が無い。前者は公式マニュアル、後者は `Reference:` の外部リポジトリを、同じ位置の一次資料として読む。
6. **IMPLEMENT → MASTER。** ユーザーのプロジェクトに移植したら、**マスター確認（After）** のチェックリストを実装に対して走らせる。最終確証は「`Tests` に挙げたテストを自分の実装へ写経して green になること」。`Tests` を持たない `manual-only` / `external` では、一次資料から読み取った仕様に対して自分で書いたテストが green になることを確証とする。全項目 ✓ なら、その Kata をマスターしたと判断する。

## 索引の構造（各 Kata の項目）

```
- ID / Aliases / Status / Use when
- 着手前チェック（Before）  … 書く前に確認する型・前提
- Source                    … 真似してよいソース（このリポジトリの実パス）
- Tests                     … 正しい振る舞いの仕様
- Key points / Do not       … 要点とアンチパターン
- マスター確認（After）      … greppable assertion ＋ test green
```

## カバレッジ

この索引がカバーするBEAR.Sunday機能とカバーしない機能:

**カバー済み（実装あり 58 Kata）:**
- Resource: GET/POST/PUT/DELETE, not-found, JSON Schema, HAL link/embed, crawl/DataLoader, state transition, error mapping
- DB: BDR read/write, entity factory（`#[DbQuery(factory:)]`）, pager, natural key, link table, result projection
- Cache: `#[Cacheable]`, `#[DonutCache]`, `#[CacheableResponse]`, `#[Purge]`, embed dependency, `fromAssoc`, 条件付きリクエスト/304
- HTML: Page/Qiq detail/list, Markdown, PRG, 確認画面, session OAuth login, admin auth boundary
- Runtime: stream, async/parallel, CLI, CSRF/Same-Origin, session cookie hardening, import-app
- Event Sourcing: extraction, filter/replay, store, observation bridge
- Deferred: `#[Defer]`, conditional defer
- Tests: resource/page/hypermedia/fake/MySQL
- Semantic: ALPS, fake data, JSON Schema generation, API doc

**manual-only（型のみ記述 7 Kata — 公式マニュアルが一次資料）:**
- `api-patch-partial-update` — PATCH差分更新（近いKata: `api-put-tristate-input`）
- `api-options-method` — OPTIONS（`OptionsMethodModule`）
- `content-negotiation` — `BEAR.Accept` / `#[Produces]`
- `form-validation-webform` — Ray.WebFormModule（このリポジトリの正規形はJSON Schema + DTO + PRG。実働参照: apple-x-co/bear-app）
- `web-context-param-binding` — `#[CookieParam]` 等 / `#[ResourceParam]`
- `db-transactional` — `#[Transactional]`
- `aop-validation-valid` — `#[Valid]` / `#[OnValidate]`（正規形は `json-schema-validation`）

**external（外部参照実装 5 Kata — 型を読み取り再実装する）:**
- `rate-limit-interceptor` — `#[RateLimiter]` × interceptor（429、総当たり対策。参照: apple-x-co/bear-app）
- `resource-permission-authorization` — `#[RequiredPermission]` × AccessControl（RBAC/ACL。参照: apple-x-co/bear-app）
- `batch-command-resource` — cron/queue workerをCommand Resource化（参照: apple-x-co/bear-app）
- `signed-url-verification` — 有効期限付き署名URL（メール検証。参照: apple-x-co/bear-app）
- `tool-use-instrument` — `#[Tool]` でResourceをLLM tool定義に（参照: bearsunday/BEAR.ToolUse）

**カバーなし（意図的スコープ外）:**
- Production デプロイ / compile / preload — インフラ層
- High-Performance Servers (Swoole/RoadRunner/FrankenPHP ワーカー設定) — ランタイム層
- BEAR.Thrift 多言語連携 — 特殊用途
- Aura.Router カスタムルーティング — フレームワーク設定

### 該当Kataが無い時

1. **manual-only / external Kata を確認** — PATCH / OPTIONS / content negotiation / WebForm / web context binding / `#[Transactional]` / `#[Valid]` / レート制限 / RBAC / バッチworker / 署名URL / Tool Use は型の記述がある（一次資料は公式マニュアルまたは外部参照実装）
2. **conventions.md を参照** — 命名・Read/Write分離・SQL外部化・HAL rel層分離などの規約はKata横断で適用できる
3. **近いKataの型を当てはめる** — 例: AOP interceptor → `csrf-same-origin-protection` の実装形、ETag/304 → `conditional-request-304`
4. **scope.md で意図的除外か確認** — [`docs/scope.md`](docs/scope.md) に意図的スコープ外の一覧がある
5. **BEAR.Sunday公式マニュアルを参照** — https://bearsunday.github.io/manuals/1.0/en/

## 注意

- `comparison-only` の Kata（`db-array-row-comparison`, `db-sqlquery-orchestration`, `db-raw-pdo-comparison`）は**理解用**。正規形として移植しない。
- 索引のパスはこのリポジトリ（BEAR.Kata）内の実ファイル。別プロジェクトへ移植する際は、命名規約（`docs/conventions.md`）と型を保ったまま自分の Entity 名へ読み替える。
- **別プロジェクトでこのスキルを使う場合**、ローカルに `index.md` / `kata/` が無いことがある。その時は GitHub のコピーを参照する: `https://github.com/bearsunday/BEAR.Kata/blob/1.x/index.md`（索引）および `https://github.com/bearsunday/BEAR.Kata/tree/1.x/kata`（各Kata）。`Source` / `Tests` のパスも同じリポジトリ（BEAR.Kata）の該当ファイルとして読む。
- 索引に該当 Kata が無い時は、近い Status=`canonical` の Kata の型（Query/Command 分離、SQL外部化、ResourceObject body/status、HAL rel 層分離）を当てはめ、`docs/conventions.md` を参照する。
