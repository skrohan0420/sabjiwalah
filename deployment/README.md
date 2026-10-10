# Apache / htdocs deployment

No `php spark serve` process is required. Apache runs PHP on each request.

## Current test server

The server template is configured for `https://sabjiwalah.site.je/`, MySQL host
`sql203.infinityfree.com`, user `if0_43119310`, and database
`if0_43119310_sabjiwalah` on port `3306`. The MySQL password was hidden in the
provided screenshot; enter it directly in the server's `.env`.

For this domain, upload the **contents** of the project directly into the
domain's `htdocs` folder, so `htdocs/index.php` and `htdocs/.htaccess` exist.
Do not place them inside an extra `htdocs/sabjiwalah` folder with this base URL.
Copy `deployment/env.example` to `htdocs/.env`, enter the hosted password once,
and set `CI_ENVIRONMENT = production`. Both database profiles stay in that file.
Import the database into `if0_43119310_sabjiwalah` through phpMyAdmin.

## Upload and configure

1. Upload the entire project to `htdocs/sabjiwalah` (or your host's equivalent).
   Include hidden `.htaccess` files, `system`, and installed `vendor` dependencies
   if present. Do not upload local `.env`, logs, sessions, or private SQL backups.
2. Copy `deployment/env.example` to `.env` beside the root `index.php`.
   It contains `app.localBaseURL`, `app.serverBaseURL`, `database.local.*`,
   and `database.server.*`. Enter the hosted password once. Preserve an existing
   configured `.env` on later uploads.
3. Change only `CI_ENVIRONMENT`: `development` for local `php spark serve` at
   `http://localhost:8080/`; `production` for `https://sabjiwalah.site.je/`.
   The local/server example files contain both profiles too; their only
   difference is the initial environment selection.
4. Apache must support `.htaccess` (`AllowOverride All`) and `mod_rewrite`.
   PHP must be 8.2+ with intl, mbstring, and mysqli. The web-server account must
   be able to write to `writable/`. Use the hosting panel to set permissions.
5. Create a separate test database using the hosting panel/phpMyAdmin. Export
   the migrated local database in phpMyAdmin and import it into the test DB.
   Include the `migrations` table. Export only development/sample data, or use
   a structure-only export if the dev database should start empty. Configure
   the server's credentials in its `.env`; local credentials stay local.
6. Verify home, products, login, cart, checkout, and `/api/v1/csrf`. Requests to
   `/.env`, `/app/Config/Database.php`, `/writable/`, and `/deployment/` must be
   forbidden. Include the installation prefix when checking a subfolder URL.

The root gateway serves assets from `public/assets` and blocks private project
directories. If the hosting panel lets you choose a document root, point it at
`public/` instead; both layouts are supported. Do not use the project-root
layout on a server that ignores `.htaccess`.

## Environment selection

- `development`: selects `app.localBaseURL`, `app.localForceHTTPS`, and
  `database.local.*`, with development error details.
- `production`: selects `app.serverBaseURL`, `app.serverForceHTTPS`, and
  `database.server.*`, with development error details hidden.
- `testing`: automated-test environment; CodeIgniter switches to `database.tests`.
  It is not the environment name for a normal development/test hosting server.

The application config now uses `CI_ENVIRONMENT` to select both the database and
URL. Change profile values once, then switch only this line. This selection is
implemented in `app/Config/App.php` and `app/Config/Database.php`.
Server-level environment variables take precedence over `.env`.
If config caching was enabled previously, clear its generated config cache
before changing configuration. There is no config cache enabled by this change.

Local development is configured for `php spark serve` at `http://localhost:8080/`.
Select `development` and run `php spark serve`. For local Apache instead, set
`app.localBaseURL = 'http://localhost/sabjiwalah/'` once.

### Phone preview through ngrok

Keep `CI_ENVIRONMENT = development`. Set your ngrok HTTPS address in the local
`.env` once:

```ini
app.previewBaseURL = 'https://your-domain.ngrok-free.dev/'
```

Run these in two terminals from the project directory:

```powershell
php spark serve
ngrok http 8080
```

Open the HTTPS forwarding URL on your phone. Edits are available when you refresh;
FTP is only needed when you deploy to the hosted server. localhost continues to
work at the same time. Both use the local database. The hosted production profile
ignores `app.previewBaseURL` and keeps its existing URL and database settings.

The preview URL must match the forwarding URL exactly. Update only this setting
if ngrok changes your domain. Do not use `--host-header=rewrite`: the app needs
the original ngrok Host header to select the preview URL. Forward to port 8080
for Spark, rather than port 80 for XAMPP. There is no need to disable CSRF or CORS.
If ngrok shows its initial browser notice, open the forwarding URL and continue
to the site before testing cart requests. On your phone, localhost refers to
the phone itself; generated URLs must therefore use the configured preview host.

Preview config checks (no database connections):

```powershell
php tests/deployment/preview.php tunnel
php tests/deployment/preview.php localhost
php tests/deployment/preview.php unknown
php tests/deployment/preview.php forwarded
php tests/deployment/preview.php invalid
php tests/deployment/preview.php production
```

Environment checks make no database connections:

```bash
php tests/deployment/environment.php development
php tests/deployment/environment.php production
php tests/deployment/environment.php testing
```

### Authentication rollout (Phase 11A)

Production phone login and checkout are unavailable until a real SMS verification
provider is integrated. OTP endpoints return HTTP 503 without a testing code in
production, even if `otp.developmentMode = true`. Existing legacy login cookies
and sessions established with testing OTPs are revoked on their next production
request. Plan for this login interruption before uploading this change; this is
not a production-ready authentication deployment.

For local testing, use `CI_ENVIRONMENT = development` and explicitly set
`otp.developmentMode = true` in the local `.env`, then open the local `/login`
page. Use an existing active administrator's phone number to enter the admin
panel; new phone numbers register as customers. The form displays the testing
OTP only when the request's direct address and Host are loopback, with no
forwarding headers. Localhost, 127.0.0.1 and IPv6 loopback are accepted; public
Host values and Forwarded/X-Forwarded-*/X-Real-IP headers deny testing codes.
Keep the setting false on deployments. Do not expose opted-in development
through a proxy/tunnel that strips forwarding headers and rewrites Host to
localhost; such traffic cannot be distinguished from a direct local client.

Before deploying the code, run `php spark migrate` to create `otp_rate_limits`.
For hosts without a terminal, import an up-to-date migrated schema using the
existing phpMyAdmin workflow. Missing rate-limit storage fails closed. Bucket
keys are hashed; the table does not store raw phone numbers or IP addresses.
Each phone and purpose allows five sends and five verification submissions per
fifteen minutes (successful submissions count too), with sixty seconds between
sends. IP limits are shared across login and checkout: twenty sends and sixty
verification submissions per fifteen minutes. Resending or opening a new browser
session does not reset these budgets. HTTP 429 includes Retry-After.

Run `php spark otp:prune` periodically to remove at most 1000 expired buckets
per run, preserving active resend cooldowns. Repeat batches if needed. No
scheduled maintenance task is installed by this change.

Focused checks use temporary SQL and isolated session storage:

```bash
php tests/deployment/otp-security.php
php tests/deployment/otp-security.php --production
node tests/deployment/otp-security-session.cjs
node tests/deployment/otp-security-session.cjs --production
node --test tests/frontend/auth-security.test.cjs
```

Production-mode HTTP fixtures deliberately use local SQL and disable forced
HTTPS redirection inside the fixture only, with a simulated HTTPS server flag
to verify Secure/HttpOnly/SameSite cookies. They verify OTP/session behavior,
including destroyed periodic-session replay, and do not verify production
certificates or transport configuration.

### Final local hardening checks (Phase 11B)

Use PHP 8.2+ and `composer install` with the tracked lock file. `composer test`
runs project PHPUnit; do not use the old global XAMPP/PEAR PHPUnit. Production
packages can use `composer install --no-dev --optimize-autoloader`; run tests
before excluding development dependencies. Keep build reports and dependencies
private, and preserve the updated root `.htaccess` if using the htdocs gateway.

```bash
composer test -- --no-coverage
node tests/deployment/admin-security-session.cjs
php tests/deployment/admin-performance.php
node tests/deployment/concurrent-admin.cjs
node tests/deployment/admin-products-upload.cjs
node tests/deployment/apache-isolated.cjs
```

The concurrent check requires local MySQL CREATE/DROP DATABASE privileges. It
creates a random `sw_hardening_*` database, clones structure only, races real
independent processes, and drops only the database recorded in its ownership
marker. It never modifies source business tables. If interrupted before cleanup,
inspect the fixture's ownership marker in the `sabjiwalah-concurrent-*` temporary
directory before manually removing an abandoned fixture database. Do not grant
these test-only privileges to the production application user.

The isolated Apache command requires local XAMPP Apache/PHP modules (override
APACHE_BINARY/XAMPP_PHP_DIR if needed). It binds only loopback on an ephemeral
port, performs guest/read-only business requests and removes its own temporary
configuration/session files. It does not change running Apache configuration.
On the hosted deployment, also run `node tests/deployment/apache-smoke.cjs URL/`
against the actual base URL to verify private paths and rewrites.

Production requires working HTTPS before cookies can be issued; the application
enforces Secure cookies even if an environment override tries to disable them.
Normal session cookies are HttpOnly/SameSite=Lax, and expired IDs are destroyed
during periodic rotation. Customer logout is POST-only with CSRF. Personalized
pages/APIs are private/no-store and cannot use automatic shared page caching;
generated product images retain public caching. Dispatch lock contention returns
a rollback-safe 409 requiring reload. Rider workload lists contain active orders;
latest-owner detail access remains compatible for historical records.

Before production acceptance, verify the SMS flow, hosted certificate/HTTPS,
PHP intl/mbstring/mysqli/fileinfo, writable session/upload storage, migrations,
private directories, database backups and restore, and representative load.
These local races prove conflict behavior, not production capacity. All-time
dashboard aggregates and the singleton checkout acceptance lock still need load
monitoring. Product/customer/personnel/campaign edits lack a complete general
administrator audit trail; order, assignment, settings and cash history exist.
Retained old product uploads need an operator retention/cleanup policy. These
requirements remain open; the local checks do not certify production readiness.

Future schema changes can be migrated locally and supplied as reviewed SQL
updates for phpMyAdmin when the host has no terminal. There is no public web
migration or seeding endpoint.

Reference: https://codeigniter.com/user_guide/installation/running.html

### Delivery map (Google Maps)

The home picker uses Google Maps with normal street and landmark labels, a
compact roadmap controls, submitted Google Places searches and a fixed center
pin. Move the map until the entrance is under the pin, add a house/landmark, and confirm.
Panning updates the selected coordinates beneath the center pin. GPS collects improving
readings for up to 20 seconds and shows the reported accuracy circle. It does not
guarantee a doorstep location; users must check the pin before confirming.

Set these in the private `.env` on local and test hosts:

```ini
maps.googleApiKey = 'YOUR_GOOGLE_BROWSER_API_KEY'
maps.googleMapId = 'DEMO_MAP_ID'
```

Create a Google Cloud project with billing enabled, and enable **Maps JavaScript
API**, **Places API (New)** and **Geocoding API**. Restrict the browser key to those
APIs and HTTP referrers:

- `http://localhost:8080/*` (add the XAMPP URL if used)
- `http://127.0.0.1:8080/*`
- `https://cristine-nonsubordinate-stevie.ngrok-free.dev/*`
- `https://sabjiwalah.site.je/*`

Use a JavaScript map ID with the default Google style for the host; DEMO_MAP_ID is
provided for development. Keep streets and POIs visible if you customize its style.
The browser API key is visible to visitors by design, so the referrer/API
restrictions matter. No key is committed. Without a configured key the picker
shows an unavailable state and prevents confirmation rather than using another
provider silently. See [Google setup](https://developers.google.com/maps/documentation/javascript/get-api-key)
and [key restrictions](https://developers.google.com/maps/api-security-best-practices).

The confirmed pin is saved in this browser and prefilled at checkout. Changing
city, state or postcode detaches the pin; adding a house number keeps it. Existing
saved coordinate pins remain compatible. Google reverse geocoding provides an
address label; it never replaces the user-selected coordinates with a street center.

Run `php spark migrate` locally. On the test host, import
`deployment/add-delivery-pin.sql` once through phpMyAdmin before uploading the
checkout changes. It adds two nullable coordinate columns to existing orders.

Checks: `node --test tests/frontend/location.test.cjs` (mocked Google API interaction)
and `php tests/deployment/delivery-pin.php` (validation and local schema, no orders).
Actual Google labels, search and device GPS require the configured key for live testing.

## Smooth page navigation and asset caching

All shared page headers load the navigation styles and script. Supported browsers
use short native page transitions, while reduced-motion preferences disable them.
The bottom navigation keeps its position during transitions. Normal links, form
submissions and browser history continue to work.

Back/forward navigation retains scroll positions and scrollable category rails.
The browser's live page cache is used when available; session storage provides a
fallback for scroll restoration. Search results keep their existing URL query.
Intent-based prefetching is limited to four same-origin public browsing URLs per
page and is disabled for data saver or slow mobile connections. Account, checkout,
logout, admin and API routes are excluded.

Upload `public/assets/.htaccess` with the assets. Apache with `mod_headers` caches
content-versioned static files for seven days and other static files for one hour.
`app_static_url()` appends a content hash, so editing an asset changes its URL.
HTML and API responses are excluded. Spark does not apply Apache `.htaccess`
headers; local Spark development continues normally.

Checks: `node --test tests/frontend/page-navigation.test.cjs tests/frontend/theme.test.cjs`
and `php tests/deployment/static-assets.php`. On the deployed Apache host, inspect
the response headers for a versioned CSS/JS URL: `Cache-Control` should contain
`max-age=604800, immutable`.
