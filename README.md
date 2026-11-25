# Laravel API Starter Kit

[![Tests](https://github.com/RasmusPJustesen/laravel-api-starter-kit/actions/workflows/tests.yml/badge.svg)](https://github.com/RasmusPJustesen/laravel-api-starter-kit/actions/workflows/tests.yml)

A headless, API-first Laravel 13 starter kit. Use Laravel's own conventions, Sanctum, Fortify's TOTP provider and native JSON:API resources without an application frontend. Inspired by the Laravel Maestro API and teams proposals ([#78](https://github.com/laravel/maestro/pull/78), [#80](https://github.com/laravel/maestro/pull/80)).

**Beta preview.** See [v0.1.0-beta.1](https://github.com/RasmusPJustesen/laravel-api-starter-kit/releases/tag/v0.1.0-beta.1). This prerelease has not undergone an independent security audit and is not a stable production-ready release.

## Features

- Stateful SPA cookie authentication, stateless Sanctum tokens, or both.
- Registration, password reset, email verification, password confirmation, account/profile management and optional or required TOTP with recovery codes.
- Device sessions across browser and native clients, individually revocable persistent browser logins, token rotation and optional security notifications.
- Multiple configured frontend clients, including frontend-aware password resets, signed email verification links and invitation links.
- Opt-in teams with customizable singular/plural terminology, optional personal teams, invitations, memberships, roles, permissions and ownership transfers.
- Scalar API documentation, OpenAPI export, generated TypeScript types, stable error codes and request locale negotiation.

## Requirements

PHP 8.3+, Composer 2, a supported Laravel database (SQLite, MySQL or PostgreSQL). Node.js 22+ is needed for TypeScript generation and HTTP contract tests, not for serving the API.

## Create an application

Until the package is published on Packagist, clone the repository:

```sh
git clone --branch v0.1.0-beta.1 --single-branch https://github.com/RasmusPJustesen/laravel-api-starter-kit.git my-api
cd my-api
composer setup
php artisan serve
```

The setup command creates the environment file and key if needed, runs the starter-kit installer and migrates the configured database. For a new independent Git repository, remove the cloned Git history and initialize your own repository; preserve the upstream license and credits.

After registration on Packagist, install this explicit prerelease with Composer:

```sh
composer create-project rasmuspjustesen/laravel-api-starter-kit my-api 0.1.0-beta.1
```

The Laravel installer integration is also supported; use a version constraint that permits the beta until a stable version is published.

The Laravel installer invokes the project's post-create hook. Direct Composer installation invokes the equivalent setup script. Non-interactive installation defaults to both authentication methods and no teams.

### Installer options

Run the installer before the initial migrations to choose authentication and optionally scaffold the team module:

```sh
php artisan starter-kit:install --teams --team-name=organization --personal-teams --auth=both --no-interaction
```

Authentication modes: `both`, `stateful`, `stateless`. Use `--no-teams` to omit the module. For a nonstandard plural, add `--team-plural=organizations`. The default singular name is `team`. Identifiers, generated code, migrations, routes, relationships, tests and permission enums use the selected terminology. Choose the name before installation; renaming a live module is not supported.

Configure multiple clients interactively or by repeating `--frontend`:

```sh
php artisan starter-kit:install --no-teams --auth=both \
  --frontend='{"id":"web","url":"https://app.example.com","authentication":"stateful","origin":"https://app.example.com"}' \
  --frontend='{"id":"mobile","url":"https://links.example.com","authentication":"stateless"}' \
  --no-interaction
```

When using manual installation, first run `composer install`, copy `.env.example` to `.env`, generate the key and create the SQLite database if applicable. After configuration, run `php artisan migrate`. Do not rerun the one-time installer after `composer setup` or `create-project`.

### Add teams to an existing application

Start with a clean Git working tree and back up the database. The separate command scaffolds the module into an already configured base application and refuses to overwrite conflicting files.

```sh
php artisan starter-kit:teams --team-name=workspace --personal-teams --no-interaction
composer dump-autoload
php artisan optimize:clear
php artisan migrate
```

If personal teams are requested, the migration provisions one for existing users. Review and commit generated files to your application repository. Generated applications use their own ordinary CI workflow rather than the starter-kit's variant matrix.

## API documentation

Visit `/docs` locally for Scalar and `/docs/api.json` for OpenAPI. Docs access is restricted by default outside local/testing environments. Scramble infers resource attributes from the database schema: run migrations before exporting.

```sh
npm install
composer api:generate
```

Copy `generated/openapi.json` and `generated/api.d.ts` into your Nuxt, Capacitor or other consuming application. The kit provides contracts, not a frontend SDK or UI. See [frontend integration](docs/frontend-integration.md) for examples.

## Configure and deploy

See [configuration and deployment](docs/configuration.md), [authentication and teams](docs/api.md), [security reporting](SECURITY.md), and the [release checklist](docs/releasing.md).

```sh
composer lint:check
composer types:check
composer test
composer audit
```

The starter-kit CI exercises PHP 8.3, 8.4 and 8.5; multiple generated entity names; SQLite, MySQL and PostgreSQL; authentication modes; real HTTP cookie and token flows; OpenAPI export, TypeScript compilation and live response schema validation. Check the current workflow results before depending on a particular commit. Automated tests are not a substitute for independent security review.

## Credits

Adapted from Laravel Maestro, including contributions by Wendell Adriel, Joe Tannenbaum and the Laravel community. See [CREDITS.md](CREDITS.md) and [LICENSE](LICENSE). This is an independent community project, not an official Laravel starter kit.
