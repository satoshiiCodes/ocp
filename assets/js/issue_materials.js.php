/* issue_materials.js
 * Extracted from issue_materials.php - inline <script> block #1.
 * Server data arrives through window.OCP_PAGE_ISSUE_MATERIALS
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
const ISSUE_MATERIALS_DATA = window.OCP_PAGE_ISSUE_MATERIALS || {};
            // Initialize DataTables
            window.addEventListener('DOMContentLoaded', event => {
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
                
                const issuedTable = document.getElementById('issuedTable');
                if (issuedTable) {
                    new simpleDatatables.DataTable(issuedTable, {
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
                // Tested in the browser: this script is its own request, so the page's
                // variables are not available to it.
                if (ISSUE_MATERIALS_DATA.hasMessage) {
                    Swal.fire({
                        title: ISSUE_MATERIALS_DATA.swalDataTitle,
                        html: ISSUE_MATERIALS_DATA.swalData,
                        icon: ISSUE_MATERIALS_DATA.swalDataIcon,
                        confirmButtonText: 'OK'
                    }).then((result) => {
                        // Tested here in the browser: this script is its own request, so the
                        // page's variables are not in scope.
                        if (ISSUE_MATERIALS_DATA.isSuccess) {
                            // Clear form on success
                            document.getElementById('issueForm').reset();
                            resetPartsContainer();
                        }
                    });
                }
                
                // Show modal if there was an error with form submission
                // Tested in the browser: this script is its own request, so the page's
                // variables are not available to it.
                if (ISSUE_MATERIALS_DATA.isError) {
                    var issueModal = new bootstrap.Modal(document.getElementById('issueModal'));
                    issueModal.show();
                }
                
                // Initialize
                updateTotalEstimate();
            });
            
            let partCounter = 1;
            
            // Function to add new part row
            function addPartRow() {
                const container = document.getElementById('partsContainer');
                const newIndex = container.children.length;
                
                const partItem = document.createElement('div');
                partItem.className = 'part-item';
                partItem.setAttribute('data-part-index', newIndex);
                
                partItem.innerHTML = `
                    <div class="part-item-header">
                        <span class="part-item-number">Item #${newIndex + 1}</span>
                        <button type="button" class="btn btn-sm btn-outline-danger remove-part-btn" onclick="removePartRow(this)">
                            <i class="fas fa-times"></i> Remove
                        </button>
                    </div>
                    <div class="row">
                        <div class="col-md-7 mb-3">
                            <div class="form-floating">
                                <select class="form-select part-select" name="part_id[]" required onchange="updatePartStock(this)">
                                    <option value="">Select Material</option>
                                    ${ISSUE_MATERIALS_DATA.materialOptionsHtml || ''}
                                </select>
                                <label>Material <span class="text-danger">*</span></label>
                                <div class="form-text available-stock" id="stockText_${newIndex}">Available: 0 | Next FIFO Price: ₱0.00</div>
                            </div>
                        </div>
                        <div class="col-md-5 mb-3">
                            <div class="form-floating">
                                <input type="number" class="form-control quantity-input" name="quantity[]" step="1" min="1" value="" required oninput="validatePartQuantity(this); updateTotalEstimate();">
                                <label>Quantity <span class="text-danger">*</span></label>
                                <div class="form-text quantity-error text-danger" style="display: none;">Quantity exceeds available stock!</div>
                                <div class="form-text estimated-cost text-success" style="display: none;">Estimate: ₱0.00</div>
                            </div>
                        </div>
                    </div>
                `;
                
                container.appendChild(partItem);
                
                // Update remove buttons - enable if more than one part
                updateRemoveButtons();
                partCounter++;
            }
            
            // Function to remove part row
            function removePartRow(button) {
                const partItem = button.closest('.part-item');
                const container = document.getElementById('partsContainer');
                
                if (container.children.length > 1) {
                    partItem.remove();
                    // Re-index all items
                    reindexPartItems();
                    updateTotalEstimate();
                    updateRemoveButtons();
                }
            }
            
            // Function to re-index part items
            function reindexPartItems() {
                const container = document.getElementById('partsContainer');
                const items = container.querySelectorAll('.part-item');
                
                items.forEach((item, index) => {
                    item.setAttribute('data-part-index', index);
                    const numberSpan = item.querySelector('.part-item-number');
                    if (numberSpan) {
                        numberSpan.textContent = `Item #${index + 1}`;
                    }
                });
            }
            
            // Function to update remove buttons state
            function updateRemoveButtons() {
                const container = document.getElementById('partsContainer');
                const removeButtons = container.querySelectorAll('.remove-part-btn');
                
                // Disable remove button if only one part remains
                removeButtons.forEach(button => {
                    if (container.children.length <= 1) {
                        button.disabled = true;
                        button.classList.add('disabled');
                    } else {
                        button.disabled = false;
                        button.classList.remove('disabled');
                    }
                });
            }
            
            // Function to update part stock information
            function updatePartStock(selectElement) {
                const partItem = selectElement.closest('.part-item');
                const index = partItem.getAttribute('data-part-index');
                const selectedOption = selectElement.options[selectElement.selectedIndex];
                
                const availableStock = selectedOption ? selectedOption.getAttribute('data-quantity') || 0 : 0;
                const nextFifoPrice = selectedOption ? parseFloat(selectedOption.getAttribute('data-next-fifo-price')) || 0 : 0;
                
                const stockText = document.getElementById(`stockText_${index}`);
                if (stockText) {
                    stockText.textContent = `Available: ${availableStock} | Next FIFO Price: ₱${nextFifoPrice.toFixed(2)}`;
                }
                
                // Validate quantity for this part
                validatePartQuantity(partItem.querySelector('.quantity-input'));
                updateTotalEstimate();
            }
            
            // Function to validate quantity for a specific part
            function validatePartQuantity(inputElement) {
                const partItem = inputElement.closest('.part-item');
                const selectElement = partItem.querySelector('.part-select');
                const selectedOption = selectElement.options[selectElement.selectedIndex];
                const availableStock = selectedOption ? parseInt(selectedOption.getAttribute('data-quantity')) || 0 : 0;
                
                const quantity = inputElement.value ? parseInt(inputElement.value) : 0;
                const errorElement = partItem.querySelector('.quantity-error');
                const estimateElement = partItem.querySelector('.estimated-cost');
                
                if (quantity > 0 && selectedOption) {
                    const nextFifoPrice = parseFloat(selectedOption.getAttribute('data-next-fifo-price')) || 0;
                    const estimatedCost = quantity * nextFifoPrice;
                    
                    estimateElement.textContent = `Estimate: ₱${estimatedCost.toFixed(2)}`;
                    estimateElement.style.display = 'block';
                } else {
                    estimateElement.style.display = 'none';
                }
                
                if (quantity > availableStock) {
                    errorElement.style.display = 'block';
                } else {
                    errorElement.style.display = 'none';
                }
                
                return quantity <= availableStock;
            }
            
            // Function to update total estimate
            function updateTotalEstimate() {
                const partItems = document.querySelectorAll('.part-item');
                let totalItems = 0;
                let grandTotal = 0;
                
                partItems.forEach(item => {
                    const selectElement = item.querySelector('.part-select');
                    const quantityInput = item.querySelector('.quantity-input');
                    const selectedOption = selectElement.options[selectElement.selectedIndex];
                    
                    if (selectedOption && quantityInput.value) {
                        const quantity = parseInt(quantityInput.value) || 0;
                        const nextFifoPrice = parseFloat(selectedOption.getAttribute('data-next-fifo-price')) || 0;
                        
                        if (quantity > 0) {
                            totalItems += quantity;
                            grandTotal += quantity * nextFifoPrice;
                        }
                    }
                });
                
                document.getElementById('totalItemsCount').textContent = totalItems;
                document.getElementById('grandTotalEstimate').textContent = `₱${grandTotal.toFixed(2)}`;
            }
            
            // Function to validate all parts before submission
            function validateAllParts() {
                const partItems = document.querySelectorAll('.part-item');
                let isValid = true;
                let errorMessages = [];
                
                partItems.forEach((item, index) => {
                    const selectElement = item.querySelector('.part-select');
                    const quantityInput = item.querySelector('.quantity-input');
                    
                    if (!selectElement.value) {
                        isValid = false;
                        errorMessages.push(`Item #${index + 1}: Please select a material`);
                    }
                    
                    if (!quantityInput.value || parseInt(quantityInput.value) <= 0) {
                        isValid = false;
                        errorMessages.push(`Item #${index + 1}: Please enter a valid quantity`);
                    }
                    
                    if (!validatePartQuantity(quantityInput)) {
                        isValid = false;
                        const selectedOption = selectElement.options[selectElement.selectedIndex];
                        const partName = selectedOption ? selectedOption.textContent.split(' - ')[1] || selectedOption.textContent : 'Selected Material';
                        errorMessages.push(`Item #${index + 1}: Quantity exceeds available stock for ${partName}`);
                    }
                });
                
                return { isValid, errorMessages };
            }
            
            // Function to reset parts container to initial state
            function resetPartsContainer() {
                const container = document.getElementById('partsContainer');
                container.innerHTML = `
                    <div class="part-item" data-part-index="0">
                        <div class="part-item-header">
                            <span class="part-item-number">Item #1</span>
                            <button type="button" class="btn btn-sm btn-outline-danger remove-part-btn" onclick="removePartRow(this)" disabled>
                                <i class="fas fa-times"></i> Remove
                            </button>
                        </div>
                        <div class="row">
                            <div class="col-md-7 mb-3">
                                <div class="form-floating">
                                    <select class="form-select part-select" name="part_id[]" required onchange="updatePartStock(this)">
                                        <option value="">Select Material</option>
                                        ${ISSUE_MATERIALS_DATA.materialOptionsHtml || ''}
                                    </select>
                                    <label>Material <span class="text-danger">*</span></label>
                                    <div class="form-text available-stock" id="stockText_0">Available: 0 | Next FIFO Price: ₱0.00</div>
                                </div>
                            </div>
                            <div class="col-md-5 mb-3">
                                <div class="form-floating">
                                    <input type="number" class="form-control quantity-input" name="quantity[]" step="1" min="1" value="" required oninput="validatePartQuantity(this); updateTotalEstimate();">
                                    <label>Quantity <span class="text-danger">*</span></label>
                                    <div class="form-text quantity-error text-danger" style="display: none;">Quantity exceeds available stock!</div>
                                    <div class="form-text estimated-cost text-success" style="display: none;">Estimate: ₱0.00</div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                partCounter = 1;
                updateTotalEstimate();
            }
            
            // Function to scroll to inventory section
            function scrollToInventory() {
                const element = document.getElementById('inventorySection');
                element.scrollIntoView({ behavior: 'smooth' });
            }
            
            // Form validation before submission
            document.getElementById('issueForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Validate required fields
                const employeeId = document.getElementById('employee_id').value;
                const purpose = document.getElementById('purpose').value;
                
                if (!employeeId || !purpose) {
                    Swal.fire({
                        title: 'Validation Error!',
                        text: 'Please fill in all required fields (Employee and Purpose).',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                    return false;
                }
                
                // Validate parts
                const validation = validateAllParts();
                
                if (!validation.isValid) {
                    Swal.fire({
                        title: 'Validation Error!',
                        html: validation.errorMessages.join('<br>'),
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                    return false;
                }
                
                // If all validations pass, submit the form
                this.submit();
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
            
