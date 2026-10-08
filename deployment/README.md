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
