/* vehicles.js
 * Extracted from vehicles.php - inline <script> block #1.
 * Server data arrives through window.OCP_PAGE_VEHICLES
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
            const VEHICLES_DATA = window.OCP_PAGE_VEHICLES || {};
            
            // Initialize DataTables
            window.addEventListener('DOMContentLoaded', event => {
                const datatablesSimple = document.getElementById('datatablesSimple');
                if (datatablesSimple) {
                    new simpleDatatables.DataTable(datatablesSimple);
                }
                
                // Show SweetAlert2 alerts from the page's data island.
                // Read here in the browser: this script is its own request, so it cannot
                // see $_SESSION. The page copies the alert into the island.
                if (VEHICLES_DATA.hasMessage) {
                    Swal.fire({
                        icon: VEHICLES_DATA.sESSIONAlertType,
                        title: VEHICLES_DATA.sESSIONAlertTitle,
                        text: VEHICLES_DATA.sESSIONAlertText,
                        confirmButtonColor: '#3085d6',
                        confirmButtonText: 'OK'
                    });
                }
                
                // Show modal if there was an error with form submission
                if (VEHICLES_DATA.showModal === 'add') {
                    var addVehicleModal = new bootstrap.Modal(document.getElementById('addVehicleModal'));
                    addVehicleModal.show();
                } else if (VEHICLES_DATA.showModal === 'edit') {
                    var editVehicleModal = new bootstrap.Modal(document.getElementById('editVehicleModal'));
                    editVehicleModal.show();
                }
                
                // Handle edit vehicle button clicks
                const editButtons = document.querySelectorAll('.edit-vehicle-btn');
                editButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const vehicleId = this.getAttribute('data-id');
                        const vehicleName = this.getAttribute('data-name');
                        const plateNumber = this.getAttribute('data-plate');
                        const fuelType = this.getAttribute('data-fuel');
                        const description = this.getAttribute('data-description');
                        const isActive = this.getAttribute('data-active');
                        
                        // Populate the edit form
                        document.getElementById('edit_vehicle_id').value = vehicleId;
                        document.getElementById('edit_vehicle_name').value = vehicleName;
                        document.getElementById('edit_plate_number').value = plateNumber;
                        document.getElementById('edit_fuel_type').value = fuelType;
                        document.getElementById('edit_description').value = description;
                        document.getElementById('edit_is_active').checked = (isActive === '1');
                        
                        // Show the modal
                        const editModal = new bootstrap.Modal(document.getElementById('editVehicleModal'));
                        editModal.show();
                    });
                });
                
                // Handle delete vehicle button clicks with SweetAlert2
                const deleteButtons = document.querySelectorAll('.delete-vehicle-btn');
                
                deleteButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const vehicleId = this.getAttribute('data-id');
                        const vehicleName = this.getAttribute('data-name');
                        const plateNumber = this.getAttribute('data-plate');
                        
                        Swal.fire({
                            title: 'Are you sure?',
                            text: `You are about to delete the vehicle: ${vehicleName} (${plateNumber}). This action cannot be undone.`,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#d33',
                            cancelButtonColor: '#3085d6',
                            confirmButtonText: 'Yes, delete it!',
                            cancelButtonText: 'Cancel'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.href = `?delete_id=${vehicleId}`;
                            }
                        });
                    });
                });
            });
