# Tailwind migration — complete

Bootstrap 5 and the SB Admin template have been removed from the application. It now runs on
Tailwind CSS v4 with a custom design system.

**All 36 app pages plus the login page are converted, and every one was verified in a headless
browser: no Bootstrap stylesheet, no Bootstrap script, Inter applied, no horizontal overflow, no
JavaScript errors.**

| | Before | After |
|---|---|---|
| Application stylesheet | 244 KB (`styles.css`, SB Admin + Bootstrap compiled in) | **55 KB** (`app.build.css`) |
| Page-specific stylesheets | 38 | **0** (retired) |
| Bootstrap JS bundle | 80 KB on every page | **0** (`assets/js/ui.js`, ~9 KB) |
| Framework class usages | ~5,081 | 0 |

## How to change styles

Edit `assets/css/tailwind.css` (design tokens + component classes), then rebuild:

```
tools\tailwindcss.exe -i assets/css/tailwind.css -o assets/css/app.build.css --minify
```

Add `--watch` while working. The binary is standalone — no npm, no `package.json`, no daemon.
`CONVERSION-RULES.md` documents the Bootstrap→Tailwind mapping if more markup needs converting.

## The one constraint that shaped the design

**Bootstrap's CSS is unlayered. Tailwind v4 emits into cascade layers. Unlayered always beats
layered, regardless of load order.**

Consequences, both learned the hard way:

1. Component rules written inside `@layer components` in `tailwind.css` **silently lost** to the
   SB Admin sheet — the new shell kept rendering with Bootstrap's colours, radii and heights.
   Fix: those rules are now written **unlayered**, so they compete on source order instead, and
   `app.build.css` is loaded **last**.
2. Even unlayered, SB Admin won wherever it used an **ID**:

   `#layoutSidenav #layoutSidenav_content { margin-left: -225px; transform: … }` outranks any
   single-class rule. On phones that leaves the content pulled 225px off-screen and the drawer
   mis-positioned.

So the shell cannot be made correct while `styles.css` is still on the page. **A page must be
migrated (and `styles.css` dropped from it) as one unit.** That is what "page by page" means
here, and why the shell alone could not fix the mobile layout.

## Done

- `tools/tailwindcss.exe` — Tailwind v4.3.3 standalone. No npm, no `package.json`, no build
  daemon: the app has no asset pipeline and this avoids adding one.
- `assets/css/tailwind.css` — source. Design tokens (indigo accent, slate neutrals, status
  ramps, card radii/shadows) plus component classes: shell, top bar, sidebar, card, button,
  form, table (incl. bordered/striped), badge, alert, dropdown, modal, tooltip, breadcrumb.
  Build: `tools\tailwindcss.exe -i assets/css/tailwind.css -o assets/css/app.build.css --minify`
  Output is **~33 KB minified**, against 244 KB before.
- `assets/js/ui.js` — replaces Bootstrap's JS bundle. Deliberately keeps Bootstrap's API and
  markup contract (`new bootstrap.Modal(el).show()`, `data-bs-dismiss`, `shown.bs.modal`), so
  the 78 + 8 + ~30 call sites need no edits. Adds a jQuery bridge for the four pages that use
  `$().modal('show')`.
- `includes/top_bar.php` — migrated to Tailwind; now carries the logo, and loads Inter, the
  compiled stylesheet and `ui.js`.
- `includes/partials/routing_notifications_items.php` — migrated.
- `assets/js/app.js` — sidebar state centralised in one `setSidebar()`; adds backdrop click,
  Escape to close, and a breakpoint reset. (A second binding anywhere makes the toggle fire
  twice per click and appear dead — this is the only one.)
- All 36 app pages link `assets/css/app.build.css` **after** their own stylesheets.
- **`dashboard.php` — fully migrated.** Body converted, `styles.css` and `dashboard-style.css`
  dropped from it, Bootstrap gone from the page. Verified at 390 / 768 / 1024 / 1440: sidebar
  off-canvas below 1024 and fixed above it, content offset correctly, card grid 1-up then 2-up
  at xl, no horizontal overflow, no JS errors.

### Grid conversion, learned from the dashboard

Bootstrap's `row` / `col-xl-*` does **not** map one-to-one:

- A `row` becomes `grid grid-cols-1 gap-6`.
- It is two columns at xl **only if it directly contains two or more columns** — i.e. a true
  2-up card layout. Every nested row (pie chart above tables) stacks, which is exactly what
  `col-xl-12` meant.
- Getting this wrong squeezes nested tables; it is the first thing to check on each page.

## What was retired

Moved to `_retired/` rather than deleted, because there is no version control in this project:

- `_retired/styles.css.retired` — the 244 KB SB Admin + Bootstrap bundle.
- `_retired/page-styles/` — the 37 per-page stylesheets, now unreferenced.

They are outside the served path and can be deleted once you are satisfied. **Delete them before
long** — leaving a retired Bootstrap next to the new design system is exactly the kind of thing
that gets re-linked by accident.

## Deliberately left alone

- `generate_*_pdf.php`, `*_report_pdf.php`, `job_order_slip_PDF.php` — 15 files that render
  through Dompdf, which supports only a limited CSS subset with no flexbox or grid. Each carries
  its own inline `<style>` and never referenced `styles.css`. They must not use Tailwind.
- Vendored libraries that bring their own CSS and are independent of Bootstrap:
  `simple-datatables` (63 references), `select2` + `select2-bootstrap-5-theme`, SweetAlert2,
  Font Awesome, Chart.js. `select2-bootstrap-5-theme` keeps the word "Bootstrap" in its URL but
  is a standalone theme — it does not require Bootstrap's CSS.

## Known follow-ups

0. **Class-name collisions with Tailwind's own utilities.** This is the sharpest trap in the
   migration and it bit the sidebar. Bootstrap and Tailwind both own some of the same short
   names, and the markup kept Bootstrap's:

   | Class | Tailwind utility | Bootstrap component it also is |
   |---|---|---|
   | `collapse` | `visibility: collapse` | the collapsible panel |
   | `container` | `width` / max-widths | the page container |
   | `fixed` / `static` / `absolute` | `position` | — |

   The sidebar's panel is `<div class="collapse">`. Our own `.sb-sidenav .collapse { display: none }`
   overrode Tailwind's `display`, so the panel measured correctly and had the right colours — but
   Tailwind's `visibility: collapse` was never overridden, and **`visibility` inherits**, so every
   submenu was invisible while being laid out and clickable. Fixed by declaring
   `visibility: visible` on the open state (see the `.collapse` block in `tailwind.css`).

   When a migrated element is correctly sized but nothing paints, check `visibility` first. The
   durable fix is to stop using `collapse` as the class name — rename the shell's panels to
   `app-collapse` and update `ui.js` and `side_menu.php` together.

1. **`!` on some utilities.** The component rules in `tailwind.css` are unlayered (see above), so
   where a utility must beat a component that sets the same property, pages use Tailwind's
   important modifier — e.g. `bg-brand-600!`, `text-white!`, `bg-danger-100!`. This is correct but
   not pretty. It goes away by moving the components into `@layer components` once nothing else
   on the page is unlayered, then stripping the `!` marks.
2. **The `.sb-*` aliases** at the foot of `tailwind.css` are still live: the shell markup and
   `assets/js/app.js` use `sb-topnav` / `sb-sidenav` / `sb-sidenav-toggled` / `sb-content`. They
   are no longer "aliases" for anything — they are simply the shell's class names now. Renaming
   them to `app-*` is cosmetic and safe to do whenever.
3. **Script-generated markup.** A few page scripts still emit Bootstrap class names into markup
   they build at runtime (notably `assets/js/purchase_request.js.php`,
   `purchase_request_spare_parts.js`, `gasoline_purchase_order.js.php`,
   `assets/js/spare_parts_inventory.js.php`, `assets/js/projects.js.php`,
   `api/gasoline_purchase_order-endpoint.php`). Rows added by those scripts will be unstyled
   until they emit design-system classes. The design system already defines the classes they need
   (`badge-*`, `card-*`, `status-badge`, `input-group`, `dataTable-top`, `searchable-*`), so this
   is a find-and-replace in those scripts rather than new CSS.
4. **`fuel_report.js` had a latent bug** that this work surfaced: it opened with
   `if (ocpRaw(DATA, "fuelReportGuard2"))`, and neither `DATA` nor that key exists anywhere, so
   the handler threw on every load and the date validation below it never ran. Fixed.
5. **`event.relatedTarget` on modal events.** Nine handlers read the control that opened a modal
   as `event.relatedTarget` — the shape Bootstrap's plugins use — to pull that row's
   `data-id` / `data-name` and fill an edit dialog. `CustomEvent` only carries it in `.detail`,
   so every one of those dialogs opened empty. `ui.js` now copies it onto the event object as
   well as into `detail`. If you add a modal handler, either shape works.
6. **Breakpoints on a grid inside a modal do not behave like a grid on a page.** Tailwind
   breakpoints test the *viewport*, but `.modal-content` caps at 42rem, so an `xl:grid-cols-2`
   (1280px) inside a modal never fires on a normal laptop — the modal's fields stack even though
   the page-level grid beside them is two-up. Grids inside dialogs should use `md:` (or a
   container query) rather than `lg:` / `xl:`. Fixed on `projects.php`; worth checking on any
   other page whose modal has a two-column row.
7. **`visibility` is not covered by testing size.** An element can have the right box, colours
   and hit area and still paint nothing. `getBoundingClientRect()` cannot see it. When something
   is "there" in the DOM but invisible, read `getComputedStyle(el).visibility` — and remember it
   inherits, so a collapsed ancestor hides everything below it.
8. **`border-radius` on a checkbox does nothing while the native appearance is on.** The rule
   matches, the value computes to `9999px`, and the control still paints square — the browser
   draws its own control and ignores the radius. `appearance: none` has to come off first, after
   which the box has to be drawn by hand (border, background, and a tick via a `::before` with a
   `clip-path`, since the native tick goes away with the appearance). The same applies to
   `input[type=radio]`.

