# Delivery app implementation report

Final local audit: 11 October 2026. Phase 11 is complete. Production deployment,
commit and push have not been performed. The working tree includes cumulative
changes from earlier approved phases as well as this audit.

## Implemented behavior

The delivery app provides session-protected Home, Deliveries, order details,
History and Profile pages. Current assignment ownership controls pickup,
failure, GPS sharing and completion. Admin dispatch supports assignment,
reassignment, availability, tracking and verified cash reconciliation.

Completion requires the customer's six-digit, hashed, expiring PIN. Failed
attempts are persisted and throttled. COD completion records the full order
amount atomically with the delivery proof and payment/status changes. Duplicate
completion or cash reconciliation does not create a second financial record.
Customer tracking exposes only the customer's own order and current permitted
tracking information. History and Profile use verified completion/cash records.

Maps show the saved destination, optional rider position and an explicitly
requested driving route. GPS sharing is opt-in, periodically refreshed, limited
to the current out-for-delivery assignment and cleared on terminal transitions,
reassignment, stop and logout. Expired samples are excluded from reads.

Installation/PWA features were removed at the user’s request after the audit.
Delivery pages now run as ordinary web pages, with no install prompt, manifest,
service worker, asset cache or offline fallback. GPS still requires HTTPS in
production.

## Phase 11 corrections and files

- `public/assets/js/delivery-dashboard.js`: a late response no longer repaints
  Home after the page becomes hidden or offline. Regression added in
  `tests/frontend/delivery-dashboard.test.cjs`.
- `app/Commands/PruneDeliveryLocations.php`: expiry pruning now drains all expired
  rows in batches of 1,000 using one fixed cutoff. Previously a larger backlog
  survived a single run. `tests/deployment/delivery-location.php` reproduces the
  backlog and verifies removal of 1,002 expired rows.
- `tests/deployment/concurrent-admin.php` and `.cjs`: added independent-connection
  delivery races for duplicate completion/reconciliation, wrong-PIN attempt
  limits, duplicate/conflicting GPS, terminal state versus GPS, and pickup versus
  reassignment.
- `tests/deployment/delivery-performance.php`: new temporary-table query-growth
  and index-plan checks using 1,000 orders, 2,000 assignments and 10 riders.
- `tests/deployment/apache-smoke.cjs`: Apache gateway checks retained; removed
  assertions for the withdrawn PWA resources.
- `tests/deployment/delivery-pin-session.cjs`: disposable preview resets safely;
  fixture state is persisted atomically before the HTTP response. Synthetic GPS
  stays confined to the CLI preview fixture; PWA diagnostics were removed.
- `deployment/README.md`, `tests/README.md` and this report document the audit,
  retention behavior and release requirements.

## Database changes

Phase 11 adds no migration, index or production data change. Tests use synthetic
data in connection-local temporary tables or an explicitly owned disposable
database. Deploy the existing PIN, completion and GPS migrations from earlier
phases before enabling the corresponding application code on a hosted database.

## Validation results

- Frontend: 138 tests passed after PWA removal, zero failures.
- PHPUnit: 24 tests, 32 assertions passed.
- Delivery service fixtures passed: dashboard, dispatch, coordinate/schema
  validation, verification PIN, completion/cash, location retention, customer
  tracking and history. Admin and delivery performance fixtures passed.
- HTTP fixtures passed for admin route/session security, competing dispatch
  changes and the delivery/customer workflow, including ownership, CSRF,
  revoked assignments, PIN rotation/attempts, GPS, cash, history and Profile.
- Independent MySQL connections: all 17 concurrency scenarios passed. These
  establish consistency for the tested races, not a production throughput target.
- Isolated XAMPP Apache gateway smoke checks passed, including private path
  denial and public asset responses. Root/subdirectory URL helper tests passed.
- Production-mode OTP fixtures passed: unavailable SMS fails closed, without
  development OTP disclosure or session privilege escalation. HTTPS was simulated
  in those HTTP fixtures; this does not validate a hosted certificate.
- PHP syntax checks and Git whitespace checks passed for audit changes.
- Browser checks: Home, Deliveries, details, History and Profile had no horizontal
  overflow at 320, 360, 390, 430 and 1280 pixels. Theme switching persisted across
  navigation. Details displayed the masked PIN field, COD acknowledgement and
  failure form. History/Profile loaded their scoped data.
- A live Google route using synthetic fixture coordinates returned 2.3 km / 9
  minutes; the next request reused its cache. Synthetic GPS sharing succeeded
  and stopping it returned the UI to tracking off. No device location was used.
Observed service/model query counts on the synthetic performance fixture:
Home 9, Profile 12, History 3 for either 1 or 50 rows, dispatch 2 after its initial
metadata check (3 on first access), active-list 1 and tracking ownership read 1.
These exclude request authentication overhead. EXPLAIN used rider assignment,
order/latest-assignment, cash-owner and expiry indexes. No new index was required.

Local screenshot evidence is ignored by Git under `build/delivery-qa/`:
`phase11-map-mobile.png`.

## Release requirements and unresolved limitations

1. **SMS integration is a release blocker for production phone OTP login.**
   Configure and implement a real SMS provider and validate delivery, retries and
   failure handling. The current production path intentionally fails closed.
2. Deploy the cumulative application, migrations, installed PHP dependencies,
   hidden Apache rules together. Follow `deployment/README.md`
   for the actual domain/document-root layout. Use PHP 8.2+, required extensions,
   writable session/runtime directories and a private production `.env`.
3. Serve the hosted app over valid HTTPS, enable production HTTPS/session cookie
   settings and verify redirects, CSRF, login and authenticated API headers on
   the real host. Production GPS requires a secure context.
4. Schedule `php spark delivery:prune-locations` every five minutes with the
   production environment/database profile. Monitor successful runs and expiry
   backlog. No hosted scheduled job was installed during this audit.
5. **Maps website restrictions are deferred by the user: "for now all".**
   The live browser key permits every website and currently allows 32 APIs.
   No key settings were changed. Maps JavaScript, Routes, Places (New) and
   Geocoding were present in the allowed API list; live routing worked locally.
   Narrow website/API restrictions when deployment origins are known, following
   [Google's API security guidance](https://developers.google.com/maps/api-security-best-practices).
   A public browser key must not be treated as a private server credential.
6. Test GPS permission denial/timeouts, route errors and network loss on physical
   Android and iPhone devices. Browser viewport checks do not replace those tests.
7. Real SMS, production TLS/gateway behavior, hosted cron, physical devices and
   production traffic/load testing remain unexecuted. The location watcher depends
   on a visible, running browser page; OS background suspension remains a limitation.

Credentials were not copied into this report or test data. `.env` remains ignored
by Git. This audit does not establish a complete machine-wide credential inventory.

## Next step and approval

The numbered implementation plan ends at Phase 11. The proposed next work is
production readiness: SMS integration, hosted HTTPS acceptance, scheduled
GPS cleanup and physical-device testing. Obtain user approval for that scope
and for any commit, push or production deployment before performing them.
