/* spare_parts_suppliers.js
 * Extracted from spare_parts_suppliers.php - inline <script> block #1.
 * Server data arrives through window.OCP_PAGE_SPARE_PARTS_SUPPLIERS
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
const SPARE_PARTS_SUPPLIERS_DATA = window.OCP_PAGE_SPARE_PARTS_SUPPLIERS || {};
            
            // Initialize DataTables
            window.addEventListener('DOMContentLoaded', event => {
                const datatablesSimple = document.getElementById('datatablesSimple');
                if (datatablesSimple) {
                    new simpleDatatables.DataTable(datatablesSimple);
                }
                
                // Handle edit modal data
                const editModal = document.getElementById('editSupplierModal');
                if (editModal) {
                    editModal.addEventListener('show.bs.modal', function (event) {
                        const button = event.relatedTarget;
                        const id = button.getAttribute('data-id');
                        const supplier_name = button.getAttribute('data-supplier_name');
                        const contact_person = button.getAttribute('data-contact_person');
                        const email = button.getAttribute('data-email');
                        const phone = button.getAttribute('data-phone');
                        const address = button.getAttribute('data-address');
                        
                        document.getElementById('edit_id').value = id;
                        document.getElementById('edit_supplier_name').value = supplier_name;
                        document.getElementById('edit_contact_person').value = contact_person || '';
                        document.getElementById('edit_email').value = email || '';
                        document.getElementById('edit_phone').value = phone || '';
                        document.getElementById('edit_address').value = address || '';
                    });
                }
                
                // Handle view modal data
                const viewModal = document.getElementById('viewSupplierModal');
                if (viewModal) {
                    viewModal.addEventListener('show.bs.modal', function (event) {
                        const button = event.relatedTarget;
                        document.getElementById('view_supplier_name').textContent = button.getAttribute('data-supplier_name');
                        document.getElementById('view_contact_person').textContent = button.getAttribute('data-contact_person') || 'N/A';
                        document.getElementById('view_email').textContent = button.getAttribute('data-email') || 'N/A';
                        document.getElementById('view_phone').textContent = button.getAttribute('data-phone') || 'N/A';
                        document.getElementById('view_address').textContent = button.getAttribute('data-address') || 'N/A';
                        document.getElementById('view_created_at').textContent = button.getAttribute('data-created_at');
                        document.getElementById('view_updated_at').textContent = button.getAttribute('data-updated_at');
                    });
                }
                
                // Handle delete buttons
                const deleteButtons = document.querySelectorAll('.delete-btn');
                deleteButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const id = this.getAttribute('data-id');
                        const supplier_name = this.getAttribute('data-supplier_name');
                        
                        Swal.fire({
                            title: 'Are you sure?',
                            text: `You are about to delete the supplier "${supplier_name}". This action cannot be undone.`,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#d33',
                            cancelButtonColor: '#3085d6',
                            confirmButtonText: 'Yes, delete it!',
                            cancelButtonText: 'Cancel'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                document.getElementById('delete_id').value = id;
                                document.getElementById('deleteSupplierForm').submit();
                            }
                        });
                    });
                });
                
                // Show SweetAlert2 notifications based on PHP response
                // Tested in the browser: this script is its own request, so the page's
                // variables are not available to it.
                if (SPARE_PARTS_SUPPLIERS_DATA.hasMessage) {
                    Swal.fire({
                        title: SPARE_PARTS_SUPPLIERS_DATA.swalDataTitle,
                        text: SPARE_PARTS_SUPPLIERS_DATA.swalDataText,
                        icon: SPARE_PARTS_SUPPLIERS_DATA.swalDataIcon,
                        confirmButtonText: 'OK'
                    }).then((result) => {
                        // Tested here in the browser: this script is its own request, so the
                        // page's variables are not in scope.
                        if (SPARE_PARTS_SUPPLIERS_DATA.isSuccessOnPost) {
                            // If success and it was a form submission, don't reopen the modal
                        } else {
                            // If error, reopen the appropriate modal
                            // Tested here in the browser: this script is its own request, so the
                            // page's variables are not in scope.
                            if (SPARE_PARTS_SUPPLIERS_DATA.wasPost) {
                                // Tested here in the browser: this script is its own request, so the
                                // page's variables are not in scope.
                                if (SPARE_PARTS_SUPPLIERS_DATA.postedEditId !== '') {
                                    var editSupplierModal = new bootstrap.Modal(document.getElementById('editSupplierModal'));
                                    editSupplierModal.show();
                                } else if (SPARE_PARTS_SUPPLIERS_DATA.postedDeleteId === '') {
                                    var addSupplierModal = new bootstrap.Modal(document.getElementById('addSupplierModal'));
                                    addSupplierModal.show();
                                }
                            }
                        }
                    });
                }
                
                // Form validation for add supplier
                const addSupplierForm = document.getElementById('addSupplierForm');
                if (addSupplierForm) {
                    addSupplierForm.addEventListener('submit', function(e) {
                        const supplierName = document.getElementById('supplier_name').value.trim();
                        if (!supplierName) {
                            e.preventDefault();
                            Swal.fire({
                                title: 'Validation Error!',
                                text: 'Supplier name is required.',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    });
                }
                
                // Form validation for edit supplier
                const editSupplierForm = document.getElementById('editSupplierForm');
                if (editSupplierForm) {
                    editSupplierForm.addEventListener('submit', function(e) {
                        const supplierName = document.getElementById('edit_supplier_name').value.trim();
                        if (!supplierName) {
                            e.preventDefault();
                            Swal.fire({
                                title: 'Validation Error!',
                                text: 'Supplier name is required.',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    });
                }
            });
