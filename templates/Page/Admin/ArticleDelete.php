<?php
/**
 * @var \BEAR\Kata\Entity\Article $article
 */
?>
{{ setLayout('layout/Default') }}
{{ setBlock('bodyClass') ~}}admin admin-confirm admin-articledelete{{ endBlock() }}
{{ setBlock('title') ~}}Delete Article - BEAR.Kata{{ endBlock() }}
{{ setBlock('header') ~}}<h1 class="AdminArticleDelete">Delete Article</h1>{{ endBlock() }}
<main>
  <section class="Article">
    <input type="hidden" class="id" value="{{a $article->id }}">
    <h2 class="title">{{h $article->title }}</h2>
    <span class="slug">{{h $article->slug }}</span>
    <span class="status" data-status="{{h $article->status->value }}">{{h $article->status->value }}</span>
  </section>

  <form class="DeleteForm" method="post" action="/admin/articledelete">
    <input type="hidden" name="id" value="{{a $article->id }}">
    {{= csrfField() }}
    <p>This action deletes the article.</p>
    <button type="submit" class="doDeleteArticle">Delete article</button>
  </form>
</main>
<nav>
  <h2>Navigation</h2>
  <ul>
    <li><a href="/admin/index" class="goAdminIndex">Admin home</a></li>
    <li><a href="/admin/article?id={{a $article->id }}" class="doUpdateArticle">Back to edit</a></li>
    <li><a href="/admin/articlelist" class="goAdminArticleList">Back to article administration</a></li>
  </ul>
</nav>
