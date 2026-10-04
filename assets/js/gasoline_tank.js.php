/* gasoline_tank.js
 * Extracted from gasoline_tank.php - inline <script> block #1.
 * Server data arrives through window.OCP_PAGE_GASOLINE_TANK
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
const GASOLINE_TANK_DATA = window.OCP_PAGE_GASOLINE_TANK || {};
            
            // Initialize DataTables
            window.addEventListener('DOMContentLoaded', event => {
                const datatablesSimple = document.getElementById('datatablesSimple');
                if (datatablesSimple) {
                    new simpleDatatables.DataTable(datatablesSimple);
                }
                
                // SweetAlert2 configuration
                const Swal = window.Swal;
                
                // Show SweetAlert2 notification if there's data to show
                // Tested in the browser: this script is its own request, so the page's
                // variables are not available to it.
                if (GASOLINE_TANK_DATA.hasMessage) {
                    Swal.fire({
                        icon: GASOLINE_TANK_DATA.swalDataIcon,
                        title: GASOLINE_TANK_DATA.swalDataTitle,
                        text: GASOLINE_TANK_DATA.swalDataText,
                        confirmButtonColor: '#3085d6',
                        confirmButtonText: 'OK'
                    }).then((result) => {
                        // Auto-open the appropriate modal after alert if there was an error.
                        // Decided here in the browser: this script is its own request, so
                        // the page's $swal_data, $form_type and $form_data are not in scope.
                        if (GASOLINE_TANK_DATA.isError) {
                            if (GASOLINE_TANK_DATA.formIsAdd) {
                                var addTankModal = new bootstrap.Modal(document.getElementById('addTankModal'));
                                addTankModal.show();
                            } else if (GASOLINE_TANK_DATA.formIsEdit) {
                                var editTankModal = new bootstrap.Modal(document.getElementById('editTankModal' + GASOLINE_TANK_DATA.formDataTankId));
                                editTankModal.show();
                            }
                        }
                    });
                }
                
                // Auto-open modals based on form type
                if (GASOLINE_TANK_DATA.formIsAdd) {
                    var addTankModal = new bootstrap.Modal(document.getElementById('addTankModal'));
                    addTankModal.show();
                } else if (GASOLINE_TANK_DATA.formIsEdit) {
                    var editTankModal = new bootstrap.Modal(document.getElementById('editTankModal' + GASOLINE_TANK_DATA.formDataTankId));
                    editTankModal.show();
                }
                
                // Delete tank confirmation with SweetAlert2
                document.querySelectorAll('.delete-tank-btn').forEach(button => {
                    button.addEventListener('click', function() {
                        const tankId = this.getAttribute('data-tank-id');
                        const tankName = this.getAttribute('data-tank-name');
                        const location = this.getAttribute('data-location');
                        
                        Swal.fire({
                            title: 'Are you sure?',
                            text: `You are about to delete the tank "${tankName}" located at "${location}". This action cannot be undone.`,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#d33',
                            cancelButtonColor: '#3085d6',
                            confirmButtonText: 'Yes, delete it!',
                            cancelButtonText: 'Cancel'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Create a form and submit it
                                const form = document.getElementById('deleteForm' + tankId);
                                if (form) {
                                    // Add the delete_tank parameter
                                    const deleteInput = document.createElement('input');
                                    deleteInput.type = 'hidden';
                                    deleteInput.name = 'delete_tank';
                                    deleteInput.value = '1';
                                    form.appendChild(deleteInput);
                                    
                                    form.submit();
                                }
                            }
                        });
                    });
                });
                
                // Form validation with SweetAlert2
                document.getElementById('addTankForm')?.addEventListener('submit', function(e) {
                    const tankName = document.getElementById('tank_name').value.trim();
                    const location = document.getElementById('location').value.trim();
                    const capacity = document.getElementById('capacity_liters').value.trim();
                    
                    if (!tankName || !location || !capacity) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Error',
                            text: 'Please fill in all required fields.',
                            confirmButtonColor: '#3085d6'
                        });
                        return false;
                    }
                    
                    if (isNaN(capacity) || parseFloat(capacity) <= 0) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Error',
                            text: 'Capacity must be a positive number.',
                            confirmButtonColor: '#3085d6'
                        });
                        return false;
                    }
                });
                
                // Edit form validation
                <?php foreach ($tanks as $tank): ?>
                document.getElementById('editTankForm<?php echo ocp_js_string($tank['id']); ?>')?.addEventListener('submit', function(e) {
                    const tankName = document.getElementById('edit_tank_name_<?php echo ocp_js_string($tank['id']); ?>').value.trim();
                    const location = document.getElementById('edit_location_<?php echo ocp_js_string($tank['id']); ?>').value.trim();
                    const capacity = document.getElementById('edit_capacity_liters_<?php echo ocp_js_string($tank['id']); ?>').value.trim();
                    
                    if (!tankName || !location || !capacity) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Error',
                            text: 'Please fill in all required fields.',
                            confirmButtonColor: '#3085d6'
                        });
                        return false;
                    }
                    
                    if (isNaN(capacity) || parseFloat(capacity) <= 0) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'error',
                            title: 'Validation Error',
                            text: 'Capacity must be a positive number.',
                            confirmButtonColor: '#3085d6'
                        });
                        return false;
                    }
                });
                <?php endforeach; ?>
            });
