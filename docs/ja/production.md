# Production / Security 運用参照

[English](../production.md)

この文書は、この CMS の production-shaped な部分を動かすための運用参照です。
意図的に opt-in にしています。default の PHPUnit と `composer tests` は、DB
server、Redis、OAuth credentials、DAST target、外部 security service を必要としません。

## Production Module

`src/Module/ProdModule.php` は BEAR.Package の production module を install し、
project 固有の production switch だけを追加します。

```dotenv
CMS_REDIS_DSN=redis://127.0.0.1:6379/0
```

`CMS_REDIS_DSN` が空なら package default の QueryRepository storage を使います。
値がある場合だけ `StorageRedisDsnModule` を install し、distributed cache storage
として Redis を使います。

## Compile Artifacts

production compile は次で実行します。

```bash
composer compile
```

compile は `BEAR\Kata` の `prod-app` context を対象にします。生成物は runtime
build products で、手で編集する source file ではありません。

- `autoload.php` — compiled autoloader entry。
- `preload.php` — opcache preload target。
- `.compile.php` — compiled dependency graph bootstrap。
- `module.dot` — Graphviz で見る module graph。

production binding と fake/test の挙動が違う時は、module graph を確認します。

```bash
dot -Tsvg module.dot > module.svg
```

module binding を変更して local で確認する場合は、DI cache を消してから再実行します。

```bash
find var/tmp -maxdepth 1 \( -name '*-hal-app' -o -name '*-hal-api-app' \) -exec rm -rf {} +
```

## Deployment Boundary

この reference project が示すのは production composition boundary です。hosting
platform 全体ではありません。実 deployment では次を別途決めます。

- process manager / server runtime。
- TLS と reverse proxy。
- database credentials と migration timing。
- Redis availability と eviction policy。
- log shipping と alerting。
- OAuth credentials と session/cookie key の secret storage。

これらは default tests へ入れません。service がある時だけ、environment-gated smoke
check として追加します。

## Security Commands

SAST は local かつ credential-free です。

```bash
composer security:sast
```

`composer security` は同じ SAST check の短い alias として残しています。

Psalm taint analysis も local で動きますが、通常の developer loop ではなく security
workflow なので default quality gate からは分けています。

```bash
composer security:taint
```

GitHub Actions の security workflow は manual-dispatch で、`src` に
同じ taint analysis を実行します。

## Opt-In Security Workflows

DAST と AI Auditor は default gate に入れません。running target、credentials、
secrets、外部 service が必要になるため、この reference では deployment project 側の
責務として扱います。

追加する場合は manual workflow か environment-gated workflow とし、次を明記します。

- target URL と seed data。
- credentials と secret storage。
- false-positive review process。
- どの failure が release blocker になるか。

## Current Quality Gate

implementation change を出す前は次を使います。

```bash
composer doc
composer tests
git diff --check
```

`composer tests` は coding standards、Psalm、PHPStan、PHPMD、PHPUnit を実行します。
`composer doc` は ApiDoc、OpenAPI、ALPS HTML/SVG、`llms.txt`、term usage index、
documentation audit report を再生成します。
