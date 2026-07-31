# CardNest frontend

The Vue 3 + TypeScript single-page app for CardNest. It renders the UI and calls the JSON API in `../backend`; it
never encodes a business rule itself. See the [root README](../README.md) for what CardNest is, and
[docs/architecture.md](../docs/architecture.md) / [docs/api.md](../docs/api.md) for the system as a whole.

## Structure

```text
src/
  pages/       one component per route (LoginPage, RegistrationPage, DashboardPage, CollectionPage, ...)
  components/  small, reusable building blocks (BaseButton, BaseInput, ErrorMessage) — not a UI kit
  stores/      Pinia stores (auth session state today)
  api/         the typed fetch client shared by every page/store
  types/       TypeScript interfaces mirroring backend response DTOs, one file per API area
  utils/       small framework-free helpers (e.g. money formatting)
  router/      route table and the auth guard
```

There is no separate "services" layer beyond `api/client.ts` and the Pinia stores — pages call `apiRequest`
directly (or through a store, for state that needs to be shared, like the logged-in user).

## Talking to the API

`api/client.ts` exports one function, `apiRequest<T>(path, options)`, used everywhere instead of raw `fetch`:

- Prefixes `path` with `VITE_API_BASE_URL` (`/api/v1` by default; see `vite.config.ts`'s dev proxy and
  `compose.yaml` for how that reaches the backend container).
- Attaches `Authorization: Bearer <token>` automatically when `cardnest.authToken` is present in `localStorage`.
- Parses the backend's structured error shape (`{"error": {"code", "message", "details"}}`, see
  [docs/api.md](../docs/api.md#errors)) and throws it as an `ApiError` with typed `status`, `code` and `details`, so
  callers can show `error.message` directly or map `error.details` onto form fields.
- Returns `undefined` for `204 No Content` responses (used by logout and archiving a collection item).

Session state is intentionally minimal: `stores/auth.ts` holds the current `user`, a reactive token mirror, and the
derived `isAuthenticated` flag. The token is persisted in `localStorage` under `cardnest.authToken`, which is also
what `router/index.ts`'s navigation guard checks for routes marked `meta: { requiresAuth: true }`. Public routes,
including the marketplace, remain usable without a session.

The public marketplace can add an active listing to the authenticated user's cart. `/cart` renders seller groups,
the server-calculated compatible shipping methods and complete totals, then submits one NL/BE address and exactly
one shipping choice per seller. `/orders` shows both purchases and sales returned by the participant-scoped API.
It renders the server-owned status timeline and only shows `Markeer als verzonden` to the seller of a paid order or
`Bevestig ontvangst` to the buyer of a shipped order, based on the response DTO's `availableActions`. The fake
checkout is labelled explicitly: the frontend never collects payment credentials.

## Adding a page or feature

1. Add a `types/<area>.ts` file with interfaces that mirror the backend response DTO for that area (see
   `types/catalog.ts` / `types/collection.ts` for the pattern) — don't reuse a loosely-typed `any` shape.
2. Build the page under `src/pages`, composing the existing `Base*` components instead of one-off markup, and call
   the API through `apiRequest`.
3. Register the route in `router/index.ts`; add `meta: { requiresAuth: true }` if it should only be reachable while
   logged in.
4. Re-validate on the backend whatever you validate in the form — a frontend check is a UX nicety, never the source
   of truth (see [CONTRIBUTING.md](../CONTRIBUTING.md)).
5. Add a Vitest test next to any non-trivial component/util, and cover the page's main flow with a Playwright spec
   if it's a new user-facing journey.

## Scripts

| Command | Purpose |
| --- | --- |
| `npm run dev` | Start the Vite dev server |
| `npm run build` | Type-check with `vue-tsc` and build production assets |
| `npm test` | Run Vitest unit tests once |
| `npm run test:e2e` | Run the Playwright end-to-end suite |

## Testing conventions

- **Unit tests** (Vitest) live next to what they test — `BaseInput.vue` → `BaseInput.test.ts`, `utils/money.ts` →
  `utils/money.test.ts` — and run in `jsdom` (configured in `vite.config.ts`).
- **End-to-end tests** (Playwright) live in `e2e/*.spec.ts` and drive a real dev server (`playwright.config.ts`
  starts one automatically), but stub the API with `page.route(...)` rather than requiring a live backend — see
  `e2e/registration.spec.ts`.
- TypeScript runs in strict mode (`tsconfig.json`); avoid `any` without a comment explaining why it's unavoidable.
