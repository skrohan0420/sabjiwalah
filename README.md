# Sabjiwalah

Sabjiwalah is a grocery and vegetable delivery application built with CodeIgniter 4, PHP, MySQL, and server-rendered CI4 views.

The current priority is backend foundation first: database structure, authentication, authorization, routing, models, services, migrations, seeders, and simple working pages. Frontend design, categories, and payment gateway work are intentionally out of scope for the first milestone.

## Project Status

Current phase: admin panel Phase 5 (customer management) completed; awaiting approval for Phase 6 (delivery management).

Done:

- Phase 5 adds `/admin/customers`, admin-only `GET /api/v1/admin/customers`, customer details with paginated order history at `GET /api/v1/admin/customers/{uid}`, and `PATCH /api/v1/admin/customers/{uid}/status`. Lists support name/phone search, status, registration/name sorting and pagination, with batched order counts. Phone numbers are masked in lists; full phone/email are confined to the account dialog. Responses explicitly exclude password hashes, OTPs, internal IDs, saved addresses and order delivery addresses. Contacts are removed from the dialog DOM on close; private responses use `no-store`.
- Customer actions are activation/deactivation only; admin/delivery UIDs return 404 and extra write fields (including role/name/phone) are rejected. Status writes require `expected_status`, use a conditional customer/status update and return 409 for stale actions. No accounts or historical orders are deleted, and open orders stay available to staff. The screen requires confirmation, prevents overlapping status actions and requires reload after conflicts or ambiguous failures. No new schema or authentication system was added.
- A global `activeSession` filter now rechecks the persisted account before setup and role authorization. Missing/inactive accounts and sessions whose UID/role no longer matches are logged out, including OTP/checkout verification state. Active account contact/name session values are refreshed. Deactivation takes effect on the next request; reactivation requires a new login if the session was revoked. Database verification failures return a generic 503 and do not allow protected operations to continue. The existing public customer-registration service already fixes role=`customer` and status=`active`; injection protection is covered by tests. Public development OTP exposure remains a separate unresolved audit finding.
- Phase 5 checks: `php tests/deployment/admin-customers.php` exercises real SQL/controllers with temporary users/orders, privacy, filtered/history pagination, stale/concurrent actions, registration role protection and session invalidation. `node tests/deployment/admin-customers-session.cjs` starts a loopback HTTP fixture using temporary SQL/storage and proves existing-session revocation, blocked login/checkout and fresh login after reactivation. `node tests/deployment/admin-customers-api.cjs http://localhost:8080/` checks live role access, privacy, query/CSRF/write rejection and privileged-account protection without changing accounts. `node --test tests/frontend/admin-customers.test.cjs` covers safe/masked rendering, history, filter/detail races, status conflicts and refresh behavior. Add `--preview` to the session check for an isolated sample UI; stop it with Ctrl+C. Set `PHP_BINARY` for a non-XAMPP PHP path.

- Phase 4 adds `/admin/products` with search, availability filters, sorting, 20-product pagination, creation/editing, regular and optional sale prices, units, stock, activation and reversible archiving. The existing product model and admin APIs are reused. DELETE now sets `is_active=0` instead of deleting the product; order item snapshots and product links remain intact. Archived products can be activated again.
- Product writes accept only editable catalogue fields; clients cannot supply a UID or image path. PATCH validates the merged product but writes only submitted fields. Prices support at most two decimal places within DECIMAL(10,2), sale prices cannot exceed regular prices, blank sale prices become NULL, and stock must be a nonnegative unsigned integer. The editor submits `expected_stock_quantity`; a conditional update rejects a competing stock change with HTTP 409. Existing checkout reservation/cancellation behavior is unchanged.
- Image management uses admin-only, CSRF-protected `POST /api/v1/admin/products/{uid}/image` (multipart `image`) and DELETE on the same path. JPEG, PNG and WebP content is checked with fileinfo and image dimensions, limited to 2 MB and 4096 pixels per dimension. Random server filenames ignore the supplied filename. Files live in `writable/uploads/products`, outside public assets, and are served through `GET /media/products/{filename}` with an explicit image MIME type and `nosniff`. Hosted installations need PHP fileinfo and writable upload storage; no schema migration is required. The existing schema supports one primary image per product. Replaced/removed uploads remain on disk for cached pages and concurrent requests; storage cleanup is not automated.
- Product editor drafts survive validation/conflict errors and require a discard decision on close. Ambiguous failures require reloading before another save. Broken thumbnail URLs use the existing placeholder without shifting the row layout.
- Phase 4 checks: `php tests/deployment/admin-products.php` tests real controllers and SQL against temporary tables. `node tests/deployment/admin-products-upload.cjs` tests genuine multipart uploads, randomized filenames, media serving and removal with temporary SQL/storage; set `PHP_BINARY` if PHP is not at the local XAMPP path. `node tests/deployment/admin-products-api.cjs http://localhost:8080/` checks live authorization, CSRF, query validation and rejected mutations without changing catalogue records. `node --test tests/frontend/admin-products.test.cjs` checks filter races, safe text, successful saves, stock conflicts and draft preservation. Browser checks cover desktop, tablet and mobile layouts.

- Added Phase 1 admin foundation: shared server-rendered layout, responsive sidebar, account menu, theme controls, and reusable scoped UI styles. Future management sections are disabled until implemented; the existing three dashboard totals are preserved.
- Added `window.SabjiwalahAdmin` utilities (`api`, `notify`, `busy`, `confirm`, `ApiError`). Mutations are serialized, acquire fresh CSRF tokens, and retry once only for an explicit CSRF filter rejection. HTTP 401 redirects to login; authorization, validation, network, and malformed-response failures are reported separately. Admin sign-out uses the existing CSRF-protected POST API.
- Admin request regression checks: `node --test tests/frontend/admin.test.cjs`. Live dashboard/API verification requires local MySQL and a running deployment. The development OTP exposure identified in the audit remains unresolved.
- Phase 2 adds `GET /api/v1/admin/dashboard`, protected by the existing admin role filter. Five bounded/aggregate database queries provide order status counts, today's orders and sales, customers, active/low-stock products, eight recent orders, and eight oldest orders needing attention. List responses omit phone numbers, addresses, and internal IDs.
- Dashboard refresh runs every 30 seconds while visible, prevents overlapping requests, pauses on hidden pages, and retains the last successful values on failure. Manual Refresh is available. Order actions remain out of scope until Phase 3.
- Dashboard business days use `Asia/Kolkata`; boundaries are converted to the configured application timestamp timezone. Today's sales means paid, delivered orders **placed today**, not cash collected today; no payment/collection timestamp exists yet. Pending COD is excluded. Low stock means active products with at most five units, including zero. Attention includes pending confirmation, ready for dispatch, and failed delivery.
- Phase 2 checks: `php tests/deployment/admin-dashboard.php` executes real MySQL queries against connection-local temporary fixture tables without changing persistent records. `node tests/deployment/admin-dashboard-api.cjs http://localhost:8080/` checks local role authorization, CSRF/validation rejection, dashboard privacy and logout using existing seeded accounts. `node --test tests/frontend/admin-dashboard.test.cjs` covers polling, stale data, cancellation, and safe text rendering. Local MySQL, migration status, and live role checks passed during Phase 2; Composer's global PHPUnit remains incompatible.
- Phase 3 adds `/admin/orders` with server-side search by order/customer, status and India-date filters, pagination, sorting, payment information, item counts, rider names, and elapsed time. Details include item/price snapshots, customer delivery information, totals, notes, assignments, and status history. The existing admin order JSON fields are retained, with additive detail/list fields.
- Order-list polling runs every eight seconds while visible, keeps applied filters and unsaved detail notes, and reports newly placed orders independently of the current filters. The interface exposes confirmation, preparation, readiness, and permitted cancellation through the existing transition rules. Dispatch and verified completion remain later phases. Cancellation retains the existing inventory behavior: it does not restore reserved stock automatically.
- Status changes now use a transaction and conditional update so a competing transition cannot overwrite the status observed by the request. The optional `expected_status` parameter detects stale forms (HTTP 409); history failure rolls back the status. Delivery callers keep the existing method signature and now receive explicit conflict/failure responses.
- Run the new additive migration `2026-10-09-000001_AddAdminOrderIndexes` for date/id and status/date/id order indexes. It was applied locally; no existing migrations, order records, or history were rewritten. Hosted installations need this migration or the equivalent reviewed index SQL through the existing deployment process.
- Phase 3 checks: `php tests/deployment/admin-orders.php` uses temporary schema copies to test filtering, pagination, snapshots, permitted/rejected transitions, duplicates, rollback, and a simulated competing write. `node tests/deployment/admin-orders-api.cjs http://localhost:8080/` checks live authorization, JSON contracts and rejected requests without modifying orders. `node --test tests/frontend/admin-orders.test.cjs` tests polling, filter races, draft preservation, conflict handling, and safe text output. Populated browser action checks use an isolated fixture; real MySQL service tests separately verify persistence.

- Added Google Maps delivery selection with landmark search, a fixed center pin, GPS accuracy feedback, checkout address prefill and exact order coordinates. Move the map beneath the pin to choose the delivery point. See `deployment/README.md` for Google key setup and the schema update.
- Fixed phone-number entry selecting the entire value after each keystroke; typing now retains the caret while the OTP Change action still focuses the phone field.
- Replaced the default CodeIgniter favicon with the header cart logo across all pages, including a multi-size ICO and an Apple touch icon.
- Slimmed the fixed product purchase bar to 64px, with smaller pack/price typography and matching offsets for the cart pill and details sheet.
- Added an optional development-only ngrok preview URL that keeps localhost and the hosted production profile working independently, with phone-preview instructions and URL/database selection checks.
- Refined the product overview into a compact bordered card with inline highlights, a tighter price row, and an integrated description link.
- Added a swipeable product hero carousel with image dots, mouse dragging, keyboard navigation, and full/close-up/detail views of the current product photo.
- Shared the home product-card markup, ADD/quantity controls, and floating checkout pill with the product detail page, including carousel, saved-product, and cart animations.
- Redesigned product details with a full-width image, transparent header that becomes white and shows the product name on scroll, and a fixed purchase bar.
- Added a description bottom sheet with highlights and expandable information, plus six active product suggestions using the existing cart and saved-product controls.

- Matched the storefront container background to the product sections so bottom spacing no longer shows a yellow strip.

- Added an Apache/htdocs project-root entry point, public asset routing, and private-file access restrictions.
- Made customer navigation, assets, login redirects, and AJAX URLs work with environment-specific domains and subdirectory base URLs.
- Added separate local and test-server `.env` templates and no-terminal deployment/database import instructions in `deployment/README.md`.
- Configured the test-server template for `https://sabjiwalah.site.je/` and its supplied hosted MySQL connection details; the hidden password must be entered on the server.
- Added a combined `.env` with local/server URL and database profiles selected only by `CI_ENVIRONMENT`: development for local, production for the hosted test server, and testing for automated tests.
- Set the local profile to `http://localhost:8080/` for `php spark serve`; the hosted profile continues to use Apache at `https://sabjiwalah.site.je/`.
- Added deployment URL checks and an Apache smoke-check script covering pages, assets, APIs, private paths, and legacy redirects.

- Standardized the project name as `Sabjiwalah`.
- Updated Composer metadata to `sabjiwalah/sabjiwalah`.
- Replaced the default CodeIgniter README with this project tracker.
- Configured local `.env` for MySQL.
- Created/verified the local MySQL database `sabjiwalah`.
- Added a visible database connection status check on the home page.
- Verified the app can connect to MySQL as `root@localhost` using the `MySQLi` driver.
- Created initial migrations for users, addresses, products, orders, order items, order status history, delivery assignments, offers, and promotions.
- Ran migrations successfully against the local MySQL database.
- Created CI4 models for the initial entities with explicit `allowedFields`, timestamps, and validation rules.
- Created development seeders for admin, delivery, customer, and sample grocery products.
- Ran the development seeder successfully.
- Organized controllers into customer, admin, and delivery areas.
- Added simple server-rendered placeholder pages for customer, admin, and delivery sections.
- Added public product listing and product detail pages backed by the products table.
- Added session-based OTP login, logout, and customer account pages.
- New public phone numbers always create `customer` users.
- Added server-side auth and role filters.
- Protected `/admin` for `admin` users only.
- Protected `/delivery` for `delivery` users only.
- Enabled global CSRF and invalid character filters.
- Enabled secure headers after requests.
- Added service skeletons for auth, order status transitions, cart, and pricing.
- Verified `/`, `/products`, `/admin`, `/delivery`, and `/account` behavior through the dev server.
- Verified admin, delivery, and customer seeded logins.
- Added `.env`, writable debugbar, and writable session files to `.gitignore`.
- Added a versioned REST-style API layer under `/api/v1`.
- Added reusable API JSON response helpers.
- Added API-specific auth, role, and CSRF filters that return JSON errors.
- Added public product API endpoints.
- Added JSON API login, register, logout, and current-user endpoints using the existing session auth service.
- Added admin product API endpoints for list, show, create, update, and delete.
- Added admin order API list/show/status endpoints backed by existing order models and `OrderService`.
- Added delivery order API list/show/status endpoints with assignment ownership checks.
- Verified API role authorization for unauthenticated, customer, admin, and delivery sessions.
- Verified missing AJAX CSRF tokens return compact `403` JSON responses.
- Added unique `uid` columns across the core tables and standardized public/client/API identifiers around `uid`.
- Added automatic UID generation in models for future inserts.
- Added a session-backed client cart foundation using product UIDs.
- Added AJAX cart API endpoints for view, add, update, remove, and clear actions.
- Added a basic `/cart` page and AJAX add-to-cart behavior on product pages.
- Added an AJAX-first checkout foundation with server-side totals, cash-on-delivery order placement, order item snapshots, stock decrement, order status history, and cart clearing.
- Protected checkout for customer accounts only and preserved login redirects back to `/checkout`.
- Added development checkout phone OTP endpoints and UI. The OTP is shown on the checkout page for local testing, and order placement requires the matching verified phone.
- Checkout currently collects name, phone, delivery address, city, state, postal code, and notes. Email is not required for checkout.
- Removed `index.php` from generated application URLs and added redirects from old `/index.php/...` URLs to clean URLs.
- Replaced customer-facing password auth with phone OTP auth. OTPs are shown in the UI/API for local testing.
- Made customer email and password optional, with phone as the unique OTP login identifier.
- Normalized customer phone numbers for OTP auth so local, leading-zero, and `+91` formats resolve to one canonical 10-digit login phone.
- Added one basic responsive auth page for phone OTP login/signup combined.
- Added a customer account section for managing personal details with AJAX profile updates.
- Added customer account API endpoints for reading and updating profile details.
- Reworked the customer home page into a static, responsive grocery storefront design.
- Added centralized CSS design tokens for the home page color palette, spacing, radius, and shadows.
- Refined the home page stylesheet so the visual layout, cards, hero, banners, and responsive breakpoints match the current markup.
- Improved the customer home page further toward the provided GreenBasket-style reference with a fuller top strip/header, styled action icons, heart-style hero crop, compact cards, slider dots, and footer trust badges.
- Strengthened responsive behavior across tablet, mobile, and narrow phone widths so grids, hero imagery, deal banner, newsletter form, nav, and footer stack more cleanly.
- Kept the new home page design static only, with no API calls or dynamic database data.

In progress / next:

- Admin Phase 6: delivery personnel accounts, availability, workload and delivery history. Begin only after approval of Phase 5.
- Refine customer frontend styling across product listings, auth, account, and future cart pages.
- Add automated feature tests once project PHPUnit dependencies are installed with Composer.
- Complete the remaining admin delivery, offers, promotions and settings phases. Customer, product and order management screens are implemented.
- Add customer saved address management.
- Add saved address selection to checkout.
- Add customer order history and order detail pages.
- Add order creation and status update workflows.
- Add delivery assignment workflows.
- Add a small frontend JavaScript API client/helper for `fetch('/api/v1/...')`.
- Add focused automated API tests after Composer-installed PHPUnit is available.

Not started yet:

- Customer order history.
- Admin product/order management screens.
- Delivery order workflow.
- Offers and promotions logic.
- Category system.
- Payment gateway.

## Application Areas

Customer area:

- Base route: `/`
- Planned features: product listing, product details, cart, checkout, orders, offers, promotions, login, signup, account, saved addresses.

Admin area:

- Base route: `/admin`
- Planned features: dashboard, product management, order management, user management, delivery user management, offer management, promotion management.

Delivery area:

- Base route: `/delivery`
- Planned features: dashboard, assigned orders, order details, mark out for delivery, mark delivered, mark failed.

## First Milestone

The first milestone is complete when:

- The CI4 application still runs.
- MySQL configuration is ready.
- Migrations exist and run successfully.
- Seeders work.
- Basic user authentication works.
- Role-based access control works.
- `/` works.
- `/admin` is protected and only accessible to admins.
- `/delivery` is protected and only accessible to delivery users.
- Customer self-auth cannot create admin or delivery accounts.
- Models and database relationships are prepared.
- Basic service architecture exists.
- CSRF/security configuration is enabled.
- No category system has been added.
- No payment gateway has been added.
- No unnecessary frontend design work has been done.

## Planned Database Tables

Initial tables:

- `users`
- `user_addresses`
- `products`
- `orders`
- `order_items`
- `order_status_history`
- `delivery_assignments`
- `offers`
- `promotions`

Important database rules:

- Passwords, when present for legacy/internal accounts, must be stored as secure hashes only.
- Customer auth is phone OTP based; email, name, and password are not required for customer login/signup.
- OTP login phones are stored in canonical 10-digit form to avoid duplicate accounts from formats like `+91...`, `0...`, and plain local numbers.
- User roles are `customer`, `admin`, and `delivery`.
- Public OTP self-auth must always create `customer` users for new phone numbers.
- Admin and delivery users must not be creatable through public self-auth.
- Orders must store address, product name, unit, and price snapshots for historical accuracy.
- Money values must use decimal database types.
- Every core table uses a unique `uid` for public/client/API references.
- Numeric `id` remains an internal database primary key and foreign-key implementation detail.
- Use migrations for all schema changes.

## Planned Code Structure

Controller areas:

```text
app/Controllers/Client/
app/Controllers/Admin/
app/Controllers/Delivery/
app/Controllers/Api/V1/
```

View areas:

```text
app/Views/client/
app/Views/admin/
app/Views/delivery/
```

Models:

```text
app/Models/UserModel.php
app/Models/UserAddressModel.php
app/Models/ProductModel.php
app/Models/OrderModel.php
app/Models/OrderItemModel.php
app/Models/OrderStatusHistoryModel.php
app/Models/DeliveryAssignmentModel.php
app/Models/OfferModel.php
app/Models/PromotionModel.php
```

Services:

```text
app/Services/OrderService.php
app/Services/CartService.php
app/Services/PricingService.php
```

API filters:

```text
app/Filters/ApiAuthFilter.php
app/Filters/ApiRoleFilter.php
app/Filters/ApiCsrfFilter.php
```

## Order Statuses

Initial statuses:

- `pending`
- `confirmed`
- `preparing`
- `ready_for_delivery`
- `out_for_delivery`
- `delivered`
- `cancelled`
- `delivery_failed`

Status changes should eventually be centralized in `OrderService` and recorded in `order_status_history`.

## Local Setup

For Apache/htdocs and test-server hosting without terminal access, follow
[deployment/README.md](deployment/README.md). Both sets of URL and database
settings are kept in `.env`; change only `CI_ENVIRONMENT` to select them.
Use development for `http://localhost:8080/` with `php spark serve` and production for
`https://sabjiwalah.site.je/`. Enter the hosted MySQL password once.
For local Apache instead, set `app.localBaseURL = 'http://localhost/sabjiwalah/'`.

Requirements:

- PHP 8.2 or higher
- PHP extensions: `intl`, `mbstring`, `json`, `mysqli`
- Composer
- MySQL or MariaDB

Install dependencies:

```bash
composer install
```

Create a local environment file if it does not exist:

```bat
copy env .env
```

Current local database settings in `.env`:

```ini
app.indexPage = ''
CI_ENVIRONMENT = development
app.localBaseURL = 'http://localhost:8080/'
app.serverBaseURL = 'https://sabjiwalah.site.je/'
database.local.hostname = localhost
database.local.database = sabjiwalah
database.local.username = root
database.local.password = ''
database.local.DBDriver = MySQLi
database.local.port = 3306
```

The same `.env` also contains `database.server.*` for the hosted database.
See `deployment/env.example` for the complete combined template.

Run the development server:

```bash
php spark serve
```

Open:

```text
http://127.0.0.1:8080
```

The home page currently displays a database connection status block. If connected, it shows the database name, user, and driver.

## API

All API routes are versioned under:

```text
/api/v1
```

Response shape:

```json
{
  "success": true,
  "data": {},
  "message": null
}
```

Error shape:

```json
{
  "success": false,
  "data": null,
  "message": "Validation failed",
  "errors": {}
}
```

Current public endpoints:

```text
GET  /api/v1/csrf
GET  /api/v1/products
GET  /api/v1/products/{uid}
POST /api/v1/auth/otp/start
POST /api/v1/auth/otp/verify
POST /api/v1/auth/register
POST /api/v1/auth/login
```

Current authenticated customer/session endpoints:

```text
GET  /api/v1/auth/me
POST /api/v1/auth/logout
GET  /api/v1/account/profile
PATCH /api/v1/account/profile
GET  /api/v1/checkout/summary
POST /api/v1/checkout/otp/start
POST /api/v1/checkout/otp/verify
POST /api/v1/checkout/place
```

Current admin endpoints:

```text
GET    /api/v1/admin/products
POST   /api/v1/admin/products
GET    /api/v1/admin/products/{uid}
PUT    /api/v1/admin/products/{uid}
PATCH  /api/v1/admin/products/{uid}
DELETE /api/v1/admin/products/{uid}
GET    /api/v1/admin/orders
GET    /api/v1/admin/orders/{uid}
PATCH  /api/v1/admin/orders/{uid}/status
```

Current delivery endpoints:

```text
GET   /api/v1/delivery/orders
GET   /api/v1/delivery/orders/{uid}
PATCH /api/v1/delivery/orders/{uid}/status
```

API authentication strategy:

- Uses the existing secure session-based authentication.
- Customer-facing auth uses phone OTP instead of passwords.
- Current development OTP responses include `dev_otp` so the flow can be tested without an SMS provider.
- New public customer accounts are created from phone after OTP verification. Email and name are optional.
- Does not use JWT yet.
- AJAX requests from the same-origin frontend should include browser cookies/session automatically.
- API auth responses never expose password hashes or session secrets.

API authorization strategy:

- `/api/v1/admin/*` uses `apiRole:admin`.
- `/api/v1/delivery/*` uses `apiRole:delivery`.
- Protected non-role API routes use `apiAuth`.
- Customer checkout API routes use `apiRole:customer`.
- Customer account profile API routes use `apiRole:customer`.
- Delivery order endpoints verify the order is assigned to the current delivery user before returning or updating it.

Checkout OTP note:

- The current checkout OTP is development-only and displays the generated code in the UI so the auth flow can be tested without SMS.
- Replace this with a real SMS/OTP provider before production.
- Checkout does not require an email address; the order-time contact fields are name, phone, and delivery address.

CSRF for AJAX:

1. Fetch `GET /api/v1/csrf`.
2. Read `data.header_name` and `data.token_value`.
3. Send the token on state-changing requests as a header, for example:

   ```text
   X-CSRF-TOKEN: {token_value}
   ```

4. If a state-changing request fails with `Invalid or missing CSRF token`, fetch `/api/v1/csrf` again and retry after normal client-side error handling.

Example JSON OTP login:

```bash
curl -X POST http://127.0.0.1:8080/api/v1/auth/otp/start \
  -H "Content-Type: application/json" \
  -H "X-CSRF-TOKEN: {token_value}" \
  -d "{\"phone\":\"9000000003\"}"
```

## Useful Commands

Check routes:

```bash
php spark routes
```

Check database migration status:

```bash
php spark migrate:status
```

Run migrations:

```bash
php spark migrate
```

Run a seeder:

```bash
php spark db:seed SeederName
```

Seed local development data:

```bash
php spark db:seed DevelopmentSeeder
```

Development account phones:

```text
Admin User / 9000000001
Delivery User / 9000000002
Customer User / 9000000003
```

Run tests:

```bash
composer test
```

Deployment-specific checks (Node.js required only on the machine running checks):

```bash
node tests/deployment/app-url.cjs
node tests/deployment/apache-smoke.cjs http://localhost/sabjiwalah/
```

Current test note: `composer test` currently resolves to an old global XAMPP/PEAR PHPUnit on this machine and fails before running the app tests. Install project dependencies with Composer so the project uses the PHPUnit version required by `composer.json`.

## Development Rules

Keep this README updated whenever a feature, migration, model, route, service, or setup step is added or changed.

For each meaningful change, update:

- `Project Status`
- `Done`
- `In progress / next`
- Any relevant setup or command notes

Security rules:

- Use `.env` for credentials, secrets, URLs, and environment-specific settings.
- Do not hardcode database credentials in application code.
- Keep CSRF protection enabled.
- Use CI4 Models/Query Builder for database writes and reads where possible.
- Escape output in views.
- Hash passwords securely when password hashes are used.
- Regenerate sessions after login.
- Enforce authorization on the server, not only through hidden links.
- Do not trust prices or roles submitted from the client.

Architecture rules:

- Keep controllers thin.
- Keep models focused on persistence.
- Put business logic in services where appropriate.
- Use migrations for schema changes.
- Use seeders for development data.
- Do not add categories yet.
- Do not add payment gateway work yet.
- Do not spend time on frontend design until the backend foundation is stable.
