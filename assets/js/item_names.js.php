/* item_names.js
 * Extracted from item_names.php - inline <script> block #1.
 * Server data arrives through window.OCP_PAGE_ITEM_NAMES
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
            // The page's data island. This script is fetched as its own request, so the
            // page's PHP variables are NOT in scope here: anything the page prepared -
            // the messages, the flags - has to be read from the island the page
            // rendered, in the browser, not tested or echoed by PHP here.
            const ITEM_NAMES_DATA = window.OCP_PAGE_ITEM_NAMES || {};

            // Initialize DataTables
            window.addEventListener('DOMContentLoaded', event => {
                const datatablesSimple = document.getElementById('datatablesSimple');
                if (datatablesSimple) {
                    new simpleDatatables.DataTable(datatablesSimple);
                }
                
                // Show modal if there was an error with form submission.
                // Tested here in the browser: this script is its own request, so
                // $_SERVER and $_SESSION are not available to it.
                if (ITEM_NAMES_DATA.reopenAddModal) {
                    var addItemModal = new bootstrap.Modal(document.getElementById('addItemModal'));
                    addItemModal.show();
                }
                
                // Show view modal if item details were requested
                if (ITEM_NAMES_DATA.openViewModal) {
                    var viewItemModal = new bootstrap.Modal(document.getElementById('viewItemModal'));
                    viewItemModal.show();
                }
                
                // Edit modal handler
                const editItemModal = document.getElementById('editItemModal');
                if (editItemModal) {
                    editItemModal.addEventListener('show.bs.modal', function (event) {
                        const button = event.relatedTarget;
                        const id = button.getAttribute('data-id');
                        const code = button.getAttribute('data-code');
                        const name = button.getAttribute('data-name');
                        const category = button.getAttribute('data-category');
                        const unit = button.getAttribute('data-unit');
                        const minStock = button.getAttribute('data-min-stock');
                        
                        document.getElementById('edit_item_id').value = id;
                        document.getElementById('edit_item_code').value = code;
                        document.getElementById('edit_item_name').value = name;
                        document.getElementById('edit_category_id').value = category;
                        document.getElementById('edit_unit_of_measure').value = unit;
                        document.getElementById('edit_min_stock_level').value = minStock;
                    });
                }
                
                // Delete item handler
                const deleteButtons = document.querySelectorAll('.delete-item-btn');
                deleteButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const id = this.getAttribute('data-id');
                        const name = this.getAttribute('data-name');
                        
                        Swal.fire({
                            title: 'Are you sure?',
                            text: `You are about to delete the item: ${name}`,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#d33',
                            cancelButtonColor: '#3085d6',
                            confirmButtonText: 'Yes, delete it!'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Send AJAX request to delete the item
                                const formData = new FormData();
                                formData.append('action', 'delete');
                                formData.append('id', id);
                                
                                fetch('actions/item_names-actions.php', {
                                    method: 'POST',
                                    body: formData
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        Swal.fire(
                                            'Deleted!',
                                            data.message,
                                            'success'
                                        ).then(() => {
                                            location.reload();
                                        });
                                    } else {
                                        Swal.fire(
                                            'Error!',
                                            data.message,
                                            'error'
                                        );
                                    }
                                })
                                .catch(error => {
                                    Swal.fire(
                                        'Error!',
                                        'An error occurred while deleting the item.',
                                        'error'
                                    );
                                });
                            }
                        });
                    });
                });
                
                // Show SweetAlert2 messages from session
                if (ITEM_NAMES_DATA.hasMessage) {
                    Swal.fire({
                        icon: ITEM_NAMES_DATA.swalMessageType,
                        title: ITEM_NAMES_DATA.swalMessageType2,
                        text: ITEM_NAMES_DATA.swalMessage,
                        timer: 3000,
                        showConfirmButton: false
                    });
                }
                
                // AJAX form submission for edit form
                const editItemForm = document.getElementById('editItemForm');
                if (editItemForm) {
                    editItemForm.addEventListener('submit', function(e) {
                        e.preventDefault();
                        
                        const formData = new FormData(this);
                        
                        fetch(this.action, {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Success!',
                                    text: data.message,
                                    timer: 2000,
                                    showConfirmButton: false
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error!',
                                    text: data.message
                                });
                            }
                        })
                        .catch(error => {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: 'An error occurred while updating the item.'
                            });
                        });
                    });
                }
            });
