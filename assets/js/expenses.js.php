/* expenses.js
 * Extracted from expenses.php - inline <script> block #1.
 * Server data arrives through window.OCP_PAGE_EXPENSES
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
const EXPENSES_DATA = window.OCP_PAGE_EXPENSES || {};
            // Function to toggle fields based on expense type for Add Modal
            function toggleAddFields() {
                const select = document.getElementById('add_expense_type_id');
                const selectedOption = select.options[select.selectedIndex];
                const expenseTypeName = selectedOption ? selectedOption.getAttribute('data-name') : '';
                
                const personSelectionContainer = document.getElementById('add_person_selection_container');
                
                // Show person selection container for all expense types (except when empty)
                if (select.value) {
                    personSelectionContainer.style.display = 'block';
                } else {
                    personSelectionContainer.style.display = 'none';
                    // Hide all person fields
                    document.getElementById('add_employee_field').style.display = 'none';
                    document.getElementById('add_ceo_field').style.display = 'none';
                    document.getElementById('add_person_name_field').style.display = 'none';
                }
                
                // Reset radio buttons
                document.getElementById('add_person_selection_none').checked = true;
                toggleAddPersonFields();
            }

            // Function to toggle person fields based on selection method for Add Modal
            function toggleAddPersonFields() {
                const selectedMethod = document.querySelector('input[name="person_selection_type"]:checked')?.value || 'none';
                
                const employeeField = document.getElementById('add_employee_field');
                const ceoField = document.getElementById('add_ceo_field');
                const personNameField = document.getElementById('add_person_name_field');
                
                // Hide all fields first
                employeeField.style.display = 'none';
                ceoField.style.display = 'none';
                personNameField.style.display = 'none';
                
                // Remove required attributes
                document.getElementById('add_employee_id').removeAttribute('required');
                document.getElementById('add_ceo_id').removeAttribute('required');
                document.getElementById('add_person_name_input').removeAttribute('required');
                
                // Show appropriate field based on selection
                if (selectedMethod === 'employee') {
                    employeeField.style.display = 'block';
                    document.getElementById('add_employee_id').setAttribute('required', 'required');
                } else if (selectedMethod === 'ceo') {
                    ceoField.style.display = 'block';
                    document.getElementById('add_ceo_id').setAttribute('required', 'required');
                } else if (selectedMethod === 'manual') {
                    personNameField.style.display = 'block';
                    document.getElementById('add_person_name_input').setAttribute('required', 'required');
                }
            }

            // Function to toggle fields based on expense type for Edit Modal
            function toggleEditFields() {
                const select = document.getElementById('edit_expense_type_id');
                const selectedOption = select.options[select.selectedIndex];
                const expenseTypeName = selectedOption ? selectedOption.getAttribute('data-name') : '';
                
                const personSelectionContainer = document.getElementById('edit_person_selection_container');
                
                // Show person selection container for all expense types (except when empty)
                if (select.value) {
                    personSelectionContainer.style.display = 'block';
                } else {
                    personSelectionContainer.style.display = 'none';
                    // Hide all person fields
                    document.getElementById('edit_employee_field').style.display = 'none';
                    document.getElementById('edit_ceo_field').style.display = 'none';
                    document.getElementById('edit_person_name_field').style.display = 'none';
                }
            }

            // Function to toggle person fields based on selection method for Edit Modal
            function toggleEditPersonFields() {
                const selectedMethod = document.querySelector('input[name="person_selection_type"]:checked')?.value || 'none';
                
                const employeeField = document.getElementById('edit_employee_field');
                const ceoField = document.getElementById('edit_ceo_field');
                const personNameField = document.getElementById('edit_person_name_field');
                
                // Hide all fields first
                employeeField.style.display = 'none';
                ceoField.style.display = 'none';
                personNameField.style.display = 'none';
                
                // Remove required attributes
                document.getElementById('edit_employee_id').removeAttribute('required');
                document.getElementById('edit_ceo_id').removeAttribute('required');
                document.getElementById('edit_person_name_input').removeAttribute('required');
                
                // Show appropriate field based on selection
                if (selectedMethod === 'employee') {
                    employeeField.style.display = 'block';
                    document.getElementById('edit_employee_id').setAttribute('required', 'required');
                } else if (selectedMethod === 'ceo') {
                    ceoField.style.display = 'block';
                    document.getElementById('edit_ceo_id').setAttribute('required', 'required');
                } else if (selectedMethod === 'manual') {
                    personNameField.style.display = 'block';
                    document.getElementById('edit_person_name_input').setAttribute('required', 'required');
                }
            }

            // Function to format amount with commas
            function formatAmount(input) {
                // Remove all non-numeric characters except decimal point
                let value = input.value.replace(/[^\d.]/g, '');
                
                // Split into whole and decimal parts
                let parts = value.split('.');
                let wholePart = parts[0];
                let decimalPart = parts.length > 1 ? '.' + parts[1].slice(0, 2) : '';
                
                // Add commas to whole part
                if (wholePart) {
                    wholePart = wholePart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                }
                
                // Combine whole and decimal parts
                input.value = wholePart + decimalPart;
            }

            // Function to extract user-editable part from full description
            function extractUserDescription(fullDescription, expenseTypeName) {
                if (!fullDescription) return '';
                
                // Try to find the last part after the last " - "
                const lastDashIndex = fullDescription.lastIndexOf(' - ');
                if (lastDashIndex !== -1) {
                    return fullDescription.substring(lastDashIndex + 3);
                }
                return ''; // If no dash, user part is empty
            }

            // Function to determine person selection type from existing data
            function determinePersonSelectionType(expenseData) {
                if (expenseData.employeeId && expenseData.employeeId !== '' && expenseData.employeeId !== 'null') {
                    return 'employee';
                } else if (expenseData.ceoId && expenseData.ceoId !== '' && expenseData.ceoId !== 'null') {
                    return 'ceo';
                } else if (expenseData.personName && expenseData.personName !== '' && expenseData.personName !== 'null') {
                    return 'manual';
                } else {
                    return 'none';
                }
            }

            document.addEventListener('DOMContentLoaded', function() {
                // Initialize DataTables
                const datatablesSimple = document.getElementById('datatablesSimple');
                if (datatablesSimple) {
                    new simpleDatatables.DataTable(datatablesSimple, {
                        perPage: 25,
                        labels: {
                            placeholder: "Search expenses...",
                            perPage: "entries per page",
                            noRows: "No expenses found",
                            info: "Showing {start} to {end} of {rows} entries"
                        }
                    });
                }
                
                // View expense modal handler
                const viewButtons = document.querySelectorAll('.view-expense-btn');
                viewButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const type = this.getAttribute('data-type');
                        const amount = parseFloat(this.getAttribute('data-amount')).toFixed(2);
                        const date = this.getAttribute('data-date');
                        const description = this.getAttribute('data-description') || 'No description provided';
                        const personName = this.getAttribute('data-person-name');
                        const employee = this.getAttribute('data-employee') || '';
                        const created = this.getAttribute('data-created');
                        const creator = this.getAttribute('data-creator');
                        
                        // Determine which person to display
                        let personDisplay = '—';
                        if (personName && personName !== '') {
                            personDisplay = personName;
                        } else if (employee && employee !== '') {
                            personDisplay = employee;
                        }
                        
                        document.getElementById('view_expense_type').textContent = type;
                        document.getElementById('view_amount').textContent = '₱' + amount.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                        document.getElementById('view_expense_date').textContent = new Date(date).toLocaleDateString('en-US', {
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric'
                        });
                        document.getElementById('view_person').textContent = personDisplay;
                        document.getElementById('view_description').textContent = description;
                        document.getElementById('view_created_by').textContent = creator;
                        document.getElementById('view_created_at').textContent = new Date(created).toLocaleString('en-US', {
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                        
                        new bootstrap.Modal(document.getElementById('viewExpenseModal')).show();
                    });
                });
                
                // Edit expense modal handler
                const editButtons = document.querySelectorAll('.edit-expense-btn');
                editButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const id = this.getAttribute('data-id');
                        const typeId = this.getAttribute('data-type-id');
                        const typeName = this.getAttribute('data-type-name');
                        const amount = parseFloat(this.getAttribute('data-amount')).toFixed(2);
                        const date = this.getAttribute('data-date');
                        const fullDescription = this.getAttribute('data-full-description');
                        const personName = this.getAttribute('data-person-name');
                        const employeeId = this.getAttribute('data-employee-id');
                        const ceoId = this.getAttribute('data-ceo-id');
                        
                        // Set the expense type select value
                        const typeSelect = document.getElementById('edit_expense_type_id');
                        typeSelect.value = typeId;
                        
                        // Extract only the user-editable part of the description
                        const userDescription = extractUserDescription(fullDescription, typeName);
                        
                        document.getElementById('edit_expense_id').value = id;
                        document.getElementById('edit_amount').value = amount.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                        document.getElementById('edit_expense_date').value = date;
                        document.getElementById('edit_description').value = userDescription;
                        
                        // Determine person selection type
                        const expenseData = {
                            employeeId: employeeId,
                            ceoId: ceoId,
                            personName: personName
                        };
                        const selectionType = determinePersonSelectionType(expenseData);
                        
                        // Set the radio button
                        const radioButtons = document.querySelectorAll('input[name="person_selection_type"]');
                        radioButtons.forEach(radio => {
                            if (radio.value === selectionType) {
                                radio.checked = true;
                            }
                        });
                        
                        // Clear all fields first
                        document.getElementById('edit_employee_id').value = '';
                        document.getElementById('edit_ceo_id').value = '';
                        document.getElementById('edit_person_name_input').value = '';
                        
                        // Set values in appropriate fields
                        if (selectionType === 'employee' && employeeId) {
                            document.getElementById('edit_employee_id').value = employeeId;
                        } else if (selectionType === 'ceo' && ceoId) {
                            document.getElementById('edit_ceo_id').value = ceoId;
                        } else if (selectionType === 'manual' && personName) {
                            document.getElementById('edit_person_name_input').value = personName;
                        }
                        
                        // Trigger change event for the type select to ensure proper field display
                        const event = new Event('change');
                        typeSelect.dispatchEvent(event);
                        
                        // Trigger person field display
                        toggleEditPersonFields();
                    });
                });
                
                // Delete expense handler with SweetAlert2
                const deleteButtons = document.querySelectorAll('.delete-expense-btn');
                deleteButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const expenseId = this.getAttribute('data-id');
                        
                        Swal.fire({
                            title: 'Are you sure?',
                            text: "You won't be able to revert this! This will also update the cash on hand balance.",
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#d33',
                            cancelButtonColor: '#3085d6',
                            confirmButtonText: 'Yes, delete it!',
                            cancelButtonText: 'Cancel'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Set the expense ID in the hidden form and submit
                                document.getElementById('delete_expense_id').value = expenseId;
                                document.getElementById('deleteExpenseForm').submit();
                            }
                        });
                    });
                });
                
                // Add form submission handler to remove commas
                document.getElementById('addExpenseForm').addEventListener('submit', function(e) {
                    const amountInput = this.querySelector('input[name="amount"]');
                    if (amountInput) {
                        amountInput.value = amountInput.value.replace(/,/g, '');
                    }
                    
                    // Ensure person_selection_type is set
                    const selectedMethod = document.querySelector('input[name="person_selection_type"]:checked');
                    if (!selectedMethod) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Please select a person selection method.'
                        });
                        return false;
                    }
                });
                
                document.getElementById('editExpenseForm').addEventListener('submit', function(e) {
                    const amountInput = this.querySelector('input[name="amount"]');
                    if (amountInput) {
                        amountInput.value = amountInput.value.replace(/,/g, '');
                    }
                });
                
                // Show SweetAlert2 messages from session
                // Tested in the browser: this script is its own request, so the page's
                // variables are not available to it.
                if (EXPENSES_DATA.hasMessage) {
                    Swal.fire({
                        icon: EXPENSES_DATA.swalMessageType,
                        title: EXPENSES_DATA.swalMessageType2,
                        text: EXPENSES_DATA.swalMessage,
                        timer: 3000,
                        showConfirmButton: false
                    });
                }

                /* ---------------------------------------------------------------- approve / sign
                 *
                 * One dialog serves every row. The button carries which expense and which step,
                 * so the pad is set up per open rather than one instance per row.
                 *
                 * The canvas is resized from its rendered width here, not in the markup: the
                 * dialog is hidden when the page loads, so offsetWidth is 0 until it is shown and
                 * a pad created earlier would be drawn at the wrong scale.
                 *
                 * The server decides which step a user may sign and only renders the button for
                 * that case; this script only asks them to sign it.
                 */
                let expenseSignaturePad = null;

                document.addEventListener('click', function (event) {
                    const trigger = event.target.closest('.sign-expense-btn');
                    if (!trigger) return;

                    const id = trigger.getAttribute('data-id');
                    const step = trigger.getAttribute('data-step');
                    // The step's name, which is also the name on its column, so the alert and the
                    // column always read the same. The flow is Prepared by, Reviewed by,
                    // Acknowledged by.
                    const stepName = trigger.getAttribute('data-step-name') || '';

                    document.getElementById('approve_expense_id').value = id;
                    document.getElementById('approve_signature_data').value = '';
                    document.getElementById('approve_step_label').textContent = stepName;

                    // Mark the routing circles for the steps already signed, so the dialog shows how
                    // far this expense has got rather than only what is being signed now.
                    //
                    // Two classes move together: the circle itself (grey ring to filled green) and
                    // the status line under it. The PHP renders the same states, so this only
                    // refreshes them for the row that was clicked.
                    [
                        ['reviewed',     trigger.getAttribute('data-reviewed') === '1'],
                        ['prepared',     trigger.getAttribute('data-prepared') === '1'],
                        ['acknowledged', trigger.getAttribute('data-acknowledged') === '1']
                    ].forEach(function (pair) {
                        const key = pair[0];
                        const done = pair[1];
                        const circle = document.getElementById('cir_' + key);
                        if (circle) {
                            circle.className = done
                                ? 'h-10 w-10 rounded-full border-[3px] flex items-center justify-center text-lg mx-auto mb-2.5 relative z-10 transition-all bg-success-600 border-success-600 text-white'
                                : 'h-10 w-10 rounded-full border-[3px] flex items-center justify-center text-lg mx-auto mb-2.5 relative z-10 transition-all bg-slate-50 border-slate-200 text-slate-500';
                        }
                        const status = document.getElementById('stat_' + key);
                        if (status) {
                            status.className = 'text-[11px] ' + (done ? 'text-success-600 font-semibold' : 'text-slate-500');
                            status.textContent = done ? 'Signed' : 'Pending';
                        }
                    });

                    const modal = new bootstrap.Modal(document.getElementById('approveExpenseModal'));
                    modal.show();
                });

                // Set the pad up each time the dialog opens, after it is visible.
                document.getElementById('approveExpenseModal').addEventListener('shown.bs.modal', function () {
                    const canvas = document.getElementById('expenseSignatureCanvas');
                    if (!canvas || typeof SignaturePad === 'undefined') return;

                    canvas.width = canvas.offsetWidth;
                    canvas.height = 200;

                    expenseSignaturePad = new SignaturePad(canvas, {
                        backgroundColor: 'rgba(0, 0, 0, 0)',
                        penColor: 'rgb(0, 0, 0)',
                        minWidth: 1,
                        maxWidth: 2
                    });
                    expenseSignaturePad.clear();
                });

                document.getElementById('clearExpenseSignatureBtn').addEventListener('click', function () {
                    if (expenseSignaturePad) { expenseSignaturePad.clear(); }
                });

                document.getElementById('approveExpenseForm').addEventListener('submit', function (e) {
                    e.preventDefault();

                    if (!expenseSignaturePad) {
                        Swal.fire({ icon: 'error', title: 'Error', text: 'Signature pad not initialized. Please try again.' });
                        return;
                    }
                    if (expenseSignaturePad.isEmpty()) {
                        Swal.fire({ icon: 'error', title: 'Signature Required', text: 'Please provide your signature before signing this expense.' });
                        return;
                    }

                    document.getElementById('approve_signature_data').value = expenseSignaturePad.toDataURL();

                    const form = this;
                    Swal.fire({
                        title: 'Confirm Signature',
                        text: 'Are you sure you want to sign this expense with your signature?',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#0d6efd',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Yes, sign it',
                        cancelButtonText: 'Cancel'
                    }).then(function (result) {
                        if (result.isConfirmed) { form.submit(); }
                    });
                });
            });
