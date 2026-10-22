# BEAR.Kata ソース索引

このページは、AIエージェントと人間が「これを実装したい時、このリファレンスのどこを見るか」を素早く引くためのソース索引です。チュートリアルではありません。説明は最小限にし、実装意図（intent）からソース、テスト、真似してよい要点へ直接到達することを目的にします。

BEAR.Kata の各エントリは「Kata（型）」です。武道の型と同じく、**着手前に型を確認し、実装後に型に従えたかを検証する**ことで、「このKataをマスターした」と自信を持って言える状態を目指します。

## エージェントの引き方（6ステップ）

```
1. INTENT    「BEAR.Sundayでページング一覧を実装したい」
               ↓ skill `bear-kata` が intent でトリガー（能動的入口）
2. ROUTE     skill → この索引 → kata `db-read-list-pager`
               ↓ Status=canonical なら真似してよい正規形
3. READ      Source（Resource + Query + SQL + Entity）+ Key points + Do not
               ↓ 【着手前チェック】型と前提を確認してから書く
4. OBSERVE   Tests = 正しい振る舞いの仕様
               ↓
5. IMPLEMENT 自分のプロジェクトに移植
               ↓
6. MASTER    【マスター確認】greppable assertion ＋ listされたTestを写経してgreen
               → 全項目 ✓ なら「このKataをマスターした」
```

## 前提

- namespace は `BEAR\Kata\`。`Source` と `Tests` はこのリポジトリ root からの相対パスです。
- 各Kataの `Manual:` は対応するBEAR.Sunday公式マニュアル章へのリンクです。型の背景にあるフレームワーク仕様はそちらが一次資料です。
- サンプルの位置付けは次の5種類です。

| Status | 意味 |
|---|---|
| `canonical` | 通常の実装で最初に真似する正規形 |
| `showcase` | 特定機能を切り出して見せる実例 |
| `comparison-only` | 比較理解用。デフォルト実装としてコピーしない |
| `support` | テスト、Fake、生成物など正規形を支える周辺実装 |
| `manual-only` | 公式マニュアルを一次資料とする型の記述のみ。このリポジトリに正規実装・テストはまだ無い |
| `external` | 外部公開リポジトリ（実働アプリ・未導入パッケージ）の参照実装を指す型の記述。このリポジトリに正規実装は無い |

## 使い方

1. `Aliases` にある語で検索します。例: `streaming`, `DbQuery`, `PRG`, `FakeSqlQuery`。
2. `Status` で、そのコードをコピーしてよい正規形か、比較用かを確認します。
3. **着手前チェック** で、書き始める前に守るべき型と前提を確認します。
4. `Source` を読みます。
5. `Tests` を読み、期待される振る舞いを確認します。
6. 実装後に **マスター確認** のチェックリストを自分のコードに対して走らせ、全項目が満たされたらそのKataをマスターしたと判断します。マスター確認は「`Tests` に挙げたテストを自分の実装へ写経して green になること」を最終確証とします。

`manual-only` と `external` のKataは手順が異なります。Sourceの代わりに一次資料（公式マニュアル章、または `Reference:` の外部リポジトリ）を読み、**近いKata** に挙げた実装済みKataの型（命名・分離・テスト形）を流用して移植します。マスター確認は自プロジェクトに書いたテストのgreenが最終確証です。

## 索引（一覧）

「やりたいこと」から Kata を引きます。`Status` の意味は [前提](#前提) を参照。

### DBを読み書きする

| やりたいこと | Kata | Status |
|---|---|---|
| 主キーで1件のEntityを読む | [`db-read-one-entity`](kata/db-read-one-entity.md) | canonical |
| DB行をEntityへ変換する（enum・日付正規化） | [`db-entity-factory`](kata/db-entity-factory.md) | canonical |
| natural keyで1件読む（INSERT後の新規ID回収） | [`db-read-by-natural-key`](kata/db-read-by-natural-key.md) | canonical |
| 一覧をページング・絞り込みして読む | [`db-read-list-pager`](kata/db-read-list-pager.md) | canonical |
| 作成・更新・削除をCommandに分ける | [`db-command-write`](kata/db-command-write.md) | canonical |
| 多対多のlink tableを同期する | [`db-link-table-sync`](kata/db-link-table-sync.md) | canonical |
| SELECT結果を型付きコレクションで返す | [`db-result-projection`](kata/db-result-projection.md) | showcase |
| 複数書き込みをトランザクションで原子化する | [`db-transactional`](kata/db-transactional.md) | manual-only |
| Entityを使わないarray実装と比較する | [`db-array-row-comparison`](kata/db-array-row-comparison.md) | comparison-only |
| 複数SQLを`SqlQueryInterface`で調停する | [`db-sqlquery-orchestration`](kata/db-sqlquery-orchestration.md) | comparison-only |
| Raw PDOとの責務差を見る | [`db-raw-pdo-comparison`](kata/db-raw-pdo-comparison.md) | comparison-only |

### APIを作る（App Resource）

| やりたいこと | Kata | Status |
|---|---|---|
| GETをHAL+JSONで返す | [`api-get-hal-resource`](kata/api-get-hal-resource.md) | canonical |
| POST入力をInput DTOで受ける（201 + Location） | [`api-post-input-dto`](kata/api-post-input-dto.md) | canonical |
| PUTで「省略/空/指定」のtri-state入力を扱う | [`api-put-tristate-input`](kata/api-put-tristate-input.md) | canonical |
| PATCHで差分更新を受ける | [`api-patch-partial-update`](kata/api-patch-partial-update.md) | manual-only |
| DELETE成功を204で返す | [`api-delete-no-content`](kata/api-delete-no-content.md) | canonical |
| OPTIONSでメソッドとパラメータ仕様を返す | [`api-options-method`](kata/api-options-method.md) | manual-only |
| 見つからないResourceを404にする | [`not-found-response`](kata/not-found-response.md) | canonical |
| 入出力をJSON Schemaで検証する | [`json-schema-validation`](kata/json-schema-validation.md) | canonical |
| 状態遷移（draft→published）を独立Resourceにする | [`state-transition-resource`](kata/state-transition-resource.md) | canonical |
| 例外をHTTPステータスへマッピングする | [`error-status-mapping`](kata/error-status-mapping.md) | canonical |
| ファイルアップロードを受ける（`#[InputFile]`） | [`file-upload-input`](kata/file-upload-input.md) | canonical |
| cookie/env/他Resource値を引数に束縛する | [`web-context-param-binding`](kata/web-context-param-binding.md) | manual-only |
| AcceptヘッダでJSON/HTML/CSV等を出し分ける | [`content-negotiation`](kata/content-negotiation.md) | manual-only |
| 検証ロジックをAOPで分離する（`#[Valid]`） | [`aop-validation-valid`](kata/aop-validation-valid.md) | manual-only |

### リソースを繋ぐ（Hypermedia）

| やりたいこと | Kata | Status |
|---|---|---|
| 遷移先を `_links` で宣言する（`#[Link]`） | [`hal-link`](kata/hal-link.md) | canonical |
| 関連Resourceを `_embedded` に埋め込む（`#[Embed]`） | [`hal-embed`](kata/hal-embed.md) | canonical |
| リソースグラフのN+1をDataLoaderで解消する | [`crawl-data-loader`](kata/crawl-data-loader.md) | showcase |

### HTMLページを作る（Page Resource + Qiq）

| やりたいこと | Kata | Status |
|---|---|---|
| 1件詳細ページを描画する | [`page-resource-qiq-detail`](kata/page-resource-qiq-detail.md) | canonical |
| 一覧ページを描画する（pager・filter付き） | [`page-resource-list`](kata/page-resource-list.md) | canonical |
| Markdown本文をHTMLへ変換する | [`markdown-to-html`](kata/markdown-to-html.md) | canonical |
| 管理フォームでPRG（成功時303 / 失敗時422再描画） | [`admin-prg-form`](kata/admin-prg-form.md) | showcase |
| 確認画面を挟んで状態遷移を実行する | [`admin-confirm-page`](kata/admin-confirm-page.md) | showcase |
| form classにフィールド定義と検証を集約する | [`form-validation-webform`](kata/form-validation-webform.md) | manual-only |

### 認証・認可・保護

| やりたいこと | Kata | Status |
|---|---|---|
| OAuth認証フローをResourceで実装する | [`auth-oauth-flow`](kata/auth-oauth-flow.md) | showcase |
| セッションログイン（login→callback→logout） | [`admin-session-login`](kata/admin-session-login.md) | showcase |
| 認証境界（401）とauthor-scoped認可（403）を分ける | [`admin-auth-boundary`](kata/admin-auth-boundary.md) | showcase |
| write操作をCSRF/Same-Originで保護する | [`csrf-same-origin-protection`](kata/csrf-same-origin-protection.md) | canonical |
| ログイン試行をレート制限する（429） | [`rate-limit-interceptor`](kata/rate-limit-interceptor.md) | external |
| ロール/権限でリソース単位の認可をする | [`resource-permission-authorization`](kata/resource-permission-authorization.md) | external |
| 有効期限付き署名URLで検証リンクを作る | [`signed-url-verification`](kata/signed-url-verification.md) | external |

### キャッシュと配信

| やりたいこと | Kata | Status |
|---|---|---|
| `#[Cacheable]` だけでleafをキャッシュする | [`cacheable-leaf`](kata/cacheable-leaf.md) | showcase |
| `#[Embed]` 子の更新で親cacheも無効化する | [`cache-embed-dependency`](kata/cache-embed-dependency.md) | showcase |
| body由来のN個の依存を `fromAssoc()` で宣言する | [`cache-body-derived-dependency`](kata/cache-body-derived-dependency.md) | showcase |
| write時にcollection cacheを `#[Purge]` する | [`cache-purge`](kata/cache-purge.md) | showcase |
| 部分キャッシュ（donut cache）を使う | [`donut-cache`](kata/donut-cache.md) | showcase |
| レスポンス全体をキャッシュしETagを付ける | [`cacheable-response`](kata/cacheable-response.md) | showcase |
| If-None-Matchに304で応える | [`conditional-request-304`](kata/conditional-request-304.md) | showcase |

### 実行モデル（並列・遅延・バッチ・CLI・ストリーム）

| やりたいこと | Kata | Status |
|---|---|---|
| ファイル/バイナリをストリームで返す | [`stream-response`](kata/stream-response.md) | showcase |
| `#[Embed]` graphを並列実行する | [`async-embed-parallel`](kata/async-embed-parallel.md) | showcase |
| 応答後にfollow-upを実行する（`#[Defer]`・202） | [`defer-resource-request`](kata/defer-resource-request.md) | showcase |
| 条件付きでdeferを手動制御する | [`defer-conditional`](kata/defer-conditional.md) | showcase |
| cron/queueワーカーをCommand Resourceにする | [`batch-command-resource`](kata/batch-command-resource.md) | external |
| ResourceをCLIコマンドとして公開する | [`cli-resource`](kata/cli-resource.md) | showcase |
| 他アプリのResourceをimportして呼ぶ | [`import-app`](kata/import-app.md) | showcase |

### Event Sourcing

| やりたいこと | Kata | Status |
|---|---|---|
| 観察ログからEventを抽出する | [`event-extraction`](kata/event-extraction.md) | showcase |
| Eventをフィルタしてreplayする | [`event-filter-replay`](kata/event-filter-replay.md) | showcase |
| Eventを永続化する | [`event-store-persistence`](kata/event-store-persistence.md) | support |
| Resource実行から観察ログを生成する | [`resource-observation-bridge`](kata/resource-observation-bridge.md) | showcase |

### テストする

| やりたいこと | Kata | Status |
|---|---|---|
| DBなしでMediaQueryをFakeする | [`fake-sql-query`](kata/fake-sql-query.md) | support |
| App ResourceのAPI contractをテストする | [`app-resource-test`](kata/app-resource-test.md) | support |
| Page ResourceのHTML contractをテストする | [`page-resource-test`](kata/page-resource-test.md) | support |
| Link/Embedを辿るworkflowをテストする | [`hypermedia-workflow-test`](kata/hypermedia-workflow-test.md) | support |
| 実DB経路を必要時だけ検証する | [`mysql-integration-test`](kata/mysql-integration-test.md) | support |

### 意味論と生成物（ALPS / schema / docs / AI）

| やりたいこと | Kata | Status |
|---|---|---|
| ALPS profileを意味のSSOTにする | [`alps-profile-ssot`](kata/alps-profile-ssot.md) | support |
| `#[Alps]` の参照がprofileからずれていないか検査する | [`alps-reference-integrity`](kata/alps-reference-integrity.md) | support |
| 決定的なfake dataを生成する | [`semantic-fake-data`](kata/semantic-fake-data.md) | support |
| 観測からJSON Schemaを生成する | [`json-schema-generated`](kata/json-schema-generated.md) | support |
| API docsとllms.txtを生成する | [`apidoc-llms-generated`](kata/apidoc-llms-generated.md) | support |
| ResourceをLLMのtool定義として公開する | [`tool-use-instrument`](kata/tool-use-instrument.md) | external |

## 補足: 設計原則

RESTメソッドはテーブルへのCRUDではなく、application stateへの操作です。各メソッドの安全性（safe=状態を変えない）と冪等性（idempotent=繰り返しても同じ結果）が、キャッシュ戦略とAI安全設計の両方を駆動します。

| Method | Safe | Idempotent | 意味 |
|---|---|---|---|
| GET | ✅ | ✅ | 状態を読む。自由にキャッシュ・AIから自由に呼べる |
| POST | — | — | 状態を変える。繰り返しは同じ結果を保証しない |
| PUT | — | ✅ | representation全体をURIに置く（無ければ作成） |
| PATCH | — | — | 差分を適用する |
| DELETE | — | ✅ | 削除する |
| OPTIONS | ✅ | ✅ | 必要パラメータと応答仕様を照会する |

`#[Embed]` が埋め込むのはresourceの**結果**ではなくresourceへの**request**（=関係そのもの）です。この区別が、Resourceクラスを変えないままの並列実行（[`async-embed-parallel`](kata/async-embed-parallel.md)）・DataLoaderバッチ（[`crawl-data-loader`](kata/crawl-data-loader.md)）・部分キャッシュ（[`donut-cache`](kata/donut-cache.md)）を可能にします。

キャッシュ束（[`cacheable-leaf`](kata/cacheable-leaf.md) 〜 [`conditional-request-304`](kata/conditional-request-304.md)）の前提は「キャッシュを無効化するのは時間（TTL）ではなくイベント（write）」です。着手前に1つだけ問うこと — そのresourceは本質的に静的（data resource。DBから読んでいても意味は静的）か、本質的に動的（計算過程自体が表現）か。前者ならcache Kataを適用し、後者にはcache属性を付けません。TTLを短くすることを戦略の代用にしないでください。

## 補足: 外部参照（external）実装について

参照実装の主な出典は [apple-x-co/bear-app](https://github.com/apple-x-co/bear-app)（DDD + CQRS構成の実働BEAR.Sundayアプリ）と、公式パッケージ [bearsunday/BEAR.ToolUse](https://github.com/bearsunday/BEAR.ToolUse)。bear-appにはライセンス表記が無いため**コードをコピーしない**こと。attribute × interceptor の構成・命名・責務分割という「型」を読み取り、自プロジェクトで再実装します。bear-appはDDD層構造（Domain/Application/Infrastructure）を採用しており、このリポジトリのBDR（Bound / Domain / Resource）とはアーキテクチャの流儀が異なります。
