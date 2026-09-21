<?php

declare(strict_types=1);

namespace Ray\Csrf\Interceptor;

use Override;
use Ray\Aop\MethodInterceptor;
use Ray\Aop\MethodInvocation;
use Ray\Csrf\CsrfTokenInterface;
use Ray\Csrf\Exception\ForbiddenException;
use Ray\Csrf\Http\RequestBodyTokenInterface;

/** Synchroniser-token gate. See the consumer's CSRF design notes. */
final readonly class CsrfTokenInterceptor implements MethodInterceptor
{
    public function __construct(
        private CsrfTokenInterface $csrf,
        private RequestBodyTokenInterface $body,
    ) {
    }

    /** @param MethodInvocation<object> $invocation */
    #[Override]
    public function invoke(MethodInvocation $invocation): mixed
    {
        // A missing token goes through verify() as '' rather than short-circuiting here, so the
        // bound CsrfTokenInterface is the only authority on what is acceptable. A context that
        // wants to accept token-less requests says so by binding an implementation that does;
        // it cannot be expressed if this method decides on its own.
        $submitted = $this->body->submitted();
        if (! $this->csrf->verify($submitted ?? '')) {
            throw new ForbiddenException(
                $submitted === null ? 'CSRF token missing.' : 'CSRF token invalid.',
            );
        }

        return $invocation->proceed();
    }
}
