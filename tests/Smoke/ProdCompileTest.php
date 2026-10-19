<?php

declare(strict_types=1);

namespace BEAR\Kata\Smoke;

use BEAR\AppMeta\Meta;
use BEAR\Package\Module;
use BEAR\Package\Module\ResourceObjectModule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Ray\Compiler\Compiler;

use function dirname;
use function glob;
use function putenv;
use function sys_get_temp_dir;
use function uniqid;

/**
 * Ray.Compiler resolves explicit bindings only. A constructor argument that the
 * dev injector JIT-binds in every other context is Unbound here, and nothing
 * else in the suite builds a prod injector.
 */
final class ProdCompileTest extends TestCase
{
    #[DataProvider('prodContextProvider')]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testProdContextCompiles(string $context): void
    {
        putenv('CMS_ALLOWED_ORIGIN=https://cms.example.com');
        $meta = new Meta('BEAR\Kata', $context, dirname(__DIR__, 2));
        $module = (new Module())($meta, $context);
        $module->install(new ResourceObjectModule($meta->getResourceListGenerator()));
        $scriptDir = sys_get_temp_dir() . '/' . uniqid('kata-' . $context . '-', true);

        (new Compiler())->compile($module, $scriptDir);

        $this->assertNotEmpty(glob($scriptDir . '/*.php'));
    }

    /** @return iterable<string, array{string}> */
    public static function prodContextProvider(): iterable
    {
        yield 'prod-hal-app' => ['prod-hal-app'];
        yield 'prod-app' => ['prod-app'];
    }
}
