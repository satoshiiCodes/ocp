# Bootstrap → Tailwind conversion rules

Reference for converting a page body. The design system already implements the right-hand side
of every row below, so most of this is substituting a class name, not writing CSS.

## Per-page procedure

1. Convert the page body's classes using the table below.
2. **Delete the `assets/css/styles.css` link** — this is the step that makes the layout correct.
   Until it is gone, SB Admin's ID rules (`#layoutSidenav #layoutSidenav_content` etc.) beat
   everything we write.
3. Delete the page's own `assets/css/<page>-style.css` link, first folding anything still needed
   into Tailwind utilities on the markup (or a component class in `assets/css/tailwind.css`).
4. Delete the Bootstrap CDN `<script src=".../bootstrap.bundle.min.js">`. `assets/js/ui.js`
   already provides `bootstrap.Modal` / `Tooltip` / `Dropdown`, `data-bs-*` wiring,
   `shown.bs.modal` / `hidden.bs.modal` events, and a jQuery `.modal()` bridge.
5. Leave `assets/css/app.css` and `assets/css/app.build.css` in place.
6. Do **not** touch `assets/css/tailwind.css` or `assets/css/app.build.css` — they are shared.

## Grid

| Bootstrap | Replace with |
|---|---|
| `row` | `grid grid-cols-1 gap-6` |
| that row, **only if it directly contains 2+ columns** | add `xl:grid-cols-2` |
| `col-md-6`, `col-xl-6` | `min-w-0` |
| `col-md-4`, `col-md-3`, `col-xl-12` | `min-w-0` |
| `container-fluid` | `w-full` |
| `d-flex` | `flex` |
| `d-inline` / `d-none` / `d-block` | `inline` / `hidden` / `block` |
| `align-items-center` | `items-center` |
| `justify-content-between` | `justify-between` |
| `justify-content-center` / `justify-content-end` | `justify-center` / `justify-end` |

**The 2-up rule matters.** A nested row (`col-xl-12` holding a chart above two tables) must stay
one column — making it two squeezes the nested tables into unreadable slivers. Only rows that
genuinely lay two cards side by side get `xl:grid-cols-2`.

## Spacing

Bootstrap's scale is 0–5, Tailwind's is 0–6+. Map by *visual* size:

`mb-1`→`mb-1`, `mb-2`→`mb-2`, `mb-3`→`mb-4`, `mb-4`→`mb-6`, `mb-5`→`mb-8`
(and the same for `mt-`, `ms-`→`ml-`, `me-`→`mr-`, `px-4`→`px-6`, `py-3`→`py-4`, `p-4`→`p-6`).

## Components — keep the class name

These are implemented in `assets/css/tailwind.css`, so the markup needs **no change**:
`card`, `card-header`, `card-body`, `card-footer`, `btn`, `btn-primary`, `btn-secondary`,
`btn-success`, `btn-danger`, `btn-warning`, `btn-sm`, `btn-lg`, `table`, `table-sm`,
`table-bordered`, `table-striped`, `table-responsive`, `badge`, `alert`, `alert-info`,
`alert-success`, `alert-warning`, `alert-danger`, `form-control`, `form-select`, `form-label`,
`form-check`, `form-check-input`, `form-floating`, `form-text`, `btn-close`,
`btn-outline-primary`, `btn-outline-secondary`, `btn-outline-danger`, `breadcrumb`,
`breadcrumb-item`, `small-pie-chart-container`, `text-muted`, `text-danger`, `text-success`,
`fw-bold`, `small`, `modal` + `modal-dialog`/`modal-content`/`modal-header`/`modal-body`/
`modal-footer`/`modal-title`.

## Badges — must be changed

Bootstrap's `bg-*` paints a solid block; the design system uses soft tinted pills.

`badge bg-danger` → `badge badge-danger`, and likewise
`bg-success`→`badge-success`, `bg-warning`→`badge-warning`, `bg-info`→`badge-info`,
`bg-primary`→`badge-primary`, `bg-secondary`→`badge-neutral`.

## Page headers

The shell provides the title area. A page's own `<h1 class="mt-4">Title</h1>` plus
`<ol class="breadcrumb">` should become:

```html
<div class="mb-6">
    <h1 class="page-title">Title</h1>
    <ol class="breadcrumb"><li class="breadcrumb-item active">Title</li></ol>
</div>
```
