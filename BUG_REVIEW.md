# miniStore bug review — 2026-09-15

## Scope and handling

Reviewed routes, controllers, middleware, models, migrations, queries, image processing, cache invalidation, sessions, Blade templates, browser scripts, and responsive layouts. Existing uncommitted work was preserved. The fixes from this review do not change the visual design or browser scripts.

All mutations ran against in-memory SQLite or a disposable SQLite database with isolated image storage. Production received only read-only HTTP requests. No commits, pushes, deployments, or `.env` edits were performed.

## Confirmed bugs fixed

### 1. Password changes silently removed intentional spaces

- **Cause:** Laravel excludes `password` and `current_password` from trimming by default, but the application's `new_password` and `new_password_confirmation` fields were not excluded. A password such as `  exact-password  ` was stored without its spaces, then rejected when entered exactly at login.
- **Fix:** Exclude both new-password fields from string trimming.
- **File:** `bootstrap/app.php`.
- **Verification:** The existing whitespace regression failed before the fix and passes afterward. The real-browser test changes the password with spaces, logs out, and successfully logs in with that exact password.

### 2. Overlong Unicode passwords could authenticate using only their prefix

- **Cause:** `max:72` measures characters; bcrypt only uses the first 72 bytes. A password comprising 36 two-byte characters plus an extra suffix passed the original validation and authenticated against the 72-byte password. The same issue affected current-password verification when changing a password.
- **Fix:** Apply a shared byte-length validation rule to login, current-password, and new-password inputs before hashing or checking them.
- **Files:** `app/Rules/BcryptPassword.php`, `app/Http/Controllers/Admin/AuthController.php`, `app/Http/Controllers/Admin/SettingController.php`.
- **Verification:** The login regression returned a successful redirect before the fix; it now returns 422 and leaves the user unauthenticated. A separate test checks current-password rejection and confirms the stored password remains unchanged. The exact 72-byte password still works.

### 3. Null bytes in a new password caused a server error

- **Cause:** String validation accepted embedded null bytes, but PHP's bcrypt implementation cannot hash them. The password-change endpoint returned 500.
- **Fix:** Reject null bytes through the shared password validation rule before calling the hasher.
- **Files:** `app/Rules/BcryptPassword.php`, `app/Http/Controllers/Admin/SettingController.php`, `app/Http/Controllers/Admin/AuthController.php`.
- **Verification:** A regression reproduced the 500 before the fix. The same request now returns 422 with a field error and preserves the existing password.

### 4. Invalid category input crashed the validation page

- **Cause:** Submitting `name[]=invalid` correctly failed validation, but the redirected Blade page passed the flashed array into HTML escaping, causing a 500 instead of showing the error.
- **Fix:** Only restore a string into the category-name text input.
- **File:** `resources/views/admin/categories/index.blade.php`.
- **Verification:** A regression reproduced the redirect's 500. It now returns 200 with the validation message and creates no category.

### 5. Invalid settings input crashed the validation page

- **Cause:** Settings fields likewise passed flashed arrays into Blade's text escaping after failed validation.
- **Fix:** Safely restore scalar text values for the store name, tagline, announcement, WhatsApp number, about title, and about text.
- **File:** `resources/views/admin/settings/index.blade.php`.
- **Verification:** A regression submits arrays for all six fields. Before the fix the redirected page returned 500; afterward it returns 200, displays validation errors, and creates no settings record.

## Test correction

`tests/Feature/DeviceProductsTest.php` had a false failure in its validation-redirect test. Session assertions restarted Laravel's JSON session before the next simulated request, and the cookie-less test request then lost the deserialized error bag. The test now follows the redirect first and checks the rendered error bag and restored input. It still verifies the image error, form identity, and specification value. A separate regression also covers this redirect.

## Verification results

### PHP feature and unit tests

- Initial run: **58 passed, 2 failed**. One was the password-space bug; the other was the test-session artifact described above.
- Final run: **66 passed, 1,192 assertions**.
- Added `tests/Feature/ReviewRegressionTest.php` with six regression tests.
- Tests use SQLite `:memory:`, an array cache/session store, and fake public storage for uploads. Explicit environment overrides were applied when running PHPUnit.
- Covered authentication, throttling, logout, revoked sessions, CSRF, product/device/perfume/category mutations, category deletion protection, validation, stable search pagination, escaping, cache invalidation, settings, and image cleanup after failed database writes.
- Image checks confirm WebP output, proportional downscaling from 1600×800 to 1200×600, no upscaling of smaller images, replacement/deletion cleanup, and rejection of invalid formats, dimensions, and prices.

### Chrome against the actual local Laravel server

Run with `node tests/browser/review-flows.mjs`. The runner creates and cleans up its own SQLite database, database-backed cache/sessions, image directory, and Chrome profile. It does not use the development or production database. CSRF protection remains enabled.

Passed:

- Public search, pagination, empty results, product pages, perfume filters, and perfume detail pages.
- Search failure/loading feedback and retry after a simulated network outage.
- Unauthorized admin access, incorrect and correct login, and a tokenless request without a trusted origin returning 419.
- Phone, device, perfume, and category create/edit/delete flows.
- Device specification validation errors, retained modal input, correction, and retry.
- Category duplicate errors and successful retry.
- Uploaded WebP retrieval and file removal after deletion.
- Settings cache invalidation visible on a fresh public request.
- Password change with intentional spaces, refreshed CSRF token, subsequent settings save, logout, and successful login.
- No horizontal overflow after animations settle at **1280, 768, 390, and 320 pixels** across the public pages, admin pages, and product/device/perfume modals.
- No uncaught browser JavaScript errors.

The existing `searchable-categories.mjs` and `category-validation.mjs` scripts also passed, covering keyboard/mouse/touch selectors, history synchronization, failed-filter rollback, required-field feedback, duplicate/rename validation, blocked deletion, and narrow layouts.

### Syntax and diff checks

- PHP syntax: **39 files passed**.
- JavaScript syntax: **12 files passed**.
- `git diff --check` reports two pre-existing trailing-space lines in `HomeController.php` and `Api/SearchController.php`; those unrelated edits were preserved.

### Live read-only checks

Site: https://ministore-production-4ade.up.railway.app/

- Home, search page, search JSON, perfumes page, sampled product page, sampled WebP image, and category search: **200**.
- Deliberately unmatched search: **200**, zero results.
- Invalid pagination value: **422**.
- Unknown product: **404**.
- Unauthenticated admin JSON request: **401**.
- Admin login page: **200**.

## Limits and remaining attention

- Changes are local and have not been deployed; the live checks do not verify these fixes in production.
- Production login, uploads, settings changes, and other mutations were intentionally not exercised.
- Local database verification used SQLite. MySQL/PostgreSQL behavior, Redis, multi-worker races, and production volume persistence were not exercised.
- Browser execution used desktop Chrome with mobile viewport/touch emulation; Safari, Firefox, and physical mobile devices were not tested.
- Not every image codec/metadata combination or infrastructure failure was exercised.
- Existing uncommitted changes, including the perfume brand-removal migration, remain in the workspace and are not attributed to this review.
