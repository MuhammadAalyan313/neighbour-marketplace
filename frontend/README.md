# Neighbour Marketplace — Next.js frontend

Source delivery v0.2.2. Mobile-first storefront, buyer, seller and admin screens, using the Laravel 12 `/api/v1` contract from the supplied `routes.zip`. No backend files have been changed. This is an integration-ready first implementation, not a verified production release.

## Run locally

Requirements: up-to-date Node.js 22 LTS (22.13+) or 24 LTS, npm, and a running Laravel backend with its database and seeded data. In the existing backend, configure the database, application URL, mail, storage and queues as appropriate; use its own setup instructions. For local API access, for example, run `php artisan serve --host=127.0.0.1 --port=8000` from the backend directory. Do not run migrations against a production database without a reviewed deployment plan. For product photos, Laravel must serve its public storage (normally `php artisan storage:link`).

From this frontend directory:

```sh
npm install
npm run setup
npm run doctor
npm run dev
```

Open http://localhost:3000. Configure `LARAVEL_API_URL` to your Laravel API root and `NEXT_PUBLIC_MEDIA_ORIGIN` to the browser-accessible storage origin. Keep `APP_ORIGIN` aligned with the actual frontend origin, without a trailing slash. Use HTTPS and the production cookie configuration in deployment.

```sh
npm test
npm run typecheck
npm run build
npm start
```

Next.js 16.4.6, React and React DOM 19.2.7 are pinned. The dependency lockfile is deliberately not fabricated: this environment could not reach the npm registry. Generate and commit `package-lock.json` after your first successful installation and use `npm ci` thereafter. Review installed dependency versions and security advisories before deploying.

## Architecture

- Next.js App Router, React 19, JavaScript/JSX UI with TypeScript interfaces for key API contracts. Native CSS tokens and layouts rather than a heavy component library; no additional runtime state or chart packages. The public homepage uses a server-side read with short caching, avoiding redundant hydration requests when data is present. Other data-driven screens use a small cancellable fetch hook and polling where appropriate.
- `src/app/(market)`: storefront, stores, products, auth, cart, checkout, tracking. `src/app/account`, `seller`, `admin`: independent role layouts and screens. `src/components`: shared design system, navigation, forms, feedback. `src/features`: business-domain UI. `src/lib`: request adapter, contracts, cart and proxy policy.
- Browser calls same-origin `/api/backend/...`; the Next.js route handler forwards only allowed Laravel API paths to the configured server. Laravel remains responsible for permissions, stock, status transitions, totals and payments. Sanctum token is stored in an HttpOnly, SameSite=Lax cookie, with Secure in production; token removed from auth JSON returned to the browser. Mutations check the exact request origin. Guest tracking credential is sent in a POST body to Next.js, then in the Laravel-required query parameter server-side. Do not log upstream request URLs containing access tokens.
- No fabricated data, no test-user auto-login, no fake gateway. Cart estimates are local; authoritative checkout preview and order creation use Laravel. Guest credentials are displayed after successful checkout; users must save them privately. The tab-scoped marker prevents immediate resubmission after an uncertain checkout; it is not a replacement for server-side recovery. Guest duplicate-key recovery requires backend changes.
- Mobile bottom navigation, drawer, safe-area padding, responsive grids, compact records, reduced-motion handling, loading/error/empty states and accessible semantic controls. Installable-style manifest and icons are provided; offline service worker and native push are not included. Install behavior varies by browser.

## Scope / verification

See `docs/API-MAP.md` and `docs/QA.md` for integration coverage, backend constraints, test results and remaining work. There is no live deployment in this ZIP. v0.2 has 16 passing Node tests (including 9 HTTP fixture tests and one cart regression), 55 syntax-checked source modules, and 24 passing Chromium CSS fixture checks. Full Next.js build, actual React browser QA, live Laravel end-to-end testing and measured Core Web Vitals are still pending. See `docs/QA.md` for exact test boundaries and commands.

## Validate against your own local backend

After configuring and starting Laravel, run `npm run smoke:api` (read-only checks for public endpoints and anonymous access control). To point to a different backend, set `LARAVEL_API_URL` in the shell. Then install npm dependencies and run the build/test commands above. For optional CSS fixture checks, install Python Playwright and its Chromium (`python -m pip install playwright`, `python -m playwright install chromium`), then `python tests/responsive_fixture.py`. On systems with an existing browser set `CHROMIUM_PATH` to its executable. These fixtures use synthetic HTML and cannot verify React runtime behavior.

## v0.2.1 — Login transport hotfix

- Replaced the server-side upstream fetch transport with Node HTTP/HTTPS to avoid the reported Undici socket-end assertion path. Responses are fully consumed with an 8 MiB limit, redirects are rejected and mutations are never automatically retried. Public server data uses the same transport (the former fetch-based revalidation is no longer applied).
- Dashboard aliases: `/admin/dashboard` → `/admin`, `/seller/dashboard` → `/seller`, `/buyer/dashboard` and `/account/dashboard` → `/account`.
- 21 Node tests passed, including local HTTP fixture tests for large error responses, truncated responses, timeouts, redirects and dashboard aliases. Live Laravel login and Next.js production build have not been verified here.
- If login still returns 502, check `storage/logs/laravel.log` in the backend and the PHP server terminal. A Laravel 500 is not fixed by changing the HTTP client. Never share tokens, passwords, OTPs, or full raw logs.
- Use your existing `.env.local` unchanged if it works for public APIs. Restart the Next development server after applying the update. No Laravel changes or database reset required.

## v0.2.2 — Clean installation release

Extract into a **new directory** and open the folder containing `package.json` in VS Code. Do not merge it with an older project or copy its `node_modules`, `.next`, or lockfile. Copy only your working `.env.local` if it is customized, or run `npm run setup` to create it without overwriting an existing file.

Windows PowerShell (also works in regular shells):

```sh
node -v
npm install
npm run setup
npm run doctor
npm test
npm run dev
```

If VS Code shows modified Next.js `Warning.js` again, close the internal dependency files, save only your own app files, and inspect your editor snippets/extensions or coding agents that may write into dependencies. Try `code --disable-extensions .` to isolate extension interference. Do not insert React components into framework files. `npm run doctor` detects the specific inserted React component shown in the reported screenshot and checks installed framework versions. It does not prove all dependencies are unmodified, and does not disable application diagnostics. A successful install generates a lockfile; retain it and use `npm ci` for subsequent clean installs.

The dev/build commands automatically invoke the doctor. `npm run smoke:api` reads `.env.local` and uses the native HTTP transport. The session refresh is deduplicated and login/register submission waits for initial authentication to settle. All previous storefront, buyer, seller, admin, checkout and API proxy source files are retained; no backend changes.

Verification in the packaging environment: 23/23 Node tests passed; 64 JS/JSX/TS/MJS source/test/script files parsed without syntax errors. These are source and fixture tests only. Registry DNS failed, so dependencies could not be installed, and the exact dependency combination, Next.js build, real browser runtime and Laravel authentication were not verified. No lockfile or dependencies are bundled. Run `npm run verify` locally after installation for real typecheck and production build.
