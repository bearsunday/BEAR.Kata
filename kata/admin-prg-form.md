# `admin-prg-form`

**Admin formでPRGを使う** · [← 索引に戻る](../index.md)

- **Category:** HTML / Page
- **Status:** `showcase`
- **Aliases:** admin form, PRG, Post Redirect Get, form validation, author scoped admin, write UI, フォーム, 管理画面, 303 See Other, バリデーション再描画, 422
- **Manual:** https://bearsunday.github.io/manuals/1.0/en/form.html
- **Use when:** HTML formからApp Resourceのwrite APIを呼び、成功時にredirectしたい。

## 例

### Form POST受け口

HTML formはGET/POSTのみなのでeditも `onPost` で受け、`id` の有無でcreate/updateを分岐する。validation失敗はcatchして422で同じformを `errors` 付き再描画:

```php
#[SameOrigin]
#[CsrfToken]
public function onPost(
    mixed $id = null,
    mixed $slug = '',
    mixed $title = '',
    mixed $body = '',
    // ...form fieldをそのまま引数で受ける
): static {
    $admin = $this->admin->user();
    $articleId = $this->intOrNull($id);
    // ...edit時は存在確認とauthor所有チェック、入力値のnormalise

    try {
        return $articleId === null
            ? $this->createArticle($values)
            : $this->updateArticle($articleId, $values);
    } catch (ValidationException | ParameterException $e) {
        $errors = $e instanceof ValidationException
            ? $e->errors
            : ['_global' => [$e->getMessage()]];
        $this->code = 422;
        $this->body = $this->formBody($article, $values, $errors, null);

        return $this;
    }
}
```

### 成功時の303 redirect

Page AdminはApp Resourceのwrite APIを包むだけ。成功したときだけ303（Post/Redirect/Get）:

```php
/** @param array<string, mixed> $values */
private function createArticle(array $values): static
{
    $created = $this->resource->post('app://self/article', $values);
    if ($created->code >= 400 || ! is_array($created->body) || ! isset($created->body['id'])) {
        return $this->writeFailure($created, 'Article create failed');
    }

    $createdId = (int) $created->body['id'];
    $this->redirect('/admin/article?id=' . $createdId . '&saved=created');

    return $this;
}

private function redirect(string $location): void
{
    $this->code = 303;
    $this->headers['Location'] = $location;
    $this->body = [];
}
```

### Delete

author-scopedの所有チェック（他authorなら403）を通してからApp Resourceのdeleteを呼び、一覧へ303:

```php
#[SameOrigin]
#[CsrfToken]
public function onPost(int $id): static
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

    $deleted = $this->resource->delete('app://self/article', ['id' => $id]);
    // ...App側の4xxはそのまま返す

    $this->code = 303;
    $this->headers['Location'] = '/admin/articlelist?deleted=1';
    $this->body = [];

    return $this;
}

private function owns(Article $article, AdminUserInterface $admin): bool
{
    return $article->authorId === $admin->authorId();
}
```

### Template

`errors` があればform上部に一覧表示し、入力値は `values` で復元。CSRF tokenとedit時の `id` はhidden field:

```php
<?php if ($errors !== []): ?>
  <section class="ErrorList">
    <h2>Could not save article</h2>
    <ul>
      <?php foreach ($errors as $field => $messages): ?>
        <?php foreach ($messages as $message): ?>
          <li><?php if ($field !== '_global'): ?><strong>{{h $field }}</strong>: <?php endif ?>{{h $message }}</li>
        <?php endforeach ?>
      <?php endforeach ?>
    </ul>
  </section>
<?php endif ?>

<form class="ArticleForm" method="post" action="{{h $formAction }}">
  {{= csrfField() }}
  <?php if ($isEdit): ?>
    <input type="hidden" name="id" value="{{h $article->id }}">
  <?php endif ?>
  <label for="title">Title</label>
  <input id="title" name="title" type="text" value="{{a $title }}" required>
  <button type="submit">{{h $submit }}</button>
</form>
```

## Naming

Template内のnavリンクのclass名はALPS Choreographyの遷移動詞で付ける（HAL relと同じ語彙）:

| 種別 | 形 | 例 |
|---|---|---|
| 画面遷移 | `go<Target>` | `goAdminIndex`, `goAdminArticleList` |
| 作用を伴う遷移 | `do<Action>` | `doDeleteArticle`, `doPublishArticle` |

Resourceの依存property — readはqueryable noun、書き込みの入口は `$resource`（App Resourceを包むため `<Entity>CommandInterface` は持たない）:

| 依存 | Property | 例 |
|---|---|---|
| Read | `$<entity>` | `private ArticleQueryInterface $article` |
| Write（App経由） | `$resource` | `private ResourceInterface $resource` |

> 命名規則の全容は [conventions.md](../docs/conventions.md#3-naming) を参照。

## 着手前チェック（Before）

- [ ] Page Admin が write rules を再実装せず、App Resource の write API を**包む**だけにすると決めたか。
- [ ] 成功時は303 redirect（Post/Redirect/Get）、validation失敗時はredirectせず422で同じformを `errors` 付き再描画にすると決めたか。
- [ ] 認可境界（author-scoped）とCSRF保護の前提を理解したか。

## Source

- [`src/Resource/Page/Admin/Article.php`](../src/Resource/Page/Admin/Article.php)
- [`src/Resource/Page/Admin/ArticleDelete.php`](../src/Resource/Page/Admin/ArticleDelete.php)
- [`templates/Page/Admin/Article.php`](../templates/Page/Admin/Article.php)
- [`templates/Page/Admin/ArticleDelete.php`](../templates/Page/Admin/ArticleDelete.php)

## Tests

- [`tests/Resource/Page/Admin/ArticleTest.php`](../tests/Resource/Page/Admin/ArticleTest.php)
- [`tests/Resource/Page/Admin/ArticleDeleteTest.php`](../tests/Resource/Page/Admin/ArticleDeleteTest.php)
- [`tests/Resource/Page/Admin/AuthBoundaryTest.php`](../tests/Resource/Page/Admin/AuthBoundaryTest.php)

## Key points

Page Adminは `$this->resource->post/put/delete('app://self/article', ...)` でApp Resourceを包み、成功時は303 redirect。HTML formはGET/POSTのみなのでeditも `onPost` で受け、`id` の有無でcreate（post）/update（put）を分岐する。validation失敗はPage側で `ValidationException` / `ParameterException` をcatchし、422で `errors` 付きにform再描画（PRGは成功時のみ）。公式manualのform.html（Ray.WebFormModule方式）は別アプローチ — [`form-validation-webform`](./form-validation-webform.md) 参照。

## Do not

- validation失敗時までredirectしない — errorsをsession flashに載せてredirectで戻す他フレームワークの型ではなく、同じrequest内で422 + `errors` 付き再描画する。303はwrite成功時のみ。

## マスター確認（After）

- [ ] Page Admin が `app://self/...` の write を呼び、独自のSQL/write logicを持たない。
- [ ] 成功時に303 redirect、validation失敗時に422 + errors再描画している。
- [ ] 他authorの記事をedit/update/deleteできない（403）ことを `ArticleTest.php` / `ArticleDeleteTest.php` の該当ケース相当で green（未ログイン→401の境界は `AuthBoundaryTest.php`）。

## See also

- [`form-validation-webform`](./form-validation-webform.md) — 公式manual方式（Ray.WebFormModule）のform validation
- [`csrf-same-origin-protection`](./csrf-same-origin-protection.md) — `#[SameOrigin]` / `#[CsrfToken]` によるform保護
- [`admin-auth-boundary`](./admin-auth-boundary.md) — 未ログイン401を返す型付き認可境界
- [`admin-session-login`](./admin-session-login.md) — session-backedなAdminログインflow
- [`admin-confirm-page`](./admin-confirm-page.md) — 実行前に確認ページを挟むAdmin form
- [`api-post-input-dto`](./api-post-input-dto.md) — 包まれる側のApp write API（create）
- [`api-put-tristate-input`](./api-put-tristate-input.md) — 包まれる側のApp write API（update）
