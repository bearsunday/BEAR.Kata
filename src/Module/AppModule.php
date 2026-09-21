<?php

declare(strict_types=1);

namespace BEAR\Kata\Module;

use Auth0\SDK\Contract\Auth0Interface;
use BEAR\Kata\Auth\AdminGuard;
use BEAR\Kata\Auth\AdminUserInterface;
use BEAR\Kata\Auth\Auth0AuthProvider;
use BEAR\Kata\Auth\AuthInterface;
use BEAR\Kata\Auth\AuthorIdentityResolver;
use BEAR\Kata\Auth\AuthSessionInterface;
use BEAR\Kata\Auth\GoogleAuthProvider;
use BEAR\Kata\Auth\NativeAuthSession;
use BEAR\Kata\Auth\UserInterface;
use BEAR\Kata\Exception\InvalidAuthProviderException;
use BEAR\Kata\Factory\ArticleFactory;
use BEAR\Kata\Provider\AdminUserProvider;
use BEAR\Kata\Provider\Auth0Provider;
use BEAR\Kata\Provider\CommonMarkConverterProvider;
use BEAR\Kata\Provider\CurrentUserProvider;
use BEAR\Kata\Provider\GoogleProvider;
use BEAR\Kata\Service\CommonMarkRenderer;
use BEAR\Kata\Service\MarkdownRendererInterface;
use BEAR\Kata\Service\SqlDateTime;
use BEAR\Kata\Validation\JsonSchemaRequestExceptionHandler;
use BEAR\Package\AbstractAppModule;
use BEAR\Package\PackageModule;
use BEAR\Resource\JsonSchemaRequestExceptionHandlerInterface;
use BEAR\Resource\Module\JsonSchemaModule;
use Koriym\EnvJson\EnvJson;
use League\CommonMark\CommonMarkConverter;
use League\OAuth2\Client\Provider\Google;
use Ray\AuraSqlModule\AuraSqlModule;
use Ray\Csrf\CsrfModule;
use Ray\Di\Scope;
use Ray\MediaQuery\MediaQuerySqlModule;

use function dirname;
use function getenv;
use function in_array;
use function strtolower;
use function trim;

/**
 * Production / CLI bindings: real DB via AuraSqlModule + Ray.MediaQuery,
 * JSON Schema validation, and Google OAuth.
 *
 * Loaded by contexts `hal-api-app` and `cli-hal-api-app`. The fake/test
 * contexts (`fake-hal-api-app`, `test-hal-api-app`) install this and then
 * override individual bindings via FakeModule / TestModule.
 *
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects") composition root by design
 */
final class AppModule extends AbstractAppModule
{
    protected function configure(): void
    {
        (new EnvJson())->load(dirname(__DIR__, 2));

        $this->install(new PackageModule());
        $this->install(new AppErrorModule());

        $dsn = (string) (getenv('DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=bear_cms;charset=utf8mb4');
        $user = (string) (getenv('DB_USER') ?: 'root');
        $password = (string) getenv('DB_PASSWORD');
        $this->install(new AuraSqlModule($dsn, $user, $password));

        // Read (QueryInterface) and Write (CommandInterface) live side-by-side
        // in src/Query so MediaQuerySqlModule scans a single directory.
        $this->install(new MediaQuerySqlModule(
            $this->appMeta->appDir . '/src/Query',
            $this->appMeta->appDir . '/var/db/sql',
        ));

        // Validate response bodies (and optionally request params) against JSON Schemas.
        $this->install(new JsonSchemaModule(
            $this->appMeta->appDir . '/var/json_schema',
            $this->appMeta->appDir . '/var/json_validate',
        ));
        // Replace the null request handler with one that groups BEAR.Resource's
        // structured request-schema errors. See src/Validation/JsonSchemaRequestExceptionHandler.
        $this->bind(JsonSchemaRequestExceptionHandlerInterface::class)
            ->to(JsonSchemaRequestExceptionHandler::class)
            ->in(Scope::SINGLETON);

        // Domain-layer services (e.g. injected into Entity via FetchInjectionFactory).
        // toProvider, not toInstance: CommonMarkConverter wires Closures internally
        // and would fail Ray.Compiler's serialise step in prod contexts.
        $this->bind(CommonMarkConverter::class)->toProvider(CommonMarkConverterProvider::class)->in(Scope::SINGLETON);
        $this->bind(MarkdownRendererInterface::class)->to(CommonMarkRenderer::class)->in(Scope::SINGLETON);
        // Explicit untargeted binding so Ray.Compiler (prod-app) can resolve the
        // factory referenced by #[DbQuery(factory: ArticleFactory::class)].
        $this->bind(ArticleFactory::class)->in(Scope::SINGLETON);
        $this->bind(SqlDateTime::class)->in(Scope::SINGLETON);

        // Authentication: Google OAuth by default; Auth0/OIDC is selected with CMS_AUTH_PROVIDER=auth0.
        $this->bind(Google::class)->toProvider(GoogleProvider::class)->in(Scope::SINGLETON);
        $this->bind(Auth0Interface::class)->toProvider(Auth0Provider::class)->in(Scope::SINGLETON);
        $authProvider = strtolower(trim((string) getenv('CMS_AUTH_PROVIDER')));
        if (! in_array($authProvider, ['', 'google', 'auth0'], true)) {
            throw new InvalidAuthProviderException($authProvider);
        }

        $this->bind(AuthInterface::class)->to(
            $authProvider === 'auth0' ? Auth0AuthProvider::class : GoogleAuthProvider::class,
        )->in(Scope::SINGLETON);
        $this->bind(AuthSessionInterface::class)->to(NativeAuthSession::class)->in(Scope::SINGLETON);
        $this->bind(AuthorIdentityResolver::class);
        $this->bind(UserInterface::class)->toProvider(CurrentUserProvider::class);
        $this->bind(AdminUserInterface::class)->toProvider(AdminUserProvider::class);
        $this->bind(AdminGuard::class);

        // Cross-origin defence for unsafe Page/Admin verbs: the synchroniser-token gate
        // (#[CsrfToken]) is always on; the same-origin gate (#[SameOrigin]) needs an origin to
        // compare against, which only an HTTP deployment has. This branch still derives that
        // from an env var, so an unset CMS_ALLOWED_ORIGIN runs without the origin gate here —
        // what makes that impossible in production is ProdModule, which refuses to boot
        // without the value. The named constructors make the choice visible; they do not make
        // it for the composition root.
        $allowedOrigin = (string) getenv('CMS_ALLOWED_ORIGIN') ?: null;
        $this->install($allowedOrigin === null
            ? CsrfModule::withoutSameOriginCheck()
            : CsrfModule::withSameOriginCheck($allowedOrigin));
    }
}
