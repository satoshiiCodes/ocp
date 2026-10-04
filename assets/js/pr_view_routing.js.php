/* pr_view_routing.js
 * Extracted from pr_view_routing.php - inline <script> block #1.
 * Server data arrives through window.OCP_PAGE_PR_VIEW_ROUTING
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
const PR_VIEW_ROUTING_DATA = window.OCP_PAGE_PR_VIEW_ROUTING || {};
        // Tested in the browser: this script is its own request, so the page's
        // variables are not available to it.
        if (PR_VIEW_ROUTING_DATA.hasMessage) {
            Swal.fire({
                title: PR_VIEW_ROUTING_DATA.swalDataTitle,
                text: PR_VIEW_ROUTING_DATA.swalDataText,
                icon: PR_VIEW_ROUTING_DATA.swalDataIcon,
                confirmButtonText: 'OK'
            });
        }

        function formatNumberWithCommas(number) {
            return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }

        function parseNumberWithCommas(formattedNumber) {
            return parseFloat(formattedNumber.replace(/,/g, ''));
        }

        function formatCurrency(amount) {
            return '₱' + formatNumberWithCommas(parseFloat(amount).toFixed(2));
        }

        function formatDateMMDDYYYY(dateString) {
            if (!dateString || dateString === 'Not set' || dateString === '-') return dateString;
            
            try {
                const date = new Date(dateString);
                if (isNaN(date.getTime())) return dateString;
                
                const month = (date.getMonth() + 1).toString().padStart(2, '0');
                const day = date.getDate().toString().padStart(2, '0');
                const year = date.getFullYear();
                
                return month + '-' + day + '-' + year;
            } catch(e) {
                return dateString;
            }
        }

        function calculatePOTotals() {
            let grandTotal = 0;
            let selectedCount = 0;
            
            document.querySelectorAll('.po-item-checkbox:checked').forEach(checkbox => {
                selectedCount++;
                const itemId = checkbox.getAttribute('data-item-id');
                const quantity = parseFloat(document.querySelector(`.po-quantity-input[data-item-id="${itemId}"]`).value) || 0;
                const unitCost = parseFloat(document.querySelector(`.po-unit-cost-input[data-item-id="${itemId}"]`).value) || 0;
                const total = quantity * unitCost;
                
                const totalElement = document.getElementById(`po_total_${itemId}`);
                if (totalElement) {
                    totalElement.textContent = formatCurrency(total);
                }
                grandTotal += total;
            });
            
            const selectedCountElement = document.getElementById('poSelectedCount');
            if (selectedCountElement) {
                selectedCountElement.textContent = selectedCount;
            }
            
            const grandTotalElement = document.getElementById('poGrandTotal');
            if (grandTotalElement) {
                grandTotalElement.textContent = formatNumberWithCommas(grandTotal.toFixed(2));
            }
            
            const createPOBtn = document.getElementById('createPOBtn');
            if (createPOBtn) {
                createPOBtn.disabled = selectedCount === 0;
            }
        }
        
        const selectAllPOCheckbox = document.getElementById('selectAllPOItems');
        if (selectAllPOCheckbox) {
            selectAllPOCheckbox.addEventListener('change', function() {
                document.querySelectorAll('.po-item-checkbox').forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                calculatePOTotals();
            });
        }
        
        document.querySelectorAll('.po-item-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', calculatePOTotals);
        });
        
        document.querySelectorAll('.po-quantity-input, .po-unit-cost-input').forEach(input => {
            input.addEventListener('input', calculatePOTotals);
        });

        function calculateWSTotals() {
            let selectedCount = 0;
            
            document.querySelectorAll('.ws-item-checkbox:checked').forEach(checkbox => {
                selectedCount++;
            });
            
            const selectedCountElement = document.getElementById('wsSelectedCount');
            if (selectedCountElement) {
                selectedCountElement.textContent = selectedCount;
            }
            
            const createWSBtn = document.getElementById('createWSBtn');
            if (createWSBtn) {
                createWSBtn.disabled = selectedCount === 0;
            }
        }
        
        const selectAllWSCheckbox = document.getElementById('selectAllWSItems');
        if (selectAllWSCheckbox) {
            selectAllWSCheckbox.addEventListener('change', function() {
                document.querySelectorAll('.ws-item-checkbox').forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                calculateWSTotals();
            });
        }
        
        document.querySelectorAll('.ws-item-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', calculateWSTotals);
        });
        
        document.querySelectorAll('.ws-quantity-input').forEach(input => {
            input.addEventListener('input', calculateWSTotals);
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof calculatePOTotals === 'function') {
                calculatePOTotals();
            }
            
            if (typeof calculateWSTotals === 'function') {
                calculateWSTotals();
            }
            
            const createPOBtn = document.getElementById('createPOBtn');
            if (createPOBtn) {
                const requestType = PR_VIEW_ROUTING_DATA.requestType || '';
                const documentType = PR_VIEW_ROUTING_DATA.documentType || '';
                const itemsAvailable = (PR_VIEW_ROUTING_DATA.itemsAvailable ? 'true' : 'false');
                
                if (itemsAvailable) {
                    createPOBtn.disabled = false;
                }
            }
            
            const createWSBtn = document.getElementById('createWSBtn');
            if (createWSBtn) {
                const wsItemsAvailable = PR_VIEW_ROUTING_DATA.itemsForWithdrawal;
                if (wsItemsAvailable) {
                    createWSBtn.disabled = false;
                }
            }
            
            // Hide Batch Number column using CSS
            const style = document.createElement('style');
            style.textContent = '.batch-column { display: none; }';
            document.head.appendChild(style);
        });

        document.addEventListener('DOMContentLoaded', function() {
            const viewButtons = document.querySelectorAll('.view-po-btn');
            const viewModal = new bootstrap.Modal(document.getElementById('viewPOModal'));
            
            viewButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const poId = this.getAttribute('data-po-id');
                    const poNumber = this.getAttribute('data-po-number');
                    const poDate = formatDateMMDDYYYY(this.getAttribute('data-po-date'));
                    const expectedDelivery = formatDateMMDDYYYY(this.getAttribute('data-expected-delivery'));
                    const totalAmount = this.getAttribute('data-total-amount');
                    const poStatus = this.getAttribute('data-po-status');
                    const poRemarks = this.getAttribute('data-po-remarks');
                    
                    // Format PO number with status badge
                    const statusClass = getPOStatusBadgeClass(poStatus);
                    document.getElementById('modal-po-number').innerHTML = poNumber + ' <span class="badge ' + statusClass + '">' + poStatus + '</span>';
                    
                    document.getElementById('modal-po-date').textContent = poDate || '-';
                    document.getElementById('modal-expected-delivery').textContent = expectedDelivery || '-';
                    document.getElementById('modal-total-amount').textContent = totalAmount ? '₱' + formatNumberWithCommas(parseFloat(totalAmount).toFixed(2)) : '-';
                    
                    // Remove the status row from the table if it exists
                    const statusRow = document.querySelector('#modal-po-status')?.closest('tr');
                    if (statusRow) {
                        statusRow.style.display = 'none';
                    }
                    
                    document.getElementById('modal-po-remarks').textContent = poRemarks || '-';
                    
                    const itemsContainer = document.getElementById('modal-po-items');
                    itemsContainer.innerHTML = '';
                    
                    // The page prepares this through a captured fragment, so the island
                    // hands it over as a JSON string, not an object. Parsed here: on a
                    // string poItems[poId] is undefined and poItems[poId].forEach throws.
                    const poItemsRaw = PR_VIEW_ROUTING_DATA.poItemsDetails;
                    const poItems = typeof poItemsRaw === 'string' ? JSON.parse(poItemsRaw || '{}') : (poItemsRaw || {});
                    
                    if (poItems[poId] && poItems[poId].length > 0) {
                        poItems[poId].forEach(item => {
                            const received = parseFloat(item.received_quantity || 0);
                            const remaining = parseFloat(item.quantity || 0) - received;
                            const status = item.status || 'pending';
                            const unitCost = parseFloat(item.unit_cost || 0);
                            const quantity = parseFloat(item.quantity || 0);
                            
                            let totalCostToDisplay;
                            if (received === 0) {
                                totalCostToDisplay = quantity * unitCost;
                            } else {
                                totalCostToDisplay = received * unitCost;
                            }
                            
                            function formatItemNameWithCode(itemName, itemCode) {
                                if (!itemCode) {
                                    return itemName || 'N/A';
                                }
                                return (itemName || 'N/A') + ' (' + itemCode + ')';
                            }

                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td>${formatItemNameWithCode(item.item_name, item.item_code)}</td>
                                ${PR_VIEW_ROUTING_DATA.requestType2 ? `<td>${item.warehouse_name || 'N/A'}</td>` : ''}
                                <td>${item.supplier_name || 'N/A'}</td>
                                <td>${quantity}</td>
                                <td>${received.toFixed(2)}</td>
                                <td>${remaining.toFixed(2)}</td>
                                <td>${formatCurrency(unitCost)}</td>
                                <td>${formatCurrency(totalCostToDisplay)}</td>
                                <td>
                                    <span class="badge ${getPOStatusBadgeClass(status)}">
                                        ${status}
                                    </span>
                                </td>
                            `;
                            itemsContainer.appendChild(row);
                        });
                    } else {
                        const colspanValue = PR_VIEW_ROUTING_DATA.requestType3;
                        itemsContainer.innerHTML = `
                            <tr>
                                <td colspan="${colspanValue}" class="text-center py-3">
                                    <i class="fas fa-exclamation-circle text-warning me-2"></i>
                                    No items found for this purchase order.
                                </td>
                            </tr>
                        `;
                    }
                    
                    viewModal.show();
                });
            });
            
            // Function to get PO status badge class
            function getPOStatusBadgeClass(status) {
                status = (status || '').toLowerCase();
                switch(status) {
                    case 'approved':
                        return 'bg-success';
                    case 'confirmed':
                    case 'delivered':
                    case 'completed':
                        return 'bg-success';
                    case 'pending':
                    case 'draft':
                        return 'bg-warning';
                    case 'rejected':
                    case 'cancelled':
                        return 'bg-danger';
                    case 'processing':
                        return 'bg-info';
                    case 'partially_received':
                        return 'bg-warning';
                    default:
                        return 'bg-secondary';
                }
            }
            
            function formatNumberWithCommas(number) {
                return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
            }
            
            function formatCurrency(amount) {
                return '₱' + formatNumberWithCommas(parseFloat(amount).toFixed(2));
            }
            
            function formatDateMMDDYYYY(dateString) {
                if (!dateString || dateString === 'Not set' || dateString === '-') return dateString;
                
                try {
                    const date = new Date(dateString);
                    if (isNaN(date.getTime())) return dateString;
                    
                    const month = (date.getMonth() + 1).toString().padStart(2, '0');
                    const day = date.getDate().toString().padStart(2, '0');
                    const year = date.getFullYear();
                    
                    return month + '-' + day + '-' + year;
                } catch(e) {
                    return dateString;
                }
            }
        });

            document.addEventListener('DOMContentLoaded', function() {
        const viewWSButtons = document.querySelectorAll('.view-ws-btn');
        const viewWSModal = new bootstrap.Modal(document.getElementById('viewWSModal'));
        
        viewWSButtons.forEach(button => {
            button.addEventListener('click', function() {
                const wsId = this.getAttribute('data-ws-id');
                const wsNumber = this.getAttribute('data-ws-number');
                const wsDate = formatDateMMDDYYYY(this.getAttribute('data-ws-date'));
                const warehouse = this.getAttribute('data-warehouse');
                const wsStatus = this.getAttribute('data-ws-status');
                const wsRemarks = this.getAttribute('data-ws-remarks');
                
                // FIX: Set WS Number with status badge
                const statusClass = getWSStatusBadgeClass(wsStatus);
                document.getElementById('modal-ws-number').innerHTML = wsNumber + ' <span class="badge ' + statusClass + '">' + wsStatus + '</span>';
                
                document.getElementById('modal-ws-date').textContent = wsDate || '-';
                document.getElementById('modal-ws-warehouse').textContent = warehouse || '-';
                
                // Hide the separate status row since we now show it next to the WS number
                const statusRow = document.querySelector('#modal-ws-status')?.closest('tr');
                if (statusRow) {
                    statusRow.style.display = 'none';
                }
                
                document.getElementById('modal-ws-remarks').textContent = wsRemarks || '-';
                
                // Rest of the code remains the same...
                const itemsContainer = document.getElementById('modal-ws-items');
                itemsContainer.innerHTML = '';
                
                // Same as poItems above: the island carries this as JSON text.
                const wsItemsRaw = PR_VIEW_ROUTING_DATA.wsItemsDetails;
                const wsItems = typeof wsItemsRaw === 'string' ? JSON.parse(wsItemsRaw || '{}') : (wsItemsRaw || {});
                
                if (wsItems[wsId] && wsItems[wsId].length > 0) {
                    wsItems[wsId].forEach(item => {
                        const status = item.status || 'pending';
                        const statusClass = getWSStatusBadgeClass(status);
                        const quantity = parseFloat(item.quantity || 0);
                        const unitCost = parseFloat(item.unit_cost || 0);
                        const totalCost = parseFloat(item.total_cost || quantity * unitCost);
                        
                        function formatItemNameWithCode(itemName, itemCode) {
                            if (!itemCode) {
                                return itemName || 'N/A';
                            }
                            return (itemName || 'N/A') + ' (' + itemCode + ')';
                        }

                        function formatCurrency(amount) {
                            return '₱' + amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
                        }

                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${formatItemNameWithCode(item.item_name, item.item_code)}</td>
                            <td>${item.warehouse_name || 'N/A'}</td>
                            <td>${quantity}</td>
                            <td>${formatCurrency(unitCost)}</td>
                            <td>${formatCurrency(totalCost)}</td>
                            <td>
                                <span class="badge ${statusClass}">
                                    ${status}
                                </span>
                            </td>
                        `;
                        itemsContainer.appendChild(row);
                    });
                } else {
                    itemsContainer.innerHTML = `
                        <tr>
                            <td colspan="6" class="text-center py-3">
                                <i class="fas fa-exclamation-circle text-warning me-2"></i>
                                No items found for this withdrawal slip.
                            </td>
                        </tr>
                    `;
                }
                
                viewWSModal.show();
            });
        });
        
        // Function to get WS status badge class
        function getWSStatusBadgeClass(status) {
            status = (status || '').toLowerCase();
            switch(status) {
                case 'confirmed':
                case 'released':
                    return 'bg-success';
                case 'approved':
                    return 'bg-success';
                case 'processing':
                    return 'bg-info';
                case 'pending':
                    return 'bg-warning';
                case 'draft':
                    return 'bg-secondary';
                case 'cancelled':
                case 'rejected':
                    return 'bg-danger';
                default:
                    return 'bg-secondary';
            }
        }
    });

        document.addEventListener('DOMContentLoaded', function() {
            const processWSButtons = document.querySelectorAll('.process-ws-btn');
            const processWSModal = new bootstrap.Modal(document.getElementById('processWSModal'));
            
            processWSButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const wsId = this.getAttribute('data-ws-id');
                    const wsNumber = this.getAttribute('data-ws-number');
                    
                    document.getElementById('process_ws_id').value = wsId || '';
                    document.getElementById('process_ws_number').value = wsNumber || '';
                    
                    processWSModal.show();
                });
            });
        });

        if (PR_VIEW_ROUTING_DATA.isProjectRequest) {
        document.addEventListener('DOMContentLoaded', function() {
            const deliverButtons = document.querySelectorAll('.deliver-item');
            const deliverModal = new bootstrap.Modal(document.getElementById('deliverItemModal'));
            const deliverForm = document.getElementById('deliverForm');
            const deliverAction = document.getElementById('deliver_action');
            const deliverQuantityInput = document.getElementById('deliver_quantity_input');
            const deliverQuantityField = document.getElementById('deliver_quantity_field');
            const deliveryMethodText = document.getElementById('delivery-method-text');
            const deliverSubmitBtn = document.getElementById('deliver_submit_btn');
            const availableStockInput = document.getElementById('deliver_available_stock');
            
            deliverButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const itemId = this.getAttribute('data-item-id');
                    const itemName = this.getAttribute('data-item-name');
                    const warehouseId = this.getAttribute('data-warehouse-id');
                    const warehouseName = this.getAttribute('data-warehouse-name');
                    const requestedQuantity = this.getAttribute('data-quantity');
                    const availableStock = this.getAttribute('data-available-stock');
                    const deliveryType = this.getAttribute('data-delivery-type');
                    
                    document.getElementById('deliver_item_id').value = itemId || '0';
                    document.getElementById('deliver_item_name').value = itemName || '';
                    document.getElementById('deliver_warehouse_id').value = warehouseId || '0';
                    document.getElementById('deliver_warehouse_name').value = warehouseName || '';
                    document.getElementById('deliver_requested_quantity').value = requestedQuantity || '0';
                    document.getElementById('deliver_requested_quantity_display').value = requestedQuantity || '0';
                    document.getElementById('deliver_available_stock').value = availableStock || '0';
                    
                    if (deliveryType === 'full') {
                        if (deliverAction) deliverAction.value = 'deliver_to_project';
                        if (document.getElementById('deliver_quantity')) document.getElementById('deliver_quantity').value = requestedQuantity || '0';
                        if (deliverQuantityField) deliverQuantityField.style.display = 'none';
                        if (deliveryMethodText) deliveryMethodText.textContent = 'This will release the full requested quantity to the project using FIFO (First-In, First-Out) method.';
                        if (deliverSubmitBtn) deliverSubmitBtn.textContent = 'Release Full Quantity';
                    } else {
                        if (deliverAction) deliverAction.value = 'deliver_partial';
                        if (deliverQuantityField) deliverQuantityField.style.display = 'block';
                        if (deliverQuantityInput) {
                            deliverQuantityInput.value = '';
                            deliverQuantityInput.max = Math.min(parseInt(requestedQuantity) || 0, parseInt(availableStock) || 0);
                        }
                        if (deliveryMethodText) deliveryMethodText.textContent = 'This will release the specified quantity to the project using FIFO (First-In, First-Out) method.';
                        if (deliverSubmitBtn) deliverSubmitBtn.textContent = 'Release Partial Quantity';
                    }
                    
                    deliverModal.show();
                });
            });
            
            if (deliverForm) {
                deliverForm.addEventListener('submit', function(e) {
                    const deliverActionValue = document.getElementById('deliver_action').value;
                    const requestedQuantity = parseInt(document.getElementById('deliver_requested_quantity').value) || 0;
                    const quantityElement = document.getElementById('deliver_quantity');
                    
                    if (deliverActionValue === 'deliver_partial') {
                        const deliverQuantity = parseInt(document.getElementById('deliver_quantity_input').value) || 0;
                        if (deliverQuantity > requestedQuantity) {
                            e.preventDefault();
                            alert('Release quantity cannot exceed requested quantity.');
                            return;
                        }
                        if (quantityElement) {
                            quantityElement.value = deliverQuantity;
                        }
                    }
                });
            }
        });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const thresholdInput = document.getElementById('threshold_amount_to_adjust');
            const adjustThresholdForm = document.getElementById('adjustThresholdForm');
            
            if (thresholdInput) {
                thresholdInput.addEventListener('input', function(e) {
                    let value = this.value.replace(/[^\d.]/g, '');
                    
                    const decimalCount = (value.match(/\./g) || []).length;
                    if (decimalCount > 1) {
                        value = value.substring(0, value.lastIndexOf('.'));
                    }
                    
                    let parts = value.split('.');
                    let wholePart = parts[0];
                    let decimalPart = parts.length > 1 ? '.' + parts[1] : '';
                    
                    if (wholePart) {
                        wholePart = wholePart.replace(/\B(?=(\d{3})+(?!\d))/g, ",");
                    }
                    
                    this.value = wholePart + decimalPart;
                });
                
                if (adjustThresholdForm) {
                    adjustThresholdForm.addEventListener('submit', function(e) {
                        const submitButton = e.submitter;
                        const adjustmentType = submitButton.value;
                        
                        const formattedValue = thresholdInput.value.trim();
                        
                        if (formattedValue === '') {
                            e.preventDefault();
                            Swal.fire({
                                title: 'No Amount',
                                text: 'Please enter an amount to adjust the threshold.',
                                icon: 'info',
                                confirmButtonText: 'OK'
                            });
                            thresholdInput.focus();
                            return;
                        }
                        
                        const numericValue = parseNumberWithCommas(formattedValue);
                        
                        if (isNaN(numericValue) || numericValue <= 0) {
                            e.preventDefault();
                            Swal.fire({
                                title: 'Invalid Amount',
                                text: 'Please enter a valid positive amount.',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                            thresholdInput.focus();
                            return;
                        }
                        
                        if (adjustmentType === 'subtract') {
                            const currentThreshold = PR_VIEW_ROUTING_DATA.thresholdAmount;
                            if (numericValue > currentThreshold) {
                                e.preventDefault();
                                Swal.fire({
                                    title: 'Invalid Subtraction',
                                    text: 'Cannot subtract more than the current threshold amount.',
                                    icon: 'error',
                                    confirmButtonText: 'OK'
                                });
                                thresholdInput.focus();
                                return;
                            }
                        }
                        
                        thresholdInput.value = numericValue.toFixed(2);
                        
                        const adjustmentTypeInput = document.createElement('input');
                        adjustmentTypeInput.type = 'hidden';
                        adjustmentTypeInput.name = 'adjustment_type';
                        adjustmentTypeInput.value = adjustmentType;
                        adjustThresholdForm.appendChild(adjustmentTypeInput);
                    });
                }
            }
        });

