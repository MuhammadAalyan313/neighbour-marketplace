# v0.2 validation and release checklist

## Verified in this environment

- `npm test`: **16/16 passed**. Original 6 pure logic tests plus 9 tests using a local Node HTTP fixture server and 1 cart restoration regression. The proxy core used in the actual Next route handler was exercised over real HTTP to a synthetic API; no Laravel process was involved. Cases: query preservation, access restrictions, auth token removal, credential forwarding, guest tracking, guest checkout payload, account-session check, expired/inactive account rejection, invalid JSON / upstream error redaction.
- TypeScript `transpileModule` parsed **55 source modules** with zero syntax errors. No full typecheck or Next production build performed.
- Chromium rendered the actual stylesheet against three representative HTML fixtures (homepage, checkout, dashboard) at 320, 360, 390, 768, 900, 901, 1024, and 1440 CSS pixels. Initially found 768px header overflow in 2 fixtures; after changing the mobile/tablet breakpoint and spacing, **24/24 passed**. Tests assert document horizontal bounds, selected component bounds, correct bottom-nav visibility and last action not covered by the bottom nav. Raw results: `responsive-fixture-results.json`. Test source: `tests/responsive_fixture.py` and `tests/fixtures/responsive.html`. These are CSS fixture tests, not React/Next runtime, Safari, native device or accessibility certification.

## v0.2 changes

- Split backend proxy into independently testable core and thin Next route adapter; preserved allowed paths, origin validation, no-store and HttpOnly cookie handling.
- Account checkout now checks session validity before forwarding an order; guest/account intent is removed before Laravel sees the payload. This prevents common expired-session fallback cases, but a server-side transaction-level account invariant is still recommended to close revocation races.
- Synchronous duplicate-submit guard, idempotency key fallback for browsers without `randomUUID`, selected payment validation and malformed-response handling. Existing uncertain guest checkout recovery limitation remains.
- Request-generation isolation in the data hook, no overlapping polls, and avoid redundant hydration fetch when initial server data is provided.
- Reject corrupt or duplicate persisted cart entries; current device session cannot be revoked from the list (use sign out).
- Tablet breakpoint 900px, mobile 44px add/quantity controls, mobile input font size, long-price wrapping, keyboard drawer focus/escape handling.

## Runtime blockers

- Retried `npm install --fetch-retries=0 --fetch-timeout=12000 --no-audit --no-fund`: failed with `EAI_AGAIN registry.npmjs.org`. Next/React dependencies unavailable. No generated lockfile, build output or Lighthouse score.
- Extracted supplied Laravel application and vendor files to a temporary directory. `php artisan --version` failed at `Illuminate\Support\mb_split()` because PHP mbstring is missing. PHP also lacks PDO MySQL/SQLite drivers. No configured live database/API, so no actual backend flow or order transaction was verified. Backend source unchanged.
- Browser navigation to file/localhost was blocked by browser policy; stylesheet fixture rendered via Playwright `set_content` with embedded actual CSS and SVG asset. No live app navigation.

## Required verification after installation

1. Run `npm install`, `npm test`, `npm run typecheck`, `npm run build`; resolve any errors; commit generated lockfile.
2. Test local backend against staging/seeded database. Sign in with separate buyer, seller, admin test accounts. Verify unauthenticated and unauthorized access, email verification, expired/revoked sessions, and failed API responses.
3. Exercise public search, filters, pagination, store details, invalid slug, missing photo; cart quantities, out-of-stock change, multiple stores, pickup/delivery preview, every available payment method. Simulate timeout and duplicate click. Verify seller confirmation, cancellation and inventory restoration.
4. Exercise guest tracking with valid/invalid codes and registered order attempts. Verify tokens never enter client URLs or logs. Confirm private API requests are not cached. Validate cross-origin POST denial, cookie flags, server-side role enforcement and media upload constraints.
5. Exercise application approval/rejection, approved store creation, product CRUD including image upload, stock update, seller orders, subscription free activation, notifications, admin store status and pagination.
6. Test widths 320, 360, 390, 768, 1024, 1440; Safari iOS and Chrome Android; keyboard navigation, contrast, screen reader labels and dialogs; slow 4G and reduced motion. Verify fixed navigation never covers content.
7. Measure real-device and Lighthouse performance after live API integration: target LCP <=2.5s, INP <=200ms, CLS <=0.1 at the 75th percentile in field data. Do not claim a passing score until measured. No offline service worker or actual push verification yet.

## Reference research used

- https://nextjs.org/docs/app/getting-started/server-and-client-components
- https://nextjs.org/docs/app/guides/backend-for-frontend
- https://nextjs.org/docs/app/guides/production-checklist
- https://nextjs.org/docs/app/api-reference/functions/cookies
- https://laravel.com/docs/12.x/sanctum
- https://web.dev/articles/vitals

The supplied Laravel source is authoritative for API shape; official guidance informed rendering, BFF, token storage and performance targets. No dependency version or performance outcome was independently tested in the blocked environment.

## v0.2.2 clean package verification

23/23 Node fixture/unit tests passed, including two targeted dependency-corruption guard tests. TypeScript parser checked 64 JS/JSX/TS/MJS modules in src, scripts and tests; zero parse diagnostics. No production Next.js build or live Laravel test was possible here (npm registry DNS failure). Prior CSS fixture results remain historical, not a new browser test. Startup guard is targeted, not a full integrity audit.
