# Sabjiwalah

Sabjiwalah is a grocery and vegetable delivery application built with CodeIgniter 4, PHP, MySQL, and server-rendered CI4 views.

The current priority is backend foundation first: database structure, authentication, authorization, routing, models, services, migrations, seeders, and simple working pages. Frontend design, categories, and payment gateway work are intentionally out of scope for the first milestone.

## Project Status

Current phase: customer home page design started after the backend and API foundations.

Done:

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
- Added session-based signup, login, logout, and customer account pages.
- Public signup always creates `customer` users.
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
- Reworked the customer home page into a static, responsive grocery storefront design.
- Added centralized CSS design tokens for the home page color palette, spacing, radius, and shadows.
- Refined the home page stylesheet so the visual layout, cards, hero, banners, and responsive breakpoints match the current markup.
- Improved the customer home page further toward the provided GreenBasket-style reference with a fuller top strip/header, styled action icons, heart-style hero crop, compact cards, slider dots, and footer trust badges.
- Strengthened responsive behavior across tablet, mobile, and narrow phone widths so grids, hero imagery, deal banner, newsletter form, nav, and footer stack more cleanly.
- Kept the new home page design static only, with no API calls or dynamic database data.

In progress / next:

- Refine customer frontend styling across product, auth, account, and future cart pages.
- Add automated feature tests once project PHPUnit dependencies are installed with Composer.
- Add proper admin CRUD screens for products, users, orders, offers, and promotions.
- Add customer saved address management.
- Add cart page and checkout flow using server-side pricing.
- Add order creation and status update workflows.
- Add delivery assignment workflows.
- Add a small frontend JavaScript API client/helper for `fetch('/api/v1/...')`.
- Add focused automated API tests after Composer-installed PHPUnit is available.

Not started yet:

- Cart and checkout flow.
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
- Customer signup cannot create admin or delivery accounts.
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

- Passwords must be stored as secure hashes only.
- User roles are `customer`, `admin`, and `delivery`.
- Public signup must always create `customer` users.
- Admin and delivery users must not be creatable through public signup.
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
database.default.hostname = localhost
database.default.database = sabjiwalah
database.default.username = root
database.default.password =
database.default.DBDriver = MySQLi
database.default.port = 3306
```

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
POST /api/v1/auth/register
POST /api/v1/auth/login
```

Current authenticated customer/session endpoints:

```text
GET  /api/v1/auth/me
POST /api/v1/auth/logout
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
- Does not use JWT yet.
- AJAX requests from the same-origin frontend should include browser cookies/session automatically.
- API auth responses never expose password hashes or session secrets.

API authorization strategy:

- `/api/v1/admin/*` uses `apiRole:admin`.
- `/api/v1/delivery/*` uses `apiRole:delivery`.
- Protected non-role API routes use `apiAuth`.
- Delivery order endpoints verify the order is assigned to the current delivery user before returning or updating it.

CSRF for AJAX:

1. Fetch `GET /api/v1/csrf`.
2. Read `data.header_name` and `data.token_value`.
3. Send the token on state-changing requests as a header, for example:

   ```text
   X-CSRF-TOKEN: {token_value}
   ```

4. If a state-changing request fails with `Invalid or missing CSRF token`, fetch `/api/v1/csrf` again and retry after normal client-side error handling.

Example JSON login:

```bash
curl -X POST http://127.0.0.1:8080/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -H "X-CSRF-TOKEN: {token_value}" \
  -d "{\"email\":\"admin@sabjiwalah.local\",\"password\":\"Password@123\"}"
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

Development accounts:

```text
admin@sabjiwalah.local / Password@123
delivery@sabjiwalah.local / Password@123
customer@sabjiwalah.local / Password@123
```

Run tests:

```bash
composer test
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
- Hash passwords securely.
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
