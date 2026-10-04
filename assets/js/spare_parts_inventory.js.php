/* spare_parts_inventory.js
 * Extracted from spare_parts_inventory.php - inline <script> block #1.
 * Server data arrives through window.OCP_PAGE_SPARE_PARTS_INVENTORY
 * (rendered by includes/page_data.php).
 *
 * This file is a PHP script that prints JavaScript: PHP and JavaScript were
 * interleaved inside string literals in the original inline block, so the
 * PHP islands are kept and their values are read from the page data island
 * ($__ocp_data). It therefore has to be served through PHP, which is why it
 * is named .js.php.
 */
<?php
/*
 * This script is generated per request from the page's data island, so a cached copy can outlive
 * the page it was built for: the HTML refreshes, the browser reuses the script, and the script
 * then reads a data island whose shape has moved on. That is how the dashboard charts once came
 * back blank - the page carried the corrected arrays while the browser replayed a script that
 * still expected the old ones.
 *
 * So it must not be cached, in either sense: the HTTP cache (no-store) and the back/forward store
 * (no-cache, must-revalidate).
 *
 * The headers are emitted only when this file is requested on its own. When the page prints it
 * inline, the response is the page and these headers would be meaningless there. They are sent
 * before the content-type below, which is the first header() call, so nothing is lost to output
 * already being on the wire.
 */
if (isset($_SERVER['SCRIPT_FILENAME'])
    && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

/* Load the data-island helpers: walk up from this file until includes/ is found. */
if (!function_exists('ocp_js_raw')) {
    $__ocp_dir = __DIR__;
    for ($__ocp_i = 0; $__ocp_i < 6; $__ocp_i++) {
        if (is_file($__ocp_dir . '/includes/page_data.php')) {
            require_once $__ocp_dir . '/includes/page_data.php';
            break;
        }
        $__ocp_parent = dirname($__ocp_dir);
        if ($__ocp_parent === $__ocp_dir) {
            break;
        }
        $__ocp_dir = $__ocp_parent;
    }
    unset($__ocp_dir, $__ocp_i, $__ocp_parent);
}
/*
 * Printed as part of its page, $__ocp_data is already the data island and
 * every page variable this script reads is in scope.
 *
 * Requested on its own (a direct hit or a cache miss) there is no page, no
 * island and none of those variables, so notices are silenced, the island
 * starts empty, and missing members answer null. The response still parses as
 * JavaScript; it simply does nothing.
 */
if (!isset($__ocp_data)) {
    $__ocp_data = [];
    if (isset($_SERVER['SCRIPT_FILENAME'])
        && realpath($_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)) {
        $GLOBALS['OCP_SCRIPT_STANDALONE'] = true;
        header('Content-Type: application/javascript; charset=utf-8');
        ini_set('display_errors', '0');
        error_reporting(0);
    }
}
?>
// The page's data island. This script is its own request, so the page's
// PHP variables are NOT in scope: everything it needs is read from the
// island the page rendered.
const SPARE_PARTS_INVENTORY_DATA = window.OCP_PAGE_SPARE_PARTS_INVENTORY || {};
            // Initialize DataTables and Select2
            window.addEventListener('DOMContentLoaded', event => {
                // Initialize Select2 for searchable dropdown
                $('.select2-search').select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    placeholder: 'Search for a part...',
                    allowClear: true,
                    dropdownParent: $('#initialPartsModal')
                });
                
                const inventoryTable = document.getElementById('inventoryTable');
                if (inventoryTable) {
                    new simpleDatatables.DataTable(inventoryTable, {
                        perPage: 10,
                        perPageSelect: [10, 25, 50, 100],
                        labels: {
                            placeholder: "Search...",
                            perPage: "entries per page",
                            noRows: "No entries to show",
                            info: "Showing {start} to {end} of {rows} entries"
                        }
                    });
                }
                
                const batchesTable = document.getElementById('batchesTable');
                if (batchesTable) {
                    new simpleDatatables.DataTable(batchesTable, {
                        perPage: 10,
                        perPageSelect: [10, 25, 50, 100],
                        labels: {
                            placeholder: "Search...",
                            perPage: "entries per page",
                            noRows: "No entries to show",
                            info: "Showing {start} to {end} of {rows} entries"
                        }
                    });
                }
                
                const movementsTable = document.getElementById('movementsTable');
                if (movementsTable) {
                    new simpleDatatables.DataTable(movementsTable, {
                        perPage: 10,
                        perPageSelect: [10, 25, 50, 100],
                        labels: {
                            placeholder: "Search...",
                            perPage: "entries per page",
                            noRows: "No entries to show",
                            info: "Showing {start} to {end} of {rows} entries"
                        }
                    });
                }
                
                // Show SweetAlert2 notifications if there are any
                // Tested here in the browser: this script is its own request, so the
                // page's variables are not in scope.
                if (SPARE_PARTS_INVENTORY_DATA.hasMessage) {
                    Swal.fire({
                        title: SPARE_PARTS_INVENTORY_DATA.swalDataTitle || '',
                        text: SPARE_PARTS_INVENTORY_DATA.swalDataText || '',
                        icon: SPARE_PARTS_INVENTORY_DATA.swalDataIcon || '',
                        confirmButtonText: 'OK'
                    });
                }
                
                // Show modal if there was an error with form submission
                // Tested here in the browser: this script is its own request, so the
                // page's variables are not in scope.
                if (SPARE_PARTS_INVENTORY_DATA.isError && SPARE_PARTS_INVENTORY_DATA.postedAction) {
                    // Tested here in the browser: this script is its own request, so the
                    // page's variables are not in scope.
                    if (SPARE_PARTS_INVENTORY_DATA.isError && SPARE_PARTS_INVENTORY_DATA.postedAction) {
                        var initialPartsModal = new bootstrap.Modal(document.getElementById('initialPartsModal'));
                        initialPartsModal.show();
                        // Reinitialize Select2 after modal is shown
                        $('#initialPartsModal').on('shown.bs.modal', function () {
                            $('.select2-search').select2({
                                theme: 'bootstrap-5',
                                width: '100%',
                                placeholder: 'Search for a part...',
                                allowClear: true,
                                dropdownParent: $('#initialPartsModal')
                            });
                        });
                    }
                }
            });
            
            // Logout function with SweetAlert2
            document.getElementById('logoutLink').addEventListener('click', function(e) {
                e.preventDefault();
                Swal.fire({
                    title: 'Are you sure?',
                    text: 'You want to logout from the system.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, logout!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'actions/logout.php';
                    }
                });
            });
            
            // ------------------------------------------------- Recent Parts Movements actions
            // The view / edit / delete buttons of the movements table. They are bound by
            // delegation on the document for two reasons: simple-datatables rebuilds those
            // rows when it pages or searches, which drops a listener put on the button
            // itself, and this way nothing here is skipped if the select2 or DataTable
            // setup at the top of this file throws.
            (function () {
                const MOVEMENT_ENDPOINT = 'api/spare_parts_inventory-endpoint.php?id=';

                function value(v) {
                    return (v === null || v === undefined) ? '' : String(v);
                }

                // Movement values are written into the modal's markup, so they are escaped.
                function escapeHtml(v) {
                    return value(v).replace(/[&<>"']/g, function (c) {
                        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
                    });
                }

                function showModal(id) {
                    const el = document.getElementById(id);
                    if (el) {
                        bootstrap.Modal.getOrCreateInstance(el).show();
                    }
                }

                function fill(id, text) {
                    const el = document.getElementById(id);
                    if (el) {
                        el.value = value(text);
                    }
                }

                function pairRow(labelA, a, labelB, b) {
                    return '<div class="row mb-3">' +
                        '<div class="col-md-6"><strong>' + labelA + ':</strong> ' + escapeHtml(a) + '</div>' +
                        '<div class="col-md-6"><strong>' + labelB + ':</strong> ' + escapeHtml(b) + '</div>' +
                        '</div>';
                }

                function wideRow(label, text) {
                    return '<div class="row mb-3"><div class="col-md-12"><strong>' + label +
                        ':</strong> ' + escapeHtml(text) + '</div></div>';
                }

                function fullName(first, middle, last, suffix) {
                    var name = value(first);
                    if (middle) {
                        name += ' ' + value(middle).charAt(0) + '.';
                    }
                    name += ' ' + value(last);
                    if (suffix) {
                        name += ' ' + value(suffix);
                    }
                    return name.replace(/\s+/g, ' ').trim();
                }

                // The same rules the movements table is rendered with.
                function sourceTarget(movement) {
                    if (movement.movement_type === 'in') {
                        return movement.supplier_name ? 'From: ' + movement.supplier_name : 'From: Initial Stock';
                    }
                    if (movement.vehicle_name) {
                        return 'To Vehicle: ' + movement.vehicle_name +
                            (movement.plate_number ? ' (' + movement.plate_number + ')' : '');
                    }
                    if (movement.equipment_name) {
                        return 'To Equipment: ' + movement.equipment_name;
                    }
                    if (movement.employee_id) {
                        return 'Issued to: ' + fullName(movement.firstname, movement.middlename, movement.lastname, movement.suffix);
                    }
                    return 'To: Unknown';
                }

                // A movement out is priced from its batch, the way the table shows it.
                function unitPrice(movement) {
                    var price = (movement.batch_price === null || movement.batch_price === '' || movement.batch_price === undefined)
                        ? movement.price_per_unit
                        : movement.batch_price;
                    return parseFloat(price || 0);
                }

                function movementDate(movement) {
                    return value(movement.movement_date).slice(0, 10);
                }

                function fillViewModal(movement) {
                    const price = unitPrice(movement);
                    const quantity = parseFloat(movement.quantity || 0);
                    let html = '';
                    html += pairRow('Date', movementDate(movement), 'Type', value(movement.movement_type).toUpperCase());
                    html += wideRow('Part', value(movement.part_name) + ' (' + value(movement.part_number) + ')');
                    html += wideRow('Source/Destination', sourceTarget(movement));
                    html += pairRow('Quantity', quantity, 'Price/Unit', '\u20B1' + price.toFixed(2));
                    html += pairRow('Total Value', '\u20B1' + (quantity * price).toFixed(2), 'Batch Number', movement.batch_number || 'N/A');
                    html += pairRow('Technician', fullName(movement.tech_firstname, movement.tech_middlename, movement.tech_lastname, movement.tech_suffix) || 'N/A',
                        'Created At', movement.created_at || 'N/A');
                    html += pairRow('Purchase Order', movement.purchase_order || 'N/A', 'Purchase Request', movement.purchase_request || 'N/A');
                    html += wideRow('Purpose', movement.purpose || 'N/A');
                    html += wideRow('Notes', movement.notes || 'N/A');
                    const details = document.getElementById('partMovementDetails');
                    if (details) {
                        details.innerHTML = html;
                    }
                }

                // The technician list is a select of employee ids; an empty value means none.
                function selectTechnician(id) {
                    const select = document.getElementById('edit_part_technician');
                    if (!select) {
                        return;
                    }
                    select.value = value(id);
                    if (select.selectedIndex === -1) {
                        select.value = '';
                    }
                }

                function fillEditForm(movement) {
                    fill('edit_part_movement_id', movement.id);
                    fill('edit_part_info', value(movement.part_name) + ' (' + value(movement.part_number) + ')');
                    fill('edit_part_type', value(movement.movement_type).toUpperCase());
                    fill('edit_part_source_target', sourceTarget(movement));
                    fill('edit_part_movement_date', movementDate(movement));
                    fill('edit_part_quantity', movement.quantity);
                    fill('edit_part_price_per_unit', movement.price_per_unit);
                    selectTechnician(movement.technician);
                    fill('edit_part_purpose', movement.purpose);
                }

                // What the user typed before an edit was refused, put back over the row's
                // own values. The editable fields only; the read-only ones stay as the row is.
                function fillTypedValues(typed) {
                    fill('edit_part_movement_id', typed.movement_id);
                    fill('edit_part_movement_date', typed.movement_date);
                    fill('edit_part_quantity', typed.quantity);
                    fill('edit_part_price_per_unit', typed.price_per_unit);
                    selectTechnician(typed.technician);
                    fill('edit_part_purpose', typed.purpose);
                }

                function loadMovement(id, onLoaded, quiet) {
                    fetch(MOVEMENT_ENDPOINT + encodeURIComponent(id))
                        .then(function (response) { return response.json(); })
                        .then(function (data) {
                            if (data && data.success && data.movement) {
                                onLoaded(data.movement);
                                return;
                            }
                            throw new Error((data && data.message) ? data.message : 'Movement not found');
                        })
                        .catch(function (error) {
                            if (window.console && console.error) {
                                console.error('Parts movement lookup failed:', error);
                            }
                            if (quiet) {
                                return;
                            }
                            Swal.fire({
                                title: 'Error!',
                                text: 'Failed to load the parts movement details.',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        });
                }

                document.addEventListener('click', function (event) {
                    if (!event.target || !event.target.closest) {
                        return;
                    }

                    const viewButton = event.target.closest('.view-part-movement');
                    if (viewButton) {
                        loadMovement(viewButton.getAttribute('data-id'), function (movement) {
                            fillViewModal(movement);
                            showModal('viewPartMovementModal');
                        });
                        return;
                    }

                    const editButton = event.target.closest('.edit-part-movement');
                    if (editButton) {
                        loadMovement(editButton.getAttribute('data-id'), function (movement) {
                            fillEditForm(movement);
                            showModal('editPartMovementModal');
                        });
                        return;
                    }

                    const deleteButton = event.target.closest('.delete-part-movement');
                    if (deleteButton) {
                        fill('delete_part_movement_id', deleteButton.getAttribute('data-id'));
                        const description = document.getElementById('delete_part_movement_description');
                        if (description) {
                            description.textContent = deleteButton.getAttribute('data-description') || '';
                        }
                        showModal('deletePartMovementModal');
                    }
                });

                // An edit the server refused (bad input, or the row gone) comes back as this
                // same page. The island says so and carries what the user typed, because this
                // script is its own request and cannot read $_POST for itself. The typed
                // values go back first, so the modal is usable whatever else happens, and the
                // lookup that follows only fills in the read-only context - quietly, since a
                // refused edit is no place for a second error.
                function reopenRejectedEdit() {
                    if (!SPARE_PARTS_INVENTORY_DATA.postedEditMovement) {
                        return;
                    }
                    const typed = SPARE_PARTS_INVENTORY_DATA.postedEditValues || {};
                    if (!typed.movement_id) {
                        return;
                    }
                    fillTypedValues(typed);
                    showModal('editPartMovementModal');
                    loadMovement(typed.movement_id, function (movement) {
                        fill('edit_part_info', value(movement.part_name) + ' (' + value(movement.part_number) + ')');
                        fill('edit_part_type', value(movement.movement_type).toUpperCase());
                        fill('edit_part_source_target', sourceTarget(movement));
                    }, true);
                }

                // Its own listener, so it still runs if the handler above threw.
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', reopenRejectedEdit);
                } else {
                    reopenRejectedEdit();
                }
            })();
