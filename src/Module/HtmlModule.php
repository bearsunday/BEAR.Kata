<?php

declare(strict_types=1);

namespace BEAR\Kata\Module;

use BEAR\Kata\Provide\Error\HtmlThrowableHandler;
use BEAR\Kata\Renderer\CmsHelpers;
use BEAR\Kata\Renderer\CmsQiqRenderer;
use BEAR\QiqModule\QiqModule;
use BEAR\Resource\RenderInterface;
use BEAR\Sunday\Extension\Error\ThrowableHandlerInterface;
use Override;
use Qiq\Helpers;
use Ray\Di\AbstractModule;
use Ray\Di\Scope;

use function dirname;

final class HtmlModule extends AbstractModule
{
    #[Override]
    protected function configure(): void
    {
        $this->install(new QiqModule(dirname(__DIR__, 2) . '/templates'));
        // Replaces QiqModule's HtmlHelpers so templates can call csrfField().
        $this->bind(Helpers::class)->to(CmsHelpers::class);
        $this->bind(RenderInterface::class)->to(CmsQiqRenderer::class)->in(Scope::SINGLETON);
        $this->bind(ThrowableHandlerInterface::class)->to(HtmlThrowableHandler::class);
    }
}
