<?php

declare(strict_types=1);

namespace BEAR\Kata\Renderer;

use BEAR\Kata\Injector;
use PHPUnit\Framework\TestCase;
use Qiq\Helpers;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function assert;
use function dirname;
use function file_get_contents;
use function preg_match_all;
use function substr_count;

final class CmsHelpersTest extends TestCase
{
    public function testCsrfFieldRendersTheBoundHiddenInput(): void
    {
        $helpers = Injector::getInstance('html-test-hal-api-app')->getInstance(Helpers::class);

        $this->assertInstanceOf(CmsHelpers::class, $helpers);
        $this->assertSame('<input type="hidden" name="_csrf_token" value="fake-csrf-token">', $helpers->csrfField());
    }

    /** A hand-written input would drift from the bound field name; every POST form goes through the helper. */
    public function testEveryPostFormRendersTheFieldThroughTheHelper(): void
    {
        $templates = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/templates'));
        $checked = 0;
        foreach ($templates as $file) {
            assert($file instanceof SplFileInfo);
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());
            $this->assertStringNotContainsString('$csrfToken', $source, $file->getPathname());
            $forms = preg_match_all('/<form\b[^>]*\bmethod="post"/i', $source);
            $this->assertSame($forms, substr_count($source, '{{= csrfField() }}'), $file->getPathname());
            $checked++;
        }

        $this->assertGreaterThan(0, $checked);
    }
}
