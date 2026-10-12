# Independent role authentication

Implemented 12 October 2026. Customer, admin and delivery are separate rows in
`users`, with independent IDs and UIDs, even when their phone and email match.

## Migration and rollout

New migration: `2026-10-12-000001_SeparateRoleAccounts.php`. No earlier migration
was edited. The new migration replaces single-column phone/email unique indexes
with `(phone, role)` and `(email, role)` indexes, canonicalizes accepted Indian
phone formats, lowercases/trims email, and stores empty email/phone as NULL.
It never deletes or merges users, renumbers IDs, or rewrites order, assignment,
or audit foreign keys. Preflight rejects invalid phone numbers and normalization
collisions, identifying conflicting IDs for explicit resolution.

Apply with the normal `php spark migrate` deployment procedure against the intended
database during a maintenance window, before allowing account writes with this code.
MySQL DDL is not transactional. Preflight runs before DDL; a normalization failure
must be resolved and the migration rerun. Index creation is idempotent. Automatic
rollback refuses to collapse role accounts because global uniqueness may no longer
be representable. Existing rows previously merged by an old migration cannot be
reconstructed by this change.

**Execution status:** applied to temporary tables and a fresh, disposable MySQL
database for verification; not applied to the existing application database.
No deployment, push, or commit was performed.

`UserModel` normalizes and validates inserts, individual edits and supported batch
imports against the effective role. Database indexes also reject racing duplicates.
Use the model for future imports; raw SQL importers must supply canonical phones,
lowercase trimmed email and NULL for empty email. There is no other application
account importer in this repository. The development seeder finds users by role and
phone, creates passwordless rows, and preserves hashes on existing rows.

## Login and endpoint contract

- Customer page: `GET /login`, fallback verification: `POST /login`.
- Admin page: `GET /admin/login`, fallback verification: `POST /admin/login`.
- Delivery page: `GET /delivery/login`, fallback verification: `POST /delivery/login`.
- Each role has `POST /api/v1/{customer|admin|delivery}/auth/otp/start`,
  `POST .../otp/verify`, `POST .../logout`, and `GET .../me`.
- CSRF-protected page logout: `POST /logout`, `POST /admin/logout`,
  `POST /delivery/logout`. GET never logs out.
- Existing `/api/v1/auth/*`, `/signup` and `/login` aliases are customer-only.
  API login/register aliases require a phone and OTP; email/password is rejected.

Roles come from fixed controller properties and explicit route filters, never
request parameters. Customer OTP verification may create a customer. Staff login
requires an existing active row in the selected role; no public staff registration
route exists. Authorized admins create delivery accounts in Delivery Personnel;
these start inactive and require activation. Existing administrator provisioning
remains a trusted operator/database administration operation; this change adds no
public administrator creation or role-promotion interface. Development seed accounts
are for local fixtures, not a production provisioning process.

Unknown/inactive staff accounts receive no usable challenge or code. Failed starts
still pass provider checks and consume the applicable send budget. Disabling an
account after issuance also prevents verification from authenticating it.

## Session and customer state

`customer_auth`, `admin_auth`, and `delivery_auth` contain ID, UID, role, method,
proof version, verified phone, OTP purpose and authentication timestamp. Use
`AuthService::forRole('customer'|'admin'|'delivery')`, `identity(role)` or `id(role)`;
never read/write a global user ID or role. The default constructor remains
customer-only for compatibility, not an inferred role selector.

Every request revalidates all present records against the current active account,
UID, role, phone and proof metadata. A disabled/deleted/changed account loses only
its own record. Reactivation does not resurrect an invalidated record. Legacy
shared identity keys and the old `auth_otp` are discarded, never promoted. Production
rejects local-development proof and accepts only the future SMS proof method.

Login and individual logout regenerate the shared session ID, preserving other
role records. Logout never destroys the session. Prior IDs are destroyed as before,
including during scheduled regeneration. Cookie Secure/HttpOnly/SameSite settings
are unchanged. CSRF verification remains mandatory for all writes; per-request
CSRF token regeneration is disabled so forms in existing role tabs share a valid
cookie token instead of invalidating one another. The existing CSRF expiry applies.

**Underlying session expiry remains shared.** All three identities expire when the
same browser session expires; this is not three separate cookie lifetimes. Requests
already carrying an obsolete session ID during rotation may need to be retried
with the browser's updated cookie; old session IDs are intentionally not accepted.

Staff login/logout preserves customer cart, coupon and checkout proof. Customer
logout or identity change clears checkout proof, coupon and saved server-side
delivery location; the cart remains. Customer setup redirects do not intercept
staff pages. Staff saved-login hints use separate local-storage keys. Order creation,
ownership, dispatch, delivery status/completion, cash and settings actors use the
explicit identity for their area.

## OTP and password limitations

Purposes are `customer_login`, `admin_login`, `delivery_login`, and `checkout`.
Challenges contain a hash, purpose, phone and expiry. Verification consumes a login
challenge; checkout verification removes the code hash and retains its expiring
receipt. Challenges cannot be replayed or moved between purposes/phones.

Ten-minute expiry, sixty-second resend cooldown per purpose and existing database
rate limiting remain. Five sends and five verification attempts per phone per
fifteen minutes now apply both per purpose and in aggregate. All purposes share
the IP limits (20 sends, 60 verifications). Successful verification counts too.
Parallel callers compare cooldown time after acquiring the database lock so lock
waiting cannot create a spurious cooldown in an IP bucket with no cooldown.

No SMS integration was invented. Production OTP issuance, verification and checkout
remain unavailable (503) until a real provider is integrated. The exact existing
local-development/explicit-preview restrictions remain; production never exposes
testing codes. A future provider must retain these purpose/budget/single-use
contracts and issue `sms` proof only after actual verification.

`password_hash` remains nullable and existing values remain in the database. The
unused password-authentication method was removed. No reachable password login
route exists. Remaining references are the model's allowed-field/length validation,
NULL values in account creation/seed code, historical migrations and privacy tests.
To remove the column later, audit any external import/provisioning jobs, remove
these current write/model dependencies, and add a separate forward migration.

## Verification

- PHPUnit: 24 tests / 32 assertions.
- Frontend suite: 141 tests, including all three login endpoint/redirect/storage
  variants and isolated delivery logout.
- `role-accounts.php`: actual migration, unchanged IDs/UIDs/references, cross-role
  phone/email reuse, normalization, duplicate constraints, partial edits and role changes.
- `role-auth-session.cjs`: actual HTTP with one cookie jar; three simultaneous
  identities with the same phone, independent logout, periodic/login regeneration,
  old-tab CSRF, OTP phone/role/replay isolation, staff signup denial, customer signup,
  revocation, legacy invalidation, customer state, shared limits, and an end-to-end
  checkout/admin dispatch/delivery pickup with correct ownership and audit IDs.
- Existing OTP production/development/preview, admin authorization, personnel,
  customer, order, campaign, coupon/settings, delivery PIN/completion/history/location,
  tracking, performance and independent-process concurrency fixtures were exercised.
- Browser inspection used the isolated login pages: admin at 360px and delivery/
  customer at 320px, with no horizontal overflow; the admin phone input was 48px high.
  This is not physical-device or assistive-technology testing.

Tests use connection-local temporary tables or owned disposable databases/session
storage. During final verification the existing local MariaDB stopped accepting
connections and reported an InnoDB checkpoint error on startup. Its data files
were not repaired or reset; remaining SQL checks used a separately initialized
loopback instance. The application's existing MySQL service requires independent
recovery before normal local application use.

Run `php tests/deployment/role-accounts.php` and
`node tests/deployment/role-auth-session.cjs` against a healthy local test database;
see `tests/README.md` for other regression runners. Never point destructive fixtures
at a production database.
