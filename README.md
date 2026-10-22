# BEAR.Kata

BEAR.Sunday アプリケーションの実装パターン集（Kata = 型）。「これを実装したい時、どのファイルを見るか」をAIエージェントと人間が素早く引くためのリファレンス実装プロジェクト。

## 引き方

1. **下の[索引](#索引)を「やりたいこと」で探す。** 各Kataの詳細（Aliases / 着手前チェック / Source / Tests / Key points / マスター確認）はリンク先の [kata/](kata/) 配下の各ページにある。
2. **キーワードで引く。** `pager` / `#[Embed]` / `PRG` / `多対多` / `認可` / `条件付きリクエスト` などの検索語（Aliases）は各Kataページ（kata/）の Aliases に載っている。この索引で見つからなければ kata/ 配下を全文検索する。
3. **AIに引かせる。** Claude Code で「◯◯を kata に従って実装して」と言うと [`bear-kata` スキル](#スキルを獲得して使う)が該当Kataへ誘導する。

| Status | 意味 |
|---|---|
| `canonical` | 最初に真似する正規形 |
| `showcase` | 特定機能を切り出した実例 |
| `comparison-only` | 比較理解用。コピーしない |
| `support` | テスト・Fake・生成物 |
| `manual-only` | 型の記述のみ。一次資料は公式マニュアル |
| `external` | 型の記述のみ。一次資料は外部の参照実装（コードはコピーしない） |

## 索引

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

## スコープ外（意図的に扱わない）

Production デプロイ / compile / preload、High-Performance Servers（Swoole / RoadRunner / FrankenPHP）、Aura.Router カスタムルーティングはこのリファレンスの対象外。理由と全リストは [docs/scope.md](docs/scope.md) を参照。

## スキルを獲得して使う

このリポジトリの価値は CMS を動かすことではなく、`bear-kata` スキルを獲得して **自分の BEAR.Sunday 実装に型(Kata)を適用する** ことにあります。獲得は3通り。

**A. このリポジトリ内（インストール不要）** — clone して中で Claude Code を開けば `bear-kata` は自動で読み込まれる。

**B. plugin として他プロジェクトへ（更新が効く）** — BEAR スキルのハブ [bearsunday/BEAR.Skills](https://github.com/bearsunday/BEAR.Skills) 経由で入れる。

```
/plugin marketplace add bearsunday/BEAR.Skills
/plugin install bear-kata@bear-skills
```

`/plugin marketplace update bear-skills` で最新の Kata に追随する（手動コピーと違い、リポジトリの更新を取り込む）。

**C. 手動コピー（最小構成・更新は手動）**

```bash
mkdir -p ~/.claude/skills && cp -r .claude/skills/bear-kata ~/.claude/skills/
```

Claude Code で「記事一覧のページングを **kata に従って実装してください**」のように言うと `bear-kata` スキルが発動し、[ソース索引](index.md)から該当 Kata（着手前チェック → Source / Tests → マスター確認）へ誘導します。明示的に呼ぶなら、リポジトリ内は `/bear-kata`、plugin 経由は `/bear-kata:bear-kata`。

## ドキュメント

- [index.md](index.md) — ソース索引（使い方・カテゴリ別索引・設計原則）。各Kataの詳細は [kata/](kata/) 配下
- [docs/setup.md](docs/setup.md) — DB・サーバー起動・管理者ログイン
- [docs/architecture.md](docs/architecture.md) — BDRレイアウトと設計根拠
- [docs/conventions.md](docs/conventions.md) — 命名・Resourceパターン・Read/Write SQL契約
- [docs/scope.md](docs/scope.md) — 実装範囲と意図的な除外事項

## Links

- [BEAR.Sunday manual](https://bearsunday.github.io/manuals/1.0/en/index.html)
- [Ray.MediaQuery](https://github.com/ray-di/Ray.MediaQuery)
- [ALPS](https://alps.io/)
