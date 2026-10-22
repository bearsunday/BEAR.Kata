# `alps-profile-ssot`

**ALPS profileを意味のSSOTにする** · [← 索引に戻る](../index.md)

- **Category:** Semantic / generated artifacts
- **Status:** `support`
- **Aliases:** ALPS, semantic profile, ontology, taxonomy, choreography, SSOT, profile.json, app-state-diagram, ALPSプロファイル, 語彙, 意味論
- **Manual:** https://bearsunday.github.io/manuals/1.0/en/apidoc.html
- **Use when:** Resource名、rel名、入力語彙を意味モデルから揃えたい。

## 例

### Ontology — field語彙

`var/alps/profile.json` のdescriptor。idは素のfield名で、可能なら `def` で schema.org に意味を固定する:

```json
{"id": "title", "title": "Title", "def": "https://schema.org/headline"},
{"id": "body", "title": "Article Body", "def": "https://schema.org/articleBody"},
{"id": "status", "title": "Lifecycle Status", "doc": {"value": "draft or published."}}
```

### Taxonomy — 名詞

Ontologyの語彙とChoreographyの遷移を `href` で束ねる名詞:

```json
{"id": "Tag", "title": "Tag Detail", "descriptor": [
  {"href": "#id"},
  {"href": "#slug"},
  {"href": "#name"},
  {"href": "#goTagList"},
  {"href": "#goArticleList"},
  {"href": "#doDeleteTag"}
], "tag": "tag"}
```

### Choreography — 遷移名

safe遷移は `go*`（GET）、unsafe遷移は `do*`（POST/PUT/DELETE）。冪等writeは `idempotent` 属性:

```json
{"id": "goArticle", "type": "safe", "rt": "#Article", "title": "View Article"},
{"id": "doCreateArticle", "type": "unsafe", "rt": "#Article", "title": "Create Article"},
{"id": "doUpdateArticle", "type": "idempotent", "rt": "#Article", "title": "Update Article"}
```

### apidoc.xml — 生成物がprofileを共有

`<alps>` 指定で ApiDoc / openapi.json / llms.txt の生成が同じprofileを読む:

```xml
<format>html,openapi,llms,audit,terms</format>
<alps>var/alps/profile.json</alps>
```

### 層分離のcontract pin

Taxonomy名詞は `_embedded`、Choreography動詞は `_links` — rename が単独の失敗として現れるようテストで固定する:

```php
$this->assertSame(['author', 'category', 'tagList'], array_keys($rendered['_embedded']));
// `self` is added by the HAL renderer; the rest are the resource's own choreography.
$rels = array_values(array_diff(array_keys($rendered['_links']), ['self']));
$this->assertSame(['goArticleList', 'goAuthor', 'goCategory'], $rels);
```

## Naming

Ontologyのdescriptor idはフラットな素のfield名（`title`、`status`、`publishedAt`）— 意味は entity prefix ではなく `def` の schema.org 参照で固定する。他エンティティの同一性を指す参照だけ prefix を残す（`authorId`、`categoryId`、`parentId`）。

HAL rel はALPSの層で分ける:

| Where | 由来する層 | 例 |
|---|---|---|
| `#[Link]` rel | Choreography（遷移動詞） | `goArticleList`, `goAuthor`, `doCreateArticle` |
| `#[Embed]` rel | Taxonomy（名詞） | `author`, `category`, `tagList` |

> 命名規則の全容は [conventions.md](../docs/conventions.md#3-naming) を参照。

## 着手前チェック（Before）

- [ ] 語彙の出所を `var/alps/profile.json`（SSOT）に一本化すると決めたか。
- [ ] Ontology（語彙）/ Taxonomy（名詞）/ Choreography（遷移名）の3層を区別したか。

## Source

- [`var/alps/profile.json`](../var/alps/profile.json)
- [`apidoc.xml`](../apidoc.xml)
- [`docs/alps.md`](../docs/alps.md)
- [`docs/architecture.md`](../docs/architecture.md)

## Tests

- [`tests/Hypermedia/HalEnvelopeContractTest.php`](../tests/Hypermedia/HalEnvelopeContractTest.php)

## Key points

Ontologyは `title`（def: schema.org/headline）などのfield語彙、Taxonomyは `Article` などの名詞、Choreographyは遷移名 — safe遷移は `go*`（GET）、unsafe遷移は `do*`（POST/PUT/DELETE、冪等writeはidempotent属性）。ALPS遷移名はHAL `_links` rel / Resource URIと1:1に対応する（`goArticle` → `app://self/article`）。`apidoc.xml` の `<alps>` 指定でApiDoc / openapi.json / llms.txt生成が同じprofileを共有する — profileがSSOTである根拠。

## Do not

- HAL link relとembed relに同じ命名層を使わない — `#[Embed(rel: 'goAuthor', ...)]` は誤り。`go*` はclientが辿るChoreography遷移で、embedはserverが同梱するTaxonomyインスタンス。名前空間を混ぜない。

## マスター確認（After）

- [ ] Resource名・rel名・入力語彙が profile.json の定義と一致。
- [ ] link rel=Choreography / embed rel=Taxonomy の層分離が守られている。

## See also

- [`semantic-fake-data`](./semantic-fake-data.md) — profile.jsonから決定論的なfakeデータを生成する（semantic-ex Phase 1）
- [`json-schema-generated`](./json-schema-generated.md) — fakeデータの観察からJSON Schema制約を導出する
- [`apidoc-llms-generated`](./apidoc-llms-generated.md) — 同じprofileからApiDoc / openapi.json / llms.txtを生成する
- [`hal-link`](./hal-link.md) — Choreography遷移名を `_links` relとして付ける
- [`hal-embed`](./hal-embed.md) — Taxonomy名詞を `_embedded` relとして埋め込む
- [`hypermedia-workflow-test`](./hypermedia-workflow-test.md) — rel名を辿ってworkflowをテストする
- [`alps-reference-integrity`](./alps-reference-integrity.md) — `#[Alps]` の参照がこのprofileからずれていないかをテストで検査する
