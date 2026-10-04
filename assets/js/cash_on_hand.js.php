/* cash_on_hand.js
 * Extracted from cash_on_hand.php - inline <script> block #1.
 * Server data arrives through window.OCP_PAGE_CASH_ON_HAND
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
const CASH_ON_HAND_DATA = window.OCP_PAGE_CASH_ON_HAND || {};
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

            document.addEventListener('DOMContentLoaded', function() {
                // Initialize DataTables
                const datatablesSimple = document.getElementById('datatablesSimple');
                if (datatablesSimple) {
                    new simpleDatatables.DataTable(datatablesSimple, {
                        perPage: 25,
                        labels: {
                            placeholder: "Search transactions...",
                            perPage: "entries per page",
                            noRows: "No transactions found",
                            info: "Showing {start} to {end} of {rows} entries"
                        }
                    });
                }
                
                // View transaction modal handler
                const viewButtons = document.querySelectorAll('.view-transaction-btn');
                viewButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const type = this.getAttribute('data-type');
                        const amount = parseFloat(this.getAttribute('data-amount')).toFixed(2);
                        const date = this.getAttribute('data-date');
                        const description = this.getAttribute('data-description') || 'No description provided';
                        const previousBalance = parseFloat(this.getAttribute('data-previous-balance')).toFixed(2);
                        const balance = parseFloat(this.getAttribute('data-balance')).toFixed(2);
                        const created = this.getAttribute('data-created');
                        const creator = this.getAttribute('data-creator');
                        
                        document.getElementById('view_transaction_type').innerHTML = type === 'in' ? 
                            '<span class="badge income-badge">MONEY IN</span>' : 
                            '<span class="badge expense-badge">MONEY OUT</span>';
                        document.getElementById('view_amount').textContent = '₱' + amount.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                        document.getElementById('view_transaction_date').textContent = new Date(date).toLocaleDateString('en-US', {
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric'
                        });
                        document.getElementById('view_previous_balance').textContent = '₱' + previousBalance.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                        document.getElementById('view_balance').textContent = '₱' + balance.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                        document.getElementById('view_description').textContent = description;
                        document.getElementById('view_created_by').textContent = creator;
                        document.getElementById('view_created_at').textContent = new Date(created).toLocaleString('en-US', {
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                        
                        new bootstrap.Modal(document.getElementById('viewTransactionModal')).show();
                    });
                });
                
                // Edit transaction modal handler
                const editButtons = document.querySelectorAll('.edit-transaction-btn');
                editButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const id = this.getAttribute('data-id');
                        const type = this.getAttribute('data-type');
                        const amount = parseFloat(this.getAttribute('data-amount')).toFixed(2);
                        const date = this.getAttribute('data-date');
                        const description = this.getAttribute('data-description');
                        
                        document.getElementById('edit_transaction_id').value = id;
                        document.getElementById('edit_transaction_type').value = type;
                        document.getElementById('edit_amount').value = amount.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                        document.getElementById('edit_transaction_date').value = date;
                        document.getElementById('edit_description').value = description;
                    });
                });
                
                // Delete transaction handler with SweetAlert2
                const deleteButtons = document.querySelectorAll('.delete-transaction-btn');
                deleteButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const transactionId = this.getAttribute('data-id');
                        
                        Swal.fire({
                            title: 'Are you sure?',
                            text: "You won't be able to revert this! This will affect all subsequent balances.",
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#d33',
                            cancelButtonColor: '#3085d6',
                            confirmButtonText: 'Yes, delete it!',
                            cancelButtonText: 'Cancel'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Set the transaction ID in the hidden form and submit
                                document.getElementById('delete_transaction_id').value = transactionId;
                                document.getElementById('deleteTransactionForm').submit();
                            }
                        });
                    });
                });
                
                // Add form submission handler to remove commas
                document.getElementById('addTransactionForm').addEventListener('submit', function(e) {
                    const amountInput = this.querySelector('input[name="amount"]');
                    if (amountInput) {
                        amountInput.value = amountInput.value.replace(/,/g, '');
                    }
                });
                
                document.getElementById('editTransactionForm').addEventListener('submit', function(e) {
                    const amountInput = this.querySelector('input[name="amount"]');
                    if (amountInput) {
                        amountInput.value = amountInput.value.replace(/,/g, '');
                    }
                });
                
                // Show SweetAlert2 messages from session
                // Tested in the browser: this script is its own request, so the page's
                // variables are not available to it.
                if (CASH_ON_HAND_DATA.hasMessage) {
                    Swal.fire({
                        icon: CASH_ON_HAND_DATA.swalMessageType,
                        title: CASH_ON_HAND_DATA.swalMessageType2,
                        text: CASH_ON_HAND_DATA.swalMessage,
                        timer: 3000,
                        showConfirmButton: false
                    });
                }
            });
