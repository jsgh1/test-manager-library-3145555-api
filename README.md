# Manager Library API — Laravel 12 / PostgreSQL 16

Business contract: [adapted English specification](https://github.com/jsgh1/test-manager-library-3145555-docs/tree/main/spec-react-laravel), retaining the reference's 10 user stories and API protocol.

## Bootstrap

Requires PHP 8.3+, Composer, PostgreSQL 16 (`pdo_pgsql`), and a configured environment. Copy `.env.example` to `.env`; replace secret placeholders and never commit real credentials. Run `composer install`, `php artisan migrate --force`, `php artisan library:bootstrap-admin`, then `php artisan serve --host=0.0.0.0 --port=8000`. The infra repository supplies Docker-based launch.

Health: `GET /health` and `GET /api/v1/health` both return `{"status":"ok"}`.

The API owns the PostgreSQL `library` schema and its **six** tables (singular names, UUID application-assigned PKs, no default columns, fixed category seed). Framework's `migrations` bookkeeping remains in the `public` schema. The reference's T-18 constraints are introduced in a separate migration.

## Evidence

- `composer test` runs PHP domain tests (requires dependencies installed).
- Inspect PostgreSQL with the three exact SQL verification queries in `spec-python/data-model.md` §10.
- The initial admin is provisioned with environment credentials, with an Argon2id hash; no admin account creation endpoint is exposed.
- The full HTTP implementation and integration/concurrency acceptance suites are tracked separately; do not infer they passed from this scaffold alone.
