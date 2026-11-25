# Laravel API Starter Kit

A headless Laravel 13 project template inspired by [Maestro #78](https://github.com/laravel/maestro/pull/78) and [#80](https://github.com/laravel/maestro/pull/80).

**Development preview — not production-ready.** The implementation is being validated; no stable version is published.

## Local installation

Requires PHP compatible with Composer dependencies (PHP 8.4 recommended), Composer, and Node.js 22 for type generation.

```sh
git clone https://github.com/RasmusPJustesen/laravel-api-starter-kit.git my-api
cd my-api
composer install
cp .env.example .env
php artisan key:generate
php artisan starter-kit:install
touch database/database.sqlite
php artisan migrate
php artisan serve
```

For a new independent project, remove the cloned Git history and initialize a new repository. Do not remove upstream license notices.

### Optional teams

```sh
php artisan starter-kit:install --teams --team-name=organization --personal-teams --auth=both --no-interaction
```

Use `--no-teams` to omit the module. Run the installer only once, before migrations, and commit generated source to the application repository. The custom name is permanent. Later installation and migration of existing users are not supported yet.

### Authentication

- Bearer: POST /login, POST /register, POST /two-factor-challenge.
- SPA: GET /sanctum/csrf-cookie, POST /spa/login, POST /spa/register, POST /spa/two-factor-challenge. Use credentials and the X-XSRF-TOKEN header.
- Shared: GET /me, POST /logout, POST /forgot-password, POST /reset-password.
- Confirm password via POST /user/confirm-password before sensitive operations.
- Manage devices via GET /user/sessions and DELETE /user/sessions/{session} or /user/sessions/others.
- Enable TOTP via POST /user/two-factor-authentication, fetch the setup key and otpauth URL from GET /user/two-factor-secret-key, confirm via POST /user/confirmed-two-factor-authentication.

Configure trusted first-party origins and SANCTUM_STATEFUL_DOMAINS, SESSION_DOMAIN, secure cookies and HTTPS for production. See config/frontends.php and config/cors.php. X-Client is an untrusted client hint, not authorization.

### API documentation and types

Visit /docs locally. OpenAPI is available at /docs/api.json. Scramble infers JSON:API resource attributes from the database schema, so create the configured database and run migrations before exporting. Generate a contract with:

```sh
npm install
composer api:generate
```

Copy generated/openapi.json and generated/api.d.ts into consuming frontend repositories. No application frontend is included.

### Deployment

Use a database-backed session driver, a shared cache, HTTPS, a queue worker and Laravel scheduler. Configure trusted proxies only for your infrastructure. Restrict documentation access, disable APP_DEBUG and configure mail. GET / is a liveness check; GET /ready checks the database connection.

### Verification status

CI covers base and named-team installations, tests, route/config caching and type generation. Results must be reviewed before relying on the kit. Multi-device remember-me revocation, concurrency, generated schemas and custom naming require security and integration review. Browser device renaming, complete OpenAPI error schema typing and Packagist publication remain incomplete.

## Credits

Adapted from Laravel Maestro, including contributions by Wendell Adriel, Joe Tannenbaum and the Laravel community. See CREDITS.md and LICENSE. This is an independent project, not an official Laravel starter kit.
