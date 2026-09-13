Perfumes are implemented independently from Products and Categories. The local migration has run successfully. Open `/admin/perfumes` for management and `/perfumes` for the public catalog.

1. **Migration created:** `database/migrations/2026_09_12_000000_create_perfumes_table.php`. Applied locally in migration batch 5.

2. **Database structure:** Only the `perfumes` table was added. It contains `id`, `name` (120), `brand` (80), unique `slug` (160), `category` (20), `price` (decimal 10,2), nullable JSON `specs`, nullable text `description`, required `image_path`, `image_status` (20, default `active`), and timestamps. Indexes cover `(category, created_at, id)` and `(created_at, id)`. Categories are validated in the application as `men` or `women`. There is no perfume category table or relationship to the device category table.

3. **Model created:** `app/Models/Perfume.php`, with the requested fillable fields, array specs and decimal price casts, fixed category labels, and unique name-based slugs with numeric suffixes. Editing a name preserves the existing URL. The `filter` slug is reserved for the JSON endpoint.

4. **Controllers created:** `app/Http/Controllers/Admin/PerfumeController.php` implements index, filter, store, update and destroy. `app/Http/Controllers/PerfumeController.php` implements public index, filter and show. `app/Support/PerfumeCatalog.php` shares validated category filtering and stable pagination between them.

5. **Routes added:** All admin routes remain inside the existing `admin` middleware group. Public details use explicit slug binding.

   | Method | URL | Route name |
   | --- | --- | --- |
   | GET | `/admin/perfumes` | `admin.perfumes.index` |
   | GET | `/admin/perfumes/filter` | `admin.perfumes.filter` |
   | POST | `/admin/perfumes` | `admin.perfumes.store` |
   | PUT/PATCH | `/admin/perfumes/{perfume}` | `admin.perfumes.update` |
   | DELETE | `/admin/perfumes/{perfume}` | `admin.perfumes.destroy` |
   | GET | `/perfumes` | `perfumes.index` |
   | GET | `/perfumes/filter` | `perfumes.filter` |
   | GET | `/perfumes/{perfume:slug}` | `perfumes.show` |

6. **Admin Blade files:** Added `resources/views/admin/perfumes/index.blade.php`, `rows.blade.php`, and `modal.blade.php`. The page has one category selector, no search field, image/name/brand/category/price/actions, pagination, and the requested empty state. Add and edit share a native dialog using the existing device modal classes. Edit preloads the image and all editable fields. Delete uses the existing confirmation dialog.

7. **Public Blade files:** Added `resources/views/perfumes/index.blade.php`, `cards.blade.php`, `show.blade.php`, and `feedback.blade.php`. Cards show image, brand, name, category and price. The detail page shows description and nonempty dynamic specifications. Public category and pagination links also work without JavaScript.

8. **Admin navigation:** Connected the existing Perfumes tab in `resources/views/admin/layout.blade.php` to `admin.perfumes.index`, including its active state. Existing tab layout and mobile scrolling styles are reused.

9. **Public navbar:** Added Perfumes with its active state in `resources/views/components/navbar.blade.php`. The Admin Dashboard button appears for logged-in admins in the public navbar. The footer retains guest Staff Login and authenticated dashboard access, with compact styling and a layout that keeps it at the bottom of short pages.

10. **JSON/filter logic:** Both filter endpoints return escaped Blade HTML, pagination HTML, total count and current page. Lists paginate 15 records, preserve category selection, and correct requests beyond the last page. JavaScript updates the list without document navigation, supports Back/Forward, cancels superseded requests, and provides loading/error/retry states.

11. **Image handling:** Extracted the existing Products image processor into `app/Services/CatalogImageService.php`. Both systems use the same implementation: JPEG/PNG/WebP, maximum 8 MB and 4096 × 4096 pixels, scale down within 1200 × 1200, WebP quality 75, metadata stripping and UUID filenames. Perfumes use `Storage::disk('public')` under `perfumes/`. Failed database creates remove new uploads. Replacements save first, update under a row lock and transaction, then remove the old image. Failed updates remove the new image and retain the old image. Deletes commit the record deletion before removing its image.

12. **Specifications:** Up to 30 generic name/value rows, with names limited to 60 characters and values to 255. Empty rows are ignored. Incomplete rows, duplicate names regardless of case, extra row keys, nested values and malformed arrays are rejected. Data is stored as a JSON object, including numeric specification names. There are no hardcoded fragrance fields or category-specific specification conditions.

13. **CSS changes:** Added only `.perfume-page`, `.perfume-filters`, `.perfume-category`, and `.perfume-empty` rules to `public/css/style.css`. Existing cards, colors, typography, tables, modals, specification rows and responsive rules are reused. No framework was added.

14. **JavaScript changes:** Added `public/js/perfumes.js` for shared admin/public filtering and pagination, and `public/js/admin-perfumes.js` for add/edit dialogs, specification rows and validation recovery. Existing Product and global scripts were not edited.

15. **Cache changes:** No perfume data cache was introduced. Existing Product cache invalidation remains unchanged. The shared public layout includes perfume routes in the existing WhatsApp button eligibility check.

16. **Commands:** Already ran `php artisan migrate --no-interaction`, `php artisan migrate:status`, and `php artisan route:list`. The public storage junction already exists locally. No additional local setup or npm build is required for this feature. On another environment, run `php artisan migrate --force` and, only if its public storage link is missing, `php artisan storage:link`. Deploy the new PHP, Blade, CSS and JavaScript files normally. Run the feature suite with `php artisan test --filter=PerfumesTest`.

17. **Existing code updated:** `routes/web.php`; `app/Http/Controllers/Admin/ProductController.php` (delegate image processing to the shared service); `resources/views/admin/layout.blade.php`; `resources/views/components/navbar.blade.php`; `resources/views/components/layout.blade.php` (WhatsApp eligibility); `resources/views/components/pagination.blade.php` (optional accessible label, retaining its Products default); and `public/css/style.css`. Existing uncommitted work was preserved. Authentication, device models/schema, Store Settings, Phone Care and Whish/Lotto behavior were not changed. The shared footer was subsequently restyled as requested.

Verification results:

- All 11 tests in `tests/Feature/PerfumesTest.php` pass. They cover separation, unique/reserved slugs, every category, image processing, field/spec/image validation, edits, deletes, database failure cleanup, JSON filters, pagination, escaping, slug details, WhatsApp, navigation, admin protection and validation recovery.
- Headless Chrome checks passed against an isolated SQLite database: category filtering without navigation, admin pagination and browser Back, creation in the categories available during the original implementation, add/edit/remove specification rows, edit image preview, image replacement and deletion cleanup, server validation recovery, public filters/pagination, and detail navigation.
- Admin list, add/edit dialogs, public cards and detail layout were checked at 320, 390, 768 and 1440 pixels. No page/modal horizontal overflow or JavaScript exceptions occurred. Mobile screenshots were visually reviewed. Test records and images were kept separate from store data; the disposable database and browser profile were removed afterward.
- Pint passed for all new PHP files. Both new JavaScript files passed `node --check`. All Blade templates compiled successfully; the temporary compiled view cache was cleared afterward.
- Full suite result: **45 passed, 2 failed (947 assertions)**. The failures are in existing tests: `DeviceProductsTest::test_new_routes_require_admin_authentication_and_device_validation_restores_input` (the validation-message assertion) and `StoreManagementTest::test_password_changes_preserve_intentional_whitespace`. The former encounters JSON session error-bag handling after session assertions; the perfume redirect test and actual browser validation recovery pass. The latter concerns existing password trimming behavior. Those tests and authentication behavior were left unchanged.
- `git diff --check` also reports pre-existing trailing whitespace in the working tree; unrelated formatting was left intact.
