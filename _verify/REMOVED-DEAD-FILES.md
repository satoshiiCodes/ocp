# Files removed as dead code

Nothing in the project referenced these: a search across every `.php`, `.js`,
`.sql`, `.html` and `.css` file (excluding `_restructure_backup/`, `vendor/` and
`_verify/`) found zero hits for each name, and none of them is reached by dynamic
dispatch — each reads a fixed `$_GET`/`$_POST` key, so nothing could call it
indirectly either.

They were folded into the one-file-per-page layout by removing them, at the
user's direction. Every one is still available in `_restructure_backup/` and can
be restored by copying it back.

| Removed | What it did | Why it was safe to remove |
|---|---|---|
| `api/get_items.php` | returned items by `?item_type=Materials\|Gasoline\|Spare Parts` | no caller; the pages that need item lists build their own dropdowns inline |
| `api/get_suppliers.php` | returned the supplier list as JSON | no caller; `suppliers.php` and the PO pages query the table directly |
| `api/get_pr_edit_data.php` | returned one purchase request for editing | no caller; `purchase_request.php` loads its own edit data |
| `api/get_wage_history.php` | returned an employee's wage history | no caller; `employee_registration.php` loads wage history itself |
| `api/get_user.php` | returned one user as JSON | no caller; `registration.php` handles `action=get_user` inline |
| `actions/delete_user.php` | deleted a user, as JSON | no caller; `registration.php` handles `action=delete_user` inline |
| `actions/update_user.php` | updated a user, as JSON | no caller; `registration.php` handles `action=update_user` inline |
| `actions/delete_purchase_request.php` | deleted a purchase request | no caller |
| `actions/update_purchase_request.php` | updated a purchase request | no caller; only referenced itself |
| `actions/edit_gasoline_po.php` | — | an empty 0-byte placeholder |

## Kept, because they are live

These five are referenced by the named script, and are folded into the page's own
`actions/`+`api/` pair rather than deleted:

| File | Called from | Folds into |
|---|---|---|
| `actions/generate_document_number.php` | `assets/js/purchase_request_spare_parts.js` | `actions/purchase_request_spare_parts-actions.php` |
| `api/get_spare_parts_pr_details.php` | `assets/js/purchase_request_spare_parts.js` | `api/purchase_request_spare_parts-endpoint.php` |
| `api/get_gasoline_po_details.php` | `assets/js/gasoline_purchase_order.js.php` | `api/gasoline_purchase_order-endpoint.php` |
| `api/get_movement_details.php` | `assets/js/inventory.js.php` | `api/inventory-endpoint.php` |
| `api/get_pr_details.php` | `assets/js/purchase_request.js.php` | `api/purchase_request-endpoint.php` |

`actions/logout.php` is also kept: it is called from `assets/js/app.js`,
`assets/js/includes/top_bar.js` and ten page scripts, so it is genuinely shared
chrome rather than one page's action.
