/* gasoline_suppliers.js
 * Extracted from gasoline_suppliers.php - inline <script> block #1.
 * Server data arrives through window.OCP_PAGE_GASOLINE_SUPPLIERS
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
const GASOLINE_SUPPLIERS_DATA = window.OCP_PAGE_GASOLINE_SUPPLIERS || {};
            
            // Initialize DataTables
            window.addEventListener('DOMContentLoaded', event => {
                const datatablesSimple = document.getElementById('datatablesSimple');
                if (datatablesSimple) {
                    new simpleDatatables.DataTable(datatablesSimple);
                }
                
                // Show SweetAlert2 messages if any
                // Tested in the browser: this script is its own request, so the page's
                // variables are not available to it.
                if (GASOLINE_SUPPLIERS_DATA.hasMessage) {
                    Swal.fire({
                        title: GASOLINE_SUPPLIERS_DATA.swalTitle,
                        text: GASOLINE_SUPPLIERS_DATA.swalMessage,
                        icon: GASOLINE_SUPPLIERS_DATA.swalType,
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#3085d6'
                    });
                }
                
                // Show add modal if there was an error with form submission.
                // Tested in the browser: this script is its own request, so $_SERVER and
                // $_POST are not available to it; the page puts the answer in the island.
                if (GASOLINE_SUPPLIERS_DATA.reopenAddModal) {
                    var addSupplierModal = new bootstrap.Modal(document.getElementById('addSupplierModal'));
                    addSupplierModal.show();
                }
                
                // Initialize delete functionality after page load
                initializeDeleteButtons();
            });
            
            // Clear form fields when modal is hidden
            document.getElementById('addSupplierModal').addEventListener('hidden.bs.modal', function () {
                document.getElementById('addSupplierForm').reset();
            });
            
            // Function to initialize delete buttons
            function initializeDeleteButtons() {
                document.querySelectorAll('.delete-supplier-btn').forEach(button => {
                    button.addEventListener('click', function() {
                        const supplierId = this.getAttribute('data-supplier-id');
                        const supplierName = this.getAttribute('data-supplier-name');
                        
                        showDeleteConfirmation(supplierId, supplierName);
                    });
                });
            }
            
            
            // Function to show delete confirmation with SweetAlert2
            function showDeleteConfirmation(supplierId, supplierName) {
                Swal.fire({
                            title: 'Are you sure?',
                            text: `You are about to delete the supplier "${supplierName}". This action cannot be undone!`,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#d33',
                            cancelButtonColor: '#3085d6',
                            confirmButtonText: 'Yes, delete it!',
                            cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        deleteSupplier(supplierId);
                    }
                });
            }
            
            // Function to delete supplier
            function deleteSupplier(supplierId) {
                // Set the supplier ID and submit the form
                document.getElementById('delete_supplier_id').value = supplierId;
                document.getElementById('deleteForm').submit();
            }
            
            // Form submission handling with SweetAlert2 confirmation for add/update
            document.getElementById('addSupplierForm').addEventListener('submit', function(e) {
                const supplierName = document.getElementById('supplier_name').value.trim();
                
                if (!supplierName) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Validation Error!',
                        text: 'Supplier name is required.',
                        icon: 'error',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#3085d6'
                    });
                    return;
                }
                
                // You can add additional confirmation for add if needed
                // For now, just allow the form to submit normally
            });
            
            // Add similar handling for edit forms if needed
            document.querySelectorAll('form[id^="editForm"]').forEach(form => {
                form.addEventListener('submit', function(e) {
                    const supplierName = this.querySelector('input[name="supplier_name"]').value.trim();
                    
                    if (!supplierName) {
                        e.preventDefault();
                        Swal.fire({
                            title: 'Validation Error!',
                            text: 'Supplier name is required.',
                            icon: 'error',
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#3085d6'
                        });
                        return;
                    }
                    
                    // Optional: Add confirmation dialog for update
                    e.preventDefault();
                    Swal.fire({
                        title: 'Update Supplier?',
                        text: 'Are you sure you want to update this supplier?',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Yes, update it!',
                        cancelButtonText: 'Cancel',
                        customClass: {
                            confirmButton: 'btn btn-primary me-2',
                            cancelButton: 'btn btn-secondary'
                        },
                        buttonsStyling: false
                    }).then((result) => {
                        if (result.isConfirmed) {
                            this.submit();
                        }
                    });
                });
            });
