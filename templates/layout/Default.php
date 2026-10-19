<?php
/**
 * @var int $cssLevel
 * @var array<int, string> $cssLinks
 * @var \BEAR\Kata\Auth\UserInterface $user
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ setBlock('title') ~}}BEAR.Kata{{= getBlock() ~}}</title>
  <link rel="profile" href="/profile/alps.json">
  <link rel="stylesheet" href="/css/level{{h $cssLevel }}.css">
</head>
<body class="{{ setBlock('bodyClass') ~}}public public-list{{= getBlock() ~}}">
  <nav class="SiteNav">
    <a class="goIndex brand" href="/">BEAR.Kata</a>
    <ul class="browse">
      <li><a class="goArticleList" href="/articlelist">Articles</a></li>
      <li><a class="goAuthorList" href="/authorlist">Authors</a></li>
      <li><a class="goCategoryList" href="/categorylist">Categories</a></li>
      <li><a class="goTagList" href="/taglist">Tags</a></li>
    </ul>
    <?php if ($user instanceof \BEAR\Kata\Auth\AdminUserInterface): ?>
      <a class="goAdminIndex" href="/admin/index">Admin</a>
      <form class="doLogout" method="post" action="/admin/logout">{{= csrfField() }}<button type="submit">Sign out</button></form>
    <?php else: ?>
      <a class="goSignIn" href="/admin/login">Sign in</a>
    <?php endif ?>
  </nav>
  <header>
    {{ setBlock('header') ~}}{{= getBlock() ~}}
  </header>
  {{= getContent() }}
  <footer>
    <p>BEAR.Kata</p>
    {{= render('partial/CssSwitcher') }}
  </footer>
</body>
</html>
