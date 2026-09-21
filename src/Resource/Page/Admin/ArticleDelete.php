<?php

declare(strict_types=1);

namespace BEAR\Kata\Resource\Page\Admin;

use BEAR\Csrf\Attribute\CsrfToken;
use BEAR\Csrf\Attribute\SameOrigin;
use BEAR\Kata\Auth\AdminGuard;
use BEAR\Kata\Auth\AdminUserInterface;
use BEAR\Kata\Entity\Article;
use BEAR\Kata\Query\ArticleQueryInterface;
use BEAR\Resource\Code;
use BEAR\Resource\ResourceInterface;
use BEAR\Resource\ResourceObject;

use function is_array;

/** @property array{message: string}|array{article: Article}|array{} $body */
class ArticleDelete extends ResourceObject
{
    public function __construct(
        private readonly ResourceInterface $resource,
        private readonly AdminGuard $admin,
        private readonly ArticleQueryInterface $article,
    ) {
    }

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

        $this->body = ['article' => $article];

        return $this;
    }

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
        if ($deleted->code === 404) {
            $this->code = 404;
            $this->body = ['message' => 'Article not found'];

            return $this;
        }

        if ($deleted->code >= 400) {
            $this->code = $deleted->code;
            $this->body = is_array($deleted->body) ? $deleted->body : ['message' => 'Article delete failed'];

            return $this;
        }

        $this->code = 303;
        $this->headers['Location'] = '/admin/articlelist?deleted=1';
        $this->body = [];

        return $this;
    }

    private function owns(Article $article, AdminUserInterface $admin): bool
    {
        return $article->authorId === $admin->authorId();
    }

    private function forbidden(): static
    {
        $this->code = Code::FORBIDDEN;
        $this->body = ['message' => 'Forbidden'];

        return $this;
    }
}
