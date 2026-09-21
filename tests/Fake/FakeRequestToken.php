<?php

declare(strict_types=1);

namespace BEAR\Kata\Fake;

use Override;
use Ray\Aop\MethodInvocation;
use Ray\Csrf\Http\CsrfTokenField;
use Ray\Csrf\Http\RequestTokenInterface;

/** Scripts what the request carried, so a test can drive the gate without a real submission. */
final readonly class FakeRequestToken implements RequestTokenInterface
{
    public function __construct(private string|null $submitted = FakeCsrfToken::DEFAULT_TOKEN)
    {
    }

    /** @param MethodInvocation<object> $invocation */
    #[Override]
    public function submitted(MethodInvocation $invocation, CsrfTokenField $field): string|null
    {
        return $this->submitted;
    }
}
