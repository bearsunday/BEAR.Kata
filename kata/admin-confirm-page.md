# `admin-confirm-page`

**確認画面Page Resourceで状態遷移をラップする** · [← 索引に戻る](../index.md)

- **Category:** HTML / Page
- **Status:** `showcase`
- **Aliases:** confirm page, preview page, publish confirmation, two-step form, 409 handling, 確認画面, プレビュー, 公開確認
- **Manual:** https://bearsunday.github.io/manuals/1.0/en/resource.html
- **Use when:** 確認ステップ（公開前プレビュー等）を挟んでからApp Resourceの状態遷移を実行したい。

## 例

### GET — read-onlyプレビュー

404 guard → author-scoped認可（`owns()`）→ プレビューbody。`alreadyPublished` flagがtemplateのform出し分けを決める:

```php
public function onGet(int $id): static
{
    $admin = $this->admin->user();
    $article = $this->article->item($id);
    if ($article === null) {
        $this->code = 404;
        $this->body = ['message' => 'Article not found'];

        return $this;
    }

    if (! $this->owns($article, $admin)) {
        return $this->forbidden();
    }

    $this->body = $this->previewBody($article, [], $article->isPublished());

    return $this;
}

private function owns(Article $article, AdminUserInterface $admin): bool
{
    return $article->authorId === $admin->authorId();
}
```

### POST — App状態遷移への転送

write rulesは再実装せず `app://self/article-publish` へ転送。409は成功でも500でもなく、確認画面への差し戻しとして描画する:

```php
#[SameOrigin]
#[CsrfToken]
public function onPost(int $id): static
{
    // ...（404 / owns() guard は onGet と同形）

    $result = $this->resource->post('app://self/article-publish', ['id' => $id]);

    if ($result->code === 409) {
        // GET/POST間に published へ移った — 競合を表示して差し戻す
        $message = is_array($result->body) && isset($result->body['message'])
            ? (string) $result->body['message']
            : 'Article is already published';
        $this->code = 409;
        $this->body = $this->previewBody($article, [$message], true);

        return $this;
    }

    // ...（その他の 4xx/5xx も同様に previewBody で差し戻し）

    $this->code = 303;
    $this->headers['Location'] = '/article?id=' . $id;
    $this->body = [];

    return $this;
}
```

### previewBody — summary構築

author/category/tag summaryをGET・差し戻しの両方で使う単一のbody builderに寄せる:

```php
private function previewBody(Article $article, array $errors, bool $alreadyPublished): array
{
    $authorEntity = $this->author->item($article->authorId);
    $categoryEntity = $this->category->item($article->categoryId);
    $tagNames = [];
    foreach ($this->tag->listByArticle($article->id) as $tag) {
        $tagNames[] = $tag->name;
    }

    return [
        'article' => $article,
        'authorName' => $authorEntity === null ? '' : $authorEntity->name,
        'categoryName' => $categoryEntity === null ? '' : $categoryEntity->name,
        'tagNames' => $tagNames,
        'alreadyPublished' => $alreadyPublished,
        'errors' => $errors,
    ];
}
```

### Qiqテンプレート — formの出し分け

`alreadyPublished` なら公開form非表示でnotice、draftならCSRF token入りの公開form:

```php
<?php if ($alreadyPublished): ?>
  <p class="notice already-published">This article is already published.</p>
  <nav class="ConfirmActions">
    <a href="/article?id={{h $article->id }}" class="goArticle">View public article</a>
    <a href="/admin/article?id={{h $article->id }}" class="doUpdateArticle">Back to edit</a>
  </nav>
<?php else: ?>
  <form class="PublishForm" method="post" action="/admin/articleconfirm">
    <input type="hidden" name="id" value="{{a $article->id }}">
    {{= csrfField() }}
    <button type="submit" class="doPublishArticle">Publish article</button>
    <a href="/admin/article?id={{h $article->id }}" class="doUpdateArticle">Back to edit</a>
  </form>
<?php endif ?>
```

### 転送先App Resource

`ArticlePublish` が状態機械を守る — 既にpublishedなら409（型は [`state-transition-resource`](./state-transition-resource.md)）:

```php
if ($article->isPublished()) {
    $this->code = 409;
    $this->body = [
        'message' => 'Article is already published',
        'id' => $id,
        'status' => $article->status->value,
    ];

    return $this;
}
```

## Naming

| 対象 | 規則 | 例 |
|---|---|---|
| 確認Page Resource | `<Entity>Confirm` — `src/Resource/Page/Admin/` 以下 | `ArticleConfirm` → `page://self/admin/articleconfirm` |
| Qiqテンプレート | Resourceの `src/Resource/` 以下のパスを `templates/` に写す | `templates/Page/Admin/ArticleConfirm.php` |
| 転送先の遷移Resource | `<Entity><Verb>` | `ArticlePublish` → `app://self/article-publish` |
| template内のtransition class | ALPS Choreography動詞（`do*` / `go*`） | `doPublishArticle` / `goArticle` / `doUpdateArticle` |

> 命名規則の全容は [conventions.md](../docs/conventions.md#3-naming) を参照。

## 着手前チェック（Before）

- [ ] 確認画面を編集フォームのquery-string modeではなく独立Page Resourceにする（URLが状態: `/admin/article/confirm?id=N` がbookmarkable）と決めたか。
- [ ] POSTはwrite rulesを再実装せず `app://self/article-publish` に転送すると決めたか（Reachability）。
- [ ] GET/POST間の競合（既にpublished）を409で受け、確認画面に差し戻す設計を理解したか。

## Source

- [`src/Resource/Page/Admin/ArticleConfirm.php`](../src/Resource/Page/Admin/ArticleConfirm.php)
- [`templates/Page/Admin/ArticleConfirm.php`](../templates/Page/Admin/ArticleConfirm.php)
- [`src/Resource/App/ArticlePublish.php`](../src/Resource/App/ArticlePublish.php)

## Tests

- [`tests/Resource/Page/Admin/ArticleConfirmTest.php`](../tests/Resource/Page/Admin/ArticleConfirmTest.php)

## Key points

GET=read-onlyプレビュー（author/category/tag summary付き。既にpublishedならform非表示でnotice）。POSTは `#[SameOrigin]` + `#[CsrfToken]` （→ [`csrf-same-origin-protection`](./csrf-same-origin-protection.md)）でApp状態遷移（→ [`state-transition-resource`](./state-transition-resource.md)）を転送。409はエラーとして差し戻し（`alreadyPublished` + `errors`）、成功は公開記事へ303。author-scoped認可（`owns()`）はGET/POST両方で実施。

## Do not

- App側の409を成功扱い・500扱いにしない — GET/POST間に状態が動いた「正常な競合」であり、409のまま `alreadyPublished` + `errors` で確認画面へ差し戻し、編集者に次の遷移を選ばせる。

## マスター確認（After）

- [ ] draft GETでフォーム表示、published GETでフォーム非表示（notice表示）。
- [ ] POST成功で公開記事へ303、既publishedのPOSTで409 + プレビュー差し戻しを `ArticleConfirmTest.php` 相当で green。

## See also

- [`state-transition-resource`](./state-transition-resource.md) — 転送先 `ArticlePublish`（409を返す状態遷移）の型
- [`admin-prg-form`](./admin-prg-form.md) — create/update側のPRGフォーム（確認画面の前段）
- [`admin-auth-boundary`](./admin-auth-boundary.md) — `AdminGuard` / 型付き管理者境界（`owns()` の土台）
- [`admin-session-login`](./admin-session-login.md) — 管理画面へ入るsession login flow
- [`csrf-same-origin-protection`](./csrf-same-origin-protection.md) — `#[SameOrigin]` + `#[CsrfToken]` の仕組み
- [`page-resource-test`](./page-resource-test.md) — Page HTMLのテスト型（`ArticleConfirmTest` が従う形）
