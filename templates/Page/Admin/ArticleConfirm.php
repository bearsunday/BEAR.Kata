<?php
/**
 * @var \BEAR\Kata\Entity\Article $article
 * @var string $authorName
 * @var string $categoryName
 * @var list<string> $tagNames
 * @var bool $alreadyPublished
 * @var list<string> $errors
 */
?>
{{ setLayout('layout/Default') }}
{{ setBlock('bodyClass') ~}}admin admin-confirm admin-articleconfirm{{ endBlock() }}
{{ setBlock('title') ~}}Publish Article - BEAR.Kata{{ endBlock() }}
{{ setBlock('header') ~}}<h1 class="AdminArticleConfirm">Publish Article</h1>{{ endBlock() }}
<main>
  <?php if ($errors !== []): ?>
    <section class="ErrorList">
      <h2>Could not publish article</h2>
      <ul>
        <?php foreach ($errors as $error): ?>
          <li>{{h $error }}</li>
        <?php endforeach ?>
      </ul>
    </section>
  <?php endif ?>

  <section class="ArticlePreview">
    <h2 class="title">{{h $article->title }}</h2>
    <p class="meta">
      <span class="slug">Slug: <code>{{h $article->slug }}</code></span>
      <span class="status" data-status="{{h $article->status->value }}">{{h $article->status->value }}</span>
      <span class="Author">Author: <span class="name">{{h $authorName }}</span></span>
      <span class="Category">Category: <span class="name">{{h $categoryName }}</span></span>
      <?php if ($tagNames !== []): ?>
        <span class="Tags">Tags: <span class="names">{{h implode(', ', $tagNames) }}</span></span>
      <?php endif ?>
    </p>
    <?php if ($article->excerpt !== null && $article->excerpt !== ''): ?>
      <p class="excerpt">{{h $article->excerpt }}</p>
    <?php endif ?>
    <article class="body">{{h $article->body }}</article>
  </section>

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
      <p>Publishing will make this article visible on the public site.</p>
      <button type="submit" class="doPublishArticle">Publish article</button>
      <a href="/admin/article?id={{h $article->id }}" class="doUpdateArticle">Back to edit</a>
    </form>
  <?php endif ?>
</main>
<nav>
  <h2>Navigation</h2>
  <ul>
    <li><a href="/admin/index" class="goAdminIndex">Admin home</a></li>
    <li><a href="/admin/articlelist" class="goAdminArticleList">Back to article administration</a></li>
  </ul>
</nav>
