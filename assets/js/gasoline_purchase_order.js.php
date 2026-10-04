/* gasoline_purchase_order.js
 * Extracted from gasoline_purchase_order.php - inline <script> block #1.
 * Server data arrives through window.OCP_PAGE_GASOLINE_PURCHASE_ORDER
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
const GASOLINE_PURCHASE_ORDER_DATA = window.OCP_PAGE_GASOLINE_PURCHASE_ORDER || {};
            // Initialize DataTables
            window.addEventListener('DOMContentLoaded', event => {
                const poTable = document.getElementById('poTable');
                if (poTable) {
                    new simpleDatatables.DataTable(poTable, {
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
                
                const pendingItemsTable = document.getElementById('pendingItemsTable');
                if (pendingItemsTable) {
                    new simpleDatatables.DataTable(pendingItemsTable, {
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
                
                // Initialize Bootstrap tooltips
                const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
                const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
                
                // Show SweetAlert2 notifications
                // Tested in the browser: this script is its own request, so the page's
                // variables are not available to it.
                if (GASOLINE_PURCHASE_ORDER_DATA.hasMessage) {
                    Swal.fire({
                        title: GASOLINE_PURCHASE_ORDER_DATA.swalData,
                        text: GASOLINE_PURCHASE_ORDER_DATA.swalData2,
                        icon: GASOLINE_PURCHASE_ORDER_DATA.swalData3,
                        confirmButtonText: 'OK'
                    });
                }
                
                // Auto-open edit modal if editing
                // Tested in the browser: this script is its own request, so the page's
                // variables are not available to it.
                //
                // The jQuery modal bridge lives in assets/js/ui.js, which is loaded from the
                // shared top bar - before jQuery itself exists. Asking for it here is what makes
                // this reliable: the ready handler below runs at once (the document is already
                // interactive), which can be sooner than ui.js's own retry.
                if (GASOLINE_PURCHASE_ORDER_DATA.openEditPo) {
                    if (window.ocpAttachJQueryBridge) {
                        window.ocpAttachJQueryBridge();
                    }
                    $(document).ready(function() {
                        if (window.ocpAttachJQueryBridge) {
                            window.ocpAttachJQueryBridge();
                        }
                        $('#createPOModal').modal('show');
                    });
                }
                
                // Calculate item total and update total amount
                function calculateItemTotal() {
                    let grandTotal = 0;
                    
                    // Calculate each item total
                    $('.item-row').each(function() {
                        const quantity = parseFloat($(this).find('.item-quantity').val()) || 0;
                        const price = parseFloat($(this).find('.item-price').val()) || 0;
                        const itemTotal = quantity * price;
                        
                        $(this).find('.item-total').val(itemTotal.toFixed(2));
                        grandTotal += itemTotal;
                    });
                    
                    // Update grand total
                    $('#total_amount').val(grandTotal.toFixed(2));
                }
                
                // Function to handle driver/operator selection change
                function handleDriverSelectChange(selectElement) {
                    const itemRow = $(selectElement).closest('.item-row');
                    const index = $(selectElement).data('index');
                    const manualDriverRow = itemRow.find('#manual-driver-row-' + index);
                    const selectedValue = $(selectElement).val();
                    
                    if (selectedValue === 'other') {
                        // Show manual driver input
                        manualDriverRow.show();
                    } else {
                        // Hide manual driver input and clear the value
                        manualDriverRow.hide();
                        manualDriverRow.find('.item-manual-driver').val('');
                    }
                }
                
                // Fix: Ensure selectedValue is defined
                $(document).on('change', '.item-driver-select', function() {
                    handleDriverSelectChange(this);
                });
                
                // Add item row - FIXED VERSION with full width manual driver field
                $('#addItemBtn').click(function() {
                    const itemCount = $('.item-row').length;
                    const newIndex = itemCount;
                    // Create a new item row using a template with proper PHP values
                    const itemTemplate = `
                    <!-- Same wrapper, grids and spacing as the PO Items rows rendered by the page,
                         so a row added here is indistinguishable from the ones already there. The
                         Bootstrap column classes were inert once the built sheet moved to
                         Tailwind, which is why an added row used to come out unformatted. -->
                    <div class="item-row border border-slate-200 rounded-lg p-4 mb-4 bg-slate-50">
                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                            <div class="min-w-0">
                                <div class="form-floating mb-4">
                                    <select class="form-select item-gasoline-type" name="item_gasoline_type[]" required>
                                        <option value="">Select Gasoline Type</option>
                                        ${GASOLINE_PURCHASE_ORDER_DATA.gasolineTypeOptionsHtml || ''}
                                    </select>
                                    <label>Gasoline Type <span class="text-danger">*</span></label>
                                </div>
                            </div>
                            <div class="min-w-0">
                                <div class="form-floating mb-4">
                                    <select class="form-select item-supplier" name="item_supplier_id[]" required>
                                        <option value="">Select Supplier</option>
                                        ${GASOLINE_PURCHASE_ORDER_DATA.supplierOptionsHtml || ''}
                                    </select>
                                    <label>Supplier <span class="text-danger">*</span></label>
                                </div>
                            </div>
                            <div class="min-w-0">
                                <div class="form-floating mb-4">
                                    <select class="form-select item-vehicle" name="item_vehicle_id[]">
                                        <option value="">Select Vehicle (Optional)</option>
                                        ${GASOLINE_PURCHASE_ORDER_DATA.vehicleOptionsHtml || ''}
                                    </select>
                                    <label>Vehicle</label>
                                </div>
                            </div>
                            <div class="min-w-0">
                                <div class="form-floating mb-4">
                                    <select class="form-select item-equipment" name="item_equipment_id[]">
                                        <option value="">Select Equipment (Optional)</option>
                                        ${GASOLINE_PURCHASE_ORDER_DATA.equipmentOptionsHtml || ''}
                                    </select>
                                    <label>Equipment</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                            <div class="min-w-0">
                                <div class="form-floating mb-4">
                                    <select class="form-select item-driver-select" name="item_driver_operator_id[]" data-index="${newIndex}">
                                        <option value="">Select Driver/Operator</option>
                                        ${GASOLINE_PURCHASE_ORDER_DATA.employeeOptionsHtml || ''}
                                        <option value="other">Other (Manual Entry)</option>
                                    </select>
                                    <label>Driver/Operator <span class="text-danger">*</span></label>
                                </div>
                            </div>
                            <div class="min-w-0">
                                <div class="form-floating mb-4">
                                    <input type="text" class="form-control item-purpose" name="item_purpose[]" required>
                                    <label>Purpose <span class="text-danger">*</span></label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 gap-6" id="manual-driver-row-${newIndex}" style="display: none;">
                            <div class="min-w-0">
                                <div class="form-floating mb-4">
                                    <input type="text" class="form-control item-manual-driver" name="item_manual_driver_name[]" 
                                           placeholder="Enter driver/operator name">
                                    <label>Manual Driver/Operator Name</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                            <div class="min-w-0">
                                <div class="form-floating mb-4">
                                    <input type="number" class="form-control item-quantity" name="item_quantity_liters[]" 
                                           step="0.01" min="0.01" placeholder="Quantity" required>
                                    <label>Quantity (Liters) <span class="text-danger">*</span></label>
                                </div>
                            </div>
                            <div class="min-w-0">
                                <div class="form-floating mb-4">
                                    <input type="number" class="form-control item-price" name="item_price_per_liter[]" 
                                           step="0.01" min="0" placeholder="Price">
                                    <label>Price per Liter (₱) <span class="text-muted">Optional</span></label>
                                </div>
                            </div>
                            <div class="min-w-0">
                                <div class="form-floating mb-4">
                                    <input type="number" class="form-control item-odometer" name="item_odometer_reading[]">
                                    <label>Odometer Reading (Optional)</label>
                                </div>
                            </div>
                            <div class="min-w-0">
                                <div class="form-floating mb-4">
                                    <input type="date" class="form-control item-date" name="item_date_issued[]" 
                                           value="${GASOLINE_PURCHASE_ORDER_DATA.today ?? ''}" required>
                                    <label>Date Issued <span class="text-danger">*</span></label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 gap-6">
                            <div class="min-w-0">
                                <div class="form-floating mb-4">
                                    <input type="text" class="form-control item-total font-bold text-success-600!" readonly placeholder="Total">
                                    <label>Total Amount (₱)</label>
                                </div>
                            </div>
                        </div>
                    </div>`;
                    
                    $('#poItems').append(itemTemplate);
                    
                    // Disable equipment when vehicle is selected and vice versa
                    const newItem = $('.item-row').last();
                    newItem.find('.item-vehicle').off('change').on('change', function() {
                        if ($(this).val()) {
                            newItem.find('.item-equipment').prop('disabled', true).val('');
                        } else {
                            newItem.find('.item-equipment').prop('disabled', false);
                        }
                    });
                    
                    newItem.find('.item-equipment').off('change').on('change', function() {
                        if ($(this).val()) {
                            newItem.find('.item-vehicle').prop('disabled', true).val('');
                        } else {
                            newItem.find('.item-vehicle').prop('disabled', false);
                        }
                    });
                    
                    calculateItemTotal();
                });
                
                // Remove last item row
                $('#removeItemBtn').click(function() {
                    if ($('.item-row').length > 1) {
                        $('.item-row').last().remove();
                        // Renumber remaining items if needed
                        $('.item-row').each(function(index) {
                            $(this).find('.item-driver-select').attr('data-index', index);
                            const manualRow = $(this).find('[id^="manual-driver-row-"]');
                            if (manualRow.length) {
                                manualRow.attr('id', 'manual-driver-row-' + index);
                            }
                        });
                        calculateItemTotal();
                    }
                });
                
                // Calculate totals when quantity or price changes
                $(document).on('input', '.item-quantity, .item-price', function() {
                    calculateItemTotal();
                });
                
                // Disable equipment when vehicle is selected and vice versa in existing items
                $('.item-vehicle').each(function() {
                    $(this).off('change').on('change', function() {
                        if ($(this).val()) {
                            $(this).closest('.item-row').find('.item-equipment').prop('disabled', true).val('');
                        } else {
                            $(this).closest('.item-row').find('.item-equipment').prop('disabled', false);
                        }
                    });
                });
                
                $('.item-equipment').each(function() {
                    $(this).off('change').on('change', function() {
                        if ($(this).val()) {
                            $(this).closest('.item-row').find('.item-vehicle').prop('disabled', true).val('');
                        } else {
                            $(this).closest('.item-row').find('.item-vehicle').prop('disabled', false);
                        }
                    });
                });
                
                // Trigger driver select change for existing rows that have manual names
                $('.item-row').each(function() {
                    const driverSelect = $(this).find('.item-driver-select');
                    const manualDriverRow = $(this).find('[id^="manual-driver-row-"]');
                    if (driverSelect.val() === 'other') {
                        manualDriverRow.show();
                    } else {
                        manualDriverRow.hide();
                    }
                });
                
                // View PO details
                $(document).on('click', '.view-po-btn', function() {
                    const poId = $(this).data('po-id');
                    
                    // Show loading indicator
                    $('#poDetailsContent').html(`
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p>Loading PO details...</p>
                        </div>
                    `);
                    
                    // Show modal first
                    $('#viewPOModal').modal('show');
                    
                    // Load content via AJAX
                    $.ajax({
                        url: 'api/gasoline_purchase_order-endpoint.php',
                        method: 'POST',
                        data: { po_id: poId },
                        dataType: 'html',
                        success: function(response) {
                            $('#poDetailsContent').html(response);
                        },
                        error: function(xhr, status, error) {
                            $('#poDetailsContent').html(`
                                <div class="alert alert-danger">
                                    <h5>Error Loading PO Details</h5>
                                    <p>Failed to load purchase order details. Please try again.</p>
                                    <p>Error: ${error}</p>
                                </div>
                            `);
                        }
                    });
                });
                
                // Complete PO button - setup signature pad when modal opens
                let completionSignaturePad = null;
                let completionCanvas = null;
                
                $(document).on('click', '.complete-po-btn', function() {
                    const poId = $(this).data('po-id');
                    const poNumber = $(this).data('po-number');
                    
                    $('#complete_po_id').val(poId);
                    $('#complete_po_number_display').text(poNumber);
                    
                    // Setup signature pad when modal is shown
                    $('#completePOModal').one('shown.bs.modal', function() {
                        completionCanvas = document.getElementById('completionSignatureCanvas');
                        if (completionCanvas) {
                            // Set canvas dimensions
                            completionCanvas.width = completionCanvas.offsetWidth;
                            completionCanvas.height = 200;
                            
                            // Initialize signature pad
                            completionSignaturePad = new SignaturePad(completionCanvas, {
                                backgroundColor: 'rgba(0, 0, 0, 0)',
                                penColor: 'rgb(0, 0, 0)',
                                minWidth: 1,
                                maxWidth: 2
                            });
                            
                            // Clear any existing signature
                            completionSignaturePad.clear();
                        }
                    });
                    
                    $('#completePOModal').modal('show');
                });
                
                // Clear completion signature button
                $('#clearCompletionSignatureBtn').click(function() {
                    if (completionSignaturePad) {
                        completionSignaturePad.clear();
                    }
                });
                
                // Handle complete form submission
                $('#submitCompleteBtn').click(function(e) {
                    e.preventDefault();
                    
                    if (!completionSignaturePad) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Signature pad not initialized. Please try again.'
                        });
                        return;
                    }
                    
                    if (completionSignaturePad.isEmpty()) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Signature Required',
                            text: 'Please provide your signature before completing this purchase order.'
                        });
                        return;
                    }
                    
                    // Get signature as base64
                    const signatureData = completionSignaturePad.toDataURL();
                    $('#completion_signature_data').val(signatureData);
                    
                    Swal.fire({
                        title: 'Confirm Completion',
                        text: 'Are you sure you want to mark this purchase order as completed?',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#28a745',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Yes, complete it!',
                        cancelButtonText: 'Cancel'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $('#completePOForm').off('submit').submit();
                        }
                    });
                });
                
                // Update Invoice button
                $(document).on('click', '.update-invoice-btn', function() {
                    const poId = $(this).data('po-id');
                    const poNumber = $(this).data('po-number');
                    const currentInvoice = $(this).data('invoice') || '';
                    
                    $('#update_invoice_po_id').val(poId);
                    $('#update_invoice_po_number_display').text(poNumber);
                    $('#update_invoice_number').val(currentInvoice);
                    $('#updateInvoiceModal').modal('show');
                });
                
                // Signature Pad Setup for Approval
                let signaturePad = null;
                let canvas = null;
                
                // Approve PO button - setup signature pad when modal opens
                $(document).on('click', '.approve-po-btn', function() {
                    const poId = $(this).data('po-id');
                    const poNumber = $(this).data('po-number');
                    
                    $('#approve_po_id').val(poId);
                    $('#approve_po_number_display').text(poNumber);
                    
                    // Setup signature pad when modal is shown
                    $('#approvePOModal').one('shown.bs.modal', function() {
                        canvas = document.getElementById('signatureCanvas');
                        if (canvas) {
                            // Set canvas dimensions
                            canvas.width = canvas.offsetWidth;
                            canvas.height = 200;
                            
                            // Initialize signature pad
                            signaturePad = new SignaturePad(canvas, {
                                backgroundColor: 'rgba(0, 0, 0, 0)',
                                penColor: 'rgb(0, 0, 0)',
                                minWidth: 1,
                                maxWidth: 2
                            });
                            
                            // Clear any existing signature
                            signaturePad.clear();
                        }
                    });
                    
                    $('#approvePOModal').modal('show');
                });
                
                // Clear signature button
                $('#clearSignatureBtn').click(function() {
                    if (signaturePad) {
                        signaturePad.clear();
                    }
                });
                
                // Handle approve form submission
                $('#submitApproveBtn').click(function(e) {
                    e.preventDefault();
                    
                    if (!signaturePad) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Signature pad not initialized. Please try again.'
                        });
                        return;
                    }
                    
                    if (signaturePad.isEmpty()) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Signature Required',
                            text: 'Please provide your signature before approving this purchase order.'
                        });
                        return;
                    }
                    
                    // Get signature as base64
                    const signatureData = signaturePad.toDataURL();
                    $('#signature_data').val(signatureData);
                    
                    Swal.fire({
                        title: 'Confirm Approval',
                        text: 'Are you sure you want to approve this purchase order with your signature?',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#17a2b8',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Yes, approve it!',
                        cancelButtonText: 'Cancel'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $('#approvePOForm').off('submit').submit();
                        }
                    });
                });
                
                // Delete PO button
                $(document).on('click', '.delete-po-btn', function() {
                    const poId = $(this).data('po-id');
                    const poNumber = $(this).data('po-number');
                    
                    $('#delete_po_id').val(poId);
                    $('#delete_po_number').val(poNumber);
                    $('#delete_po_number_display').text(poNumber);
                    $('#deletePOModal').modal('show');
                });
                
                // Set up print button for PO details
                $('#printPODetailsBtn').click(function() {
                    const poId = $('#poDetailsContent').data('po-id');
                    if (poId) {
                        window.open('reports/po_print.php?id=' + poId, '_blank');
                    }
                });
                
                // Deliver PO button
                $(document).on('click', '.deliver-po-btn', function() {
                    const poId = $(this).data('po-id');
                    $('#delivery_po_id').val(poId);
                    $('#deliveryModal').modal('show');
                });
                
                // Issue gasoline button
                $(document).on('click', '.issue-gasoline-btn', function() {
                    const itemId = $(this).data('item-id');
                    const gasolineType = $(this).data('gasoline-type');
                    const quantity = $(this).data('quantity');
                    
                    $('#issue_po_item_id').val(itemId);
                    $('#itemDetails').html(`
                        <div>Gasoline Type: ${gasolineType}</div>
                        <div>Quantity: ${quantity} Liters</div>
                    `);
                    
                    $('#issueGasolineModal').modal('show');
                });
                
                // Validate create/update PO form
                $('#createPOForm').submit(function(e) {
                    // Check if user is trying to change status to 'approved' without permission
                    const selectedStatus = $('#status').val();
                    const canApprove = GASOLINE_PURCHASE_ORDER_DATA.canApprovePo;
                    
                    if (!canApprove && selectedStatus === 'approved') {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'error',
                            title: 'Access Denied',
                            text: 'Only Admin with CEO position can approve purchase orders.'
                        });
                        return false;
                    }
                    
                    // Check if at least one item is added
                    const itemCount = $('.item-row').filter(function() {
                        return $(this).find('.item-gasoline-type').val() !== '';
                    }).length;
                    
                    if (itemCount === 0) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Error',
                            text: 'Please add at least one item to the purchase order.'
                        });
                        return false;
                    }
                    
                    // Validate all items have required fields
                    let valid = true;
                    let errorMessage = '';
                    let hasSupplier = false;
                    
                    $('.item-row').each(function(index) {
                        const type = $(this).find('.item-gasoline-type').val();
                        const supplier = $(this).find('.item-supplier').val();
                        const driverSelect = $(this).find('.item-driver-select').val();
                        const manualDriverName = $(this).find('.item-manual-driver').val();
                        const purpose = $(this).find('.item-purpose').val();
                        const quantity = $(this).find('.item-quantity').val();
                        const dateIssued = $(this).find('.item-date').val();
                        
                        if (type || supplier || driverSelect || purpose || quantity || dateIssued) {
                            if (!type) {
                                valid = false;
                                errorMessage = 'Item ' + (index + 1) + ': Gasoline Type is required';
                                return false;
                            }
                            if (!supplier) {
                                valid = false;
                                errorMessage = 'Item ' + (index + 1) + ': Supplier is required';
                                return false;
                            } else {
                                hasSupplier = true;
                            }
                            // Check driver/operator validation
                            if (!driverSelect) {
                                valid = false;
                                errorMessage = 'Item ' + (index + 1) + ': Driver/Operator selection is required';
                                return false;
                            }
                            if (driverSelect === 'other' && (!manualDriverName || manualDriverName.trim() === '')) {
                                valid = false;
                                errorMessage = 'Item ' + (index + 1) + ': Manual driver/operator name is required when "Other" is selected';
                                return false;
                            }
                            if (!purpose) {
                                valid = false;
                                errorMessage = 'Item ' + (index + 1) + ': Purpose is required';
                                return false;
                            }
                            if (!quantity || parseFloat(quantity) <= 0) {
                                valid = false;
                                errorMessage = 'Item ' + (index + 1) + ': Valid Quantity is required';
                                return false;
                            }
                            if (!dateIssued) {
                                valid = false;
                                errorMessage = 'Item ' + (index + 1) + ': Date Issued is required';
                                return false;
                            }
                        }
                    });
                    
                    // Check if at least one item has a supplier selected
                    if (!hasSupplier) {
                        valid = false;
                        errorMessage = 'At least one item must have a supplier selected.';
                    }
                    
                    if (!valid) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Error',
                            text: errorMessage
                        });
                        return false;
                    }
                });
                
                // Validate update invoice form
                $('#updateInvoiceForm').submit(function(e) {
                    e.preventDefault();
                    
                    Swal.fire({
                        title: 'Confirm Update',
                        text: 'Are you sure you want to update the invoice number?',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#28a745',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Yes, update it!',
                        cancelButtonText: 'Cancel'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $('#updateInvoiceForm').off('submit').submit();
                        }
                    });
                });
                
                // Validate delete PO form
                $('#deletePOForm').submit(function(e) {
                    e.preventDefault();
                    
                    Swal.fire({
                        title: 'Confirm Deletion',
                        text: 'Are you sure you want to delete this purchase order? This action cannot be undone!',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#dc3545',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Yes, delete it!',
                        cancelButtonText: 'Cancel'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $('#deletePOForm').off('submit').submit();
                        }
                    });
                });
                
                // Validate issue gasoline form
                $('#issueGasolineForm').submit(function(e) {
                    const tankId = $('#issue_tank_id').val();
                    
                    if (!tankId) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Error',
                            text: 'Please select a source tank.'
                        });
                        return false;
                    }
                });
                
                // Calculate initial total
                calculateItemTotal();
                
                // Handle modal close for Create/Edit PO modal
                $('#createPOModal').on('hidden.bs.modal', function () {
                    // Tested in the browser: this script is its own request, so the page's
                    // variables are not available to it.
                    if (GASOLINE_PURCHASE_ORDER_DATA.openEditPo) {
                        window.location.href = 'gasoline_purchase_order.php';
                    }
                });
                
                // Handle modal close for other modals
                $('#cancelPOModal, #approvePOModal, #viewPOModal, #issueGasolineModal, #deletePOModal, #completePOModal, #updateInvoiceModal').on('hidden.bs.modal', function () {
                    $(this).find('form')[0].reset();
                    if (signaturePad) {
                        signaturePad.clear();
                    }
                    if (completionSignaturePad) {
                        completionSignaturePad.clear();
                    }
                });
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
            
