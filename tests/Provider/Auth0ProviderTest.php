<?php

declare(strict_types=1);

namespace BEAR\Kata\Provider;

use Auth0\SDK\Configuration\SdkConfiguration;
use Auth0\SDK\Store\CookieStore;
use BEAR\Kata\Auth\RequestScheme;
use Override;
use PHPUnit\Framework\TestCase;

use function array_key_exists;
use function getenv;
use function putenv;
use function sprintf;

final class Auth0ProviderTest extends TestCase
{
    private const array ENV = [
        'AUTH0_DOMAIN' => 'example.auth0.com',
        'AUTH0_CLIENT_ID' => 'client-id',
        'AUTH0_CLIENT_SECRET' => 'client-secret',
        'AUTH0_REDIRECT_URI' => 'https://cms.example.com/admin/callback',
        'AUTH0_COOKIE_SECRET' => '0123456789abcdef0123456789abcdef',
        'AUTH0_AUDIENCE' => '',
        'CMS_ALLOWED_ORIGIN' => '',
    ];

    /** @var array<string, string|false> */
    private array $previousEnv = [];
    private bool $hadHttps = false;
    private mixed $previousHttps = null;

    #[Override]
    protected function setUp(): void
    {
        foreach (self::ENV as $name => $value) {
            $this->previousEnv[$name] = getenv($name);
            putenv(sprintf('%s=%s', $name, $value));
        }

        $this->hadHttps = array_key_exists('HTTPS', $_SERVER);
        $this->previousHttps = $_SERVER['HTTPS'] ?? null;
        unset($_SERVER['HTTPS']);
    }

    #[Override]
    protected function tearDown(): void
    {
        foreach ($this->previousEnv as $name => $value) {
            putenv($value === false ? $name : sprintf('%s=%s', $name, $value));
        }

        $this->previousEnv = [];
        unset($_SERVER['HTTPS']);
        if (! $this->hadHttps) {
            return;
        }

        $_SERVER['HTTPS'] = $this->previousHttps;
    }

    public function testHttpsOriginMarksSdkCookiesSecureBehindTlsTerminatingProxy(): void
    {
        putenv('CMS_ALLOWED_ORIGIN=https://cms.example.com');

        $config = $this->configuration();

        $this->assertTrue($config->getCookieSecure());
        foreach ([$config->getSessionStorage(), $config->getTransientStorage()] as $store) {
            $this->assertInstanceOf(CookieStore::class, $store);
            $this->assertTrue($store->getCookieOptions()['secure']);
        }
    }

    public function testHttpsRequestMarksSdkCookiesSecureDespitePlainOrigin(): void
    {
        putenv('CMS_ALLOWED_ORIGIN=http://localhost:8081');
        $_SERVER['HTTPS'] = 'on';

        $this->assertTrue($this->configuration()->getCookieSecure());
    }

    public function testOffHttpsWithoutOriginLeavesSdkCookiesPlain(): void
    {
        $_SERVER['HTTPS'] = 'OFF';

        $this->assertFalse($this->configuration()->getCookieSecure());
    }

    public function testNonStringHttpsWithoutOriginLeavesSdkCookiesPlain(): void
    {
        $_SERVER['HTTPS'] = 1;

        $this->assertFalse($this->configuration()->getCookieSecure());
    }

    private function configuration(): SdkConfiguration
    {
        return (new Auth0Provider(new RequestScheme()))->get()->configuration();
    }
}
