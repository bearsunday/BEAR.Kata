<?php

declare(strict_types=1);

namespace BEAR\Kata\Provider;

use Auth0\SDK\Auth0;
use Auth0\SDK\Configuration\SdkConfiguration;
use Auth0\SDK\Contract\Auth0Interface;
use BEAR\Kata\Auth\RequestScheme;
use BEAR\Kata\Exception\MissingAuth0ConfigurationException;
use Ray\Di\ProviderInterface;

use function getenv;

/**
 * @implements ProviderInterface<Auth0Interface>
 * @SuppressWarnings("PHPMD.Superglobals") The request is not in the DI graph; the provider reads it at the boundary.
 */
final class Auth0Provider implements ProviderInterface
{
    public function __construct(private readonly RequestScheme $scheme)
    {
    }

    public function get(): Auth0Interface
    {
        $config = $this->requiredEnv();
        $audience = $this->env('AUTH0_AUDIENCE');

        return new Auth0(new SdkConfiguration(
            domain: $config['domain'],
            clientId: $config['clientId'],
            clientSecret: $config['clientSecret'],
            redirectUri: $config['redirectUri'],
            audience: $audience === null ? null : [$audience],
            scope: ['openid', 'email', 'profile'],
            cookieSecret: $config['cookieSecret'],
            cookieSecure: $this->scheme->isHttps($_SERVER, $this->env('CMS_ALLOWED_ORIGIN')),
            queryUserInfo: true,
        ));
    }

    /** @return array{domain: string, clientId: string, clientSecret: string, redirectUri: string, cookieSecret: string} */
    private function requiredEnv(): array
    {
        $domain = $this->env('AUTH0_DOMAIN');
        $clientId = $this->env('AUTH0_CLIENT_ID');
        $clientSecret = $this->env('AUTH0_CLIENT_SECRET');
        $redirectUri = $this->env('AUTH0_REDIRECT_URI');
        $cookieSecret = $this->env('AUTH0_COOKIE_SECRET');

        if (
            $domain === null ||
            $clientId === null ||
            $clientSecret === null ||
            $redirectUri === null ||
            $cookieSecret === null
        ) {
            throw new MissingAuth0ConfigurationException($this->missingEnv([
                'AUTH0_DOMAIN' => $domain,
                'AUTH0_CLIENT_ID' => $clientId,
                'AUTH0_CLIENT_SECRET' => $clientSecret,
                'AUTH0_REDIRECT_URI' => $redirectUri,
                'AUTH0_COOKIE_SECRET' => $cookieSecret,
            ]));
        }

        return [
            'domain' => $domain,
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
            'redirectUri' => $redirectUri,
            'cookieSecret' => $cookieSecret,
        ];
    }

    /**
     * @param array<string, string|null> $values
     *
     * @return list<string>
     */
    private function missingEnv(array $values): array
    {
        $missing = [];
        foreach ($values as $name => $value) {
            if ($value !== null) {
                continue;
            }

            $missing[] = $name;
        }

        return $missing;
    }

    private function env(string $name): string|null
    {
        $value = getenv($name);

        return $value === false || $value === '' ? null : (string) $value;
    }
}
