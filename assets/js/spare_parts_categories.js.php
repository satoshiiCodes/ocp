/* spare_parts_categories.js
 * Extracted from spare_parts_categories.php - inline <script> block #1.
 * Server data arrives through window.OCP_PAGE_SPARE_PARTS_CATEGORIES
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
            const SPARE_PARTS_CATEGORIES_DATA = window.OCP_PAGE_SPARE_PARTS_CATEGORIES || {};
            
            // Initialize DataTables
            window.addEventListener('DOMContentLoaded', event => {
                const datatablesSimple = document.getElementById('datatablesSimple');
                if (datatablesSimple) {
                    new simpleDatatables.DataTable(datatablesSimple);
                }
                
                // Handle edit modal data
                const editModal = document.getElementById('editCategoryModal');
                if (editModal) {
                    editModal.addEventListener('show.bs.modal', function (event) {
                        const button = event.relatedTarget;
                        const id = button.getAttribute('data-id');
                        const name = button.getAttribute('data-name');
                        const description = button.getAttribute('data-description');
                        
                        document.getElementById('edit_id').value = id;
                        document.getElementById('edit_category_name').value = name;
                        document.getElementById('edit_description').value = description;
                    });
                }
                
                // Handle delete button clicks with SweetAlert2
                const deleteButtons = document.querySelectorAll('.delete-btn');
                deleteButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const id = this.getAttribute('data-id');
                        const name = this.getAttribute('data-name');
                        
                        Swal.fire({
                            title: 'Are you sure?',
                            text: `You are about to delete the category "${name}". This action cannot be undone.`,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#d33',
                            cancelButtonColor: '#3085d6',
                            confirmButtonText: 'Yes, delete it!',
                            cancelButtonText: 'Cancel'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                document.getElementById('delete_id').value = id;
                                document.getElementById('deleteForm').submit();
                            }
                        });
                    });
                });
                
                // Show SweetAlert2 notifications if any.
                // Tested here in the browser: this script is its own request, so it cannot
                // read $_SESSION. The page puts the answer in the island.
                if (SPARE_PARTS_CATEGORIES_DATA.hasMessage) {
                    Swal.fire({
                        title: SPARE_PARTS_CATEGORIES_DATA.sESSIONSweetalertTitle,
                        text: SPARE_PARTS_CATEGORIES_DATA.sESSIONSweetalertText,
                        icon: SPARE_PARTS_CATEGORIES_DATA.sESSIONSweetalertIcon,
                        confirmButtonText: 'OK'
                    });
                }
                
                // Form validation with SweetAlert2
                const addForm = document.getElementById('addCategoryForm');
                const editForm = document.getElementById('editCategoryForm');
                
                if (addForm) {
                    addForm.addEventListener('submit', function(e) {
                        const categoryName = document.getElementById('category_name').value.trim();
                        
                        if (!categoryName) {
                            e.preventDefault();
                            Swal.fire({
                                title: 'Validation Error!',
                                text: 'Category name is required.',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    });
                }
                
                if (editForm) {
                    editForm.addEventListener('submit', function(e) {
                        const categoryName = document.getElementById('edit_category_name').value.trim();
                        
                        if (!categoryName) {
                            e.preventDefault();
                            Swal.fire({
                                title: 'Validation Error!',
                                text: 'Category name is required.',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        }
                    });
                }
            });
