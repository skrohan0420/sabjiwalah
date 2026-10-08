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

Current OTP endpoints still return development OTP codes. This deployment is
for development testing; environment selection does not add an SMS provider.

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
