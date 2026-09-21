# Production and Security Operations

[日本語](ja/production.md)

This is an operating reference for the production-shaped parts of this CMS.
It is intentionally opt-in: the default PHPUnit and `composer tests` path still
needs no database server, Redis server, OAuth credentials, DAST target, or
external security service.

## Production Module

`src/Module/ProdModule.php` installs BEAR.Package's production module and then
applies the only project-specific production switch:

```dotenv
CMS_REDIS_DSN=redis://127.0.0.1:6379/0
```

When `CMS_REDIS_DSN` is empty, the project keeps the package default
QueryRepository storage. When it is set, `StorageRedisDsnModule` is installed
for distributed cache storage.

## Compile Artifacts

Run production compilation with:

```bash
composer compile
```

The compile step targets `BEAR\Kata` in the `prod-app` context. The generated
artifacts are runtime build products, not source files to edit by hand:

- `autoload.php` — compiled autoloader entry.
- `preload.php` — opcache preload target.
- `.compile.php` — compiled dependency graph bootstrap.
- `module.dot` — module graph for Graphviz inspection.

Use the module graph when a production binding differs from fake/test behavior:

```bash
dot -Tsvg module.dot > module.svg
```

If a module binding changes while testing locally, clear compiled DI caches
before re-running:

```bash
find var/tmp -maxdepth 1 \( -name '*-hal-app' -o -name '*-hal-api-app' \) -exec rm -rf {} +
```

## Deployment Boundary

The reference project demonstrates the production composition boundary, not a
complete hosting platform. A real deployment still owns:

- process manager or server runtime choice;
- TLS and reverse proxy configuration;
- database credentials and migration timing;
- Redis availability and eviction policy;
- log shipping and alerting;
- secret storage for OAuth credentials and session/cookie keys.

Keep these outside default tests. Add environment-gated smoke checks only when
the corresponding service is present.

## Security Commands

SAST is local and credential-free:

```bash
composer security:sast
```

`composer security` is kept as the short alias for the same SAST check.

Psalm taint analysis is also local, but it is separated from the default
quality gate because it is a security workflow rather than a normal developer
loop:

```bash
composer security:taint
```

The GitHub Actions security workflow is manual-dispatch and runs the same taint
analysis against `src`.

## Opt-In Security Workflows

DAST and AI Auditor are not part of the default gate. They require a running
target, credentials, secrets, or external services, so this reference treats
them as deployment-project concerns.

Add them only as manual or environment-gated workflows, and document:

- target URL and seed data;
- credentials and secret storage;
- false-positive review process;
- what failure should block a release.

## Current Quality Gate

Use this before publishing implementation changes:

```bash
composer doc
composer tests
git diff --check
```

`composer tests` runs coding standards, Psalm, PHPStan, PHPMD, and PHPUnit.
`composer doc` regenerates ApiDoc, OpenAPI, ALPS HTML/SVG, `llms.txt`, the term
usage index, and the documentation audit report.
