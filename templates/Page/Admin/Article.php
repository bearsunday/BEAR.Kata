<?php
/**
 * @var 'create'|'edit' $mode
 * @var \BEAR\Kata\Entity\Article|null $article
 * @var array<string, mixed> $values
 * @var array<string, list<string>> $errors
 * @var list<\BEAR\Kata\Entity\Author> $authors
 * @var list<\BEAR\Kata\Entity\Category> $categories
 * @var list<\BEAR\Kata\Entity\Tag> $tags
 * @var list<int> $selectedTagIds
 * @var string|null $saved
 */
$isEdit = $mode === 'edit' && $article !== null;
$title = (string) ($values['title'] ?? '');
$slug = (string) ($values['slug'] ?? '');
$body = (string) ($values['body'] ?? '');
$status = (string) ($values['status'] ?? 'draft');
$excerpt = (string) ($values['excerpt'] ?? '');
$publishedAt = (string) ($values['publishedAt'] ?? '');
$categoryId = isset($values['categoryId']) ? (int) $values['categoryId'] : null;
$heading = $isEdit ? 'Edit Article' : 'Create Article';
$submit = $isEdit ? 'Update article' : 'Create article';
$formAction = $isEdit ? '/admin/article?id=' . $article->id : '/admin/article';
$nameFor = static function (array $entities, int $id): string {
    foreach ($entities as $entity) {
        if ($entity->id === $id) {
            return $entity->name;
        }
    }

    return '';
};
?>
{{ setLayout('layout/Default') }}
{{ setBlock('bodyClass') ~}}admin admin-form admin-article{{ endBlock() }}
{{ setBlock('title') ~}}{{h $heading }} - BEAR.Kata{{ endBlock() }}
{{ setBlock('header') ~}}<h1 class="AdminArticle">{{h $heading }}</h1>{{ endBlock() }}
<main>
  <?php if ($saved === 'created'): ?>
    <p class="notice">Article created.</p>
  <?php elseif ($saved === 'updated'): ?>
    <p class="notice">Article updated.</p>
  <?php endif ?>

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
      <p class="slug">Slug: <code>{{h $article->slug }}</code></p>
      <p class="Author">Author: <span class="name">{{h $nameFor($authors, $article->authorId) }}</span></p>
      <p class="Category">Category: <span class="name">{{h $nameFor($categories, $article->categoryId) }}</span></p>
    <?php else: ?>
      <label for="slug">Slug</label>
      <input id="slug" name="slug" type="text" value="{{a $slug }}" required>

      <label for="categoryId">Category</label>
      <select id="categoryId" name="categoryId" required>
        <option value="">Select category</option>
        <?php foreach ($categories as $category): ?>
          <option value="{{a $category->id }}" <?php if ($categoryId === $category->id): ?>selected<?php endif ?>>{{h $category->name }}</option>
        <?php endforeach ?>
      </select>
    <?php endif ?>

    <label for="title">Title</label>
    <input id="title" name="title" type="text" value="{{a $title }}" required>

    <label for="status">Status</label>
    <select id="status" name="status" required>
      <option value="draft" <?php if ($status === 'draft'): ?>selected<?php endif ?>>Draft</option>
      <option value="published" <?php if ($status === 'published'): ?>selected<?php endif ?>>Published</option>
    </select>

    <label for="excerpt">Excerpt</label>
    <textarea id="excerpt" name="excerpt" rows="3">{{h $excerpt }}</textarea>

    <label for="publishedAt">Published at</label>
    <input id="publishedAt" name="publishedAt" type="text" value="{{a $publishedAt }}" placeholder="2026-01-01T10:07:00Z">

    <label for="body">Body</label>
    <textarea id="body" name="body" rows="12" required>{{h $body }}</textarea>

    <label for="tagIds">Tags</label>
    <select id="tagIds" name="tagIds[]" multiple>
      <?php foreach ($tags as $tag): ?>
        <option value="{{a $tag->id }}" <?php if (in_array($tag->id, $selectedTagIds, true)): ?>selected<?php endif ?>>{{h $tag->name }}</option>
      <?php endforeach ?>
    </select>

    <button type="submit">{{h $submit }}</button>
  </form>
</main>
<nav>
  <h2>Navigation</h2>
  <ul>
    <li><a href="/admin/index" class="goAdminIndex">Admin home</a></li>
    <li><a href="/admin/articlelist" class="goAdminArticleList">Back to article administration</a></li>
    <?php if ($isEdit): ?>
      <?php if ($article->isDraft()): ?>
        <li><a href="/admin/articleconfirm?id={{h $article->id }}" class="doPublishArticle">Publish article…</a></li>
      <?php endif ?>
      <li><a href="/article?id={{h $article->id }}" class="goArticle">View public article</a></li>
      <li><a href="/admin/articledelete?id={{h $article->id }}" class="doDeleteArticle">Delete article</a></li>
    <?php endif ?>
  </ul>
</nav>
