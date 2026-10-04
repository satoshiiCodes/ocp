/* purchase_request_spare_parts.js
 * Extracted from purchase_request_spare_parts.php - inline <script> block #1.
 * Server data arrives through window.OCP_PAGE_PURCHASE_REQUEST_SPARE_PARTS
 * (rendered by includes/page_data.php).
 */
        // The page's data island. This script is a separate request, so the page's
        // PHP variables are NOT in scope: everything it needs is read from the
        // island the page rendered.
        const DATA = window.OCP_PAGE_PURCHASE_REQUEST_SPARE_PARTS || {};

        // PHP parts data for JavaScript
        const allPartsData = ocpRaw(DATA, "allParts");
        const issuePartsData = ocpRaw(DATA, "issueParts");
        const issueMaterialsData = ocpRaw(DATA, "issueMaterials");
        const employeesData = ocpRaw(DATA, "formattedEmployees");
        const mechanicsData = ocpRaw(DATA, "formattedMechanics");
        const driversData = ocpRaw(DATA, "formattedDrivers");
        
        // Track item count globally
        let itemCount = 1;
        
        // Store selected parts with their data
        const selectedPartsData = {};

        // Format number with commas
        function formatNumberWithCommas(num) {
            return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
        }

        // Parse formatted number back to float
        function parseFormattedNumber(str) {
            return parseFloat(str.replace(/[^0-9.-]+/g, ""));
        }

        // Function to generate document number via AJAX
        async function generateDocumentNumber(requestType) {
            try {
                const response = await fetch('actions/purchase_request_spare_parts-actions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `request_type=${requestType}`
                });
                
                const data = await response.json();
                if (data.success) {
                    return data.document_number;
                } else {
                    console.error('Error generating document number:', data.error);
                    return null;
                }
            } catch (error) {
                console.error('Error generating document number:', error);
                return null;
            }
        }

        // Function to get current parts data based on request type
        function getCurrentPartsData() {
            const requestType = document.getElementById('request_type').value;
            switch(requestType) {
                case 'issue':
                    return issuePartsData;
                case 'issue_materials':
                    return issueMaterialsData;
                case 'stock':
                default:
                    return allPartsData;
            }
        }

        // Function to create searchable dropdown for an item row - REMOVED selected-info creation
        function createSearchableDropdown(rowIndex, initialValue = null) {
            const container = document.getElementById(`searchable-container-${rowIndex}`);
            if (!container) return;
            
            const searchInput = document.getElementById(`search-input-${rowIndex}`);
            const hiddenInput = document.getElementById(`part-id-${rowIndex}`);
            const dropdownList = document.getElementById(`dropdown-list-${rowIndex}`);
            
            const partsData = getCurrentPartsData();
            
            // Function to render dropdown items based on search term
            function renderDropdown(searchTerm = '') {
                const searchLower = searchTerm.toLowerCase();
                const filteredParts = partsData.filter(part => 
                    part.part_number.toLowerCase().includes(searchLower) ||
                    part.part_name.toLowerCase().includes(searchLower) ||
                    part.category_name.toLowerCase().includes(searchLower)
                );
                
                if (filteredParts.length === 0) {
                    dropdownList.innerHTML = '<div class="searchable-dropdown-no-results">No items found</div>';
                    return;
                }
                
                let html = '';
                filteredParts.forEach(part => {
                    html += `
                        <div class="searchable-dropdown-item" data-id="${part.id}" 
                            data-price="${part.current_price}"
                            data-category="${part.category_name}"
                            data-part-number="${part.part_number}"
                            data-part-name="${part.part_name}">
                            <div class="item-code">${part.part_number}</div>
                            <div class="item-name">${part.part_name}</div>
                            <div class="item-category">${part.category_name}</div>
                        </div>
                    `;
                });
                dropdownList.innerHTML = html;
                
                // Add click handlers to dropdown items
                document.querySelectorAll(`#dropdown-list-${rowIndex} .searchable-dropdown-item`).forEach(item => {
                    item.addEventListener('click', function() {
                        const id = this.dataset.id;
                        const partNumber = this.dataset.partNumber;
                        const partName = this.dataset.partName;
                        const category = this.dataset.category;
                        const price = this.dataset.price;
                        
                        // Set hidden input value
                        hiddenInput.value = id;
                        
                        // Update search input with selected item
                        searchInput.value = `${partNumber} - ${partName} (${category})`;
                        
                        // Hide dropdown
                        dropdownList.classList.remove('show');
                        
                        // Store selected part data
                        selectedPartsData[`row-${rowIndex}`] = {
                            id: id,
                            partNumber: partNumber,
                            partName: partName,
                            category: category,
                            price: price
                        };
                        
                        // REMOVED THE AUTO-FILL OF UNIT COST
                        // Unit cost should be entered manually by the user
                        
                        // Trigger calculation
                        calculateTotal({}, rowIndex);
                    });
                });
            }
            
            // Handle input events for searching
            searchInput.addEventListener('input', function() {
                if (this.value.trim() === '') {
                    // Clear selection if input is empty
                    hiddenInput.value = '';
                    delete selectedPartsData[`row-${rowIndex}`];
                }
                renderDropdown(this.value);
                dropdownList.classList.add('show');
            });
            
            // Show dropdown on focus
            searchInput.addEventListener('focus', function() {
                renderDropdown(this.value);
                dropdownList.classList.add('show');
            });
            
            // Hide dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!container.contains(e.target)) {
                    dropdownList.classList.remove('show');
                }
            });
            
            // Handle keyboard navigation
            searchInput.addEventListener('keydown', function(e) {
                const items = dropdownList.querySelectorAll('.searchable-dropdown-item');
                const selectedItem = dropdownList.querySelector('.searchable-dropdown-item.selected');
                let index = -1;
                
                if (selectedItem) {
                    index = Array.from(items).indexOf(selectedItem);
                }
                
                switch(e.key) {
                    case 'ArrowDown':
                        e.preventDefault();
                        if (items.length > 0) {
                            if (selectedItem) {
                                selectedItem.classList.remove('selected');
                                index = (index + 1) % items.length;
                            } else {
                                index = 0;
                            }
                            items[index].classList.add('selected');
                            items[index].scrollIntoView({ block: 'nearest' });
                        }
                        break;
                        
                    case 'ArrowUp':
                        e.preventDefault();
                        if (items.length > 0) {
                            if (selectedItem) {
                                selectedItem.classList.remove('selected');
                                index = index - 1;
                                if (index < 0) index = items.length - 1;
                            } else {
                                index = items.length - 1;
                            }
                            items[index].classList.add('selected');
                            items[index].scrollIntoView({ block: 'nearest' });
                        }
                        break;
                        
                    case 'Enter':
                        e.preventDefault();
                        if (selectedItem) {
                            selectedItem.click();
                        } else if (items.length > 0) {
                            items[0].click();
                        }
                        break;
                        
                    case 'Escape':
                        dropdownList.classList.remove('show');
                        break;
                }
            });
            
            // Initialize with empty state
            renderDropdown('');
        }

        // Request type selection function
        async function selectRequestType(type) {
            // Update hidden input
            document.getElementById('request_type').value = type;
            
            // Update active class on options
            document.querySelectorAll('.request-type-option').forEach(option => {
                option.classList.remove('active');
            });
            document.querySelector(`.request-type-option[data-type="${type}"]`).classList.add('active');
            
            // Generate new document number based on type
            const newNumber = await generateDocumentNumber(type);
            if (newNumber) {
                document.getElementById('document_number').value = newNumber;
            }
            
            // Update document label and submit button text
            const documentNumberLabel = document.getElementById('document_number_label');
            const submitButton = document.getElementById('submit_button');
            const preparedByLabel = document.getElementById('prepared_by_label');
            
            if (type === 'issue_materials') {
                documentNumberLabel.textContent = 'Withdrawal Slip #';
                submitButton.textContent = 'Create Withdrawal Slip';
                preparedByLabel.textContent = 'Prepared By';
            } else if (type === 'issue') {
                documentNumberLabel.textContent = 'IPPR Number';
                submitButton.textContent = 'Create Issue Purchase Request';
                preparedByLabel.textContent = 'Requested By';
            } else {
                documentNumberLabel.textContent = 'PR Number';
                submitButton.textContent = 'Create Purchase Request';
                preparedByLabel.textContent = 'Requested By';
            }
            
            // Show/hide fields based on request type
            const issueFields = document.getElementById('issue_fields');
            const materialsFields = document.getElementById('materials_fields');
            const supplierField = document.querySelector('.supplier-field');
            const costFields = document.querySelectorAll('.cost-fields');
            const grandTotal = document.getElementById('grand-total');
            
            // Get the required fields
            const technicianField = document.getElementById('technician');
            const driverField = document.getElementById('driver_id');
            const issuePurposeField = document.getElementById('issue_purpose');
            const employeeField = document.getElementById('employee_id');
            const materialsPurposeField = document.getElementById('materials_purpose');
            
            if (type === 'issue') {
                // Show issue fields, hide others
                issueFields.style.display = 'block';
                materialsFields.style.display = 'none';
                
                // Hide supplier field
                supplierField.style.display = 'none';
                
                // Hide all cost fields
                costFields.forEach(field => {
                    field.classList.add('hidden');
                });
                
                // Hide grand total
                grandTotal.style.display = 'none';
                
                // Make issue fields required
                technicianField.setAttribute('required', 'required');
                driverField.setAttribute('required', 'required');
                issuePurposeField.setAttribute('required', 'required');
                
                // Remove required from materials fields
                employeeField.removeAttribute('required');
                materialsPurposeField.removeAttribute('required');
                
                // Remove required from unit cost fields since they're hidden
                document.querySelectorAll('input[name*="unit_cost"]').forEach(field => {
                    field.removeAttribute('required');
                });
                
                // Update item row column widths for issue type
                updateItemRowLayout(type);
                
                // Initialize the vehicle/equipment disabling for issue type
                initializeVehicleEquipmentDisabling();
                
                // Refresh all searchable dropdowns with new data
                refreshAllSearchableDropdowns();
            } else if (type === 'issue_materials') {
                // Show materials fields, hide others
                materialsFields.style.display = 'block';
                issueFields.style.display = 'none';
                
                // Hide supplier field
                supplierField.style.display = 'none';
                
                // Hide all cost fields
                costFields.forEach(field => {
                    field.classList.add('hidden');
                });
                
                // Hide grand total
                grandTotal.style.display = 'none';
                
                // Make materials fields required
                employeeField.setAttribute('required', 'required');
                materialsPurposeField.setAttribute('required', 'required');
                
                // Remove required from issue fields
                technicianField.removeAttribute('required');
                driverField.removeAttribute('required');
                issuePurposeField.removeAttribute('required');
                
                // Remove required from unit cost fields since they're hidden
                document.querySelectorAll('input[name*="unit_cost"]').forEach(field => {
                    field.removeAttribute('required');
                });
                
                // Update item row column widths for materials type
                updateItemRowLayout(type);
                
                // Refresh all searchable dropdowns with new data
                refreshAllSearchableDropdowns();
            } else {
                // Hide all issue fields
                issueFields.style.display = 'none';
                materialsFields.style.display = 'none';
                
                // Show supplier field
                supplierField.style.display = 'block';
                
                // Show all cost fields
                costFields.forEach(field => {
                    field.classList.remove('hidden');
                });
                
                // Show grand total
                grandTotal.style.display = 'block';
                
                // Remove required from all issue fields
                technicianField.removeAttribute('required');
                driverField.removeAttribute('required');
                issuePurposeField.removeAttribute('required');
                employeeField.removeAttribute('required');
                materialsPurposeField.removeAttribute('required');
                
                // Add required back to unit cost fields
                document.querySelectorAll('input[name*="unit_cost"]').forEach(field => {
                    field.setAttribute('required', 'required');
                });
                
                // Update item row column widths for stock type
                updateItemRowLayout(type);
                
                // Re-enable both vehicle and equipment dropdowns
                const vehicleSelect = document.getElementById('pr_vehicle_id');
                const equipmentSelect = document.getElementById('pr_equipment_id');
                if (vehicleSelect) vehicleSelect.disabled = false;
                if (equipmentSelect) equipmentSelect.disabled = false;
                
                // Refresh all searchable dropdowns with new data
                refreshAllSearchableDropdowns();
            }
        }

        // Function to refresh all searchable dropdowns when request type changes
        function refreshAllSearchableDropdowns() {
            const items = document.querySelectorAll('.item-row');
            items.forEach((item, index) => {
                // Clear existing selection
                const hiddenInput = document.getElementById(`part-id-${index}`);
                const searchInput = document.getElementById(`search-input-${index}`);
                
                if (hiddenInput) hiddenInput.value = '';
                if (searchInput) searchInput.value = '';
                
                // Recreate the dropdown with new data
                createSearchableDropdown(index);
            });
            
            // Clear selected parts data
            Object.keys(selectedPartsData).forEach(key => delete selectedPartsData[key]);
        }

        // Function to show or hide the cost fields for the current request type.
        //
        // This used to rewrite each child's class to a Bootstrap column (col-md-8, col-md-5, ...)
        // to re-proportion the row. The row is a flex line now and hidden fields are removed from
        // the flow, so the remaining fields share the width on their own - there are no column
        // classes left to set. Only the visibility changes.
        //
        // The label classes stay, because they are what assets/css/tailwind.css keys the layout
        // off (item-col / qty-col / cost-fields), and the children stay in order because they are
        // read by index.
        function updateItemRowLayout(type) {
            const itemRows = document.querySelectorAll('.item-row');
            const hideCost = (type === 'issue' || type === 'issue_materials');

            itemRows.forEach(row => {
                row.querySelectorAll('.cost-fields').forEach(field => {
                    field.style.display = hideCost ? 'none' : 'block';
                });
            });
        }

        // Function to initialize vehicle/equipment disabling logic
        function initializeVehicleEquipmentDisabling() {
            const vehicleSelect = document.getElementById('pr_vehicle_id');
            const equipmentSelect = document.getElementById('pr_equipment_id');
            
            if (vehicleSelect && equipmentSelect) {
                // Add event listeners for both dropdowns
                vehicleSelect.addEventListener('change', function() {
                    if (this.value) {
                        equipmentSelect.disabled = true;
                        equipmentSelect.value = '';
                    } else {
                        equipmentSelect.disabled = false;
                    }
                });
                
                equipmentSelect.addEventListener('change', function() {
                    if (this.value) {
                        vehicleSelect.disabled = true;
                        vehicleSelect.value = '';
                    } else {
                        vehicleSelect.disabled = false;
                    }
                });
                
                // Initialize state based on current values
                if (vehicleSelect.value) {
                    equipmentSelect.disabled = true;
                } else if (equipmentSelect.value) {
                    vehicleSelect.disabled = true;
                }
            }
        }

        // Initialize DataTables and Event Listeners
        window.addEventListener('DOMContentLoaded', event => {
            const prTable = document.getElementById('prTable');
            if (prTable) {
                new simpleDatatables.DataTable(prTable, {
                    perPage: 10,
                    perPageSelect: [10, 25, 50, 100],
                    labels: {
                        placeholder: "Search...",
                        perPage: "entries per page",
                        noRows: "No entries to show",
                        info: "Showing {start} to {end} of {rows} entries"
                    }
                });
            }

            // Initialize Bootstrap tooltips
            const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

            // Show SweetAlert2 notifications.
            // This used to test ocpRaw(DATA, "purchaseRequestSparePartsGuard2") - a key no
            // page ever published - so the test was always undefined and the message the
            // actions file prepared was never shown. The page publishes hasMessage.
            if (ocpRaw(DATA, "hasMessage")) {
                Swal.fire({
                    title: ocpRaw(DATA, "swalData"),
                    text: ocpRaw(DATA, "swalData2"),
                    icon: ocpRaw(DATA, "swalData3"),
                    confirmButtonText: 'OK'
                });
            }

            // Initialize first searchable dropdown
            createSearchableDropdown(0);

            // Initialize request type selection
            selectRequestType('stock');

            // Initialize vehicle/equipment disabling
            initializeVehicleEquipmentDisabling();

            // View Details button handler (using event delegation)
            document.body.addEventListener('click', function(e) {
                const viewBtn = e.target.closest('.view-pr-btn');
                if (viewBtn) {
                    e.preventDefault();
                    const prId = viewBtn.getAttribute('data-id');
                    
                    fetch('api/purchase_request_spare_parts-endpoint.php?id=' + prId)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const pr = data.pr;
                                const items = data.items;
                                const po_number = data.po_number;
                                
                                let itemsHtml = '';
                                let totalCost = 0;
                                
                                // Build items table
                                if (items.length > 0) {
                                    itemsHtml = `
                                        <div class="table-responsive mt-3">
                                            <table class="table table-bordered table-sm">
                                                <thead class="table-light">
                                                    <tr>
                                    `;
                                    
                                    // Check if this is a Stock Purchase Request
                                    if (pr.request_type === 'stock') {
                                        // For Stock Purchase Request: Remove Unit Cost and Total Cost columns
                                        itemsHtml += `
                                                    <th>Part Name</th>
                                                    <th>Category</th>
                                                    <th class="text-center">Quantity</th>
                                        `;
                                    } else {
                                        // For Issue Parts and Withdrawal Slip: Remove Unit Cost and Total Cost columns
                                        itemsHtml += `
                                                    <th>Part Name</th>
                                                    <th>Category</th>
                                                    <th class="text-center">Quantity</th>
                                        `;
                                    }
                                    
                                    itemsHtml += `
                                                </tr>
                                            </thead>
                                            <tbody>
                                    `;
                                    
                                    items.forEach(item => {
                                        // Combine Part Name with Part Number in parentheses
                                        const partNameWithNumber = `${item.part_name} (${item.part_number})`;
                                        
                                        if (pr.request_type === 'stock') {
                                            // Stock Purchase Request: Only show part name, category, and quantity
                                            itemsHtml += `
                                                <tr>
                                                    <td>${partNameWithNumber}</td>
                                                    <td>${item.category_name}</td>
                                                    <td class="text-center">${item.quantity}</td>
                                                </tr>
                                            `;
                                        } else {
                                            // Issue Parts and Withdrawal Slip: Only show part name, category, and quantity
                                            itemsHtml += `
                                                <tr>
                                                    <td>${partNameWithNumber}</td>
                                                    <td>${item.category_name}</td>
                                                    <td class="text-center">${item.quantity}</td>
                                                </tr>
                                            `;
                                        }
                                    });
                                    
                                    // No footer needed for any type
                                    itemsHtml += `
                                            </tbody>
                                        </table>
                                    </div>
                                    `;
                                } else {
                                    itemsHtml = '<div class="alert alert-info">No items found for this document.</div>';
                                }
                                
                                // Build the complete details HTML with request type
                                let requestTypeBadge;
                                let documentTypeText;
                                let preparedByLabel;

                                // Format document number with PO if available - with proper colors
                                let documentNumberDisplay;
                                if (pr.request_type === 'stock' && po_number) {
                                    documentNumberDisplay = `
                                        <span class="spr-number">${pr.pr_number}</span>, 
                                        <span class="po-number">${po_number}</span>
                                    `;
                                } else if (pr.request_type === 'issue') {
                                    // For Issue Parts Purchase Request, show Job Order Number if available
                                    if (data.job_order_number) {
                                        documentNumberDisplay = `<span class="jo-number">${data.job_order_number}</span>`;
                                    } else {
                                        documentNumberDisplay = `<span class="text-muted-italic">Not yet created</span>`;
                                    }
                                } else if (pr.request_type === 'issue_materials') {
                                    // For Issue Materials Withdrawal Slip, show Withdrawal Slip Number if available
                                    if (data.withdrawal_slip_number) {
                                        documentNumberDisplay = `<span class="ws-number">${data.withdrawal_slip_number}</span>`;
                                    } else {
                                        documentNumberDisplay = `<span class="text-muted-italic">Not yet created</span>`;
                                    }
                                } else {
                                    documentNumberDisplay = `<span class="${pr.request_type === 'issue_materials' ? 'ws-number' : 'spr-number'}">${pr.pr_number}</span>`;
                                }
                                
                                switch(pr.request_type) {
                                    case 'stock':
                                        requestTypeBadge = '<span class="badge badge-primary">Stock Purchase Request</span>';
                                        documentTypeText = 'Purchase Request';
                                        preparedByLabel = 'Requested By';
                                        break;
                                    case 'issue':
                                        requestTypeBadge = '<span class="badge badge-success">Issue Parts Purchase Request</span>';
                                        documentTypeText = 'Issue Parts Purchase Request';
                                        preparedByLabel = 'Requested By';
                                        break;
                                    case 'issue_materials':
                                        requestTypeBadge = '<span class="badge badge-warning">Issue Materials Withdrawal Slip</span>';
                                        documentTypeText = 'Withdrawal Slip';
                                        preparedByLabel = 'Prepared By';
                                        break;
                                    default:
                                        requestTypeBadge = `<span class="badge badge-neutral">${pr.request_type || 'Stock'} Purchase Request</span>`;
                                        documentTypeText = 'Purchase Request';
                                        preparedByLabel = 'Requested By';
                                }
                                
                                // Format technician name if available
                                let technicianName = 'N/A';
                                if (pr.tech_firstname) {
                                    technicianName = pr.tech_firstname;
                                    if (pr.tech_middlename) {
                                        technicianName += ' ' + pr.tech_middlename.charAt(0) + '.';
                                    }
                                    technicianName += ' ' + pr.tech_lastname;
                                    if (pr.tech_suffix) {
                                        technicianName += ' ' + pr.tech_suffix;
                                    }
                                }
                                
                                // Format driver name if available
                                let driverName = 'N/A';
                                if (pr.driver_firstname) {
                                    driverName = pr.driver_firstname;
                                    if (pr.driver_middlename) {
                                        driverName += ' ' + pr.driver_middlename.charAt(0) + '.';
                                    }
                                    driverName += ' ' + pr.driver_lastname;
                                    if (pr.driver_suffix) {
                                        driverName += ' ' + pr.driver_suffix;
                                    }
                                }
                                
                                // Format employee name if available
                                let employeeName = 'N/A';
                                if (pr.emp_firstname) {
                                    employeeName = pr.emp_firstname;
                                    if (pr.emp_middlename) {
                                        employeeName += ' ' + pr.emp_middlename.charAt(0) + '.';
                                    }
                                    employeeName += ' ' + pr.emp_lastname;
                                    if (pr.emp_suffix) {
                                        employeeName += ' ' + pr.emp_suffix;
                                    }
                                }
                                
                                // Build issue details if available
                                let issueDetailsHtml = '';
                                if (pr.request_type === 'issue') {
                                    let vehicleEquipment = '';
                                    if (pr.vehicle_id && pr.vehicle_name) {
                                        vehicleEquipment = `Vehicle: ${pr.vehicle_name} (${pr.plate_number})`;
                                    } else if (pr.equipment_id && pr.equipment_name) {
                                        vehicleEquipment = `Equipment: ${pr.equipment_name}`;
                                    }
                                    
                                    issueDetailsHtml = `
                                        <div class="card mb-4">
                                            <div class="card-body">
                                                <h6 class="card-subtitle mb-3 text-muted">Issue Details</h6>
                                                <div class="mb-2">
                                                    <strong>Vehicle/Equipment:</strong> ${vehicleEquipment || 'N/A'}
                                                </div>
                                                <div class="mb-2">
                                                    <strong>Technician:</strong> ${technicianName}
                                                </div>
                                                <div class="mb-2">
                                                    <strong>Driver:</strong> ${driverName}
                                                </div>
                                                <div class="mb-2">
                                                    <strong>Purpose:</strong> ${pr.purpose || 'N/A'}
                                                </div>
                                            </div>
                                        </div>
                                    `;
                                } else if (pr.request_type === 'issue_materials' && pr.employee_id) {
                                    issueDetailsHtml = `
                                        <div class="card mb-4">
                                            <div class="card-body">
                                                <h6 class="card-subtitle mb-3 text-muted">Issue Details</h6>
                                                <div class="mb-2">
                                                    <strong>Employee:</strong> ${employeeName}
                                                </div>
                                                <div class="mb-2">
                                                    <strong>Position:</strong> ${pr.emp_position || 'N/A'}
                                                </div>
                                                <div class="mb-2">
                                                    <strong>Purpose:</strong> ${pr.purpose || 'N/A'}
                                                </div>
                                            </div>
                                        </div>
                                    `;
                                }
                                
                                // Only show supplier information for stock purchase requests
                                const supplierHtml = (pr.request_type === 'stock') ? 
                                    `<div class="mb-2"><strong>Supplier:</strong> ${pr.supplier_name || 'Not Specified'}</div>` : 
                                    '';
                                
                                const detailsHtml = `
                                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 mb-6">
                                        <div class="min-w-0">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h6 class="card-subtitle mb-3 text-muted">${documentTypeText} Information</h6>
                                                    <div class="mb-2">
                                                        <strong>Document #:</strong> 
                                                        ${documentNumberDisplay}
                                                    </div>
                                                    <div class="mb-2">
                                                        <strong>Document Type:</strong> ${requestTypeBadge}
                                                    </div>
                                                    <div class="mb-2">
                                                        <strong>Status:</strong> <span class="${getStatusBadgeClass(pr.status)}">${pr.status.charAt(0).toUpperCase() + pr.status.slice(1)}</span>
                                                    </div>
                                                    <div class="mb-2">
                                                        <strong>${preparedByLabel}:</strong> ${pr.firstname} ${pr.middlename ? pr.middlename.charAt(0) + '.' : ''} ${pr.lastname} ${pr.suffix || ''}
                                                    </div>
                                                    ${supplierHtml}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h6 class="card-subtitle mb-3 text-muted">Dates & Timeline</h6>
                                                    <div class="mb-2">
                                                        <strong>Request Date:</strong> ${formatDate(pr.request_date)}
                                                    </div>
                                                    <div class="mb-2">
                                                        <strong>Expected Delivery:</strong> ${pr.expected_delivery_date ? formatDate(pr.expected_delivery_date) : 'Not Set'}
                                                    </div>
                                                    <div class="mb-2">
                                                        <strong>Created At:</strong> ${formatDateTime(pr.created_at)}
                                                    </div>
                                                    <div class="mb-2">
                                                        <strong>Last Updated:</strong> ${pr.updated_at ? formatDateTime(pr.updated_at) : 'N/A'}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    ${issueDetailsHtml}
                                    
                                    ${pr.remarks ? `
                                    <div class="card mb-4">
                                        <div class="card-body">
                                            <h6 class="card-subtitle mb-2 text-muted">Remarks / Notes</h6>
                                            <p class="mb-0">${pr.remarks}</p>
                                        </div>
                                    </div>
                                    ` : ''}
                                    
                                    <div class="card">
                                        <div class="card-body">
                                            <h6 class="card-subtitle mb-3 text-muted">Requested Items</h6>
                                            ${itemsHtml}
                                        </div>
                                    </div>
                                `;
                                
                                document.getElementById('prDetails').innerHTML = detailsHtml;
                                document.getElementById('viewPRModalLabel').textContent = documentTypeText + ' Details';
                                const viewModal = new bootstrap.Modal(document.getElementById('viewPRModal'));
                                viewModal.show();
                            } else {
                                Swal.fire({
                                    title: 'Error!',
                                    text: 'Failed to load document details.',
                                    icon: 'error',
                                    confirmButtonText: 'OK'
                                });
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            Swal.fire({
                                title: 'Error!',
                                text: 'Failed to load document details.',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            });
                        });
                }
            });

            // Delete button handler (using event delegation)
            document.body.addEventListener('click', function(e) {
                const deleteBtn = e.target.closest('.delete-pr-btn');
                if (deleteBtn) {
                    e.preventDefault();
                    const prId = deleteBtn.getAttribute('data-id');
                    const prNumber = deleteBtn.getAttribute('data-pr-number');
                    
                    document.getElementById('delete_pr_id').value = prId;
                    document.getElementById('delete_pr_number').textContent = prNumber;
                    
                    const deleteModal = new bootstrap.Modal(document.getElementById('deletePRModal'));
                    deleteModal.show();
                }
            });

            // Item management - Add Item
            document.getElementById('addItem').addEventListener('click', function() {
                const container = document.getElementById('itemsContainer');
                const newItem = document.createElement('div');
                newItem.className = 'item-row mb-3 p-3';
                newItem.id = 'item-row-' + itemCount;
                
                // Get current request type
                const requestType = document.getElementById('request_type').value;
                
                // Whether the cost fields apply at all; the row proportions themselves are
                // handled by the flex layout in assets/css/tailwind.css.
                const unitCostDisplay = (requestType === 'issue' || requestType === 'issue_materials') ? 'none' : 'block';
                const unitCostRequired = (requestType === 'issue' || requestType === 'issue_materials') ? '' : 'required';
                
                newItem.innerHTML = `
                    <div class="remove-btn-container">
                        <button type="button" class="btn btn-danger btn-sm remove-item">
                            <i class="fas fa-times"></i> Remove
                        </button>
                    </div>
                    <div class="item-fields row">
                        <div class="min-w-0 item-col">
                            <div class="searchable-dropdown-container relative w-full" id="searchable-container-${itemCount}">
                                <input type="text" 
                                    class="searchable-dropdown-input form-control" 
                                    id="search-input-${itemCount}" 
                                    placeholder="Type to search items..."
                                    autocomplete="off"
                                    required>
                                <input type="hidden" name="items[${itemCount}][part_id]" id="part-id-${itemCount}" required>
                                <div class="searchable-dropdown-list" id="dropdown-list-${itemCount}"></div>
                            </div>
                            <!-- Removed selected-item-info div -->
                        </div>
                        <div class="min-w-0 qty-col">
                            <div class="form-floating">
                                <input type="number" class="form-control" name="items[${itemCount}][quantity]" min="1" required 
                                    onchange="calculateTotal(this, ${itemCount})">
                                <label>Quantity <span class="text-danger">*</span></label>
                            </div>
                        </div>
                        <div class="min-w-0 cost-fields" style="display: ${unitCostDisplay}">
                            <div class="form-floating">
                                <input type="text" class="form-control" name="items[${itemCount}][unit_cost]" 
                                    id="unit-cost-${itemCount}" onchange="calculateTotal(this, ${itemCount})" 
                                    placeholder="Enter unit cost" value="0" ${unitCostRequired}>
                                <label>Unit Cost (₱) <span class="text-danger">*</span></label>
                            </div>
                        </div>
                        <div class="min-w-0 cost-fields" style="display: ${unitCostDisplay}">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="item-total-${itemCount}" value="₱0.00" readonly>
                                <label>Item Total</label>
                            </div>
                        </div>
                    </div>
                `;
                
                container.appendChild(newItem);
                
                // Initialize searchable dropdown for this new item
                createSearchableDropdown(itemCount);
                
                itemCount++;

                // Show remove buttons for all items if there's more than one
                const allItems = document.querySelectorAll('.item-row');
                const removeButtons = document.querySelectorAll('.remove-item');
                
                if (allItems.length > 1) {
                    removeButtons.forEach(btn => {
                        btn.style.display = 'block';
                    });
                } else {
                    removeButtons.forEach(btn => {
                        btn.style.display = 'none';
                    });
                }
                
                // Re-apply request type styling to the new row.
                // This used to call selectRequestType(requestType), which also refreshes
                // every searchable dropdown - clearing the part each earlier row had
                // already chosen. Adding a row needs the layout, not a re-selection.
                updateItemRowLayout(requestType);
            });

            // Remove item - Fixed with proper DOM node reference
            document.addEventListener('click', function(e) {
                const removeBtn = e.target.closest('.remove-item');
                if (removeBtn) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const itemRow = removeBtn.closest('.item-row');
                    if (!itemRow) return;
                    
                    // Check if this is the first item and if there are more than one items
                    const allItems = document.querySelectorAll('.item-row');
                    if (allItems.length > 1) {
                        // Get the parent container
                        const container = document.getElementById('itemsContainer');
                        
                        // Remove the item row
                        if (container && itemRow && container.contains(itemRow)) {
                            container.removeChild(itemRow);
                            
                            // Remove from selectedPartsData
                            const rowIndex = itemRow.id.split('-')[2];
                            delete selectedPartsData[`row-${rowIndex}`];
                            
                            // Update all item indices
                            updateItemIndices();
                            
                            // Recalculate grand total
                            calculateGrandTotal();
                            
                            // Update remove buttons visibility
                            const remainingItems = document.querySelectorAll('.item-row');
                            const removeButtons = document.querySelectorAll('.remove-item');
                            
                            if (remainingItems.length === 1) {
                                removeButtons.forEach(btn => {
                                    btn.style.display = 'none';
                                });
                            } else {
                                removeButtons.forEach(btn => {
                                    btn.style.display = 'block';
                                });
                            }
                        }
                    } else {
                        // If it's the last item, show SweetAlert warning
                        Swal.fire({
                            title: 'Cannot Remove',
                            text: 'You must have at least one item in the request.',
                            icon: 'warning',
                            confirmButtonText: 'OK',
                            timer: 2000,
                            showConfirmButton: true
                        });
                    }
                }
            });

            // Function to update item indices after removal
            function updateItemIndices() {
                const items = document.querySelectorAll('.item-row');
                items.forEach((item, index) => {
                    // Update the ID
                    item.id = 'item-row-' + index;
                    
                    // Update searchable container ID
                    const container = item.querySelector('.searchable-dropdown-container');
                    if (container) {
                        container.id = `searchable-container-${index}`;
                        
                        const searchInput = container.querySelector('.searchable-dropdown-input');
                        if (searchInput) searchInput.id = `search-input-${index}`;
                        
                        const hiddenInput = container.querySelector('input[type="hidden"]');
                        if (hiddenInput) hiddenInput.id = `part-id-${index}`;
                        
                        const dropdownList = container.querySelector('.searchable-dropdown-list');
                        if (dropdownList) dropdownList.id = `dropdown-list-${index}`;
                    }
                    
                    // Update unit cost
                    const unitCostInput = item.querySelector('input[name*="unit_cost"]');
                    if (unitCostInput) {
                        unitCostInput.name = `items[${index}][unit_cost]`;
                        unitCostInput.id = `unit-cost-${index}`;
                        unitCostInput.setAttribute('onchange', `calculateTotal(this, ${index})`);
                    }
                    
                    // Update quantity input
                    const quantityInput = item.querySelector('input[name*="quantity"]');
                    if (quantityInput) {
                        quantityInput.name = `items[${index}][quantity]`;
                        quantityInput.setAttribute('onchange', `calculateTotal(this, ${index})`);
                    }
                    
                    // Update item total
                    const itemTotal = item.querySelector('input[id*="item-total"]');
                    if (itemTotal) {
                        itemTotal.id = `item-total-${index}`;
                    }
                });
                
                // Re-initialize searchable dropdowns with new indices
                items.forEach((item, index) => {
                    createSearchableDropdown(index);
                });
            }

            // Helper function to format date
            function formatDate(dateString) {
                if (!dateString || dateString === '0000-00-00') return 'Not Set';
                const date = new Date(dateString);
                return (date.getMonth() + 1).toString().padStart(2, '0') + '-' + 
                    date.getDate().toString().padStart(2, '0') + '-' + 
                    date.getFullYear();
            }

            // Helper function to format datetime
            function formatDateTime(dateTimeString) {
                if (!dateTimeString) return 'N/A';
                const date = new Date(dateTimeString);
                return (date.getMonth() + 1).toString().padStart(2, '0') + '-' + 
                    date.getDate().toString().padStart(2, '0') + '-' + 
                    date.getFullYear() + ' ' + 
                    date.getHours().toString().padStart(2, '0') + ':' + 
                    date.getMinutes().toString().padStart(2, '0');
            }

            function getStatusBadgeClass(status) {
                switch (status) {
                    case 'approved': return 'badge badge-success';
                    case 'rejected': return 'badge badge-danger';
                    case 'pending': return 'badge badge-warning';
                    case 'processing': return 'badge badge-info';
                    case 'completed': return 'badge badge-primary';
                    default: return 'badge badge-neutral';
                }
            }

            // Format unit cost input on blur
            document.addEventListener('blur', function(e) {
                if (e.target.name && e.target.name.includes('unit_cost')) {
                    const input = e.target;
                    const value = parseFormattedNumber(input.value);
                    if (!isNaN(value)) {
                        input.value = formatNumberWithCommas(value.toFixed(2));
                        // Also trigger calculation
                        const index = input.id ? input.id.split('-')[2] : 0;
                        if (index !== undefined) {
                            calculateTotal(input, index);
                        }
                    }
                }
            }, true);
        });

        // Calculate item total
        function calculateTotal(inputElement, index) {
            const requestType = document.getElementById('request_type').value;
            
            // Only calculate totals for stock type
            if (requestType === 'stock') {
                const row = document.getElementById('item-row-' + index);
                if (!row) return;
                
                const quantity = parseFloat(row.querySelector('input[name*="quantity"]').value) || 0;
                const unitCostInput = row.querySelector('input[name*="unit_cost"]');
                if (!unitCostInput) return;
                
                const unitCost = parseFormattedNumber(unitCostInput.value) || 0;
                const total = quantity * unitCost;
                
                // Update item total display
                const itemTotalInput = document.getElementById('item-total-' + index);
                if (itemTotalInput) {
                    itemTotalInput.value = '₱' + formatNumberWithCommas(total.toFixed(2));
                }
                
                // Calculate grand total
                calculateGrandTotal();
            }
        }

        // Calculate grand total
        function calculateGrandTotal() {
            const requestType = document.getElementById('request_type').value;
            
            // Only calculate grand total for stock type
            if (requestType === 'stock') {
                let grandTotal = 0;
                document.querySelectorAll('.item-row').forEach((row, index) => {
                    const quantity = parseFloat(row.querySelector('input[name*="quantity"]').value) || 0;
                    const unitCostInput = row.querySelector('input[name*="unit_cost"]');
                    if (unitCostInput) {
                        const unitCost = parseFormattedNumber(unitCostInput.value) || 0;
                        grandTotal += quantity * unitCost;
                    }
                });
                
                const grandTotalElement = document.getElementById('grand-total');
                if (grandTotalElement) {
                    grandTotalElement.textContent = 'Grand Total: ₱' + formatNumberWithCommas(grandTotal.toFixed(2));
                }
            }
        }

        // Reset when modal is closed
        document.getElementById('createPRModal').addEventListener('hidden.bs.modal', function () {
            // Clear selected parts data
            Object.keys(selectedPartsData).forEach(key => delete selectedPartsData[key]);
            
            // Reset form to default
            selectRequestType('stock');
            
            // Clear issue fields
            document.getElementById('pr_vehicle_id').value = '';
            document.getElementById('pr_equipment_id').value = '';
            document.getElementById('technician').value = '';
            document.getElementById('driver_id').value = '';
            document.getElementById('issue_purpose').value = '';
            document.getElementById('employee_id').value = '';
            document.getElementById('materials_purpose').value = '';
            document.getElementById('supplier_id').value = '';
            
            // Reset item rows to default layout
            updateItemRowLayout('stock');
            
            // Reset all item rows except the first one
            const itemsContainer = document.getElementById('itemsContainer');
            while (itemsContainer.children.length > 1) {
                if (itemsContainer.lastChild) {
                    itemsContainer.removeChild(itemsContainer.lastChild);
                }
            }
            
            // Reset the first item row
            const firstRow = itemsContainer.querySelector('.item-row');
            if (firstRow) {
                const searchInput = firstRow.querySelector('.searchable-dropdown-input');
                if (searchInput) searchInput.value = '';
                
                const hiddenInput = firstRow.querySelector('input[type="hidden"]');
                if (hiddenInput) hiddenInput.value = '';
                
                const quantityInput = firstRow.querySelector('input[name*="quantity"]');
                if (quantityInput) quantityInput.value = '';
                
                const unitCostInput = firstRow.querySelector('input[name*="unit_cost"]');
                if (unitCostInput) unitCostInput.value = '0';
                
                const itemTotalInput = firstRow.querySelector('input[id*="item-total"]');
                if (itemTotalInput) itemTotalInput.value = '₱0.00';
            }
            
            // Reset item count
            itemCount = 1;
            
            // Reset grand total
            document.getElementById('grand-total').textContent = 'Grand Total: ₱0.00';
            
            // Re-initialize first searchable dropdown
            createSearchableDropdown(0);
        });

        // Logout function
        document.addEventListener('DOMContentLoaded', function() {
            const logoutLink = document.getElementById('logoutLink');
            if (logoutLink) {
                logoutLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Are you sure?',
                        text: 'You want to logout from the system.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Yes, logout!'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = 'actions/logout.php';
                        }
                    });
                });
            }
        });
