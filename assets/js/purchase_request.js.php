/* purchase_request.js
 * Extracted from purchase_request.php - inline <script> block #1.
 * Server data arrives through window.OCP_PAGE_PURCHASE_REQUEST
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
// The page's data island. This script is fetched as its own request, so the page's
// PHP variables are NOT in scope here: anything the page prepared is read from the
// island it rendered, in the browser.
const PURCHASE_REQUEST_DATA = window.OCP_PAGE_PURCHASE_REQUEST || {};
        // The items for the searchable dropdowns - INCLUDING CATEGORY.
        // The page builds this through a captured fragment, so it arrives as a JSON
        // string rather than an array; parse it here, and accept an array too in case
        // the page ever hands one over directly.
        const itemsRaw = PURCHASE_REQUEST_DATA.items;
        const itemsData = typeof itemsRaw === 'string' ? (JSON.parse(itemsRaw || '[]')) : (itemsRaw || []);
        
        // Track selected items to prevent duplicates
        const selectedItems = {};

        // Format date as mm-dd-yyyy
        function formatDate(date) {
            if (!date) return 'N/A';
            var d = new Date(date);
            var month = '' + (d.getMonth() + 1);
            var day = '' + d.getDate();
            var year = d.getFullYear();
            
            if (month.length < 2) month = '0' + month;
            if (day.length < 2) day = '0' + day;
            
            return month + '-' + day + '-' + year;
        }
        
        // Format datetime as mm-dd-yyyy hh:mm:ss
        function formatDateTime(datetime) {
            if (!datetime) return 'N/A';
            var d = new Date(datetime);
            var month = '' + (d.getMonth() + 1);
            var day = '' + d.getDate();
            var year = d.getFullYear();
            var hours = d.getHours();
            var minutes = d.getMinutes();
            var seconds = d.getSeconds();
            
            if (month.length < 2) month = '0' + month;
            if (day.length < 2) day = '0' + day;
            hours = hours < 10 ? '0' + hours : hours;
            minutes = minutes < 10 ? '0' + minutes : minutes;
            seconds = seconds < 10 ? '0' + seconds : seconds;
            
            return month + '-' + day + '-' + year + ' ' + hours + ':' + minutes + ':' + seconds;
        }

        // Function to create searchable dropdown for an item row - UPDATED for the new display format
        function createSearchableDropdown(rowIndex) {
            const searchInput = document.getElementById(`item-search-input-${rowIndex}`);
            const hiddenInput = document.getElementById(`item-id-${rowIndex}`);
            const dropdownList = document.getElementById(`item-dropdown-list-${rowIndex}`);
            
            if (!searchInput || !hiddenInput || !dropdownList) return;
            
            // Function to render dropdown items based on search term
            function renderDropdown(searchTerm = '') {
                const searchLower = searchTerm.toLowerCase();
                
                // Filter items based on search term
                const filteredItems = itemsData.filter(item => 
                    item.item_code.toLowerCase().includes(searchLower) ||
                    item.item_name.toLowerCase().includes(searchLower) ||
                    (item.category_name && item.category_name.toLowerCase().includes(searchLower))
                );
                
                if (filteredItems.length === 0) {
                    dropdownList.innerHTML = '<div class="searchable-dropdown-no-results">No items found</div>';
                    return;
                }
                
                let html = '';
                filteredItems.forEach(item => {
                    // Skip if this item is already selected in another row
                    const isSelected = selectedItems[item.id] && selectedItems[item.id] !== rowIndex;
                    
                    // Format category name - default to 'Uncategorized' if not set
                    const categoryName = item.category_name || 'Uncategorized';
                    
                    html += `
                        <div class="searchable-dropdown-item ${isSelected ? 'disabled' : ''}" 
                             data-id="${item.id}" 
                             data-code="${item.item_code}"
                             data-name="${item.item_name}"
                             data-category="${categoryName}"
                             ${isSelected ? 'style="opacity:0.5; pointer-events:none;"' : ''}>
                            <span class="item-code">${item.item_code}</span>
                            <span class="item-name">${item.item_name}</span>
                            <span class="item-category">${categoryName}</span>
                        </div>
                    `;
                });
                dropdownList.innerHTML = html;
                
                // Add click handlers to dropdown items
                document.querySelectorAll(`#item-dropdown-list-${rowIndex} .searchable-dropdown-item`).forEach(item => {
                    if (!item.classList.contains('disabled')) {
                        item.addEventListener('click', function() {
                            const id = this.dataset.id;
                            const code = this.dataset.code;
                            const name = this.dataset.name;
                            const category = this.dataset.category;
                            
                            // Clear any previous selection for this row
                            if (hiddenInput.value) {
                                delete selectedItems[hiddenInput.value];
                            }
                            
                            // Set hidden input value
                            hiddenInput.value = id;
                            
                            // Update search input with selected item in the requested format: "001 - Bakal 2mm (Materials)"
                            searchInput.value = `${code} - ${name} (${category})`;
                            searchInput.classList.add('item-selected');
                            
                            // Mark this item as selected
                            selectedItems[id] = rowIndex;
                            
                            // Hide dropdown
                            dropdownList.classList.remove('show');
                            
                            // Update all dropdowns to reflect new selections
                            refreshAllDropdowns();
                        });
                    }
                });
            }
            
            // Handle input events for searching
            searchInput.addEventListener('input', function() {
                if (this.value.trim() === '') {
                    // Clear selection if input is empty
                    if (hiddenInput.value) {
                        delete selectedItems[hiddenInput.value];
                        hiddenInput.value = '';
                    }
                    this.classList.remove('item-selected');
                }
                renderDropdown(this.value);
                dropdownList.classList.add('show');
            });
            
            // Show dropdown on focus
            searchInput.addEventListener('focus', function() {
                // If input is empty, show all items
                if (this.value.trim() === '') {
                    renderDropdown('');
                } else {
                    renderDropdown(this.value);
                }
                dropdownList.classList.add('show');
            });
            
            // Hide dropdown when clicking outside
            document.addEventListener('click', function(e) {
                const container = document.getElementById(`item-searchable-container-${rowIndex}`);
                if (container && !container.contains(e.target)) {
                    dropdownList.classList.remove('show');
                }
            });
            
            // Handle keyboard navigation
            searchInput.addEventListener('keydown', function(e) {
                const items = dropdownList.querySelectorAll('.searchable-dropdown-item:not(.disabled)');
                const selectedItem = dropdownList.querySelector('.searchable-dropdown-item.selected');
                let index = -1;
                
                if (selectedItem) {
                    index = Array.from(items).indexOf(selectedItem);
                }
                
                switch(e.key) {
                    case 'ArrowDown':
                        e.preventDefault();
                        if (items.length > 0) {
                            if (selectedItem) {
                                selectedItem.classList.remove('selected');
                                index = (index + 1) % items.length;
                            } else {
                                index = 0;
                            }
                            items[index].classList.add('selected');
                            items[index].scrollIntoView({ block: 'nearest' });
                        }
                        break;
                        
                    case 'ArrowUp':
                        e.preventDefault();
                        if (items.length > 0) {
                            if (selectedItem) {
                                selectedItem.classList.remove('selected');
                                index = index - 1;
                                if (index < 0) index = items.length - 1;
                            } else {
                                index = items.length - 1;
                            }
                            items[index].classList.add('selected');
                            items[index].scrollIntoView({ block: 'nearest' });
                        }
                        break;
                        
                    case 'Enter':
                        e.preventDefault();
                        if (selectedItem) {
                            selectedItem.click();
                        } else if (items.length > 0) {
                            items[0].click();
                        }
                        break;
                        
                    case 'Escape':
                        dropdownList.classList.remove('show');
                        break;
                }
            });
            
            // Initialize with empty state
            renderDropdown('');
        }

        // Function to refresh all dropdowns (update disabled states)
        function refreshAllDropdowns() {
            const rows = document.querySelectorAll('.item-row');
            rows.forEach((row, index) => {
                const dropdownList = document.getElementById(`item-dropdown-list-${index}`);
                if (dropdownList) {
                    const searchTerm = document.getElementById(`item-search-input-${index}`).value;
                    renderDropdownForRow(index, searchTerm);
                }
            });
        }

        // Helper function to render dropdown for a specific row - UPDATED for the new display format
        function renderDropdownForRow(rowIndex, searchTerm) {
            const dropdownList = document.getElementById(`item-dropdown-list-${rowIndex}`);
            if (!dropdownList) return;
            
            const searchLower = searchTerm.toLowerCase();
            
            const filteredItems = itemsData.filter(item => 
                item.item_code.toLowerCase().includes(searchLower) ||
                item.item_name.toLowerCase().includes(searchLower) ||
                (item.category_name && item.category_name.toLowerCase().includes(searchLower))
            );
            
            if (filteredItems.length === 0) {
                dropdownList.innerHTML = '<div class="searchable-dropdown-no-results">No items found</div>';
                return;
            }
            
            let html = '';
            filteredItems.forEach(item => {
                // Skip if this item is already selected in another row
                const isSelected = selectedItems[item.id] && selectedItems[item.id] !== rowIndex;
                
                // Format category name - default to 'Uncategorized' if not set
                const categoryName = item.category_name || 'Uncategorized';
                
                html += `
                    <div class="searchable-dropdown-item ${isSelected ? 'disabled' : ''}" 
                         data-id="${item.id}" 
                         data-code="${item.item_code}"
                         data-name="${item.item_name}"
                         data-category="${categoryName}"
                         ${isSelected ? 'style="opacity:0.5; pointer-events:none;"' : ''}>
                        <span class="item-code">${item.item_code}</span>
                        <span class="item-name">${item.item_name}</span>
                        <span class="item-category">${categoryName}</span>
                    </div>
                `;
            });
            dropdownList.innerHTML = html;
            
            // Add click handlers to dropdown items
            document.querySelectorAll(`#item-dropdown-list-${rowIndex} .searchable-dropdown-item`).forEach(item => {
                if (!item.classList.contains('disabled')) {
                    item.addEventListener('click', function() {
                        const id = this.dataset.id;
                        const code = this.dataset.code;
                        const name = this.dataset.name;
                        const category = this.dataset.category;
                        
                        // Clear any previous selection for this row
                        const hiddenInput = document.getElementById(`item-id-${rowIndex}`);
                        if (hiddenInput.value) {
                            delete selectedItems[hiddenInput.value];
                        }
                        
                        // Set hidden input value
                        hiddenInput.value = id;
                        
                        // Update search input with selected item in the requested format: "001 - Bakal 2mm (Materials)"
                        const searchInput = document.getElementById(`item-search-input-${rowIndex}`);
                        searchInput.value = `${code} - ${name} (${category})`;
                        searchInput.classList.add('item-selected');
                        
                        // Mark this item as selected
                        selectedItems[id] = rowIndex;
                        
                        // Hide dropdown
                        dropdownList.classList.remove('show');
                        
                        // Update all dropdowns to reflect new selections
                        refreshAllDropdowns();
                    });
                }
            });
        }

        // Initialize DataTables
        window.addEventListener('DOMContentLoaded', event => {
            const prTable = document.getElementById('prTable');
            if (prTable) {
                new simpleDatatables.DataTable(prTable, {
                    perPage: 10,
                    perPageSelect: [10, 25, 50, 100],
                    labels: {
                        placeholder: "Search...",
                        perPage: "entries per page",
                        noRows: "No entries to show",
                        info: "Showing {start} to {end} of {rows} entries"
                    },
                    responsive: true,
                    columnDefs: [
                        { targets: 0, width: "15%" }, // Document # column
                        { targets: 1, width: "15%" }, // Requested By
                        { targets: 2, width: "8%" },  // Type
                        { targets: 3, width: "20%" }, // Project/Supplier
                        { targets: 4, width: "8%" },  // Request Date
                        { targets: 5, width: "8%" },  // Doc Type
                        { targets: 6, width: "10%" },  // Status
                        { targets: 7, width: "16%" }  // Actions
                    ]
                });
            }

            // Initialize Bootstrap tooltips
            const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

            // Show SweetAlert2 notifications
            // Tested in the browser: this script is its own request, so the page's
            // variables are not available to it.
            if (PURCHASE_REQUEST_DATA.hasMessage) {
                Swal.fire({
                    title: PURCHASE_REQUEST_DATA.swalData,
                    text: PURCHASE_REQUEST_DATA.swalData2,
                    icon: PURCHASE_REQUEST_DATA.swalData3,
                    confirmButtonText: 'OK'
                });
            }

            // Initialize first searchable dropdown
            createSearchableDropdown(0);

            // Initialize request type selection
            selectRequestType('project');

            // Item management
            let itemCount = 1;
            document.getElementById('addItem').addEventListener('click', function() {
                const container = document.getElementById('itemsContainer');
                const newItem = document.createElement('div');
                newItem.className = 'item-row mb-2 p-3 position-relative';
                newItem.setAttribute('data-item-index', itemCount);
                
                let warehouseOptions = '<option value="">Select Warehouse</option>';
                // The options are rendered by the page and read from its data island here:
                // this script is a separate request, so the page's $warehouses is not in scope.
                warehouseOptions += PURCHASE_REQUEST_DATA.warehouseOptionsHtml || '';
                
                // Determine if we should show/hide unit cost based on current request type
                const unitCostDisplay = document.getElementById('request_type').value === 'project' ? 'none' : 'block';
                
                newItem.innerHTML = `
                    <div class="remove-btn-container">
                        <button type="button" class="btn btn-danger btn-sm remove-item">
                            <i class="fas fa-times mr-1"></i> Remove
                        </button>
                    </div>
                    <div class="item-fields row">
                        <div class="min-w-0 item-col">
                            <div class="searchable-dropdown-container relative w-full" id="item-searchable-container-${itemCount}">
                                <input type="text" 
                                       class="searchable-dropdown-input item-search-input form-control" 
                                       id="item-search-input-${itemCount}" 
                                       placeholder="Type to search items..."
                                       autocomplete="off"
                                       data-item-index="${itemCount}">
                                <input type="hidden" name="items[${itemCount}][item_id]" id="item-id-${itemCount}" class="item-id-hidden" required>
                                <div class="searchable-dropdown-list" id="item-dropdown-list-${itemCount}"></div>
                            </div>
                        </div>
                        <div class="min-w-0 qty-col">
                            <div class="form-floating">
                                <input type="number" class="form-control" name="items[${itemCount}][quantity]" min="1" required>
                                <label>Quantity <span class="text-danger">*</span></label>
                            </div>
                        </div>
                        <div class="min-w-0 unit-cost-col" style="display: ${unitCostDisplay};">
                            <div class="form-floating">
                                <input type="number" class="form-control" name="items[${itemCount}][unit_cost]" step="0.01" min="0" value="0">
                                <label>Unit Cost (₱)</label>
                            </div>
                        </div>
                        <div class="min-w-0 warehouse-col">
                            <div class="form-floating">
                                <select class="form-select warehouse-select" name="items[${itemCount}][warehouse_id]" required>
                                    ${warehouseOptions}
                                </select>
                                <label>Warehouse <span class="text-danger">*</span></label>
                            </div>
                        </div>
                    </div>
                `;
                container.appendChild(newItem);
                
                // Initialize searchable dropdown for this new item
                createSearchableDropdown(itemCount);
                
                itemCount++;

                // Show remove buttons for all items (including the first one)
                document.querySelectorAll('.remove-item').forEach(btn => {
                    btn.style.display = 'block';
                });
                
                // Apply current column sizing for desktop
                if (window.innerWidth >= 992) {
                    adjustColumnWidths(document.getElementById('request_type').value);
                }
                
                // Hide stock preview when items change
                document.getElementById('stockPreview').style.display = 'none';
            });

            // Remove item
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-item') || e.target.closest('.remove-item')) {
                    const btn = e.target.classList.contains('remove-item') ? e.target : e.target.closest('.remove-item');
                    const itemRow = btn.closest('.item-row');
                    if (document.querySelectorAll('.item-row').length > 1) {
                        // Remove from selected items tracking
                        const hiddenInput = itemRow.querySelector('.item-id-hidden');
                        if (hiddenInput && hiddenInput.value) {
                            delete selectedItems[hiddenInput.value];
                        }
                        
                        itemRow.remove();
                        
                        // Refresh all dropdowns to update disabled states
                        refreshAllDropdowns();
                    }
                    
                    // If only one item remains, hide its remove button
                    if (document.querySelectorAll('.item-row').length === 1) {
                        document.querySelectorAll('.remove-item').forEach(btn => {
                            btn.style.display = 'none';
                        });
                    }
                    
                    // Hide stock preview when items change
                    document.getElementById('stockPreview').style.display = 'none';
                }
            });

            // Use event delegation for view-pr buttons (FIX FOR PAGINATION ISSUE)
            document.addEventListener('click', function(e) {
                // Check if the clicked element or its parent has the view-pr class
                const viewButton = e.target.closest('.view-pr');
                if (viewButton) {
                    const prId = viewButton.getAttribute('data-id');
                    
                    fetch('api/purchase_request-endpoint.php?id=' + prId)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const pr = data.pr;
                                const items = data.items;
                                
                                let itemsHtml = '';
                                
                                // Build items table WITH Category column
                                itemsHtml = '<div class="table-responsive">';
                                itemsHtml += '<table class="table table-bordered table-striped table-hover">';
                                itemsHtml += '<thead class="table-light">';
                                itemsHtml += '<tr>';
                                itemsHtml += '<th>Item</th>';
                                itemsHtml += '<th>Category</th>'; // New category column
                                itemsHtml += '<th class="text-end">Quantity</th>';
                                itemsHtml += '<th>Warehouse</th>';
                                itemsHtml += '</tr>';
                                itemsHtml += '</thead>';
                                itemsHtml += '<tbody>';

                                items.forEach(item => {
                                    // Get category from item data (assuming it's available)
                                    // If category is not in the item object, you'll need to modify the endpoint to include it
                                    const category = item.category_name || 'N/A';
                                    
                                    itemsHtml += `<tr>`;
                                    itemsHtml += `<td>${item.item_name} ${item.item_code ? '(' + item.item_code + ')' : ''}</td>`;
                                    itemsHtml += `<td>${category}</td>`; // New category column
                                    itemsHtml += `<td class="text-end">${item.quantity}</td>`;
                                    itemsHtml += `<td>${item.warehouse_name || 'N/A'}</td>`;
                                    itemsHtml += `</tr>`;
                                });
                                
                                itemsHtml += '</tbody>';
                                itemsHtml += '</table>';
                                itemsHtml += '</div>';
                                
                                // Determine target info based on request type
                                let targetInfo = '';
                                if (pr.request_type === 'project') {
                                    targetInfo = `<strong>Project:</strong> ${pr.project_name || 'N/A'}`;
                                } else if (pr.request_type === 'supplier') {
                                    targetInfo = `<strong>Supplier:</strong> ${pr.supplier_name || 'N/A'}`;
                                }
                                
                                // Get document type display
                                let docTypeDisplay = '';
                                if (pr.document_type) {
                                    const docTypeLabels = {
                                        'ws': '<span class="badge badge-success" title="Warehouse Stock Only">WS</span>',
                                        'po_ws': '<span class="badge badge-warning" title="PO with Warehouse Stock">PO/WS</span>',
                                        'pr_po': '<span class="badge badge-danger" title="PR to PO">PR + PO</span>',
                                        'direct_po': '<span class="badge badge-info" title="Direct Purchase Order">Direct PO</span>'
                                    };
                                    docTypeDisplay = docTypeLabels[pr.document_type] || pr.document_type;
                                } else {
                                    docTypeDisplay = '<span class="badge badge-neutral">N/A</span>';
                                }
                                
                                // Get PR, PO and WS numbers for display
                                let documentNumbers = [];

                                // For WS document type, only show WS number
                                if (pr.document_type === 'ws') {
                                    if (pr.ws_number) {
                                        documentNumbers.push(`<span class="ws-number">${pr.ws_number}</span>`);
                                    } else {
                                        documentNumbers.push(`<span class="text-muted fst-italic">Not yet created</span>`);
                                    }
                                } else {
                                    // For other document types, show PR, PO, and WS as appropriate
                                    if (pr.pr_number) {
                                        documentNumbers.push(`<span class="pr-number">${pr.pr_number}</span>`);
                                    }
                                    if (pr.po_number) {
                                        documentNumbers.push(`<span class="po-number">${pr.po_number}</span>`);
                                    }
                                    if (pr.ws_number) {
                                        documentNumbers.push(`<span class="ws-number">${pr.ws_number}</span>`);
                                    }
                                }

                                const documentNumbersDisplay = documentNumbers.length > 0 
                                    ? documentNumbers.join(', ') 
                                    : 'N/A';
                                
                                // Helper function to capitalize first letter of each word
                                const capitalizeStatus = (status) => {
                                    if (!status) return '';
                                    return status.split(/_|\s|-/).map(word => {
                                        return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
                                    }).join(' ');
                                };
                                
                                // Get status badges for PO and WS with proper capitalization
                                const getStatusBadge = (status) => {
                                    if (!status) return '<span class="badge badge-neutral">N/A</span>';
                                    
                                    const displayStatus = capitalizeStatus(status);
                                    const statusLower = status.toLowerCase();
                                    
                                    let badgeClass = 'badge-neutral';
                                    if (statusLower === 'approved') {
                                        badgeClass = 'badge-primary';
                                    } else if (statusLower === 'confirmed') {
                                        badgeClass = 'badge-success';
                                    } else if (statusLower === 'rejected') {
                                        badgeClass = 'badge-danger';
                                    } else if (statusLower === 'pending') {
                                        badgeClass = 'badge-warning';
                                    } else if (statusLower === 'processing') {
                                        badgeClass = 'badge-info';
                                    } else if (statusLower === 'completed') {
                                        badgeClass = 'badge-primary';
                                    } else if (statusLower === 'released') {
                                        badgeClass = 'badge-success';
                                    }
                                    
                                    return `<span class="badge ${badgeClass}">${displayStatus}</span>`;
                                };
                                
                                // Get PR status badge
                                const prStatusDisplay = pr.status ? capitalizeStatus(pr.status) : 'N/A';
                                let prBadgeClass = 'badge-neutral';
                                if (pr.status) {
                                    const statusLower = pr.status.toLowerCase();
                                    if (statusLower === 'approved') {
                                        prBadgeClass = 'badge-success';
                                    } else if (statusLower === 'rejected') {
                                        prBadgeClass = 'badge-danger';
                                    } else if (statusLower === 'pending') {
                                        prBadgeClass = 'badge-warning';
                                    } else if (statusLower === 'processing') {
                                        prBadgeClass = 'badge-info';
                                    } else if (statusLower === 'completed') {
                                        prBadgeClass = 'badge-primary';
                                    }
                                }
                                
                                const detailsHtml = `
                                    <div class="row mb-3">
                                        <div class="col-12">
                                            <strong>Document #:</strong> 
                                            ${documentNumbersDisplay}
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <strong>Type:</strong> ${pr.request_type === 'project' ? 
                                                '<span class="badge badge-info">Project</span>' : 
                                                '<span class="badge badge-primary">Stock</span>'}
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Request Date:</strong> ${formatDate(pr.request_date)}
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <strong>Document Type:</strong> ${docTypeDisplay}
                                        </div>
                                        <div class="col-md-6">
                                            ${targetInfo}
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <strong>Status:</strong> 
                                            <span class="badge ${prBadgeClass}">${prStatusDisplay}</span>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Requested By:</strong> ${pr.firstname} ${pr.middlename ? pr.middlename.charAt(0) + '.' : ''} ${pr.lastname} ${pr.suffix || ''}
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <strong>Created At:</strong> ${formatDateTime(pr.created_at)}
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Last Updated:</strong> ${pr.updated_at ? formatDateTime(pr.updated_at) : 'N/A'}
                                        </div>
                                    </div>
                                    <hr>
                                    <h6>Items Requested:</h6>
                                    ${itemsHtml}
                                `;
                                
                                document.getElementById('prDetails').innerHTML = detailsHtml;
                                const viewModal = new bootstrap.Modal(document.getElementById('viewPRModal'));
                                viewModal.show();
                            } else {
                                Swal.fire({
                                    title: 'Error!',
                                    text: 'Failed to load PR details.',
                                    icon: 'error',
                                    confirmButtonText: 'OK'
                                });
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            Swal.fire({
                                title: 'Error!',
                                text: 'Failed to load PR details.',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        });
                }
            });

            // Use event delegation for delete-pr buttons (FIX FOR PAGINATION ISSUE)
            document.addEventListener('click', function(e) {
                const deleteButton = e.target.closest('.delete-pr');
                if (deleteButton) {
                    const prId = deleteButton.getAttribute('data-id');
                    const prNumber = deleteButton.getAttribute('data-pr-number');
                    
                    document.getElementById('delete_pr_id').value = prId;
                    document.getElementById('delete_pr_number').textContent = prNumber;
                    
                    const deleteModal = new bootstrap.Modal(document.getElementById('deletePRModal'));
                    deleteModal.show();
                }
            });

            function getStatusBadgeClass(status) {
                switch (status) {
                    case 'approved': return 'badge badge-success';
                    case 'rejected': return 'badge badge-danger';
                    case 'pending': return 'badge badge-warning';
                    case 'processing': return 'badge badge-info';
                    case 'completed': return 'badge badge-primary';
                    default: return 'badge badge-neutral';
                }
            }
        });

        // Request type selection function
        function selectRequestType(type) {
            // Update hidden input and display field
            document.getElementById('request_type').value = type;
            document.getElementById('request_type_display').value = type === 'project' ? 'Project' : 'Stock';
            
            // Update active class on options
            document.querySelectorAll('.request-type-option').forEach(option => {
                option.classList.remove('active');
            });
            document.querySelector(`.request-type-option[data-type="${type}"]`).classList.add('active');
            
            // Show/hide dependent fields
            if (type === 'project') {
                document.getElementById('project-field').style.display = 'block';
                document.getElementById('supplier-field').style.display = 'none';
                document.getElementById('project_id').required = true;
                document.getElementById('supplier_id').required = false;
                document.getElementById('supplier_id').value = '';
                
                // Hide all unit cost fields
                document.querySelectorAll('.unit-cost-col').forEach(field => {
                    field.style.display = 'none';
                });
                
                // Show stock preview and check stock button
                document.getElementById('stockPreview').style.display = 'block';
                document.getElementById('stockMessage').innerHTML = 'Click "Check Stock" to determine the document type based on current stock levels.';
                document.getElementById('checkStockBtn').style.display = 'inline-block';
            } else {
                document.getElementById('project-field').style.display = 'none';
                document.getElementById('supplier-field').style.display = 'block';
                document.getElementById('project_id').required = false;
                document.getElementById('supplier_id').required = false;
                document.getElementById('project_id').value = '';
                
                // Show all unit cost fields
                document.querySelectorAll('.unit-cost-col').forEach(field => {
                    field.style.display = 'block';
                });
                
                // Hide stock preview and check stock button for supplier requests
                document.getElementById('stockPreview').style.display = 'none';
                document.getElementById('checkStockBtn').style.display = 'none';
            }
            
            // Adjust column widths for desktop only
            if (window.innerWidth >= 992) {
                adjustColumnWidths(type);
            }
        }

        // Function to adjust column widths for desktop
        function adjustColumnWidths(type) {
            const itemsContainer = document.getElementById('itemsContainer');
            
            if (type === 'project') {
                itemsContainer.classList.add('project-mode');
            } else {
                itemsContainer.classList.remove('project-mode');
            }
        }

        // Function to check stock availability and preview document type
        function checkStockAvailability() {
            const requestType = document.getElementById('request_type').value;
            
            if (requestType !== 'project') {
                Swal.fire({
                    title: 'Info',
                    text: 'Stock checking is only available for Project Purchase Requests.',
                    icon: 'info',
                    confirmButtonText: 'OK'
                });
                return;
            }
            
            // Get all items
            const itemRows = document.querySelectorAll('.item-row');
            const items = [];
            
            let hasValidItems = false;
            itemRows.forEach((row, index) => {
                const itemId = row.querySelector(`.item-id-hidden`).value;
                const quantityInput = row.querySelector(`input[name*="[quantity]"]`);
                const warehouseSelect = row.querySelector(`select[name*="[warehouse_id]"]`);
                
                if (itemId && quantityInput && warehouseSelect) {
                    const quantity = quantityInput.value;
                    const warehouseId = warehouseSelect.value;
                    
                    if (itemId && quantity && warehouseId) {
                        hasValidItems = true;
                        items.push({
                            item_id: itemId,
                            quantity: quantity,
                            warehouse_id: warehouseId
                        });
                    }
                }
            });
            
            if (!hasValidItems) {
                Swal.fire({
                    title: 'Warning!',
                    text: 'Please add at least one item with item, quantity, and warehouse selected.',
                    icon: 'warning',
                    confirmButtonText: 'OK'
                });
                return;
            }
            
            // Show loading
            Swal.fire({
                title: 'Checking Stock...',
                html: 'Please wait while we check stock availability.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Make AJAX call to check stock
            fetch('actions/check_stock_availability.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    items: items
                })
            })
            .then(response => response.json())
            .then(data => {
                Swal.close();
                
                if (data.success) {
                    let documentType = data.document_type;
                    let docTypeLabel = '';
                    let docTypeClass = '';
                    let docTypeDescription = '';
                    
                    switch(documentType) {
                        case 'ws':
                            docTypeLabel = 'Warehouse Stock Only (WS)';
                            docTypeClass = 'badge-success';
                            docTypeDescription = 'All items have sufficient stock in the selected warehouses. This PR will be processed as Warehouse Stock.';
                            break;
                        case 'po_ws':
                            docTypeLabel = 'PR/WS';
                            docTypeClass = 'badge-warning';
                            docTypeDescription = 'Some items have insufficient stock or are out of stock. This PR will require a Purchase Order for the shortfall.';
                            break;
                        case 'pr_po':
                            docTypeLabel = 'PR to PO (PR/PO)';
                            docTypeClass = 'badge-danger';
                            docTypeDescription = 'All items are out of stock. This PR will be converted to a Purchase Order.';
                            break;
                    }
                    
                    // Build stock details HTML
                    let stockDetailsHtml = '';
                    if (data.stock_details && data.stock_details.length > 0) {
                        stockDetailsHtml = '<div class="mt-3"><h6>Stock Details:</h6><table class="table table-sm table-bordered">';
                        stockDetailsHtml += '<thead><tr><th>Item</th><th>Requested</th><th>Available</th><th>Status</th></tr></thead><tbody>';
                        
                        data.stock_details.forEach(detail => {
                            let status = '';
                            let statusClass = '';
                            if (detail.available == 0) {
                                status = 'Out of Stock';
                                statusClass = 'text-danger';
                            } else if (detail.available < detail.requested) {
                                status = 'Insufficient';
                                statusClass = 'text-warning';
                            } else {
                                status = 'Sufficient';
                                statusClass = 'text-success';
                            }
                            
                            stockDetailsHtml += `<tr>
                                <td>${detail.item_name}</td>
                                <td>${detail.requested}</td>
                                <td>${detail.available}</td>
                                <td class="${statusClass}"><strong>${status}</strong></td>
                            </tr>`;
                        });
                        
                        stockDetailsHtml += '</tbody></table></div>';
                    }
                    
                    // Update stock preview
                    document.getElementById('stockPreview').style.display = 'block';
                    document.getElementById('stockMessage').innerHTML = `
                        <strong>Document Type:</strong> <span class="badge ${docTypeClass}">${docTypeLabel}</span><br>
                        <span class="mt-2 d-block">${docTypeDescription}</span>
                        ${stockDetailsHtml}
                    `;
                    
                    Swal.fire({
                        title: 'Stock Check Complete',
                        html: `
                            <div class="text-center">
                                <span class="badge ${docTypeClass} fs-5 mb-3">${docTypeLabel}</span>
                                <p>${docTypeDescription}</p>
                            </div>
                        `,
                        icon: 'info',
                        confirmButtonText: 'OK'
                    });
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: data.message || 'Failed to check stock availability.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            })
            .catch(error => {
                Swal.close();
                console.error('Error:', error);
                Swal.fire({
                    title: 'Error!',
                    text: 'Failed to check stock availability.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            });
        }

        // Handle window resize for responsive adjustments
        window.addEventListener('resize', function() {
            const currentType = document.getElementById('request_type').value;
            
            if (window.innerWidth >= 992) {
                adjustColumnWidths(currentType);
            } else {
                // Remove project-mode class on tablet/mobile
                document.getElementById('itemsContainer').classList.remove('project-mode');
            }
        });

        // Form validation
        document.getElementById('createPRForm').addEventListener('submit', function(e) {
            const requestType = document.getElementById('request_type').value;
            const projectId = document.getElementById('project_id').value;
            const supplierId = document.getElementById('supplier_id').value;
            
            if (requestType === 'project' && !projectId) {
                e.preventDefault();
                Swal.fire({
                    title: 'Error!',
                    text: 'Please select a project for the purchase request.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return false;
            }
            
            // Validate at least one item
            const itemRows = document.querySelectorAll('.item-row');
            if (itemRows.length === 0) {
                e.preventDefault();
                Swal.fire({
                    title: 'Error!',
                    text: 'Please add at least one item to the purchase request.',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
                return false;
            }
            
            // Validate item selection for each row
            let itemsValid = true;
            itemRows.forEach((row, index) => {
                const itemId = row.querySelector('.item-id-hidden').value;
                if (!itemId) {
                    itemsValid = false;
                    Swal.fire({
                        title: 'Error!',
                        text: `Please select an item for Row ${index + 1}.`,
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            });
            
            if (!itemsValid) {
                e.preventDefault();
                return false;
            }
            
            // Validate warehouse selection for each item
            let warehouseValid = true;
            itemRows.forEach((row, index) => {
                const warehouseSelect = row.querySelector('select[name*="[warehouse_id]"]');
                if (!warehouseSelect || warehouseSelect.value === '') {
                    warehouseValid = false;
                    Swal.fire({
                        title: 'Error!',
                        text: `Please select a warehouse for Item ${index + 1}.`,
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            });
            
            if (!warehouseValid) {
                e.preventDefault();
                return false;
            }
            
            return true;
        });

        // Reset modal when closed
        document.getElementById('createPRModal').addEventListener('hidden.bs.modal', function() {
            // Clear selected items
            Object.keys(selectedItems).forEach(key => delete selectedItems[key]);
            
            // Reset item rows
            const container = document.getElementById('itemsContainer');
            while (container.children.length > 1) {
                container.removeChild(container.lastChild);
            }
            
            // Reset first item row
            const firstRow = container.querySelector('.item-row');
            if (firstRow) {
                const searchInput = firstRow.querySelector('.item-search-input');
                const hiddenInput = firstRow.querySelector('.item-id-hidden');
                const quantityInput = firstRow.querySelector('input[name*="quantity"]');
                const warehouseSelect = firstRow.querySelector('select[name*="[warehouse_id]"]');
                
                if (searchInput) {
                    searchInput.value = '';
                    searchInput.classList.remove('item-selected');
                }
                if (hiddenInput) hiddenInput.value = '';
                if (quantityInput) quantityInput.value = '';
                if (warehouseSelect) warehouseSelect.value = '';
            }
            
            // Reset item count
            itemCount = 1;
            
            // Reset request type to project
            selectRequestType('project');
        });

        // Logout function
        const logoutLink = document.getElementById('logoutLink');
        if (logoutLink) {
            logoutLink.addEventListener('click', function(e) {
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
        }
