<?php

declare(strict_types=1);

namespace Ray\Csrf;

use BEAR\Resource\ResourceObject;
use Override;
use Ray\Csrf\Attribute\CsrfToken;
use Ray\Csrf\Attribute\SameOrigin;
use Ray\Csrf\Http\AllowedOrigin;
use Ray\Csrf\Http\CsrfTokenField;
use Ray\Csrf\Http\RequestBodyTokenInterface;
use Ray\Csrf\Http\RequestOriginInterface;
use Ray\Csrf\Http\ServerRequestBodyToken;
use Ray\Csrf\Http\ServerRequestOrigin;
use Ray\Csrf\Interceptor\CsrfTokenInterceptor;
use Ray\Csrf\Interceptor\SameOriginInterceptor;
use Ray\Di\AbstractModule;
use Ray\Di\Scope;

/** @SuppressWarnings("PHPMD.CouplingBetweenObjects") composition root */
final class CsrfModule extends AbstractModule
{
    /**
     * @param string|null $allowedOrigin Origin the same-origin gate accepts, or null to run
     *                                   without that gate. Deliberately has no default: an
     *                                   origin nobody chose is how a security control ends up
     *                                   off in production, and `null` here is greppable in a
     *                                   way a missing argument is not. Use the named
     *                                   constructors rather than passing it positionally.
     */
    private function __construct(
        private readonly string|null $allowedOrigin,
        private readonly string $csrfTokenField,
    ) {
        parent::__construct();
    }

    /** Both gates on: the token gate always, the same-origin gate against $allowedOrigin. */
    public static function withSameOriginCheck(
        string $allowedOrigin,
        string $csrfTokenField = '_csrf_token',
    ): self {
        return new self($allowedOrigin, $csrfTokenField);
    }

    /**
     * Token gate only. For a deployment with no browser origin to compare against — a CLI or
     * API host — where the same-origin gate would reject every request rather than protect it.
     * The token gate stays on either way; the two are independent defences.
     */
    public static function withoutSameOriginCheck(string $csrfTokenField = '_csrf_token'): self
    {
        return new self(null, $csrfTokenField);
    }

    #[Override]
    protected function configure(): void
    {
        $this->bind(AllowedOrigin::class)->toInstance(new AllowedOrigin($this->allowedOrigin));
        $this->bind(CsrfTokenField::class)->toInstance(new CsrfTokenField($this->csrfTokenField));

        $this->bind(RequestOriginInterface::class)->to(ServerRequestOrigin::class);
        $this->bindInterceptor(
            $this->matcher->subclassesOf(ResourceObject::class),
            $this->matcher->annotatedWith(SameOrigin::class),
            [SameOriginInterceptor::class],
        );

        $this->bind(CsrfTokenInterface::class)->to(SessionCsrfToken::class)->in(Scope::SINGLETON);
        $this->bind(RequestBodyTokenInterface::class)->to(ServerRequestBodyToken::class);
        $this->bindInterceptor(
            $this->matcher->subclassesOf(ResourceObject::class),
            $this->matcher->annotatedWith(CsrfToken::class),
            [CsrfTokenInterceptor::class],
        );
    }
}
