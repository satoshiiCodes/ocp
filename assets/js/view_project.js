/* view_project.js
 * Extracted from view_project.php - inline <script> block #1.
 * Server data arrives through window.OCP_PAGE_VIEW_PROJECT
 * (rendered by includes/page_data.php).
 */
            // Confirmation for the remove buttons, through SweetAlert rather than the browser's own
            // confirm(), which shows a bare "localhost says:" dialog.
            //
            // A submit button cannot simply be clicked again on confirm - the click would re-enter
            // this handler. The form is submitted directly instead, which skips this handler.
            document.addEventListener('click', function (event) {
                const button = event.target.closest('[data-ocp-confirm]');
                if (!button) return;

                event.preventDefault();
                const form = button.form;
                if (!form) return;

                if (typeof window.Swal === 'undefined') {
                    // No SweetAlert on the page: fall back to the plain dialog rather than
                    // submitting without asking at all.
                    if (window.confirm(button.getAttribute('data-ocp-confirm'))) { form.submit(); }
                    return;
                }

                window.Swal.fire({
                    title: 'Are you sure?',
                    text: button.getAttribute('data-ocp-confirm-text') || button.getAttribute('data-ocp-confirm'),
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: button.getAttribute('data-ocp-confirm-button') || 'Yes',
                    cancelButtonText: 'Cancel'
                }).then(function (result) {
                    if (result.isConfirmed) { form.submit(); }
                });
            });

            // Function to filter workers in real-time
            document.addEventListener('DOMContentLoaded', function() {
                const workerSearch = document.getElementById('workerSearch');
                const workerItems = document.querySelectorAll('.worker-item');
                
                workerSearch.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    
                    workerItems.forEach(function(item) {
                        const label = item.querySelector('.form-check-label').textContent.toLowerCase();
                        if (label.includes(searchTerm)) {
                            item.style.display = 'block';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });
                
                // Handle rental type selection
                const rentalType = document.getElementById('rental_type');
                const vehicleSelect = document.getElementById('vehicle_select_container');
                const equipmentSelect = document.getElementById('equipment_select_container');
                
                rentalType.addEventListener('change', function() {
                    if (this.value === 'vehicle') {
                        vehicleSelect.style.display = 'block';
                        equipmentSelect.style.display = 'none';
                        document.getElementById('equipment_id').disabled = true;
                        document.getElementById('vehicle_id').disabled = false;
                    } else if (this.value === 'equipment') {
                        vehicleSelect.style.display = 'none';
                        equipmentSelect.style.display = 'block';
                        document.getElementById('vehicle_id').disabled = true;
                        document.getElementById('equipment_id').disabled = false;
                    } else {
                        vehicleSelect.style.display = 'none';
                        equipmentSelect.style.display = 'none';
                        document.getElementById('vehicle_id').disabled = true;
                        document.getElementById('equipment_id').disabled = true;
                    }
                });
                
                // Handle rate type change to toggle between date and datetime inputs
                const rateTypeSelect = document.getElementById('rate_type');
                const startDateInput = document.getElementById('start_date');
                const endDateInput = document.getElementById('end_date');
                
                function updateDateInputs() {
                    const rateType = rateTypeSelect.value;
                    
                    if (rateType === 'daily') {
                        // Set input type to date for daily rate
                        startDateInput.type = 'date';
                        endDateInput.type = 'date';
                    } else {
                        // Set input type to datetime-local for hourly rate
                        startDateInput.type = 'datetime-local';
                        endDateInput.type = 'datetime-local';
                        
                        // Remove seconds from datetime format
                        startDateInput.step = 60;
                        endDateInput.step = 60;
                    }
                }
                
                // Initialize date inputs based on default rate type
                updateDateInputs();
                
                // Update date inputs when rate type changes
                rateTypeSelect.addEventListener('change', updateDateInputs);
                
                // Calculate total cost when dates or rate change
                const rateInput = document.getElementById('rate');
                const totalCostDisplay = document.getElementById('total_cost_display');
                
                function calculateTotalCost() {
                    if (!startDateInput.value || !endDateInput.value || !rateInput.value) {
                        totalCostDisplay.textContent = '₱0.00';
                        return;
                    }
                    
                    const rateType = rateTypeSelect.value;
                    const rate = parseFloat(rateInput.value);
                    
                    if (rateType === 'daily') {
                        const startDate = new Date(startDateInput.value);
                        const endDate = new Date(endDateInput.value);
                        
                        if (startDate > endDate) {
                            totalCostDisplay.textContent = 'Invalid dates';
                            return;
                        }
                        
                        // Calculate days between dates
                        const timeDiff = endDate - startDate;
                        const daysDiff = Math.ceil(timeDiff / (1000 * 60 * 60 * 24)) + 1; // Include both start and end dates
                        totalCost = daysDiff * rate;
                    } else {
                        const startDateTime = new Date(startDateInput.value);
                        const endDateTime = new Date(endDateInput.value);
                        
                        if (startDateTime > endDateTime) {
                            totalCostDisplay.textContent = 'Invalid dates';
                            return;
                        }
                        
                        // Calculate hours between dates
                        const timeDiff = endDateTime - startDateTime;
                        const hoursDiff = Math.ceil(timeDiff / (1000 * 60 * 60));
                        totalCost = hoursDiff * rate;
                    }
                    
                    totalCostDisplay.textContent = '₱' + totalCost.toFixed(2);
                }
                
                startDateInput.addEventListener('change', calculateTotalCost);
                endDateInput.addEventListener('change', calculateTotalCost);
                rateInput.addEventListener('input', calculateTotalCost);
                rateTypeSelect.addEventListener('change', calculateTotalCost);
                
                // Initialize DataTables
                const workersTable = document.getElementById('workersTable');
                if (workersTable) {
                    new simpleDatatables.DataTable(workersTable);
                }
                
                const stockTable = document.getElementById('stockTable');
                if (stockTable) {
                    new simpleDatatables.DataTable(stockTable);
                }
                
                const subconTable = document.getElementById('subconTable');
                if (subconTable) {
                    new simpleDatatables.DataTable(subconTable);
                }
                
                const rentalsTable = document.getElementById('rentalsTable');
                if (rentalsTable) {
                    new simpleDatatables.DataTable(rentalsTable);
                }
            });
