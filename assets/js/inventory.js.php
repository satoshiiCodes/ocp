/* inventory.js
 * Extracted from inventory.php - inline <script> block #1.
 * Server data arrives through window.OCP_PAGE_INVENTORY
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
const INVENTORY_DATA = window.OCP_PAGE_INVENTORY || {};
            // Initialize DataTables and Select2
            window.addEventListener('DOMContentLoaded', event => {
                // Initialize Select2 for all searchable dropdowns
                function initializeSelect2() {
                    // Common Select2 configuration
                    const select2Config = {
                        theme: 'bootstrap-5',
                        width: '100%',
                        placeholder: 'Search for an item...',
                        allowClear: true,
                        dropdownAutoWidth: false,
                        minimumResultsForSearch: 1,
                        // Increase the number of visible options
                        dropdownCssClass: 'select2-dropdown--large'
                    };
                    
                    // Initialize with modal-specific dropdown parents
                    const modalConfigs = [
                        { selector: '#initialStockModal .select2-search', parent: '#initialStockModal' },
                        { selector: '#stockOutSubconModal .select2-search', parent: '#stockOutSubconModal' },
                        { selector: '#transferModal .select2-search', parent: '#transferModal' }
                    ];
                    
                    modalConfigs.forEach(config => {
                        $(config.selector).select2({
                            ...select2Config,
                            dropdownParent: $(config.parent)
                        });
                    });
                    
                    // Initialize any other select2-search elements not in modals
                    $('.select2-search').not('#initialStockModal .select2-search, #stockOutSubconModal .select2-search, #transferModal .select2-search').select2(select2Config);
                }
                
                initializeSelect2();
                
                const inventoryTable = document.getElementById('inventoryTable');
                if (inventoryTable) {
                    new simpleDatatables.DataTable(inventoryTable, {
                        perPage: 10,
                        perPageSelect: [10, 25, 50, 100],
                        searchable: false,
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
                        searchable: false, 
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
                        searchable: false, 
                        labels: {
                            placeholder: "Search...",
                            perPage: "entries per page",
                            noRows: "No entries to show",
                            info: "Showing {start} to {end} of {rows} entries"
                        }
                    });
                }
                
                // Hide DataTables search boxes
                document.querySelectorAll('.dataTable-input').forEach(input => {
                    input.style.display = 'none';
                });
                document.querySelectorAll('.dataTable-top').forEach(top => {
                    top.style.display = 'none';
                });
                document.querySelectorAll('.dataTable-bottom').forEach(bottom => {
                    bottom.style.display = 'none';
                });

                // Real-time search functionality
                function setupRealTimeSearch(searchInputId, tableId) {
                    const searchInput = document.getElementById(searchInputId);
                    const table = document.getElementById(tableId);
                    
                    if (!searchInput || !table) return;
                    
                    searchInput.addEventListener('input', function() {
                        const searchTerm = this.value.toLowerCase().trim();
                        const rows = table.querySelectorAll('tbody tr');
                        
                        rows.forEach(row => {
                            const rowText = row.textContent.toLowerCase();
                            if (rowText.includes(searchTerm)) {
                                row.style.display = '';
                            } else {
                                row.style.display = 'none';
                            }
                        });
                    });
                    
                    // Clear search functionality
                    const clearButton = document.getElementById('clear' + searchInputId.replace('Search', 'Search'));
                    if (clearButton) {
                        clearButton.addEventListener('click', function() {
                            searchInput.value = '';
                            const rows = table.querySelectorAll('tbody tr');
                            rows.forEach(row => {
                                row.style.display = '';
                            });
                        });
                    }
                }
                
                // Setup real-time search for all tables
                setupRealTimeSearch('inventorySearch', 'inventoryTable');
                setupRealTimeSearch('batchesSearch', 'batchesTable');
                setupRealTimeSearch('movementsSearch', 'movementsTable');

                // Put the page's own search box on the same line as the entries-per-page selector.
                //
                // These tables are built with `searchable: false` and use a search input of their
                // own, which the markup places in a separate div above the table. The library's
                // control bar is created around the table, so the two ended up stacked: the search
                // box on one line and "N entries per page" on the next.
                //
                // Moving the markup instead would have to happen before the library runs, and would
                // leave the input outside the table container it filters. The bar is already a
                // space-between flex row, so appending the wrapper there puts the selector on the
                // left and the search on the right, on one line.
                [
                    ['inventoryTable', 'inventorySearch'],
                    ['batchesTable', 'batchesSearch'],
                    ['movementsTable', 'movementsSearch']
                ].forEach(function (pair) {
                    const table = document.getElementById(pair[0]);
                    const input = document.getElementById(pair[1]);
                    if (!table || !input) return;

                    const wrap = table.closest('.datatable-wrapper');
                    const bar = wrap ? wrap.querySelector('.datatable-top') : null;
                    const box = input.closest('.ocp-table-search');
                    if (!bar || !box || bar.contains(box)) return;

                    bar.appendChild(box);
                });

                
                // Show SweetAlert2 notifications
                // Tested here in the browser: this script is its own request, so the
                // page's variables are not in scope.
                if (INVENTORY_DATA.hasMessage) {
                    Swal.fire({
                        title: INVENTORY_DATA.swalDataTitle || '',
                        text: INVENTORY_DATA.swalDataText || '',
                        icon: INVENTORY_DATA.swalDataIcon || '',
                        confirmButtonText: 'OK'
                    }).then((result) => {
                        // If it was an error and we need to show a specific modal
                        // Tested here in the browser: this script is its own request, so the
                        // page's variables are not in scope.
                        if (INVENTORY_DATA.isError && INVENTORY_DATA.postedAction) {
                            // Which modal to reopen comes from the page's island: this script is its own
                            // request, so $_POST is not in scope here.
                            switch (INVENTORY_DATA.postedAction) {
                                case 'stock_in':
                                    var stockInModal = new bootstrap.Modal(document.getElementById('stockInModal'));
                                    stockInModal.show();
                                    break;
                                case 'stock_out':
                                    var stockOutModal = new bootstrap.Modal(document.getElementById('stockOutModal'));
                                    stockOutModal.show();
                                    break;
                                case 'stock_out_subcon':
                                    var stockOutSubconModal = new bootstrap.Modal(document.getElementById('stockOutSubconModal'));
                                    stockOutSubconModal.show();
                                    // Reinitialize Select2 when modal is shown
                                    $('#stockOutSubconModal').on('shown.bs.modal', function () {
                                    $('#subcon_item_id').select2({
                                    theme: 'bootstrap-5',
                                    width: '100%',
                                    placeholder: 'Search for an item...',
                                    allowClear: true,
                                    dropdownParent: $('#stockOutSubconModal')
                                    });
                                    });
                                    break;
                                case 'transfer':
                                    var transferModal = new bootstrap.Modal(document.getElementById('transferModal'));
                                    transferModal.show();
                                    // Reinitialize Select2 when modal is shown
                                    $('#transferModal').on('shown.bs.modal', function () {
                                    $('#transfer_item_id').select2({
                                    theme: 'bootstrap-5',
                                    width: '100%',
                                    placeholder: 'Search for an item...',
                                    allowClear: true,
                                    dropdownParent: $('#transferModal')
                                    });
                                    });
                                    break;
                                case 'set_min_stock':
                                    var minStockModal = new bootstrap.Modal(document.getElementById('minStockModal'));
                                    minStockModal.show();
                                    break;
                                case 'initial_stock':
                                    var initialStockModal = new bootstrap.Modal(document.getElementById('initialStockModal'));
                                    initialStockModal.show();
                                    // Reinitialize Select2 when modal is shown
                                    $('#initialStockModal').on('shown.bs.modal', function () {
                                    $('#initial_item_id').select2({
                                    theme: 'bootstrap-5',
                                    width: '100%',
                                    placeholder: 'Search for an item...',
                                    allowClear: true,
                                    dropdownParent: $('#initialStockModal')
                                    });
                                    });
                                    break;
                                case '__edit_movement__':
                                    var editModal = new bootstrap.Modal(document.getElementById('editMovementModal'));
                                    editModal.show();
                                    break;
                            }
                        }
                    });
                }
                
                // Show modal if there was an error with form submission
                // Tested here in the browser: this script is its own request, so the
                // page's variables are not in scope.
                if (INVENTORY_DATA.isError && INVENTORY_DATA.postedAction) {
                    // Which modal to reopen comes from the page's island: this script is its own
                    // request, so $_POST is not in scope here.
                    switch (INVENTORY_DATA.postedAction) {
                        case 'stock_in':
                            var stockInModal = new bootstrap.Modal(document.getElementById('stockInModal'));
                            stockInModal.show();
                            break;
                        case 'stock_out':
                            var stockOutModal = new bootstrap.Modal(document.getElementById('stockOutModal'));
                            stockOutModal.show();
                            break;
                        case 'stock_out_subcon':
                            var stockOutSubconModal = new bootstrap.Modal(document.getElementById('stockOutSubconModal'));
                            stockOutSubconModal.show();
                            // Reinitialize Select2 when modal is shown
                            $('#stockOutSubconModal').on('shown.bs.modal', function () {
                            $('#subcon_item_id').select2({
                            theme: 'bootstrap-5',
                            width: '100%',
                            placeholder: 'Search for an item...',
                            allowClear: true,
                            dropdownParent: $('#stockOutSubconModal')
                            });
                            });
                            break;
                        case 'transfer':
                            var transferModal = new bootstrap.Modal(document.getElementById('transferModal'));
                            transferModal.show();
                            // Reinitialize Select2 when modal is shown
                            $('#transferModal').on('shown.bs.modal', function () {
                            $('#transfer_item_id').select2({
                            theme: 'bootstrap-5',
                            width: '100%',
                            placeholder: 'Search for an item...',
                            allowClear: true,
                            dropdownParent: $('#transferModal')
                            });
                            });
                            break;
                        case 'set_min_stock':
                            var minStockModal = new bootstrap.Modal(document.getElementById('minStockModal'));
                            minStockModal.show();
                            break;
                        case 'initial_stock':
                            var initialStockModal = new bootstrap.Modal(document.getElementById('initialStockModal'));
                            initialStockModal.show();
                            // Reinitialize Select2 when modal is shown
                            $('#initialStockModal').on('shown.bs.modal', function () {
                            $('#initial_item_id').select2({
                            theme: 'bootstrap-5',
                            width: '100%',
                            placeholder: 'Search for an item...',
                            allowClear: true,
                            dropdownParent: $('#initialStockModal')
                            });
                            });
                            break;
                    }
                }
                
                // Function to attach event listeners to action buttons
                function attachEventListeners() {
                    // View movement details
                    document.querySelectorAll('.view-movement').forEach(button => {
                        button.addEventListener('click', function() {
                            const movementId = this.getAttribute('data-id');
                            
                            // Fetch movement details via AJAX
                            fetch('api/inventory-endpoint.php?id=' + movementId)
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        const movement = data.movement;
                                        let detailsHtml = `
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <strong>Date:</strong> ${movement.movement_date}
                                                </div>
                                                <div class="col-md-6">
                                                    <strong>Type:</strong> ${movement.movement_type.toUpperCase()}
                                                </div>
                                            </div>
                                            <div class="row mb-3">
                                                <div class="col-md-12">
                                                    <strong>Item:</strong> ${movement.item_code} - ${movement.item_name}
                                                </div>
                                            </div>
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <strong>Quantity:</strong> ${movement.quantity}
                                                </div>
                                                <div class="col-md-6">
                                                    <strong>Unit Cost:</strong> ₱${parseFloat(movement.unit_cost).toFixed(2)}
                                                </div>
                                            </div>
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <strong>Total Value:</strong> ₱${(movement.quantity * movement.unit_cost).toFixed(2)}
                                                </div>
                                                <div class="col-md-6">
                                                    <strong>Batch Number:</strong> ${movement.batch_number || 'N/A'}
                                                </div>
                                            </div>
                                        `;
                                        
                                        if (movement.movement_type === 'in') {
                                            if (movement.supplier_name) {
                                                detailsHtml += `
                                                    <div class="row mb-3">
                                                        <div class="col-md-12">
                                                            <strong>Supplier:</strong> ${movement.supplier_name}
                                                        </div>
                                                    </div>
                                                `;
                                            } else if (movement.transfer_from) {
                                                detailsHtml += `
                                                    <div class="row mb-3">
                                                        <div class="col-md-12">
                                                            <strong>Transfer From:</strong> ${movement.from_warehouse_name}
                                                        </div>
                                                    </div>
                                                `;
                                            }
                                        } else {
                                            if (movement.project_name && movement.subcon_name) {
                                                detailsHtml += `
                                                    <div class="row mb-3">
                                                        <div class="col-md-12">
                                                            <strong>Project:</strong> ${movement.project_name}
                                                        </div>
                                                    </div>
                                                    <div class="row mb-3">
                                                        <div class="col-md-12">
                                                            <strong>Subcontractor:</strong> ${movement.subcon_name}
                                                        </div>
                                                    </div>
                                                `;
                                            } else if (movement.project_name) {
                                                detailsHtml += `
                                                    <div class="row mb-3">
                                                        <div class="col-md-12">
                                                            <strong>Project:</strong> ${movement.project_name}
                                                        </div>
                                                    </div>
                                                `;
                                            } else if (movement.subcon_name) {
                                                detailsHtml += `
                                                    <div class="row mb-3">
                                                        <div class="col-md-12">
                                                            <strong>Subcontractor:</strong> ${movement.subcon_name}
                                                        </div>
                                                    </div>
                                                `;
                                            } else if (movement.transfer_to) {
                                                detailsHtml += `
                                                    <div class="row mb-3">
                                                        <div class="col-md-12">
                                                            <strong>Transfer To:</strong> ${movement.to_warehouse_name}
                                                        </div>
                                                    </div>
                                                `;
                                            }
                                        }
                                        
                                        detailsHtml += `
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <strong>Warehouse:</strong> ${movement.warehouse_name || 'N/A'}
                                                </div>
                                                <div class="col-md-6">
                                                    <strong>Location:</strong> ${movement.location || 'N/A'}
                                                </div>
                                            </div>
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <strong>Purchase Order:</strong> ${movement.purchase_order || 'N/A'}
                                                </div>
                                                <div class="col-md-6">
                                                    <strong>Purchase Request:</strong> ${movement.purchase_request || 'N/A'}
                                                </div>
                                            </div>
                                            <div class="row mb-3">
                                                <div class="col-md-12">
                                                    <strong>Created At:</strong> ${movement.created_at}
                                                </div>
                                            </div>
                                        `;
                                        
                                        document.getElementById('movementDetails').innerHTML = detailsHtml;
                                        const viewModal = new bootstrap.Modal(document.getElementById('viewMovementModal'));
                                        viewModal.show();
                                    } else {
                                        Swal.fire({
                                            title: 'Error!',
                                            text: 'Failed to load movement details.',
                                            icon: 'error',
                                            confirmButtonText: 'OK'
                                        });
                                    }
                                })
                                .catch(error => {
                                    console.error('Error:', error);
                                    Swal.fire({
                                        title: 'Error!',
                                        text: 'Failed to load movement details.',
                                        icon: 'error',
                                        confirmButtonText: 'OK'
                                    });
                                });
                        });
                    });
                    
                    // Edit movement functionality
                    document.querySelectorAll('.edit-movement').forEach(button => {
                        button.addEventListener('click', function() {
                            const movementId = this.getAttribute('data-id');
                            
                            // Fetch movement details via AJAX
                            fetch('api/inventory-endpoint.php?id=' + movementId)
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        const movement = data.movement;
                                        
                                        // Populate the edit form
                                        document.getElementById('edit_movement_id').value = movement.id;
                                        document.getElementById('edit_item_info').value = movement.item_code + ' - ' + movement.item_name;
                                        document.getElementById('edit_movement_type').value = movement.movement_type.toUpperCase();
                                        
                                        // Determine source/target information
                                        let sourceTarget = '';
                                        if (movement.movement_type === 'in') {
                                            if (movement.supplier_name) {
                                                sourceTarget = 'From: ' + movement.supplier_name;
                                            } else if (movement.transfer_from) {
                                                sourceTarget = 'Transfer From: ' + movement.from_warehouse_name;
                                            } else {
                                                sourceTarget = 'From: Initial Stock';
                                            }
                                        } else {
                                            if (movement.project_name && movement.subcon_name) {
                                                sourceTarget = 'To: ' + movement.project_name + ' (Subcon: ' + movement.subcon_name + ')';
                                            } else if (movement.project_name) {
                                                sourceTarget = 'To: ' + movement.project_name;
                                            } else if (movement.subcon_name) {
                                                sourceTarget = 'To: Subcon: ' + movement.subcon_name;
                                            } else if (movement.transfer_to) {
                                                sourceTarget = 'Transfer To: ' + movement.to_warehouse_name;
                                            } else {
                                                sourceTarget = 'To: Unknown';
                                            }
                                        }
                                        
                                        document.getElementById('edit_source_target').value = sourceTarget;
                                        
                                        // Add warehouse information
                                        let warehouseInfo = '';
                                        if (movement.transfer_from || movement.transfer_to) {
                                            // This is a transfer operation
                                            if (movement.movement_type === 'in') {
                                                // For incoming transfers, show the destination warehouse with "To: " prefix
                                                warehouseInfo = 'To: ' + (movement.warehouse_name || 'Unknown');
                                            } else {
                                                // For outgoing transfers, show the source warehouse with "From: " prefix
                                                warehouseInfo = 'From: ' + (movement.warehouse_name || 'Unknown');
                                            }
                                        } else {
                                            // Regular stock in/out operations
                                            if (movement.movement_type === 'in') {
                                                warehouseInfo = 'To: ' + (movement.warehouse_name || 'Unknown');
                                            } else {
                                                warehouseInfo = 'From: ' + (movement.warehouse_name || 'Unknown');
                                            }
                                        }

                                        document.getElementById('edit_warehouse_info').value = warehouseInfo;
                                        
                                        document.getElementById('edit_quantity').value = movement.quantity;
                                        document.getElementById('edit_unit_cost').value = movement.unit_cost;
                                        document.getElementById('edit_movement_date').value = movement.movement_date;
                                        document.getElementById('edit_purchase_order').value = movement.purchase_order || '';
                                        document.getElementById('edit_purchase_request').value = movement.purchase_request || '';
                                        
                                        // Show the edit modal
                                        const editModal = new bootstrap.Modal(document.getElementById('editMovementModal'));
                                        editModal.show();
                                    } else {
                                        Swal.fire({
                                            title: 'Error!',
                                            text: 'Failed to load movement details for editing.',
                                            icon: 'error',
                                            confirmButtonText: 'OK'
                                        });
                                    }
                                })
                                .catch(error => {
                                    console.error('Error:', error);
                                    Swal.fire({
                                        title: 'Error!',
                                        text: 'Failed to load movement details for editing.',
                                        icon: 'error',
                                        confirmButtonText: 'OK'
                                    });
                                });
                        });
                    });

                    // Confirm edit with SweetAlert2
                    document.getElementById('confirmEditBtn').addEventListener('click', function() {
                        Swal.fire({
                            title: 'Are you sure?',
                            text: 'You are about to update this stock movement. This action will also update the inventory batches.',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#3085d6',
                            cancelButtonColor: '#d33',
                            confirmButtonText: 'Yes, update it!'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                Swal.fire({
                                    title: "Updated!",
                                    text: "The stock movement has been updated successfully.",
                                    icon: "success"
                                }).then(() => {
                                    document.getElementById('editMovementForm').submit();
                                });
                            }
                        });
                    });
                    
                    // Delete movement
                    document.querySelectorAll('.delete-movement').forEach(button => {
                        button.addEventListener('click', function() {
                            const movementId = this.getAttribute('data-id');
                            const movementDescription = this.getAttribute('data-description');
                            
                            document.getElementById('delete_movement_id').value = movementId;
                            document.getElementById('delete_movement_description').textContent = movementDescription;
                            
                            const deleteModal = new bootstrap.Modal(document.getElementById('deleteMovementModal'));
                            deleteModal.show();
                        });
                    });
                }
                
                // Attach event listeners initially
                attachEventListeners();

                // From Warehouse and To Warehouse must not offer the same warehouse. Both
                // selects are built from the same list, so picking one has to remove it from
                // the other - a transfer to the warehouse it came from moves nothing while
                // still writing a movement pair.
                (function () {
                    var from = document.getElementById('from_warehouse_id');
                    var to = document.getElementById('to_warehouse_id');
                    if (!from || !to) return;

                    function sync(source, target) {
                        for (var i = 0; i < target.options.length; i++) {
                            var opt = target.options[i];
                            var clash = opt.value !== '' && opt.value === source.value;
                            opt.disabled = clash;
                            opt.hidden = clash;
                        }
                        // if the other side is holding the value just chosen, let it go
                        if (target.value !== '' && target.value === source.value) {
                            target.value = '';
                            if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
                                window.jQuery(target).trigger('change.select2');
                            }
                        }
                    }

                    function apply() {
                        sync(from, to);
                        sync(to, from);
                    }

                    from.addEventListener('change', apply);
                    to.addEventListener('change', apply);
                    apply();
                })();
                
                // Re-attach event listeners when DataTables redraws (after search, pagination, etc.)
                if (movementsTable) {
                    movementsTable.addEventListener('datatable.init', function() {
                        setTimeout(attachEventListeners, 100);
                    });
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
            
