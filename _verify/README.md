# _verify — checks for the restructured layout

Scripts that confirm the file/folder restructure did not break anything. They
only read the app (plus one throw-away test session), and are safe to re-run.
Nothing in the application references this directory, so it can be deleted at any
time once you are satisfied.

Run everything with:

```powershell
cd E:\laragon\www\OCP
node _verify\layout.mjs         # requested layout, centralized config, path integrity
node _verify\js.mjs             # every generated script parses
node _verify\preservation.mjs   # no code lost: compares against _restructure_backup
node _verify\script-context.mjs # a page's scripts behave the same when fetched directly
node _verify\runtime.mjs        # serves the app: login, pages x roles, assets, api, PDFs
node _verify\standalone.mjs     # server-rendered assets emit no PHP notice
```

## The individual checks

| Script | What it proves |
|---|---|
| `layout.mjs` | `actions/ api/ assets/{css,js,images}/ config/ includes/` exist, the old `action/ css/ js/ img/` are gone, every page has its own `<page>-style.css` and `<page>.js`, every referenced asset exists, no page still points at an old path, and no inline `<style>`/`<script>` blocks remain. Also: a per-page actions file the page's JavaScript posts to directly must be able to open its own connection, and a per-page endpoint must return `$ocp_endpoint` |
| `js.mjs` | all 13 plain `.js` files and all server-rendered `.js.php` files parse (PHP islands neutralised), with balanced PHP tags |
| `preservation.mjs` | every string and identifier from each page's original inline `<script>` still exists in the generated script, its captured partial, its data island, or its per-page actions/endpoint files. A rename recorded in `folded-actions.json` is forgiven only when the file it moved into really performs that operation on that table |
| `script-context.mjs` | **the important one for `actions/`+`api/` work.** The browser fetches `<page>.js.php` as its own request, where the page's variables are not in scope. This buffers the page's own output to capture each script exactly as the page embedded it, fetches the same script directly, and requires the two to be identical. A server-side branch reading a page variable - a `foreach` over a dropdown list, an `if ($show_modal)` - silently takes the wrong path otherwise, which is invisible in the page's HTML |
| `runtime.mjs` | logs in, loads all 37 pages as 8 roles, fetches the 67 referenced assets, calls an `api/` endpoint and `actions/logout.php`, and renders report generators as real PDFs |
| `islands-by-role.mjs` | prints the dashboard data island per role (it is role-gated, so the keys differ by design) |
| `standalone.mjs` | requests each server-rendered asset on its own and reports any PHP notice; expects 0 |
| `patch-php-js.mjs` | the one-off fixup already applied to `assets/js/*.js.php` (island reads via `ocp_island_get()`, standalone bootstrap). A no-op on a patched tree |
| `baseline-compare.mjs` | runs a pre-restructure copy of the app beside this one and compares 296 page × role combinations. Needs the baseline tree (see below) |

### Per-page tests

These drive one page over HTTP, exercising its real forms and checking the
database, so they catch behaviour the whole-app suites cannot see:

```powershell
node _verify\test-page.mjs              # expenses_type: add, validation, duplicate, view, AJAX update/delete
node _verify\test-item-names.mjs
node _verify\test-items-categories.mjs
node _verify\test-spare-parts-categories.mjs
node _verify\test-suppliers.mjs
node _verify\test-subcon-name.mjs
node _verify\test-projects.mjs
node _verify\test-vehicles.mjs
node _verify\test-heavy-equipment.mjs
node _verify\test-spare-parts-inventory-movements.mjs  # the movements table's view/edit/delete
node _verify\test-routing-scripts.mjs   # the two routing pages, which need a record id
node _verify\smoke.mjs <page,page,...>  # quick load check + the island keys a page emits
```

Each one is additive and removes what it created, then compares row counts to
prove it. `_verify/query.php` runs a single read for them; it refuses a `DELETE`
or `UPDATE` with no `WHERE`. `_verify/engineers.php` and `_verify/sessions.php`
provide fixtures.


## Test helpers

These create and remove throw-away state; `sessions.php` writes fake PHP session
files (the same technique PHP's own test suite uses) so the pages behind the login
guard can be reached. **Nothing here changes stored data**: no password is reset,
no row is written. `runtime.mjs` logs in by presenting one of those sessions, and
checks the login form separately by confirming that the page renders and that a
wrong password is rejected.

Run `node _verify\sessions.php`-generated sessions are left in PHP's session
directory; delete them with:

```powershell
Remove-Item E:\laragon\tmp\sess_ocpverify* -Force
```

## Rebuilding the baseline comparison

`baseline-compare.mjs` needs `_baseline/`, a runnable copy of the old code:

```powershell
cd E:\laragon\www\OCP
Copy-Item -Recurse -Force vendor _baseline\vendor
Copy-Item -Recurse -Force _restructure_backup\* _baseline\
Move-Item -Force _baseline\db_config.php _baseline\includes\db_config.php
Copy-Item -Force composer.json, composer.lock _baseline\
node _verify\baseline-compare.mjs
Remove-Item -Recurse -Force _baseline
```
