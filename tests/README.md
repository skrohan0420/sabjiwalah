# Sabjiwalah tests

Install locked dependencies with `composer install`. PHP 8.2+ and the application's
intl/mbstring extensions are required. `composer test -- --no-coverage` invokes
the project PHPUnit runner, not the old XAMPP/PEAR global PHPUnit. The default
suite includes `tests/unit` and `tests/session` and does not require a database.
It has 24 tests/32 assertions, including hostile phone/link/upload inputs and
private-response cache boundaries. Xdebug is needed only for coverage.

The preserved starter database examples are opt-in, separate from application
MySQL regressions. On XAMPP with the SQLite module available, run:

```bash
php -d extension=sqlite3 vendor/phpunit/phpunit/phpunit tests/database --no-coverage
```

If SQLite is already enabled, omit `-d extension=sqlite3`. These two scaffold
tests use `database.tests` (SQLite memory by default); never point the tests group
at a production database. Generated reports/cache stay in ignored `build/`.

The frontend suite runs with Node's built-in test runner. In PowerShell, expand
file paths because Node does not expand the shell wildcard:

```powershell
$testFiles = @(Get-ChildItem tests/frontend -Filter '*.test.cjs' | ForEach-Object { $_.FullName })
node --test @testFiles
```

There are 79 frontend checks for safe rendering, authorization failures, polling,
stale responses, drafts/conflicts, uncertain writes, checkout, OTP overlap and
customer navigation/location/theme behavior.

Focused deployment checks under `tests/deployment` are standalone PHP/Node
runners. Application MySQL checks require a migrated local MySQL database;
they shadow business tables with connection-local temporary copies. HTTP checks
also use isolated session/upload storage and owned loopback servers. Set
`PHP_BINARY` when PHP is not at `D:/xampp/php/php.exe`. Files named `*-api.cjs`
instead accept an existing deployment URL; consult their header before running.

Start with:

```bash
php tests/deployment/otp-security.php
php tests/deployment/otp-security.php --production
node tests/deployment/otp-security-session.cjs
node tests/deployment/otp-security-session.cjs --production
node tests/deployment/admin-security-session.cjs
php tests/deployment/admin-performance.php
node tests/deployment/concurrent-admin.cjs
node tests/deployment/admin-products-upload.cjs
node tests/deployment/apache-isolated.cjs
```

`admin-security-session.cjs` tests all 35 admin API routes across guest/customer/
rider sessions, plus page filters, logout CSRF, multipart roles and order IDOR.
Its `--preview` mode skips the automated matrix and opens disposable sample
customer data on port 8775; send `stop` or Ctrl+C to close it.

`concurrent-admin.cjs` requires local CREATE/DROP DATABASE privileges. It creates
an owned random `sw_hardening_*` database containing structure and synthetic data
only, races separate PHP/MySQL connections, and cleans it up. The ownership marker
prevents dropping a pre-existing database. On abrupt process termination, inspect
the retained temporary directory/ownership marker before manual cleanup. These
tests verify conflicts/consistency, not a production throughput target.

Production-mode OTP HTTP tests use local SQL and a simulated HTTPS server flag;
they test cookie/session behavior, not real certificates. The Apache fixture
starts a separate local XAMPP server with temporary configuration and guest-only
business requests. Hosted acceptance remains a separate deployment step; see
`deployment/README.md`. The complete regression set also includes dashboard,
products, orders, customers, personnel, campaigns, dispatch, PIN/completion,
checkout-offer and operational-settings runners.
