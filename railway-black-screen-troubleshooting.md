# Railway: black screen / 502 troubleshooting

Guide for when the production deploy at `https://vidula-laravel-production.up.railway.app/` shows a **black empty screen** or a **502**. Based on the real failures fixed on 2026-07-02.

---

## Step 0 — Identify which failure you have

| Symptom | Meaning | Go to |
| --- | --- | --- |
| 502 Bad Gateway | The container crashed or never started listening | §1 |
| Black screen, no content | HTML + CSS loaded but the React app never mounted (background is black because `<html class="dark">`) | §2 |
| Page loads unstyled / assets blocked | Mixed content (http asset URLs on an https page) | §3 |
| 500 error page | App boots but a request throws | §4 |

---

## §1 — 502: the container is not running

The Dockerfile `CMD` chains commands with `&&`:

```
config:cache && route:cache && view:cache && serve
```

If **any** artisan command throws, `serve` never runs and Railway returns 502. Check the **Deploy Logs** in Railway — the failing command prints its exception there.

Known causes already fixed (do not reintroduce them):

1. **`View path not found` from `view:cache`** — happens when `storage/framework/views` does not exist in the container (Laravel resolves it with `realpath()`, which returns `false` for a missing dir). `.dockerignore` excludes `storage/framework/*`, so the Dockerfile must recreate them:
   ```dockerfile
   RUN ... && mkdir -p storage/framework/cache/data storage/framework/sessions \
       storage/framework/views storage/framework/testing storage/logs bootstrap/cache
   ```
2. **`Unable to prepare route ... for serialization. Uses Closure.` from `route:cache`** — closure routes cannot be cached. Every route must point to a controller class or use `Route::inertia()`. Never write `Route::get('/x', fn () => ...)`.

---

## §2 — Black screen: the JS bundle crashed before React mounted

The page is served, `app.css` applies (dark background), but nothing renders. **Always open the browser console (F12) first** — the first red error names the culprit.

### 2a. Echo/Reverb crash (the 2026-07-02 case)

`resources/js/app.tsx` imports `./echo` at the top. Vite inlines `VITE_*` variables **at build time**, and the Docker build has no `.env` (excluded by `.dockerignore`). If `echo.js` instantiates Echo unconditionally, `VITE_REVERB_APP_KEY` is `undefined`, pusher-js throws `You must pass your app key`, and the whole bundle dies before `createInertiaApp` runs.

**Rule:** `echo.js` must guard the instantiation:

```js
const reverbAppKey = import.meta.env.VITE_REVERB_APP_KEY;

if (reverbAppKey) {
    window.Echo = new Echo({ /* ... */ });
}
```

To actually enable Reverb in production, set `VITE_REVERB_APP_KEY`, `VITE_REVERB_HOST`, `VITE_REVERB_PORT`, `VITE_REVERB_SCHEME` in Railway variables — the Dockerfile declares them as `ARG`s in the frontend stage, and Railway passes matching variables as build args automatically. Then redeploy (the value is baked in at build time; changing the variable without rebuilding does nothing).

**General rule:** any module imported at the top level of `app.tsx` must never throw when an env var is missing — guard everything that reads `import.meta.env`.

### 2b. Page component not found

Console shows `Page not found: ./pages/Xxx.tsx`. Causes:

- The name passed to `Inertia::render('Xxx')` / `Route::inertia()` doesn't match a file under `resources/js/pages/`.
- **Casing mismatch**: Windows is case-insensitive but the Linux build container is not. `git ls-files resources/js/` shows the real tracked casing — it must be `pages/` (lowercase) and match the glob in `app.tsx` exactly. If you ever rename casing, use `git mv` (a plain rename on Windows won't be picked up by git).

### 2c. Stale build

If the console shows 404s for `/build/assets/*.js`, the manifest and the deployed assets are out of sync — the frontend stage failed silently or an old image is cached. In Railway, redeploy with cache cleared.

---

## §3 — Mixed content: assets requested over http

Railway terminates TLS at its proxy and forwards plain HTTP to the container, so Laravel thinks the request is `http://` and generates `http://` asset URLs, which the browser blocks.

Both safeguards must stay in place:

1. `bootstrap/app.php` → `$middleware->trustProxies(at: '*');`
2. `AppServiceProvider::boot()` → `URL::forceScheme('https')` when `app()->environment('production')`.

And in Railway variables (frozen into the config by `config:cache`, so a redeploy is required after changing them):

- `APP_ENV=production`
- `APP_URL=https://vidula-laravel-production.up.railway.app`

---

## §4 — 500 errors after boot

- **`could not find driver (Connection: pgsql)`** — the PHP image is missing the Postgres extension. The Dockerfile must install `php${PHP_VERSION}-pgsql` (Supabase is Postgres; `php-mysql` is useless here).
- **`relation "sessions" does not exist`** (or any missing table) — migrations were never run against Supabase. Run them once from a machine pointed at the production DB, or add `php artisan migrate --force &&` to the Dockerfile `CMD` before `serve`.
- Anything else: check Railway **Deploy Logs** and `storage/logs/laravel.log` inside the container. Never set `APP_DEBUG=true` in production to debug — read the logs instead.

---

## Quick checklist before every deploy

- [ ] No closure routes (everything cacheable by `route:cache`).
- [ ] No top-level frontend code that throws when a `VITE_*` var is missing.
- [ ] `APP_ENV`, `APP_URL` (https), `APP_KEY`, and DB variables set in Railway.
- [ ] `VITE_*` variables set in Railway **before** the build if the frontend needs them.
- [ ] After changing any env variable: redeploy (both `config:cache` and the Vite build freeze values).

That's the fix for the black screen. Summary of what happened and what I changed:

  Cause: app.tsx does import './echo' before anything else, and echo.js called new Echo({ key:
  import.meta.env.VITE_REVERB_APP_KEY, ... }) unconditionally. Vite bakes env vars into the bundle at build time, and
  the Docker build has no .env, so the key was undefined and pusher-js threw an exception while the module was loading.
  That aborted the whole JS bundle before createInertiaApp ever ran — CSS was already applied and your <html
  class="dark"> gave you the black screen. (You would have seen the "You must pass your app key" error in the browser
  console.)

  Changes:
  1. resources/js/echo.js — Echo is now only instantiated when VITE_REVERB_APP_KEY exists. A missing WebSocket config no
  longer takes down the entire app; the page just runs without realtime features.
  2. Dockerfile — declared ARG VITE_REVERB_APP_KEY / _HOST / _PORT / _SCHEME in the frontend stage. Railway
  automatically passes matching service variables as build args, so when you configure Reverb later, just set those
  VITE_REVERB_* variables in Railway and rebuild — Echo will activate again.

  Push and redeploy; the welcome page should render now. If you do see a black screen again, open the browser console
  (F12) and send me the first red error — with this class of failure the console always names the exact culprit.
