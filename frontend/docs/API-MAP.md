# API contract map and scope

Source: supplied Laravel `routes/api.php`, controllers, requests, resources and services. Paths below are relative to `/api/v1`. The proxy allowlist is in `src/lib/policy.mjs`; an allowed endpoint is not necessarily surfaced in UI.

| Frontend | Laravel integration | Status |
|---|---|---|
| Homepage / products | GET marketplace/categories, marketplace/products, marketplace/stores | Wired, source-level |
| Store detail / reviews | GET marketplace/stores/{slug}, stores/{slug}/reviews | Wired; backend rejects closed/inactive store details |
| Product detail | Product object from list or store response | Detail sheet, no public per-product endpoint |
| Login / register | POST auth/login, auth/register; GET auth/me | Wired, token via server cookie |
| Reset / verification | POST auth/password/forgot, auth/password/reset, auth/email/send-otp, auth/email/verify-otp | Wired |
| Sessions | GET auth/sessions, DELETE auth/sessions/{id}, POST auth/logout, auth/logout-all | Wired; initial sessions page only |
| Cart | Local product IDs and quantities | Implemented; no server cart endpoint |
| Checkout | POST checkout/preview, checkout | Wired; fulfillment pickup/seller_delivery; method from preview; idempotency key |
| Guest tracking | GET orders/{order_number}?access_token=... via server-only adapter | Wired; token is per order, not entire batch |
| Buyer orders | GET orders, orders/{order_number} | Wired; payment instructions and timeline |
| Review | POST orders/{order_number}/review | Create wired; edit/delete UI deferred |
| Seller application | GET/POST/DELETE seller-application | Wired |
| Notifications | GET notifications; PATCH notifications/{id}/read, notifications/read-all | Wired with 30-second foreground polling |
| Seller overview | GET seller/dashboard | Wired; actual stats and daily sales values |
| Store management | GET/POST/PATCH seller/store | Wired, approved application required for creation |
| Products / inventory | GET/POST seller/products; GET/PATCH/DELETE seller/products/{id} | Wired |
| Product media | POST seller/media (multipart type, image) | Wired |
| Seller orders | GET seller/orders, seller/orders/{number}; PATCH .../status, .../payment/confirm | Wired; backend enforces transitions |
| Seller payment configuration | PATCH seller/store/payment-methods | Wired; GET store resource lacks account settings; edit only selected method |
| Subscriptions | GET seller/subscription, seller/subscription/plans; POST seller/subscription/activate-free; PATCH seller/subscription/cancel | Wired; paid activation unavailable |
| Admin metrics | GET admin/dashboard | Wired |
| Admin users / stores | GET admin/users, admin/stores; PATCH admin/stores/{id}/status | List and store status wired |
| Admin orders | GET admin/orders, admin/orders/{number} | Wired |
| Admin applications | GET admin/seller-applications; PATCH .../{id}/approve, .../{id}/reject | Wired, rejection reason required |

## Backend-dependent gaps / next pass

- Wishlist, distance/radius filtering, public product permalink endpoint, editable buyer profile, saved addresses, public deals endpoint: not supplied as APIs. UI does not invent these.
- True WebSocket/Reverb private channel auth, native push subscription UI, offline service worker, background sync, paid subscription integration, downloadable invoices/WhatsApp receipt action, buyer batch history screen, admin store detail, expanded reporting and review edit/delete are deferred. Checkout returns a batch and per-order results; it does not expose all guest orders through one guest token.
- Seller payment method API does not expose current stored settings in GET seller/store; an endpoint returning masked existing settings is recommended before a complete edit screen is built.
- Remove the public test login route identified in backend `routes/web.php` before public deployment. Enforce production token expiration/revocation policy, rate limits, proper mail/queue config and HTTPS. Fix guest uncertain checkout recovery and audit access-token logs. Frontend cannot repair these server concerns.
- Current implementation is intentionally dependency-light: CSS rather than Tailwind/shadcn, fetch/context rather than TanStack/Zustand, native form fields rather than React Hook Form/Zod; types describe core contracts but UI JS is not fully type-checked. These are scope choices for this first source version, not claims of superior benchmarked performance.
