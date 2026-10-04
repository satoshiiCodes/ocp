/* pr_spare_view_routing.js
 * Extracted from pr_spare_view_routing.php - inline <script> block #1.
 * Server data arrives through window.OCP_PAGE_PR_SPARE_VIEW_ROUTING
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
const PR_SPARE_VIEW_ROUTING_DATA = window.OCP_PAGE_PR_SPARE_VIEW_ROUTING || {};

// The item lists the detail modals render. Their partials echo json_encode(), so the page
// stores the encoded TEXT in the island and this script receives a string, not an array.
// Parsing it here is what turns "Job Order Items:" and "Withdrawal Slip Items:" back into
// rows; reading the member directly and calling .length on it measured its characters.
function ocpSpareItems(value) {
    // Tolerate a real array too, in case a page ever hands the island decoded data.
    if (Array.isArray(value)) {
        return value;
    }
    if (typeof value !== 'string' || value.trim() === '') {
        return [];
    }
    try {
        const parsed = JSON.parse(value);
        return Array.isArray(parsed) ? parsed : [];
    } catch (e) {
        return [];
    }
}
        // Show SweetAlert2 notifications
        // Tested here in the browser: this script is its own request, so the
        // page's variables are not in scope.
        if (PR_SPARE_VIEW_ROUTING_DATA.hasMessage) {
            Swal.fire({
                title: PR_SPARE_VIEW_ROUTING_DATA.swalDataTitle || '',
                text: PR_SPARE_VIEW_ROUTING_DATA.swalDataText || '',
                icon: PR_SPARE_VIEW_ROUTING_DATA.swalDataIcon || '',
                confirmButtonText: 'OK'
            });
        }

        // Format number with commas for display
        function formatNumberWithCommas(number) {
            return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }

        // Remove commas and convert to number for calculation
        function parseNumberWithCommas(formattedNumber) {
            return parseFloat(formattedNumber.replace(/,/g, ''));
        }

        // Format currency with peso sign and commas
        function formatCurrency(amount) {
            return '₱' + formatNumberWithCommas(parseFloat(amount).toFixed(2));
        }

        // Action button functions
        function requestReplenishment(itemId, partName) {
            Swal.fire({
                title: 'Request Stock Replenishment',
                text: 'Are you sure you want to request stock replenishment for "' + partName + '"?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, request',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // In a real application, you would make an AJAX call here
                    // For now, just show a success message
                    Swal.fire({
                        title: 'Request Sent!',
                        text: 'Stock replenishment request has been sent for ' + partName,
                        icon: 'success',
                        confirmButtonText: 'OK'
                    });
                }
            });
        }

        function addToJobOrder(itemId, partName) {
            Swal.fire({
                title: 'Add to Job Order',
                text: 'Add "' + partName + '" to the Job Order?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, add',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Check if Job Order modal is already open, if not open it
                    const createJobOrderModal = new bootstrap.Modal(document.getElementById('createJobOrderModal'));
                    createJobOrderModal.show();
                    
                    // After a short delay, check the checkbox for this item
                    setTimeout(() => {
                        const checkbox = document.querySelector(`.item-checkbox-jo[data-item-id="${itemId}"]`);
                        if (checkbox) {
                            checkbox.checked = true;
                            // Trigger change event to recalculate totals
                            checkbox.dispatchEvent(new Event('change'));
                        }
                    }, 500);
                }
            });
        }

        function addToWithdrawalSlip(itemId, partName) {
            Swal.fire({
                title: 'Add to Withdrawal Slip',
                text: 'Add "' + partName + '" to the Withdrawal Slip?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, add',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Check if Withdrawal Slip modal is already open, if not open it
                    const createWithdrawalSlipModal = new bootstrap.Modal(document.getElementById('createWithdrawalSlipModal'));
                    createWithdrawalSlipModal.show();
                    
                    // After a short delay, check the checkbox for this item
                    setTimeout(() => {
                        const checkbox = document.querySelector(`.item-checkbox-ws[data-item-id="${itemId}"]`);
                        if (checkbox) {
                            checkbox.checked = true;
                            // Trigger change event to recalculate totals
                            checkbox.dispatchEvent(new Event('change'));
                        }
                    }, 500);
                }
            });
        }

        function addToPO(itemId, partName) {
            Swal.fire({
                title: 'Add to Purchase Order',
                text: 'Add "' + partName + '" to the purchase order?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, add',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Check if PO modal is already open, if not open it
                    const createPOModal = new bootstrap.Modal(document.getElementById('createPOModal'));
                    createPOModal.show();
                    
                    // After a short delay, check the checkbox for this item
                    setTimeout(() => {
                        const checkbox = document.querySelector(`.item-checkbox-po[data-item-id="${itemId}"]`);
                        if (checkbox) {
                            checkbox.checked = true;
                            // Trigger change event to recalculate totals
                            checkbox.dispatchEvent(new Event('change'));
                        }
                    }, 500);
                }
            });
        }

        function receiveItem(itemId, partName) {
            Swal.fire({
                title: 'Receive Item',
                text: 'Mark "' + partName + '" as received?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, receive',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // In a real application, you would make an AJAX call here
                    // For now, just show a success message
                    Swal.fire({
                        title: 'Item Received!',
                        text: partName + ' has been marked as received',
                        icon: 'success',
                        confirmButtonText: 'OK'
                    });
                }
            });
        }

        function releaseItem(itemId, partName) {
            Swal.fire({
                title: 'Release Item',
                text: 'Mark "' + partName + '" as released?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, release',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // In a real application, you would make an AJAX call here
                    // For now, just show a success message
                    Swal.fire({
                        title: 'Item Released!',
                        text: partName + ' has been marked as released',
                        icon: 'success',
                        confirmButtonText: 'OK'
                    });
                }
            });
        }

        // Purchase Order functionality
        // Calculate totals for PO items
        function calculatePOTotals() {
            let grandTotal = 0;
            let selectedCount = 0;
            
            document.querySelectorAll('.item-checkbox-po:checked').forEach(checkbox => {
                selectedCount++;
                const itemId = checkbox.getAttribute('data-item-id');
                const quantity = parseFloat(document.querySelector(`.quantity-input-po[data-item-id="${itemId}"]`).value) || 0;
                const unitCost = parseFloat(document.querySelector(`.unit-cost-input-po[data-item-id="${itemId}"]`).value) || 0;
                const total = quantity * unitCost;
                
                document.getElementById(`total_po_${itemId}`).textContent = formatCurrency(total);
                grandTotal += total;
            });
            
            document.getElementById('selectedCountPO').textContent = selectedCount;
            document.getElementById('grandTotalPO').textContent = formatNumberWithCommas(grandTotal.toFixed(2));
            
            // Enable/disable create button based on selection
            document.getElementById('createPOBtn').disabled = selectedCount === 0;
        }
        
        // Select all items for PO
        const selectAllCheckboxPO = document.getElementById('selectAllItemsPO');
        if (selectAllCheckboxPO) {
            selectAllCheckboxPO.addEventListener('change', function() {
                document.querySelectorAll('.item-checkbox-po').forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                calculatePOTotals();
            });
        }
        
        // Individual item selection for PO
        document.querySelectorAll('.item-checkbox-po').forEach(checkbox => {
            checkbox.addEventListener('change', calculatePOTotals);
        });
        
        // Quantity and unit cost changes for PO
        document.querySelectorAll('.quantity-input-po, .unit-cost-input-po').forEach(input => {
            input.addEventListener('input', calculatePOTotals);
        });
        
        // Initial calculation for PO
        calculatePOTotals();

        // Job Order functionality
        // Calculate totals for Job Order items
        function calculateJOTotals() {
            let selectedCount = 0;
            let totalQuantity = 0;
            
            document.querySelectorAll('.item-checkbox-jo:checked').forEach(checkbox => {
                selectedCount++;
                const itemId = checkbox.getAttribute('data-item-id');
                const quantity = parseFloat(document.querySelector(`.quantity-input-jo[data-item-id="${itemId}"]`).value) || 0;
                totalQuantity += quantity;
            });
            
            document.getElementById('selectedCountJO').textContent = selectedCount;
            
            // Enable/disable create button based on selection and quantity > 0
            const createBtn = document.getElementById('createJobOrderBtn');
            createBtn.disabled = selectedCount === 0 || totalQuantity === 0;
            
            // Update button text with total quantity
            if (selectedCount > 0 && totalQuantity > 0) {
                createBtn.innerHTML = `<i class="fas fa-tools me-1"></i> Create Job Order (${totalQuantity.toFixed(2)} items)`;
            } else {
                createBtn.innerHTML = `<i class="fas fa-tools me-1"></i> Create Job Order`;
            }
        }
        
        // Select all items for Job Order (only those with available stock)
        const selectAllCheckboxJO = document.getElementById('selectAllItemsJO');
        if (selectAllCheckboxJO) {
            selectAllCheckboxJO.addEventListener('change', function() {
                document.querySelectorAll('.item-checkbox-jo:not(:disabled)').forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                calculateJOTotals();
            });
        }
        
        // Individual item selection for Job Order
        document.querySelectorAll('.item-checkbox-jo').forEach(checkbox => {
            checkbox.addEventListener('change', calculateJOTotals);
        });
        
        // Quantity changes for Job Order - limit to available stock
        document.querySelectorAll('.quantity-input-jo').forEach(input => {
            input.addEventListener('input', function() {
                const itemId = this.getAttribute('data-item-id');
                const maxStock = parseFloat(this.getAttribute('data-max-stock')) || 0;
                const quantity = parseFloat(this.value) || 0;
                
                // Ensure quantity doesn't exceed available stock
                if (quantity > maxStock) {
                    this.value = maxStock;
                    Swal.fire({
                        title: 'Quantity Limit',
                        text: `Cannot exceed available stock of ${maxStock}`,
                        icon: 'warning',
                        confirmButtonText: 'OK'
                    });
                }
                
                // Ensure quantity is at least 0.01 if positive
                if (quantity > 0 && quantity < 0.01) {
                    this.value = 0.01;
                }
                
                calculateJOTotals();
            });
        });
        
        // Initial calculation for Job Order
        calculateJOTotals();

        // Withdrawal Slip functionality
        // Calculate totals for Withdrawal Slip items
        function calculateWSTotals() {
            let selectedCount = 0;
            let totalQuantity = 0;
            
            document.querySelectorAll('.item-checkbox-ws:checked').forEach(checkbox => {
                selectedCount++;
                const itemId = checkbox.getAttribute('data-item-id');
                const quantity = parseFloat(document.querySelector(`.quantity-input-ws[data-item-id="${itemId}"]`).value) || 0;
                totalQuantity += quantity;
            });
            
            document.getElementById('selectedCountWS').textContent = selectedCount;
            
            // Enable/disable create button based on selection and quantity > 0
            const createBtn = document.getElementById('createWithdrawalSlipBtn');
            createBtn.disabled = selectedCount === 0 || totalQuantity === 0;
            
            // Update button text with total quantity
            if (selectedCount > 0 && totalQuantity > 0) {
                createBtn.innerHTML = `<i class="fas fa-file-invoice me-1"></i> Create Withdrawal Slip for ${PR_SPARE_VIEW_ROUTING_DATA.isIssueMaterials || 'Spare Parts'} (${totalQuantity.toFixed(2)} items)`;
            } else {
                createBtn.innerHTML = `<i class="fas fa-file-invoice me-1"></i> Create Withdrawal Slip for ${PR_SPARE_VIEW_ROUTING_DATA.isIssueMaterials || 'Spare Parts'}`;
            }
        }
        
        // Select all items for Withdrawal Slip (only those with available stock)
        const selectAllCheckboxWS = document.getElementById('selectAllItemsWS');
        if (selectAllCheckboxWS) {
            selectAllCheckboxWS.addEventListener('change', function() {
                document.querySelectorAll('.item-checkbox-ws:not(:disabled)').forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                calculateWSTotals();
            });
        }
        
        // Individual item selection for Withdrawal Slip
        document.querySelectorAll('.item-checkbox-ws').forEach(checkbox => {
            checkbox.addEventListener('change', calculateWSTotals);
        });
        
        // Quantity changes for Withdrawal Slip - limit to available stock
        document.querySelectorAll('.quantity-input-ws').forEach(input => {
            input.addEventListener('input', function() {
                const itemId = this.getAttribute('data-item-id');
                const maxStock = parseFloat(this.getAttribute('data-max-stock')) || 0;
                const quantity = parseFloat(this.value) || 0;
                
                // Ensure quantity doesn't exceed available stock
                if (quantity > maxStock) {
                    this.value = maxStock;
                    Swal.fire({
                        title: 'Quantity Limit',
                        text: `Cannot exceed available stock of ${maxStock}`,
                        icon: 'warning',
                        confirmButtonText: 'OK'
                    });
                }
                
                // Ensure quantity is at least 0.01 if positive
                if (quantity > 0 && quantity < 0.01) {
                    this.value = 0.01;
                }
                
                calculateWSTotals();
            });
        });
        
        // Initial calculation for Withdrawal Slip
        calculateWSTotals();

        // View PO Modal functionality
        document.addEventListener('DOMContentLoaded', function() {
            const viewButtons = document.querySelectorAll('.view-po-btn');
            const viewModal = new bootstrap.Modal(document.getElementById('viewPOModal'));
            
            viewButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const poId = this.getAttribute('data-po-id');
                    const poNumber = this.getAttribute('data-po-number');
                    const poDate = this.getAttribute('data-po-date');
                    const expectedDelivery = this.getAttribute('data-expected-delivery');
                    const totalAmount = this.getAttribute('data-total-amount');
                    const poStatus = this.getAttribute('data-po-status');
                    const poRemarks = this.getAttribute('data-po-remarks');
                    
                    // Set basic PO information with status badge next to PO number
                    document.getElementById('modal-po-number').innerHTML = poNumber + ' <span class="badge ' + getPOStatusBadgeClass(poStatus.toLowerCase()) + '">' + poStatus + '</span>';
                    document.getElementById('modal-po-date').textContent = poDate;
                    document.getElementById('modal-expected-delivery').textContent = expectedDelivery;
                    document.getElementById('modal-total-amount').textContent = '₱' + formatNumberWithCommas(parseFloat(totalAmount).toFixed(2));
                    document.getElementById('modal-po-remarks').textContent = poRemarks || '-';
                    
                    // Clear previous items
                    const itemsContainer = document.getElementById('modal-po-items');
                    itemsContainer.innerHTML = '';
                    
                    // Get PO items from PHP data
                    // The island carries this as a JSON string (the page encodes it once); tolerate a
// raw object too, so this works however it arrives.
                    const poItemsRaw = PR_SPARE_VIEW_ROUTING_DATA.poItemsDetails;
                    const poItems = typeof poItemsRaw === 'string' ? JSON.parse(poItemsRaw || 'null') : (poItemsRaw ?? null);
                    
                    if (poItems[poId] && poItems[poId].length > 0) {
                        poItems[poId].forEach(item => {
                            const received = parseFloat(item.received_quantity || 0);
                            const remaining = parseFloat(item.quantity) - received;
                            const status = item.status || 'pending';
                            const unitCost = parseFloat(item.unit_cost);
                            const totalCost = parseFloat(item.total_cost);
                            const quantity = parseFloat(item.quantity);
                            
                            // Calculate total cost based on the condition
                            let totalCostToDisplay;
                            if (received === 0) {
                                totalCostToDisplay = totalCost;
                            } else {
                                totalCostToDisplay = received * unitCost;
                            }
                            
                            // Get supplier name from the PR data or from the item.
                            // Read from the page's island: this script is its own request, so
                            // $pr is not in scope and the name would come out empty.
                            const supplierName = PR_SPARE_VIEW_ROUTING_DATA.prSupplierName || 'N/A';
                            
                            const row = document.createElement('tr');
                            // FIXED: Added Supplier column after Category
                            row.innerHTML = `
                                <td>${item.part_name || 'N/A'} (${item.part_number || 'N/A'})</td>
                                <td>${item.category_name || 'N/A'}</td>
                                <td>${supplierName}</td>
                                <td>${parseFloat(item.quantity)}</td>
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
                        itemsContainer.innerHTML = `
                            <tr>
                                <td colspan="9" class="text-center py-3"> <!-- Updated colspan from 8 to 9 -->
                                    <i class="fas fa-exclamation-circle text-warning me-2"></i>
                                    No items found for this purchase order.
                                </td>
                            </tr>
                        `;
                    }
                    
                    viewModal.show();
                });
            });
            
            // Helper function to get badge class for PO status
            function getPOStatusBadgeClass(status) {
                switch(status) {
                    case 'draft': return 'bg-secondary';
                    case 'pending': return 'bg-warning';
                    case 'approved': return 'bg-success';
                    case 'confirmed': return 'bg-success';
                    case 'delivered': return 'bg-success';
                    case 'partially_received': return 'bg-warning';
                    case 'cancelled': return 'bg-danger';
                    case 'completed': return 'bg-success';
                    default: return 'bg-secondary';
                }
            }
        });

        // View Job Order Modal functionality
        document.addEventListener('DOMContentLoaded', function() {
            const viewJobOrderButtons = document.querySelectorAll('.view-job-order-btn');
            const viewJobOrderModal = new bootstrap.Modal(document.getElementById('viewJobOrderModal'));
            
            viewJobOrderButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const jobOrderId = this.getAttribute('data-job-order-id');
                    const jobOrderNumber = this.getAttribute('data-job-order-number');
                    const jobOrderDate = this.getAttribute('data-job-order-date');
                    const technician = this.getAttribute('data-technician');
                    const purpose = this.getAttribute('data-purpose');
                    const jobOrderStatus = this.getAttribute('data-job-order-status');
                    const jobOrderRemarks = this.getAttribute('data-job-order-remarks');
                    
                    // Set basic Job Order information
                    document.getElementById('modal-job-order-number').innerHTML = jobOrderNumber + ' <span class="badge ' + getJobOrderStatusBadgeClass(jobOrderStatus.toLowerCase()) + '">' + jobOrderStatus + '</span>';
                    document.getElementById('modal-job-order-date').textContent = jobOrderDate;

                    // Format technician name from the PHP data
                    document.getElementById('modal-technician').textContent = PR_SPARE_VIEW_ROUTING_DATA.technicianFormatted || 'N/A';

                    document.getElementById('modal-purpose').textContent = purpose;
                    document.getElementById('modal-job-order-remarks').textContent = jobOrderRemarks || '-';
                    
                    // Clear previous items
                    const itemsContainer = document.getElementById('modal-job-order-items');
                    itemsContainer.innerHTML = '';
                    
                    // The job order's lines, as the page rendered them into the island. This
                    // used to be json_encode($job_order_details['items']) evaluated here - but
                    // this script is its own request, so that variable is not in scope, the
                    // expression was always [], and the table stayed empty.
                    const jobOrderItems = ocpSpareItems(PR_SPARE_VIEW_ROUTING_DATA.jobOrderDetails);

                    // The request's vehicle or equipment, in the same form the page shows it.
                    const vehicleEquipmentDisplay = PR_SPARE_VIEW_ROUTING_DATA.prVehicleName || 'N/A';

                    if (jobOrderItems && jobOrderItems.length > 0) {
                        jobOrderItems.forEach(item => {
                            const status = item.status || 'pending';
                            const quantity = parseFloat(item.quantity);
                            // Get unit cost and calculate total cost
                            // You may need to adjust these based on your data structure
                            const unitCost = parseFloat(item.unit_cost) || 0;
                            const totalCost = quantity * unitCost;
                            
                            const row = document.createElement('tr');
                            // FIXED: Reordered columns - Vehicle/Equipment first, then Part Name, Category, Quantity, Unit Cost, Total Cost, Status
                            row.innerHTML = `
                                <td>${vehicleEquipmentDisplay}</td>
                                <td>${item.part_name || 'N/A'} (${item.part_number || 'N/A'})</td>
                                <td>${item.category_name || 'N/A'}</td>
                                <td>${quantity.toFixed(2)}</td>
                                <td>${formatCurrency(unitCost)}</td>
                                <td>${formatCurrency(totalCost)}</td>
                                <td>
                                    <span class="badge ${getJobOrderStatusBadge(status)}">
                                        ${status}
                                    </span>
                                </td>
                            `;
                            itemsContainer.appendChild(row);
                        });
                    } else {
                        itemsContainer.innerHTML = `
                            <tr>
                                <td colspan="7" class="text-center py-3">
                                    <i class="fas fa-exclamation-circle text-warning me-2"></i>
                                    No items found for this job order.
                                </td>
                            </tr>
                        `;
                    }
                    
                    viewJobOrderModal.show();
                });
            });
            
            function getJobOrderStatusBadge(status) {
                switch(status) {
                    case 'draft': return 'bg-primary';
                    case 'pending': return 'bg-warning';
                    case 'approved': return 'bg-success';
                    case 'released': return 'bg-success';
                    case 'confirmed': return 'bg-success';
                    case 'completed': return 'bg-success';
                    case 'cancelled': return 'bg-danger';
                    default: return 'bg-secondary';
                }
            }
            
            function getJobOrderStatusBadgeClass(status) {
                switch(status) {
                    case 'draft': return 'bg-primary';
                    case 'pending': return 'bg-warning';
                    case 'approved': return 'bg-success';
                    case 'released': return 'bg-success';
                    case 'confirmed': return 'bg-success';
                    case 'completed': return 'bg-success';
                    case 'cancelled': return 'bg-danger';
                    default: return 'bg-secondary';
                }
            }
        });

        // View Withdrawal Slip Modal functionality - UPDATED FOR EMPLOYEE AND TO HIDE VEHICLE/EQUIPMENT
        document.addEventListener('DOMContentLoaded', function() {
            const viewWithdrawalSlipButtons = document.querySelectorAll('.view-withdrawal-slip-btn');
            const viewWithdrawalSlipModal = new bootstrap.Modal(document.getElementById('viewWithdrawalSlipModal'));
            
            viewWithdrawalSlipButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const withdrawalSlipId = this.getAttribute('data-withdrawal-slip-id');
                    const withdrawalSlipNumber = this.getAttribute('data-withdrawal-slip-number');
                    const withdrawalSlipDate = this.getAttribute('data-withdrawal-slip-date');
                    const requestedBy = this.getAttribute('data-requested-by');
                    const employee = this.getAttribute('data-employee');
                    const purpose = this.getAttribute('data-purpose');
                    const withdrawalSlipStatus = this.getAttribute('data-withdrawal-slip-status');
                    const withdrawalSlipRemarks = this.getAttribute('data-withdrawal-slip-remarks');
                    
                    // FIX 1: Add status badge next to Withdrawal Slip Number
                    const statusBadgeClass = getWithdrawalSlipStatusBadgeClass(withdrawalSlipStatus.toLowerCase());
                    document.getElementById('modal-withdrawal-slip-number').innerHTML = withdrawalSlipNumber + ' <span class="badge ' + statusBadgeClass + '">' + withdrawalSlipStatus + '</span>';
                    
                    document.getElementById('modal-withdrawal-slip-date').textContent = withdrawalSlipDate;
                    document.getElementById('modal-requested-by').textContent = requestedBy;
                    document.getElementById('modal-employee').textContent = employee || 'N/A';
                    document.getElementById('modal-ws-purpose').textContent = purpose;
                    document.getElementById('modal-withdrawal-slip-remarks').textContent = withdrawalSlipRemarks || '-';
                    
                    // Clear previous items
                    const itemsContainer = document.getElementById('modal-withdrawal-slip-items');
                    itemsContainer.innerHTML = '';
                    
                    // The withdrawal slip's lines, as the page rendered them into the island.
                    // Read from the island for the same reason as the job order above: the
                    // page's $withdrawal_slip_details is not in scope in this request, so the
                    // direct expression was always [] and the table stayed empty.
                    const withdrawalSlipItems = ocpSpareItems(PR_SPARE_VIEW_ROUTING_DATA.withdrawalSlipDetails);
                    
                    if (withdrawalSlipItems && withdrawalSlipItems.length > 0) {
                        withdrawalSlipItems.forEach(item => {
                            const status = item.status || 'pending';
                            const quantity = parseFloat(item.quantity);
                            
                            // FIX: Get unit_cost and total_cost from the item data
                            // If not available in the item, calculate from available data
                            let unitCost = parseFloat(item.unit_cost) || 0;
                            let totalCost = parseFloat(item.total_cost) || (unitCost * quantity);
                            
                            // If totalCost is still 0, calculate from other sources if available
                            if (totalCost === 0 && unitCost > 0) {
                                totalCost = unitCost * quantity;
                            }
                            
                            const row = document.createElement('tr');
                            
                            // Determine column span and content based on request type
                            // Tested here in the browser: this script is its own request, so the
                            // page's variables are not in scope.
                            if (PR_SPARE_VIEW_ROUTING_DATA.isMaterialRequest) {
                            // For Issue Materials - no Vehicle/Equipment column
                            row.innerHTML = `
                                <td>${item.part_name || 'N/A'} (${item.part_number || 'N/A'})</td>
                                <td>${item.category_name || 'N/A'}</td>
                                <td>${quantity.toFixed(2)}</td>
                                <td>${formatCurrency(unitCost)}</td>
                                <td>${formatCurrency(totalCost)}</td>
                                <td>
                                    <span class="badge ${getWithdrawalSlipStatusBadge(status)}">
                                        ${status}
                                    </span>
                                </td>
                            `;
                            } else {
                            // For Issue Parts - include Vehicle/Equipment column
                            row.innerHTML = `
                                <td>${item.part_name || 'N/A'} (${item.part_number || 'N/A'})</td>
                                <td>${item.category_name || 'N/A'}</td>
                                <td>${quantity.toFixed(2)}</td>
                                <td>${formatCurrency(unitCost)}</td>
                                <td>${formatCurrency(totalCost)}</td>
                                <td>${item.vehicle_equipment_display || PR_SPARE_VIEW_ROUTING_DATA.prVehicleName || 'N/A'}</td>
                                <td>
                                    <span class="badge ${getWithdrawalSlipStatusBadge(status)}">
                                        ${status}
                                    </span>
                                </td>
                            `;
                            }
                            
                            itemsContainer.appendChild(row);
                        });
                    } else {
                        // Tested here in the browser: this script is its own request, so the
                        // page's variables are not in scope.
                        // Declared before the branch: a `let` inside an if/else body is scoped to
                        // that body, so the template literal below could not see it and threw
                        // "colspan is not defined" - which killed the click handler before the
                        // modal was ever shown.
                        let colspan;
                        if (PR_SPARE_VIEW_ROUTING_DATA.isMaterialRequest) {
                        // For Issue Materials: Part Name, Category, Quantity, Unit Cost, Total Cost, Status
                        colspan = 6;
                        } else {
                        // For Issue Parts: Part Name, Category, Quantity, Unit Cost, Total Cost, Vehicle/Equipment, Status
                        colspan = 7;
                        }

                        itemsContainer.innerHTML = `
                            <tr>
                                <td colspan="${colspan}" class="text-center py-3">
                                    <i class="fas fa-exclamation-circle text-warning me-2"></i>
                                    No items found for this withdrawal slip.
                                </td>
                            </tr>
                        `;
                    }
                    
                    viewWithdrawalSlipModal.show();
                });
            });
            
            function getWithdrawalSlipStatusBadge(status) {
                switch(status) {
                    case 'draft': return 'bg-info';
                    case 'pending': return 'bg-warning';
                    case 'approved': return 'bg-success';
                    case 'released': return 'bg-success';
                    case 'completed': return 'bg-success';
                    case 'cancelled': return 'bg-danger';
                    default: return 'bg-secondary';
                }
            }
            
            // Helper function for status badge class
            function getWithdrawalSlipStatusBadgeClass(status) {
                switch(status) {
                    case 'draft': return 'bg-info';
                    case 'pending': return 'bg-warning';
                    case 'approved': return 'bg-success';
                    case 'released': return 'bg-success';
                    case 'completed': return 'bg-success';
                    case 'cancelled': return 'bg-danger';
                    default: return 'bg-secondary';
                }
            }
        });

        // Make sure to add the formatCurrency function if it doesn't exist
        function formatCurrency(amount) {
            return '₱' + amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }

        // Edit PO Modal functionality
        document.addEventListener('DOMContentLoaded', function() {
            const editButtons = document.querySelectorAll('.edit-po-btn');
            const editModal = new bootstrap.Modal(document.getElementById('editPOModal'));
            const editForm = document.getElementById('editPOForm');
            
            editButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const poId = this.getAttribute('data-po-id');
                    const poNumber = this.getAttribute('data-po-number');
                    const poDate = this.getAttribute('data-po-date');
                    const expectedDelivery = this.getAttribute('data-expected-delivery');
                    const totalAmount = this.getAttribute('data-total-amount');
                    const poStatus = this.getAttribute('data-po-status');
                    const poRemarks = this.getAttribute('data-po-remarks');
                    
                    // Set basic PO information
                    document.getElementById('edit_po_id').value = poId;
                    document.getElementById('edit-modal-po-number').textContent = poNumber;
                    document.getElementById('edit-modal-po-date').textContent = poDate;
                    document.getElementById('edit-modal-expected-delivery').textContent = expectedDelivery;
                    document.getElementById('edit-modal-total-amount').textContent = '₱' + formatNumberWithCommas(parseFloat(totalAmount).toFixed(2));
                    document.getElementById('edit-modal-po-status').textContent = poStatus;
                    document.getElementById('edit-modal-po-remarks').textContent = poRemarks || '-';
                    
                    // Clear previous items
                    const itemsContainer = document.getElementById('edit-modal-po-items');
                    itemsContainer.innerHTML = '';
                    
                    // Get PO items from PHP data
                    // The island carries this as a JSON string (the page encodes it once); tolerate a
// raw object too, so this works however it arrives.
                    const poItemsRaw = PR_SPARE_VIEW_ROUTING_DATA.poItemsDetails;
                    const poItems = typeof poItemsRaw === 'string' ? JSON.parse(poItemsRaw || 'null') : (poItemsRaw ?? null);
                    
                    if (poItems[poId] && poItems[poId].length > 0) {
                        poItems[poId].forEach(item => {
                            const unitCost = parseFloat(item.unit_cost);
                            const currentTotal = parseFloat(item.total_cost);
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td>${item.part_number || 'N/A'}</td>
                                <td>${item.part_name || 'N/A'}</td>
                                <td>${item.category_name || 'N/A'}</td>
                                <td>${parseFloat(item.quantity)}</td>
                                <td>
                                    <input type="number" 
                                           name="new_quantity" 
                                           value="${parseFloat(item.quantity)}"
                                           min="1" 
                                           class="form-control form-control-sm"
                                           data-po-item-id="${item.id}"
                                           data-original-value="${parseFloat(item.quantity)}"
                                           required>
                                    <input type="hidden" name="po_item_id" value="${item.id}">
                                </td>
                                <td>${formatCurrency(unitCost)}</td>
                                <td>${formatCurrency(currentTotal)}</td>
                            `;
                            itemsContainer.appendChild(row);
                        });
                    } else {
                        itemsContainer.innerHTML = `
                            <tr>
                                <td colspan="7" class="text-center py-3">
                                    <i class="fas fa-exclamation-circle text-warning me-2"></i>
                                    No items found for this purchase order.
                                </td>
                            </tr>
                        `;
                    }
                    
                    editModal.show();
                });
            });
            
            // Handle edit form submission
            if (editForm) {
                editForm.addEventListener('submit', function(e) {
                    // Validate that at least one quantity is changed
                    let hasChanges = false;
                    const quantityInputs = document.querySelectorAll('#edit-modal-po-items input[type="number"]');
                    
                    quantityInputs.forEach(input => {
                        const originalValue = input.getAttribute('data-original-value');
                        if (input.value !== originalValue) {
                            hasChanges = true;
                        }
                    });
                    
                    if (!hasChanges) {
                        e.preventDefault();
                        Swal.fire({
                            title: 'No Changes',
                            text: 'No quantity changes detected. Please modify at least one quantity before submitting.',
                            icon: 'warning',
                            confirmButtonText: 'OK'
                        });
                    }
                });
            }
        });

        // Date handling for forms
        document.addEventListener('DOMContentLoaded', function() {
            // Set today's date in yyyy-mm-dd format (HTML5 date input format)
            const today = new Date();
            const todayFormatted = today.toISOString().split('T')[0];
            
            // Set expected delivery field (for PO) - default to today + 7 days
            const expectedDeliveryField = document.getElementById('expected_delivery');
            if (expectedDeliveryField) {
                const nextWeek = new Date();
                nextWeek.setDate(today.getDate() + 7);
                expectedDeliveryField.value = nextWeek.toISOString().split('T')[0];
            }
            
            // Set job order date field - default to today
            const jobOrderDateField = document.getElementById('job_order_date');
            if (jobOrderDateField) {
                jobOrderDateField.value = todayFormatted;
            }
            
            // Set withdrawal slip date field - default to today
            const withdrawalSlipDateField = document.getElementById('withdrawal_slip_date');
            if (withdrawalSlipDateField) {
                withdrawalSlipDateField.value = todayFormatted;
            }
            
            // Set minimum date for all date inputs to today
            const dateInputs = document.querySelectorAll('input[type="date"]');
            dateInputs.forEach(input => {
                input.min = todayFormatted;
            });
        });

        // Logout function
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
