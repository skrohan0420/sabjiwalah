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

There are 117 frontend checks for safe rendering, authorization failures, polling,
stale responses, drafts/conflicts, uncertain writes, checkout, OTP overlap and
customer navigation/location/theme behavior.

Delivery foundation verification uses `node tests/deployment/admin-security-session.cjs`
for all four protected delivery pages, navigation, availability, shared components,
and private cache policies. `php tests/deployment/admin-dispatch.php` also verifies
the layout's availability through assignment, reassignment, pickup, and failure.
For a disposable browser preview, run `node tests/deployment/delivery-pin-session.cjs --preview`
and use its printed sample accounts; send `stop` to close the fixture. The dispatch
and PIN fixtures allow Home, Deliveries, History, and Profile routes.

Phase 1 browser QA on 11 October 2026 checked widths 320, 360, 390, 430, and 1280px
without horizontal overflow and with visible action/navigation targets at least
44px tall. Manual checks covered both themes, navigation, keyboard skip-link,
dialog cancellation/Escape and focus return, pickup, wrong PIN, required COD cash
confirmation, verified completion, failed delivery, logout, and signed-out Back
navigation. These checks used temporary SQL fixture orders. They do not constitute
physical-device or screen-reader testing. Availability is a page-load snapshot;
live Home updates were added in Phase 2 Step 1 and self-service controls in Step 2.

Phase 2 Step 1 adds `php tests/deployment/delivery-dashboard.php` for latest rider
ownership, India-midnight boundaries, recorded COD, and legacy completion fallback.
The HTTP security fixture checks the dashboard's role, query, and cache boundaries.
Six frontend checks cover polling overlap, hidden/offline pages, unchanged DOM,
assignment notifications, malformed/error responses, busy actions, and departure.
Browser QA checked the live Home dashboard at 320, 390, and 1280px without horizontal
overflow, manual refresh, and automatic timestamp updates. Polling runs on Home;
delivery action forms keep their existing refresh and write guards.

Phase 2 Step 2 adds rider-only PATCH availability with CSRF, strict input, active
account/workload checks, and expected-value conflict handling. Available,
Unavailable, and Offline are selectable only without active assignments; Busy
and Assigned remain derived. The dashboard MySQL fixture checks each transition,
stale/idempotent writes, missing profiles, and inactive/non-rider rejection. Four
additional frontend checks cover drafts, confirmation/cancellation, write overlap,
uncertain outcomes, authorization loss, and departure. `concurrent-admin.cjs`
also races availability against dispatch and another availability write using
independent MySQL connections. Browser QA covered blocked Busy controls, cancelled
confirmation, and persisted Available/Unavailable/Offline changes in disposable
fixture storage, with mobile controls at least 44px high.

Phase 3 extends the active delivery list and adds `/delivery/orders/{uid}` details.
The existing rider API now includes assignment timestamps, product snapshots and
status timestamps; internal IDs and administrative history notes stay private.
Four frontend checks cover safe links/instructions, products/location/timeline,
terminal actions, detail pickup reload, reassignment removal and 409 recovery.
The HTTP security fixture checks detail page roles/ownership, field privacy and
strict status input. The dispatch fixture verifies both page and API access are
revoked after reassignment. Existing completion/PIN/COD checks remain in place.
Browser QA checked list-to-details navigation, customer/navigation link targets,
product/coordinate/timeline rendering, pickup, cancelled failure with retained
notes, confirmed failure and terminal action removal. Widths 320, 390 and 1280px
had no horizontal overflow. The preview used synthetic data and did not launch
real phone calls or external directions.

Phase 4 adds eight Maps/GPS/Routes tests plus an order-to-map access-loss check.
Mocks cover coordinate validation, loading/auth/network failures, explicit GPS,
permission denial/unavailability/timeouts, provider estimates, route overlap,
60-second throttle, five-minute nearby-origin cache, hidden/departure cleanup,
terminal orders and late callbacks. The real preview can use
`node tests/deployment/delivery-pin-session.cjs --preview --mock-gps` to return
synthetic GPS only on the disposable detail page, with a visible QA label. This
does not request device location and does not mock the Google map or routing API.
Live browser QA on 11 October 2026 loaded the Google map and destination/rider
markers with synthetic GPS. Routes API was enabled in project
`sabjiwalah-511011`; the configured Maps Platform API Key already allowed it.
Live Google routing returned 2.3 km and 9 minutes estimated, rendered the route,
and reused the cached result on a second click. The production homepage at
`https://sabjiwalah.site.je/` loaded successfully over HTTPS. No physical-device
GPS or permission prompt was exercised.
The real map had no horizontal overflow at 320, 390 and 1280px. Clear my location
removed the rider marker and disabled routing. The blue You marker distinguishes
the rider from the customer destination.

Phase 5 adds eight tracking tests for explicit consent, active assignment/HTTPS,
sample accuracy/freshness, 25-metre movement, one-minute heartbeat, 15-second
intervals, authorization/network errors, permission denial, hidden/offline/departure
cleanup, overlap and stop-during-upload races. Run
`php tests/deployment/delivery-location.php` for real MySQL service checks using
temporary tables: role/current assignment, duplicate/conflicting/older samples,
rate limits across stop/restart, latest-only storage, terminal cleanup and pruning.
The PIN HTTP fixture also checks the location endpoint's roles, CSRF, strict input,
freshness, private responses, no GET exposure, duplicate/rate-limit and stop clearing.
It uses a serial PHP server and does not prove parallel MySQL transaction races.

Browser QA on 11 October 2026 enabled tracking with synthetic GPS, verified one
latest backend row and visible upload status, and verified Stop tracking cleared
the coordinates. Widths 320, 390 and 1280px had no horizontal overflow. No real
device location/permission prompt or production GPS upload was exercised.

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
