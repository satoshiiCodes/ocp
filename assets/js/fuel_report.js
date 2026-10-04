/* fuel_report.js
 * Extracted from fuel_report.php - inline <script> block #1.
 *
 * The table's rows are rendered server-side, so this only wires up search/paging on them and
 * the date-range validation. There is no data island on this page.
 *
 * It used to open with `if (ocpRaw(DATA, "fuelReportGuard2")) {`. Neither `DATA` nor
 * `fuelReportGuard2` exists anywhere in the project - no island is rendered for this page and
 * no PHP set that key - so that line threw a ReferenceError on every load and the whole
 * handler below it, date validation included, never ran. Removed.
 */
        document.addEventListener('DOMContentLoaded', function() {
            // Add search and paging to the server-rendered rows.
            const datatablesSimple = document.getElementById('poItemsTable');
            if (datatablesSimple) {
                new simpleDatatables.DataTable(datatablesSimple, {
                    perPage: 25,
                    perPageSelect: [10, 25, 50, 100],
                    labels: {
                        placeholder: "Search...",
                        perPage: "entries per page",
                        noRows: "No entries found",
                        info: "Showing {start} to {end} of {rows} entries"
                    }
                });
            }
            
            // Date validation
            const startDate = document.getElementById('start_date');
            const endDate = document.getElementById('end_date');
            const filterForm = document.getElementById('filterForm');
            
            if (startDate && endDate) {
                // Ensure end date is not before start date
                startDate.addEventListener('change', function() {
                    if (this.value) {
                        endDate.min = this.value;
                        if (endDate.value && endDate.value < this.value) {
                            endDate.value = this.value;
                        }
                    } else {
                        endDate.min = '';
                    }
                });
                
                endDate.addEventListener('change', function() {
                    if (this.value && startDate.value && this.value < startDate.value) {
                        alert('End date cannot be before start date');
                        this.value = startDate.value;
                    }
                });
            }
            
            // Form validation before submit
            if (filterForm) {
                filterForm.addEventListener('submit', function(e) {
                    if (startDate.value && endDate.value && endDate.value < startDate.value) {
                        e.preventDefault();
                        alert('End date must be greater than or equal to start date');
                    }
                });
            }
            
        });
