/* suppliers.js
 * Extracted from suppliers.php - inline <script> block #1.
 * Server data arrives through window.OCP_PAGE_SUPPLIERS
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
            const SUPPLIERS_DATA = window.OCP_PAGE_SUPPLIERS || {};
            
            // Initialize DataTables
            window.addEventListener('DOMContentLoaded', event => {
                const datatablesSimple = document.getElementById('datatablesSimple');
                if (datatablesSimple) {
                    new simpleDatatables.DataTable(datatablesSimple);
                }
                
                // Show SweetAlert if there's a message, and reopen the form it belongs to.
                // All of these tests are made here in the browser: this script is its own
                // request, so the page's $swal_data and $_POST are not available to it.
                if (SUPPLIERS_DATA.hasMessage) {
                    Swal.fire({
                        title: SUPPLIERS_DATA.swalDataTitle,
                        text: SUPPLIERS_DATA.swalDataText,
                        icon: SUPPLIERS_DATA.swalDataIcon,
                        confirmButtonText: 'OK'
                    });

                    // On a rejected submission, put the user back in the form they used.
                    if (SUPPLIERS_DATA.isError) {
                        if (SUPPLIERS_DATA.pOSTEditId) {
                            // Re-fill the edit form with what was submitted
                            document.getElementById('edit_id').value = SUPPLIERS_DATA.pOSTEditId;
                            document.getElementById('edit_supplier_name').value = SUPPLIERS_DATA.editSupplierName || '';
                            document.getElementById('edit_contact_person').value = SUPPLIERS_DATA.editContactPerson || '';
                            document.getElementById('edit_phone').value = SUPPLIERS_DATA.editPhone || '';
                            document.getElementById('edit_email').value = SUPPLIERS_DATA.editEmail || '';
                            document.getElementById('edit_address').value = SUPPLIERS_DATA.editAddress || '';

                            var editSupplierModal = new bootstrap.Modal(document.getElementById('editSupplierModal'));
                            editSupplierModal.show();
                        } else {
                            var addSupplierModal = new bootstrap.Modal(document.getElementById('addSupplierModal'));
                            addSupplierModal.show();
                        }
                    }
                }
                
                // View supplier modal
                const viewButtons = document.querySelectorAll('.view-supplier');
                viewButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        document.getElementById('view-name').textContent = this.getAttribute('data-name');
                        document.getElementById('view-contact').textContent = this.getAttribute('data-contact') || 'N/A';
                        document.getElementById('view-phone').textContent = this.getAttribute('data-phone') || 'N/A';
                        document.getElementById('view-email').textContent = this.getAttribute('data-email') || 'N/A';
                        document.getElementById('view-address').textContent = this.getAttribute('data-address') || 'N/A';
                    });
                });
                
                // Edit supplier modal
                const editButtons = document.querySelectorAll('.edit-supplier');
                editButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        document.getElementById('edit_id').value = this.getAttribute('data-id');
                        document.getElementById('edit_supplier_name').value = this.getAttribute('data-name');
                        document.getElementById('edit_contact_person').value = this.getAttribute('data-contact') || '';
                        document.getElementById('edit_phone').value = this.getAttribute('data-phone') || '';
                        document.getElementById('edit_email').value = this.getAttribute('data-email') || '';
                        document.getElementById('edit_address').value = this.getAttribute('data-address') || '';
                    });
                });
                
                // Delete supplier modal
                const deleteButtons = document.querySelectorAll('.delete-supplier');
                deleteButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        document.getElementById('delete_id').value = this.getAttribute('data-id');
                        document.getElementById('delete-name').textContent = this.getAttribute('data-name');
                    });
                });
            });
