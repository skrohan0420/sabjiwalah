# Sabjiwalah

Sabjiwalah is a grocery and vegetable delivery application built with CodeIgniter 4, PHP, MySQL, and server-rendered CI4 views.

The current priority is backend foundation first: database structure, authentication, authorization, routing, models, services, migrations, seeders, and simple working pages. Frontend design, categories, and payment gateway work are intentionally out of scope for the first milestone.

## Project Status

Current phase: admin panel Phase 11 (local hardening and final regression testing) completed. Production rollout is blocked until SMS verification is integrated and hosted deployment checks pass.

Done:

- Phase 11B completes the remaining local hardening review. Customer sign-out is now a CSRF-protected POST form; GET `/logout` cannot change the session. Production cookies enforce Secure, with existing HttpOnly/SameSite protection, and periodic session rotation destroys the prior ID. A required response filter supplies private/no-store to pages, APIs and authorization denials. Framework CSRF exceptions already carry no-store. Automatic page-cache filters were removed to avoid serving session-specific content before authorization; only valid generated product media keeps explicit public caching. Apache also blocks generated `build/` reports and Composer lock files.
- Development OTP checks also reject forwarded/proxy headers and public Host values, closing testing-code exposure through common loopback tunnels. Local testing requires localhost, 127.0.0.1 or IPv6 loopback. A proxy that strips every forwarding header and rewrites Host cannot be distinguished from a direct loopback client; do not publicly expose opted-in development. Production always rejects testing OTPs regardless of host/header configuration.
- Real independent-process MySQL testing exposed a first-assignment range-lock conflict. Dispatch now rolls back deadlocks/lock timeouts and returns a reload-required 409 rather than a generic server failure. Current-assignment reads handle failed queries explicitly. The rider workload API returns active ready/out-for-delivery orders rather than repeatedly transmitting historical customer contacts; historical detail remains available only to the latest assigned rider.
- Phase 11B checks: `node tests/deployment/admin-security-session.cjs` proves 105 guest/customer/rider denials across all 35 admin API routes, all admin page restrictions, CSRF-protected logout, private success/error responses, multipart role protection, customer/rider IDOR and unsupported methods. `node tests/deployment/concurrent-admin.cjs` races independent PHP processes/connections on an owned disposable local MySQL database: status/history, rider capacity, competing assignments, last stock unit, order capacity, last coupon use and shared phone/IP OTP limits all pass. Production OTP fixtures verify cookie attributes, old-session revocation and periodic-ID replay denial; their HTTPS server flag is simulated, not actual TLS.
- `php tests/deployment/admin-performance.php` measures dashboard five queries with eight-row lists, order listings four queries and customer/rider listings three queries at both one and 100 rows. Existing UID/date/status/assignment indexes were reviewed; order sorting indexes are usable. The small temporary fixture's optimizer chooses a scan for recent orders, so this is query-budget/index verification, not a production latency benchmark. Order/dispatch polling remains eight seconds and dashboard polling thirty seconds with existing visibility/overlap guards. Global order-acceptance locking and all-time dashboard aggregates need hosted load monitoring; shared response caching is unsuitable for personalized admin data.
- Composer dependencies are installed and pinned in `composer.lock`; `composer test` invokes project PHPUnit rather than XAMPP's obsolete global runner. The default database-free suite passes 24 tests/32 assertions. The optional SQLite example suite passes two tests/three assertions with SQLite enabled for that command only. All 79 frontend tests, fifteen focused PHP deployment checks plus production OTP mode, upload checks and seven HTTP flow regressions pass. An owned loopback Apache server passed pages/assets/API/private-file/legacy-redirect smoke tests; no hosted configuration was changed. Browser verification confirmed customer POST logout and guest state at 390px without horizontal overflow; previews were stopped.
- Remaining rollout limits: SMS integration is required before production login/checkout; hosted TLS, PHP extensions, private-directory restrictions, storage permissions, backups and realistic throughput still require deployment verification. Order/assignment/settings/cash histories cover their workflows, but product/customer/personnel/campaign edits do not yet have a complete general administrator audit trail. Retained product uploads need an operator cleanup policy. Local checks do not certify production readiness.

- Phase 11A centralizes login and checkout OTPs in `OtpService`: only explicitly opted-in development requests from a direct loopback address can issue testing codes. Production rejects issuance, verification and checkout receipts even when the development flag is enabled. No SMS provider exists yet, so production phone login and checkout remain unavailable (HTTP 503) until provider integration. Production also revokes legacy sessions and sessions authenticated through testing OTPs; deployment does not retain old insecure login cookies. See `deployment/README.md` for rollout and local login instructions.
- OTP challenges store password hashes, expire after ten minutes and consume the code on successful verification. Checkout retains only a phone-bound verified receipt; auth and checkout challenges cannot substitute for each other. Login rotates the session ID, and login/logout clear checkout verification and coupon state. Strict Indian mobile inputs and six-digit codes replace last-ten-digit truncation on OTP paths. OTP/identity/CSRF responses use private/no-store; login redirects reject unsafe destinations, and form failures do not flash submitted OTPs into the session.
- Database-backed OTP budgets survive resends, logout and new browser sessions. Each phone/purpose permits five sends and five verification submissions per fifteen-minute window, including successful submissions; phone resends wait sixty seconds. Shared IP budgets permit twenty sends and sixty verification submissions per window across auth/checkout. Limits return HTTP 429 with Retry-After; missing/broken rate storage returns generic 503 without code or database details. Frontend issuance blocks duplicate sends, phone changes and verification while a send is pending, without automatic retries on uncertain/rate-limit failures.
- Additive migration `2026-10-10-000005_CreateOtpRateLimits.php` creates `otp_rate_limits` with hashed bucket keys and an expiry index. Applied locally; migrate other installations before deploying. `php spark otp:prune` deletes at most 1000 expired rows per invocation while preserving active resend cooldowns; operators should run it periodically and repeat batches if needed. No recurring job was installed.
- Phase 11A checks pass: development/production `php tests/deployment/otp-security.php` and `node tests/deployment/otp-security-session.cjs` modes test opt-in/IP gates, expiry/hash/single-use/phone binding, session rotation, production legacy-session revocation, CSRF/roles, strict input, cross-session limits, resend persistence, inactive admins, storage failure closure and pruning on disposable SQL/storage. Production HTTP fixtures intentionally use loopback SQL and disable HTTPS redirection only inside the fixture; they test authentication behavior, not TLS deployment. All 79 frontend tests pass. Existing checkout, campaigns, dispatch, customers, delivery-personnel and PIN/completion HTTP regressions pass. Browser testing confirmed local sample-admin login reaches protected settings, and the isolated preview was stopped. Multi-worker contention and project PHPUnit setup remain for Phase 11B.

- Phase 10 adds `/admin/settings` and admin-only GET/PUT `/api/v1/admin/settings`, with active-session/role checks, CSRF, strict validation, stale-revision conflicts and private/no-store responses. Controls cover shop closure, order acceptance pause, delivery charge, nullable free-delivery threshold, minimum subtotal, paired daily ordering hours in Asia/Kolkata (including overnight ranges) and nullable active-order capacity. Blank hours mean all day; closing time is exclusive. Capacity counts pending, confirmed, preparing, ready-for-delivery and out-for-delivery orders; completed/cancelled/failed deliveries do not count. Service-area rules and notification preferences are deferred because supporting enforcement workflows are absent.
- `OperationalSettingsService` centralizes rules used by checkout quotes and order placement. A singleton row lock serializes new-order acceptance/capacity checks with settings updates. Quotes include the settings revision; stale quotes require review, blocked ordering is explained to the customer, and OTP verification cannot enable a blocked checkout. Delivery fees/free thresholds and minimum order use subtotal before coupons. Guest cart previews receive public pricing rules from the cart API and server-rendered page rather than hardcoded charges. Existing order financial snapshots are retained. This global checkout lock prioritizes consistent capacity enforcement; multi-worker contention/throughput remains unmeasured.
- Additive migration `2026-10-10-000004_CreateOperationalSettings.php` creates the singleton `operational_settings` and `operational_setting_history` audit tables. Applied locally; run `php spark migrate` on other installations before deploying the code. Defaults preserve open/unpaused all-day ordering, ₹40 delivery, free delivery from ₹499 subtotal, no minimum and unlimited capacity. Changed settings and administrator/before/after history commit atomically; no-op saves retain the revision without creating history. The UI shows the latest ten changes, protects drafts during refresh and blocks repeat saves after conflicts or uncertain failures until refreshed.
- Phase 10 checks: `php tests/deployment/operational-settings.php` covers validation, active-admin authorization, stale/no-op writes, audit rollback, daily/overnight boundaries, order closure/pause/minimum/capacity guards, stale quotes, configured persisted fees, terminal-status capacity release and retained historical pricing on temporary SQL. The extended `node tests/deployment/checkout-offers-session.cjs` verifies actual HTTP settings role/CSRF/input/revision/audit contracts and checkout rejection/public guest pricing on disposable SQL/storage. All 77 frontend tests pass, including settings drafts/overlap/uncertain saves/safe history, disabled checkout after OTP and guest disabled/zero/inclusive free thresholds. Checkout-offer SQL regression, PHP/JS syntax and route-filter checks pass. Browser sample saves and audit updates passed at desktop/mobile widths; 390px viewport had no horizontal overflow. Development OTP exposure/attempt limits, Composer PHPUnit setup and multi-worker stress remain for final hardening.

- Phase 9B connects customer-only POST/DELETE `/api/v1/checkout/offer` to session-selected coupon codes. Checkout summary, customer page and order creation share `CheckoutSummaryService::quote`; all discount arithmetic comes from server product prices in cents. One coupon per order supports fixed/percentage values, minimum subtotal, optional cap, active state, inclusive start/exclusive end and global usage limits. Discounts cannot exceed the subtotal. Existing delivery charges/free-delivery threshold use the subtotal before discounts. Unknown checkout fields, including injected prices/discounts, are rejected. New checkout clients send a quote fingerprint; stale product prices, quantities or coupon settings produce a conflict requiring review. Selected invalid/exhausted coupons remain visible with an error and cannot silently become full-price orders. No-coupon legacy clients remain compatible without a fingerprint.
- Order placement locks the active customer and product rows in deterministic order, calculates prices from locked product snapshots, locks the offer and uses a current-read redemption count. Stock, order, items, history and redemption commit together or all roll back. Additive migration `2026-10-10-000003_CreateOfferRedemptions.php` adds one redemption per order with offer FK/index and immutable code/discount snapshots; applied locally, run `php spark migrate` on other deployments. Only committed orders consume usage, and later cancellations retain usage. No historical records are backfilled and no automatic discount stacking/refund/reversal engine is introduced. Coupon/OTP state clears on login/logout and coupon selection clears after a committed order.
- Customer checkout now provides apply/remove controls and coupon savings in the bill. Cart updates request fresh server totals; older reads cannot overwrite newer totals. Placement is blocked during refresh, invalid coupons and overlapping mutations. Uncertain placement is not repeated automatically and directs the customer to check orders. Successful placement resets local OTP state. Promotions with placement `home`, active state and eligible schedules appear under Store highlights, up to eight newest records, with safe image/destination URLs and escaped titles. Legacy unsafe URLs are excluded. Other placement labels stay management-only. Changes to schedules take effect on subsequent page requests.
- Phase 9B checks: `php tests/deployment/checkout-offers.php` verifies discount rounding/caps/subtotal clamping, dates/minimum/usage, stale price/offer quotes, rollback after a late storage failure, persisted order/stock/redemption snapshots, cancellation usage retention, no-coupon compatibility and safe scheduled promotion rendering. `node tests/deployment/checkout-offers-session.cjs` tests the complete HTTP cart → coupon → OTP → order flow, apply/remove/minimum changes, roles/CSRF/privacy, input tampering, competing last-use submissions and logout clearing against temporary SQL/storage. Its single-worker server serializes requests; multi-worker database stress is still untested. Five frontend checks cover authoritative totals, late reads, invalid coupons, overlap, stale quotes, uncertain placement and OTP reset. Browser sample checks exercised a promotion link, cart additions, coupon savings, mobile/desktop layout, OTP and successful discounted COD order placement. All 71 frontend checks passed; prior order, dispatch, PIN-coordinate and delivery-completion SQL checks passed. Existing development OTP exposure/attempt-limit and project PHPUnit setup issues remain for the final hardening phase.

- Phase 9A adds `/admin/offers` and `/admin/promotions` with search, pagination, schedule-status filters and create/edit dialogs. Admin-only GET/POST `/api/v1/admin/{offers|promotions}` and GET/PUT detail routes reuse active-session, role and CSRF protection. Campaigns can be disabled without deleting records. New forms default to disabled; effective schedule states are disabled, scheduled, enabled or expired, with exclusive end times. Browser date inputs use the device timezone, API dates require explicit offsets, storage uses the app timezone and list dates display IST.
- Campaign validation rejects unknown fields, invalid payload shapes, blank titles/names/codes, duplicate coupon codes, percentages above 100, invalid monetary precision/ranges, nonpositive caps, invalid usage limits, invalid dates and end-before-start schedules. Offer codes normalize to uppercase. Promotion image/destination fields accept HTTPS URLs without credentials or site paths; dangerous schemes, protocol-relative URLs, whitespace/control characters and backslashes are rejected. URLs render as inert text in admin lists. Edits use a locked record and server revision to reject stale saves; ambiguous failures require closing/reloading before retrying. API serializers expose UIDs rather than database IDs. Existing offer/promotion tables are reused; no migration or real campaign records were changed during verification.
- Phase 9A originally stopped at campaign management with checkout integration pending; Phase 9B above now connects discount/usage enforcement and storefront highlights. `php tests/deployment/admin-campaigns.php` checks validation/persistence/scheduling against connection-local temporary tables. `node tests/deployment/admin-campaigns-session.cjs` checks actual HTTP create/edit/deactivate, duplicate/stale writes, roles, CSRF and privacy using temporary SQL and isolated storage. The single-worker fixture does not demonstrate multi-worker contention. `node --test tests/frontend/admin-campaigns.test.cjs` checks safe rendering, revision submission, overlap and uncertain failures. Browser sample checks cover offer creation/editing and promotion editing on mobile and desktop.

- Phase 8B adds POST `/api/v1/delivery/orders/{uid}/complete` with only `pin` (six digits) and `cash_received` (JSON boolean). Only the active rider on the newest assignment can complete an out-for-delivery order. The server verifies the customer-issued PIN and 30-minute expiry. Five incorrect attempts exhaust a 30-minute window starting at the first failure; failures persist even though completion is rejected. Replacement PINs preserve this budget and cannot be issued during lockout. PINs never appear in rider/admin reads or status-history notes.
- Completion locks the order/account/assignment/PIN and atomically writes a unique `delivery_completions` record, delivered status/history, assignment completion timestamp, and COD payment status `paid`. COD requires confirmation of the full stored order total; client amounts are rejected. Existing paid non-COD orders can complete without a cash amount. Duplicate completion by the same current rider returns success without duplicate cash/history, even after PIN expiry. Generic admin/rider status updates to `delivered` now reject unverified completion; existing callers must use the new completion endpoint. `delivery_failed` requires a nonblank reason in existing notes/history. Pickup and preparation transitions remain available.
- `/delivery` now lists current active deliveries with safe customer/address rendering, pickup, PIN entry, COD checkbox, failed-delivery reason and POST sign-out. Refresh is manual and confirms discarding entries. Entered PINs clear immediately on submit and private content clears on departure; restored pages reload. Ambiguous failures disable writes until refresh. Workload counts refresh from the current list. `/admin/cash` and admin-only GET `/api/v1/admin/cash` provide paginated order/rider search, pending/reconciled filters and all-time recorded cash totals. POST `/api/v1/admin/cash/{order_uid}/reconcile` records confirmed cash handover with server-selected amount, active admin and timestamp; duplicate reconciliation preserves the original actor/time and creates no extra history. Order history records the cash handover separately from delivery completion. Reconciliation is a staff acknowledgement, not a bank transfer or accounting integration; legacy delivered orders without cash records are excluded and no totals are invented.
- Additive migration `2026-10-10-000002_AddVerifiedDeliveryCompletion.php` adds PIN failure count/window fields and the completion/cash table with order primary key, assignment/collector/admin FKs, timestamps, immutable DECIMAL cash amount and reconciliation index. Applied locally; hosted installations need `php spark migrate` before deploying these controllers. No historical order/payment/assignment records were rewritten or backfilled. Reconciliation has no reversal/correction workflow in this phase.
- Phase 8B checks: `php tests/deployment/delivery-completion.php` exercises real SQL/services with temporary tables for ownership, unverified-completion rejection, expiry/lockout, COD confirmation, persisted attempts, rollback, duplicate completion/handover, payment/assignment/history and prepaid cases. The expanded `node tests/deployment/delivery-pin-session.cjs` covers actual HTTP role/CSRF/input restrictions, generic admin bypass rejection, failed reasons, wrong PIN, duplicate completion/reconciliation, ledger privacy and payment/history. `node --test tests/frontend/delivery-completion.test.cjs` covers PIN clearing, overlap, workload refresh, uncertain failures, page departure and safe/confirmed reconciliation. The prior order/dispatch tests now expect generic delivered transitions to be rejected. Browser sample checks exercised customer PIN → rider COD completion → admin handover, including mobile/tablet layouts. Multi-worker DB contention, refund/cash adjustment workflows and legacy COD recovery remain untested/out of scope; existing development login OTP exposure and missing login OTP attempt limits remain unresolved.

- Phase 8A adds customer-only POST `/api/v1/orders/{uid}/delivery-pin`, protected by active-session, customer-role and CSRF filters. The service locks the order and customer, rechecks ownership/account status, and allows issuance only for `out_for_delivery` orders. Payloads cannot set the customer, PIN or expiry. Cryptographically random six-digit PINs are returned once; only a password hash, issue time and 30-minute expiry are stored. Regeneration replaces the old PIN and is limited to once per minute per order, across customer sessions. Competing requests recheck the locked PIN row; storage errors roll back. No PIN or hash is added to admin/rider/order serializers.
- Customer `/orders` shows a Generate delivery PIN control on eligible orders. PINs stay in page memory, are cleared on expiry/departure, and are never saved to browser storage. Reloading requires generating a replacement after the cooldown. Requests use fresh CSRF tokens and base-aware URLs, avoid overlapping issuance and automatic retries, and report session/connection/cooldown errors. PIN responses and the customer orders page use private/no-store headers. The card uses a separate CSS class from the existing map-location icon.
- New additive migration `2026-10-10-000001_CreateOrderDeliveryPins.php` creates `order_delivery_pins` with order FK/primary key, hash and timestamps; it was applied locally. Run `php spark migrate` on hosted deployments. Existing orders, migration files and delivery status/payment behavior are preserved. At the end of Phase 8A, completion verification and COD were still pending; Phase 8B above now enforces them. Existing public development OTP exposure and missing login OTP attempt limits also remain unresolved.
- Phase 8A checks: `php tests/deployment/delivery-verification-pin.php` uses temporary SQL to test ownership/roles/status/input restrictions, hash-only storage, expiry timestamps, cooldown/rotation, simulated interleaving, rollback and safe view rendering. The existing `delivery-pin.php` coordinate check is preserved. `node tests/deployment/delivery-pin-session.cjs` tests actual HTTP role/ownership/CSRF restrictions, competing issuance, storage privacy and customer page responses with temporary tables/storage. Its single-worker PHP server serializes requests; multi-worker database contention is not load-tested. `node --test tests/frontend/delivery-pin.test.cjs` tests fresh CSRF/base paths, overlap prevention, leading zeros, expiry/departure clearing, cooldowns, ambiguous failures and response/session validation. Use `--preview` in an interactive terminal for an isolated customer sample on port 8771 (phone 9000000010); type `stop` or Ctrl+C to close. Set `PHP_BINARY` for a non-XAMPP PHP path.

- Phase 7 adds `/admin/dispatch`, admin-only GET `/api/v1/admin/dispatch`, GET `/api/v1/admin/dispatch/candidates`, and POST `/api/v1/admin/orders/{uid}/assignment`. Dispatch has paginated order-number search, ready/out-for-delivery and assigned/unassigned filters, current rider, pickup progress, elapsed status time and attention flags. The queue polls every eight seconds while visible, avoids overlap, retains filters and failed-response snapshots, and leaves the rider picker intact. Assignment forms require confirmation and reload after conflicts or ambiguous failures. Order links open existing order details/history; Delivery Management controls availability.
- Assignment payloads accept only `rider_uid` and `expected_assignment_uid` (`none` when unassigned). The server locks the order, revalidates the active admin actor, compares the latest assignment, and locks the selected rider before availability/capacity checks. New assignments require an active delivery-role account with staff-set `available` and zero current workload. This initial workflow gives each rider one current order at a time. Current reads before the rider lock avoid stale transaction snapshots; capacity reads start after that lock. Status/availability controls share the account lock. Stale/duplicate/conflicting assignments return 409, and insertion/commit failures roll back. A request matching the current rider and current assignment is a no-op. Existing assignment rows preserve who assigned whom and when; no new schema was needed.
- Orders can be assigned/reassigned only in `ready_for_delivery`. In-transit and terminal orders cannot be reassigned; cancelled, delivered and failed orders cannot receive new assignments. Assignment does not advance status. The existing rider status endpoint records pickup through `OrderService` (`ready_for_delivery` to `out_for_delivery`), followed by existing delivered/failed transitions. Failed deliveries remain visible in order management; retry/recovery transitions are not invented. The existing admin status API remains compatible with its current transition rules.
- Delivery list/detail authorization now follows the highest assignment ID instead of any historical assignment. Reassignment revokes the former rider's order addresses/details and status actions while preserving staff-visible history. `DeliveryOrderService` locks the order and active delivery actor, rechecks latest ownership, then delegates to `OrderService` inside the transaction. Optional `expected_status` adds stale-update protection to the rider API. The rider dashboard counts current workload, and the admin order list shows the latest rider, including completed deliveries. Delivery responses use private/no-store headers.
- Delay flags are review thresholds, not promised delivery times: ready orders at least 15 minutes in status, and out-for-delivery orders at least 30 minutes, based on `orders.updated_at` in the configured storage timezone. Assignment does not reset the wait clock. The UI reports India time. Candidate/queue responses omit customer contacts/addresses and internal IDs. Existing indexes are reused. Deploy after the Phase 6 profile-table migration; Phase 7 has no migration.
- Phase 7 checks: `php tests/deployment/admin-dispatch.php` uses temporary SQL for queue/candidates, privacy, stale/duplicate/capacity conflicts, reassignment history, latest-owner access, pickup/delivery transitions, simulated interleaving and rollback on assignment/history failures. `node tests/deployment/admin-dispatch-session.cjs` tests actual HTTP competing assignment requests, capacity, former-rider revocation, pickup/failed-delivery progress and inactive session denial. Its PHP fixture serializes HTTP requests; this is not a multi-worker database load test. Add `--preview` in an interactive terminal for sample UI on port 8770; type `stop` or use Ctrl+C to close. `node tests/deployment/admin-dispatch-api.cjs http://localhost:8080/` checks live role access, private contracts, CSRF, validation and missing records without changing assignments. `node --test tests/frontend/admin-dispatch.test.cjs` covers polling, filter/detail races, safe rendering, conflicts, preserved selection and empty states. Desktop/tablet/mobile assignment and reassignment were checked with sample data. Existing development OTP exposure, missing OTP attempt limits and PIN/COD verification remain unresolved until their respective security/delivery phases.

- Phase 6 adds `/admin/delivery-personnel` and enables Delivery Management navigation. Admin-only `/api/v1/admin/delivery-personnel` supports GET listing, POST creation, GET `/{uid}` details/history, and PATCH `/{uid}/status` or `/{uid}/availability`. Lists have name/phone search, account-status filtering, deterministic name sorting and server pagination with batched workload/completion counts. Phone numbers are masked in lists; full phone/email appear only in the details dialog, which clears contacts on close. Responses exclude password hashes, OTPs, internal IDs and customer addresses/contacts and use private `no-store` headers.
- Delivery account creation accepts name, a valid Indian mobile number (10 digits or optional 91 country code) and optional email. Role is always `delivery`, status starts `inactive`, UID is generated by the existing model, and no password or OTP is generated by the management endpoint. Existing phone/email accounts cannot be overwritten or converted. User/profile creation is transactional. Admins review and explicitly activate the account; the delivery person uses the existing `/login` phone OTP flow. **The existing development OTP exposure and missing OTP attempt limits remain unresolved; privileged phone login is not ready for production until those are addressed.**
- Migration `2026-10-09-000002_CreateDeliveryPersonnelProfiles.php` creates `delivery_personnel_profiles` with a user FK/primary key, staff-set `availability` (`offline`, `available`, `unavailable`) and `updated_at`. Run `php spark migrate` on hosted deployments before using the module. Existing delivery accounts need no backfill and default to offline when no profile exists. Customer/admin schemas and existing assignment rows are preserved. No migration rollback was run locally.
- Availability is an administrative setting, not online-presence tracking. Active orders owned by the newest assignment determine `assigned`; at least one current out-for-delivery order determines `busy`. Inactive accounts always display `unavailable`. Current workload excludes delivered, cancelled and delivery-failed orders. Completed delivery counts use recorded assignment completion or a delivered order's latest assignment, accommodating legacy deliveries without a completion timestamp. Details switch between paginated current assignments and the full assignment history; past assignees retain history but do not acquire current workload. Existing order/rider indexes support these queries.
- Status/availability changes lock the delivery account in a transaction, require observed `expected_status` or `expected_availability`, and reject stale changes with 409. Only delivery UIDs are accepted; extra write fields and client-supplied assigned/busy states are rejected. Deactivation resets the staff-set state to offline atomically and the existing active-session filter revokes rider access on their next request. Current assignments/history remain available for staff; deactivation does not dispatch or reassign orders. The screen confirms writes and requires reload after conflicts/uncertain results. Availability is refreshed manually so drafts are not replaced. Account action audit logging is not added in this phase.
- Phase 6 checks: `php tests/deployment/admin-delivery-personnel.php` uses real controllers with temporary SQL tables to cover ownership/history counts, creation/privacy/validation, stale writes, session revocation and rollback on profile failures. `node tests/deployment/admin-delivery-personnel-session.cjs` verifies actual HTTP provisioning, OTP delivery-role access and session revocation using temporary SQL/storage. Add `--preview` for a disposable sample UI on port 8769; stop with Ctrl+C. `node tests/deployment/admin-delivery-personnel-api.cjs http://localhost:8080/` checks live authorization, CSRF, IDOR, private data and rejected mutations without changing records. `node --test tests/frontend/admin-delivery-personnel.test.cjs` covers rendering/races, history, conflicting writes, availability and creation drafts/uncertain outcomes. Set `PHP_BINARY` when PHP is not at the default XAMPP path.

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

- Integrate a real SMS verification provider, then perform hosted deployment acceptance and realistic load checks before production rollout.
- Refine customer frontend styling across product listings, auth, account, and future cart pages.
- Extend automated feature coverage as new workflows are added; project PHPUnit and isolated SQL/HTTP regression runners are available.
- Add customer saved address management.
- Add saved address selection to checkout.
- Add customer order history and order detail pages.
- Add order creation and status update workflows.
- Add delivery assignment workflows.
- Add a small frontend JavaScript API client/helper for `fetch('/api/v1/...')`.

Not started yet:

- Customer order history.
- Hosted production acceptance and SMS verification integration.
- Delivery order workflow.
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
