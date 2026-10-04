/* projects.js
 * Extracted from projects.php - inline <script> block #1.
 * Server data arrives through window.OCP_PAGE_PROJECTS
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
            // the engineer options, the messages - has to be read from the island the
            // page rendered, on the client, not echoed by PHP here.
            const PROJECTS_DATA = window.OCP_PAGE_PROJECTS || {};

            
            // Initialize DataTables
            window.addEventListener('DOMContentLoaded', event => {
                const datatablesSimple = document.getElementById('datatablesSimple');
                if (datatablesSimple) {
                    new simpleDatatables.DataTable(datatablesSimple);
                }
                
                // Initialize tooltips
                const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
                const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
                
                // Engineer management
                const engineersContainer = document.getElementById('engineers-container');
                const addEngineerButton = document.getElementById('add-engineer');
                let engineerCount = PROJECTS_DATA.engineerCount;
                
                // Add engineer field
                addEngineerButton.addEventListener('click', function() {
                    if (engineerCount < 3) {
                        engineerCount++;
                        const newField = document.createElement('div');
                        newField.className = 'input-group mb-2 engineer-field';
                        newField.id = 'engineer-field-' + engineerCount;
                        
                        // Create the form-floating div with select element
                        let fieldHtml = `
                            <div class="form-floating flex-grow-1">
                                <select class="form-select" name="engineers[]" id="engineer-select-${engineerCount}" required>
                                    <option value="">Select an Engineer</option>
                        `;
                        
                        // The options are rendered by the page and handed over in the
                        // data island. They are read from there in the browser, not
                        // echoed by PHP: this script is a separate request, so the
                        // page's $engineers is not in scope and echoing it here would
                        // always come out empty.
                        fieldHtml += PROJECTS_DATA.engineerOptionsHtml || '';
                        
                        fieldHtml += `</select>
                                <label for="engineer-select-${engineerCount}">Engineer ${engineerCount + 1}</label>
                            </div>
                            <button type="button" class="btn btn-danger remove-engineer" data-field-id="engineer-field-${engineerCount}">
                                <i class="fas fa-times"></i>
                            </button>
                        `;
                        
                        newField.innerHTML = fieldHtml;
                        engineersContainer.appendChild(newField);
                        
                        // Add event listener to the remove button
                        newField.querySelector('.remove-engineer').addEventListener('click', function() {
                            const fieldId = this.getAttribute('data-field-id');
                            document.getElementById(fieldId).remove();
                            engineerCount--;
                            
                            // Renumber remaining fields
                            const fields = engineersContainer.querySelectorAll('.engineer-field');
                            fields.forEach((field, index) => {
                                const newId = index;
                                field.id = 'engineer-field-' + newId;
                                
                                // Update the select and label IDs
                                const select = field.querySelector('select');
                                const label = field.querySelector('label');
                                const newSelectId = 'engineer-select-' + newId;
                                
                                select.id = newSelectId;
                                label.htmlFor = newSelectId;
                                label.textContent = 'Engineer ' + (newId + 1);
                                
                                if (newId > 0) {
                                    const removeBtn = field.querySelector('.remove-engineer');
                                    if (removeBtn) {
                                        removeBtn.setAttribute('data-field-id', 'engineer-field-' + newId);
                                    }
                                }
                            });
                        });
                        
                        // Hide add button if we've reached the maximum
                        if (engineerCount >= 3) {
                            addEngineerButton.style.display = 'none';
                        }
                    }
                });
                
                // Remove engineer field
                document.querySelectorAll('.remove-engineer').forEach(button => {
                    button.addEventListener('click', function() {
                        const fieldId = this.getAttribute('data-field-id');
                        document.getElementById(fieldId).remove();
                        engineerCount--;
                        
                        // Renumber remaining fields
                        const fields = engineersContainer.querySelectorAll('.engineer-field');
                        fields.forEach((field, index) => {
                            const newId = index;
                            field.id = 'engineer-field-' + newId;
                            
                            // Update the select and label IDs
                            const select = field.querySelector('select');
                            const label = field.querySelector('label');
                            const newSelectId = 'engineer-select-' + newId;
                            
                            select.id = newSelectId;
                            label.htmlFor = newSelectId;
                            label.textContent = 'Engineer ' + (newId + 1);
                            
                            if (newId > 0) {
                                const removeBtn = field.querySelector('.remove-engineer');
                                if (removeBtn) {
                                    removeBtn.setAttribute('data-field-id', 'engineer-field-' + newId);
                                }
                            }
                        });
                        
                        // Show add button
                        addEngineerButton.style.display = 'block';
                    });
                });
                
                // Format threshold amount input
                const thresholdAmountInput = document.getElementById('threshold_amount');
                if (thresholdAmountInput) {
                    thresholdAmountInput.addEventListener('input', function(e) {
                        let value = e.target.value.replace(/[^\d.]/g, '');
                        value = value.replace(/\.(?=.*\.)/g, '');
                        
                        if (value) {
                            // Format with commas
                            const parts = value.split('.');
                            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                            e.target.value = parts.join('.');
                        }
                    });
                    
                    thresholdAmountInput.addEventListener('blur', function(e) {
                        if (e.target.value) {
                            const num = parseFloat(e.target.value.replace(/,/g, ''));
                            if (!isNaN(num)) {
                                e.target.value = num.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
                            }
                        }
                    });
                }
                
                // Show success message using SweetAlert2.
                // The test is made here in the browser, not by PHP: this script is its
                // own request, so $success_message is not in scope and a PHP test would
                // be false on every load - which is why the alert never appeared.
                if (PROJECTS_DATA.successMessage) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: PROJECTS_DATA.successMessage,
                        showConfirmButton: true,
                        timer: 3000,
                        timerProgressBar: true,
                        toast: false,
                        position: 'center'
                    });
                }
                
                // Show error message using SweetAlert2 and reopen modal if there was an error
                if (PROJECTS_DATA.errorMessage) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: PROJECTS_DATA.errorMessage,
                        showConfirmButton: true,
                        confirmButtonColor: '#d33'
                    }).then((result) => {
                        // Reopen modal after error alert
                        const addProjectModal = new bootstrap.Modal(document.getElementById('addProjectModal'));
                        addProjectModal.show();
                    });
                }
                
                // Form submission with SweetAlert2 confirmation
                const projectForm = document.getElementById('projectForm');
                if (projectForm) {
                    projectForm.addEventListener('submit', function(e) {
                        // Validate required fields
                        const projectName = document.getElementById('project_name').value.trim();
                        const projectCode = document.getElementById('project_code').value.trim();
                        const engineers = document.querySelectorAll('select[name="engineers[]"]');
                        let hasEngineer = false;
                        
                        engineers.forEach(select => {
                            if (select.value !== '') {
                                hasEngineer = true;
                            }
                        });
                        
                        if (!projectName || !projectCode || !hasEngineer) {
                            e.preventDefault();
                            Swal.fire({
                                icon: 'warning',
                                title: 'Validation Error',
                                text: 'Please fill in all required fields.',
                                showConfirmButton: true,
                                confirmButtonColor: '#3085d6'
                            });
                        }
                    });
                }
                
                // Reopen the add form when a submission came back with an error, so the
                // typed values are still visible. The island carries the error in both
                // contexts, so this works whether the script is printed by the page or
                // requested on its own.
                if (PROJECTS_DATA.errorMessage) {
                    const addProjectModal = new bootstrap.Modal(document.getElementById('addProjectModal'));
                    addProjectModal.show();
                }
            });
