<?php

declare(strict_types=1);

namespace BEAR\Kata\Renderer;

use BEAR\Csrf\CsrfTokenInterface;
use BEAR\Csrf\Http\CsrfTokenField;
use BEAR\Kata\Auth\AuthSessionInterface;
use BEAR\Kata\Auth\UserInterface;
use BEAR\Kata\Renderer\Exception\InvalidResourcePathException;
use BEAR\Resource\AbstractRequest;
use BEAR\Resource\RenderInterface;
use BEAR\Resource\ResourceObject;
use ErrorException;
use Override;
use Qiq\Template;
use Ray\Aop\WeavedInterface;
use ReflectionClass;
use Throwable;

use function array_key_exists;
use function error_reporting;
use function http_build_query;
use function in_array;
use function is_array;
use function restore_error_handler;
use function set_error_handler;
use function str_replace;
use function strpos;
use function substr;

final readonly class CmsQiqRenderer implements RenderInterface
{
    private const int DEFAULT_CSS_LEVEL = 3;
    private const array CSS_LEVELS = [1, 2, 3];
    private const int RESOURCE_DIR_LEN = 13;

    public function __construct(
        private Template $template,
        private AuthSessionInterface $session,
        private CsrfTokenInterface $csrf,
        private CsrfTokenField $csrfTokenField,
    ) {
    }

    #[Override]
    public function render(ResourceObject $ro): string
    {
        if (! array_key_exists('Content-Type', $ro->headers)) {
            $ro->headers['Content-Type'] = 'text/html; charset=utf-8';
        }

        if ($ro->code >= 300 && $ro->code < 400 && array_key_exists('Location', $ro->headers)) {
            $ro->view = '';

            return '';
        }

        $vars = is_array($ro->body) ? $ro->body : ['value' => $ro->body];
        // Resolve embedded child resources (lazy Requests) to their rendered
        // string BEFORE the page template runs. Rendering a child mid-template
        // shares Qiq's block state and would clobber the parent's setBlock()
        // (e.g. the page header). Pre-rendering keeps each resource's blocks
        // isolated, and each child is drawn with its own App template.
        foreach ($vars as $key => $value) {
            if (! ($value instanceof AbstractRequest)) {
                continue;
            }

            $vars[$key] = (string) $value;
        }

        $vars += $this->commonVars($ro);
        if ($ro->code >= 500) {
            return $this->renderError($ro);
        }

        try {
            return $this->renderTemplate($ro, $vars);
        } catch (Throwable) {
            if ($ro->code >= 400) {
                return $this->renderError($ro);
            }

            $ro->code = 500;

            return $this->renderError($ro);
        }
    }

    /** @param array<string, mixed> $vars */
    private function renderTemplate(ResourceObject $ro, array $vars): string
    {
        set_error_handler($this->errorToException(...));

        try {
            $template = clone $this->template;
            $template->setData($vars);
            $template->setView($this->templateName($ro));
            $ro->view = ($template)();

            return $ro->view;
        } finally {
            restore_error_handler();
        }
    }

    private function errorToException(int $severity, string $message, string $file, int $line): bool
    {
        if ((error_reporting() & $severity) === 0) {
            return false;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    private function templateName(ResourceObject $ro): string
    {
        $reflection = $ro instanceof WeavedInterface
            ? (new ReflectionClass($ro))->getParentClass()
            : new ReflectionClass($ro);
        $fileName = str_replace('\\', '/', (string) $reflection->getFileName());
        $pos = strpos($fileName, 'src/Resource/');
        if ($pos === false) {
            throw new InvalidResourcePathException($fileName);
        }

        $relativePath = substr($fileName, $pos + self::RESOURCE_DIR_LEN);

        return str_replace('.php', '', $relativePath);
    }

    private function renderError(ResourceObject $ro): string
    {
        $ro->view = $this->template->render('Error', [
            'code' => $ro->code,
        ]);

        return $ro->view;
    }

    /** @return array{cssLevel: int, cssLinks: array<int, string>, user: UserInterface, csrfToken: string, csrfTokenField: string} */
    private function commonVars(ResourceObject $ro): array
    {
        $query = $ro->uri->query;
        $requested = isset($query['css']) ? (int) $query['css'] : self::DEFAULT_CSS_LEVEL;
        $level = in_array($requested, self::CSS_LEVELS, true) ? $requested : self::DEFAULT_CSS_LEVEL;

        $base = $query;
        unset($base['css']);
        $path = $ro->uri->path;
        if ($path === '' || $path === '/index') {
            $path = '/';
        }

        $links = [];
        foreach (self::CSS_LEVELS as $n) {
            $merged = $base + ['css' => $n];
            $links[$n] = $path . '?' . http_build_query($merged);
        }

        // `csrfToken` is exposed to every template so layout-level forms
        // (e.g. the sign-out form in the layout's nav) can embed the
        // hidden field without each Page resource having to inject it
        // into $this->body.
        return [
            'cssLevel' => $level,
            'cssLinks' => $links,
            'user' => $this->session->currentUser(),
            'csrfToken' => $this->csrf->issue(),
            'csrfTokenField' => $this->csrfTokenField->name,
        ];
    }
}
