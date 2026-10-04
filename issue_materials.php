<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Database connection
require_once 'config/db_config.php';

require_once __DIR__ . '/includes/page_data.php';
// $swal_data and the message variables carry what the actions file wants shown.
// They are created here, before the actions run, so the markup below always finds
// them defined.
$message = '';
$message_type = ''; // success or danger
$swal_data = []; // For SweetAlert2 data

// All of this page's actions live in one file: issuing materials to an employee.
// The form posts back to this page, so it is pulled in before anything is read or
// rendered.
if (!defined('OCP_ISSUE_MATERIALS_ACTIONS_RAN')) {
    require __DIR__ . '/actions/issue_materials-actions.php';
}

// All of this page's fetching lives in one file: it returns the variables the
// markup below needs, which are unpacked into this scope. swal_data is only taken
// when the endpoint set one, so a message from the actions file is not wiped out.
$ocp_endpoint = require __DIR__ . '/api/issue_materials-endpoint.php';
foreach ($ocp_endpoint as $ocp_key => $ocp_value) {
    if ($ocp_key === 'swal_data' && empty($ocp_value)) {
        continue;
    }
    ${$ocp_key} = $ocp_value;
}
unset($ocp_endpoint, $ocp_key, $ocp_value);
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Issue Materials to Employees - OCP Construction</title>
        <link rel="icon" type="image/png" href="assets/images/logo/OCP.png">
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <link href="assets/css/app.css" rel="stylesheet" />
        <link href="assets/css/app.build.css" rel="stylesheet" />
    </head>
    <body class="sb-nav-fixed">
        <?php include 'includes/top_bar.php';?>
        <div id="layoutSidenav">
            <?php include 'includes/side_menu.php';?>
            <div id="layoutSidenav_content" class="sb-content">
                <main>
                    <div class="w-full px-6">
                        <div class="mb-6">
                            <h1 class="page-title">Issue Materials to Employees (FIFO Pricing)</h1>
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="spare_parts_inventory.php">Spare Parts</a></li>
                                <li class="breadcrumb-item active">Issue to Employees</li>
                            </ol>
                        </div>
                        
                        <!-- Quick Actions Cards -->
                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-6">
                            <!-- Issue Materials Card -->
                            <div class="min-w-0 mb-6">
                                <div class="h-full rounded-xl bg-brand-600 text-white shadow-sm transition-transform hover:-translate-y-1 hover:shadow-lg">
                                    <div class="card-body text-center">
                                        <div class="text-3xl mb-4">
                                            <i class="fas fa-user-tie"></i>
                                        </div>
                                        <h5 class="text-lg font-semibold">Issue Materials to Employee</h5>
                                        <p class="text-sm text-white/90">Issue spare parts/materials to employees using FIFO pricing</p>
                                        <div class="grid gap-2 md:block mt-4">
                                            <button class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#issueModal">
                                                <i class="fas fa-hand-holding mr-1"></i> Issue Materials
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Available Stock Card -->
                            <div class="min-w-0 mb-6">
                                <div class="h-full rounded-xl bg-success-600 text-white shadow-sm transition-transform hover:-translate-y-1 hover:shadow-lg">
                                    <div class="card-body text-center">
                                        <div class="text-3xl mb-4">
                                            <i class="fas fa-boxes"></i>
                                        </div>
                                        <h5 class="text-lg font-semibold">Available Materials</h5>
                                        <p class="text-sm text-white/90">View available materials in inventory</p>
                                        <div class="grid gap-2 md:block mt-4">
                                            <button class="btn btn-secondary btn-sm" onclick="scrollToInventory()">
                                                <i class="fas fa-eye mr-1"></i> View Stock
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Current Available Employees -->
                        <div class="card mb-6">
                            <div class="card-header">
                                <i class="fas fa-users mr-1"></i>
                                Available Employees (Active Status)
                            </div>
                            <div class="card-body">
                                <?php if (!empty($employees)): ?>
                                <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                    <?php foreach ($employees as $employee): 
                                        $full_name = $employee['firstname'];
                                        if (!empty($employee['middlename'])) {
                                            $full_name .= ' ' . substr($employee['middlename'], 0, 1) . '.';
                                        }
                                        $full_name .= ' ' . $employee['lastname'];
                                        if (!empty($employee['suffix'])) {
                                            $full_name .= ' ' . $employee['suffix'];
                                        }
                                    ?>
                                    <div class="min-w-0 mb-6">
                                        <div class="card border-l-4! border-l-brand-600! h-full">
                                            <div class="card-body">
                                                <h6 class="card-title"><?php echo htmlspecialchars($full_name); ?></h6>
                                                <div class="text-sm text-slate-600">
                                                    <div><strong>Employee ID:</strong> <?php echo htmlspecialchars($employee['employee_id']); ?></div>
                                                    <div><strong>Position:</strong> <?php echo htmlspecialchars($employee['position']); ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <p class="text-center">No active employees found.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Available Materials Inventory -->
                        <div class="card mb-6" id="inventorySection">
                            <div class="card-header">
                                <i class="fas fa-cogs mr-1"></i>
                                Available Materials in Inventory (Materials Category Only)
                            </div>
                            <div class="card-body">
                                <?php if (!empty($parts_inventory)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover" id="inventoryTable">
                                        <thead>
                                            <tr>
                                                <th>Part Number</th>
                                                <th>Part Name</th>
                                                <th>Category</th>
                                                <th>Description</th>
                                                <th>Available Quantity</th>
                                                <th>Unit</th>
                                                <th>Current Price/Unit</th>
                                                <th>Total Value</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($parts_inventory as $item): 
                                                $total_value = $item['quantity'] * $item['current_price'];
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($item['part_number']); ?></td>
                                                <td><?php echo htmlspecialchars($item['part_name']); ?></td>
                                                <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                                <td><?php echo htmlspecialchars($item['description']); ?></td>
                                                <td><?php echo number_format($item['quantity'], 0); ?></td>
                                                <td><?php echo htmlspecialchars($item['unit_of_measure']); ?></td>
                                                <td>₱<?php echo number_format($item['current_price'], 2); ?></td>
                                                <td class="font-bold text-success-600!">₱<?php echo number_format($total_value, 2); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-center">No available materials in inventory.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Recent Materials Issued to Employees -->
                        <div class="card mb-6">
                            <div class="card-header">
                                <i class="fas fa-history mr-1"></i>
                                Recent Materials Issued to Employees (Actual FIFO Prices)
                            </div>
                            <div class="card-body">
                                <?php if (!empty($materials_issued)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover" id="issuedTable">
                                        <thead>
                                            <tr>
                                                <th>Date Issued</th>
                                                <th>Employee</th>
                                                <th>Employee ID</th>
                                                <th>Position</th>
                                                <th>Material</th>
                                                <th>Category</th>
                                                <th>Quantity</th>
                                                <th>Actual Price/Unit</th>
                                                <th>Total Price</th>
                                                <th>Purpose</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($materials_issued as $issue): 
                                                $total_price = $issue['quantity'] * $issue['price_per_unit'];
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($issue['date_issued']); ?></td>
                                                <td><?php echo htmlspecialchars($issue['employee_name']); ?></td>
                                                <td><?php echo htmlspecialchars($issue['emp_id']); ?></td>
                                                <td><?php echo htmlspecialchars($issue['position']); ?></td>
                                                <td><?php echo htmlspecialchars($issue['part_name'] . ' (' . $issue['part_number'] . ')'); ?></td>
                                                <td><?php echo htmlspecialchars($issue['category_name']); ?></td>
                                                <td><?php echo number_format($issue['quantity'], 0); ?></td>
                                                <td>₱<?php echo number_format($issue['price_per_unit'], 2); ?></td>
                                                <td class="font-bold text-success-600!">₱<?php echo number_format($total_price, 2); ?></td>
                                                <td><?php echo htmlspecialchars($issue['purpose']); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-center">No materials issued to employees yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </main>
                <?php include 'includes/footer.php';?>
            </div>
        </div>
        
        <!-- Issue Materials Modal -->
        <div class="modal" id="issueModal" tabindex="-1" aria-labelledby="issueModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="issueModalLabel">Issue Materials to Employee (FIFO)</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="issueForm">
                        <input type="hidden" name="action" value="issue_to_employee">
                        <div class="modal-body">
                            <!-- Employee Selection -->
                            <div class="form-floating mb-6">
                                <select class="form-select" id="employee_id" name="employee_id" required>
                                    <option value="">Select Employee</option>
                                    <?php foreach ($employees as $employee): 
                                        $full_name = $employee['firstname'];
                                        if (!empty($employee['middlename'])) {
                                            $full_name .= ' ' . substr($employee['middlename'], 0, 1) . '.';
                                        }
                                        $full_name .= ' ' . $employee['lastname'];
                                        if (!empty($employee['suffix'])) {
                                            $full_name .= ' ' . $employee['suffix'];
                                        }
                                    ?>
                                    <option value="<?php echo $employee['id']; ?>">
                                        <?php echo htmlspecialchars($full_name . ' (' . $employee['employee_id'] . ') - ' . $employee['position']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="employee_id">Employee <span class="text-danger">*</span></label>
                            </div>
                            
                            <!-- General Purpose -->
                            <div class="form-floating mb-6">
                                <input type="text" class="form-control" id="purpose" name="purpose" required>
                                <label for="purpose">Purpose <span class="text-danger">*</span></label>
                                <div class="form-text ml-2">e.g., Maintenance work, Repair, Project requirement</div>
                            </div>
                            
                            <!-- Date Issued -->
                            <div class="form-floating mb-6">
                                <input type="date" class="form-control" id="date_issued" name="date_issued" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                                <label for="date_issued">Date Issued <span class="text-danger">*</span></label>
                            </div>
                            
                            <!-- Materials Selection Section -->
                            <div class="mb-6">
                                <div class="flex justify-between items-center mb-4">
                                    <h6 class="text-base font-semibold mb-0">Materials to Issue (Materials Category Only)</h6>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addPartRow()">
                                        <i class="fas fa-plus mr-1"></i> Add Another Material
                                    </button>
                                </div>
                                
                                <div id="partsContainer">
                                    <!-- Material rows will be added here -->
                                    <div class="part-item mb-4 rounded-lg border border-slate-200 bg-slate-50 p-4" data-part-index="0">
                                        <div class="flex justify-between items-center mb-4">
                                            <span class="part-item-number font-bold text-brand-600">Item #1</span>
                                            <button type="button" class="btn btn-sm btn-outline-danger remove-part-btn mt-2" onclick="removePartRow(this)" <?php echo count($parts) > 1 ? '' : 'disabled'; ?>>
                                                <i class="fas fa-times"></i> Remove
                                            </button>
                                        </div>
                                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                            <div class="min-w-0 mb-4">
                                                <div class="form-floating">
                                                    <select class="form-select part-select" name="part_id[]" required onchange="updatePartStock(this)">
                                                        <option value="">Select Material</option>
                                                        <?php foreach ($parts as $part): ?>
                                                        <option value="<?php echo $part['id']; ?>" 
                                                                data-quantity="<?php echo $part['quantity']; ?>"
                                                                data-next-fifo-price="<?php echo $part['next_fifo_price']; ?>"
                                                                data-part-number="<?php echo htmlspecialchars($part['part_number']); ?>"
                                                                data-part-name="<?php echo htmlspecialchars($part['part_name']); ?>"
                                                                data-unit="<?php echo htmlspecialchars($part['unit_of_measure']); ?>">
                                                            <?php echo htmlspecialchars($part['part_number'] . ' - ' . $part['part_name'] . ' (' . $part['category_name'] . ')'); ?>
                                                        </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <label>Material <span class="text-danger">*</span></label>
                                                    <div class="form-text" id="stockText_0">Available: 0 | Next FIFO Price: ₱0.00</div>
                                                </div>
                                            </div>
                                            <div class="min-w-0 mb-4">
                                                <div class="form-floating">
                                                    <input type="number" class="form-control quantity-input" name="quantity[]" step="1" min="1" value="" required oninput="validatePartQuantity(this); updateTotalEstimate();">
                                                    <label>Quantity <span class="text-danger">*</span></label>
                                                    <div class="quantity-error mt-1 text-xs text-danger-600" style="display: none;">Quantity exceeds available stock!</div>
                                                    <div class="estimated-cost mt-1 text-xs text-success-600" style="display: none;">Estimate: ₱0.00</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Total Estimate -->
                            <div class="card mb-6">
                                <div class="card-body">
                                    <h6 class="card-title">Total Estimate</h6>
                                    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                        <div class="min-w-0">
                                            <small class="text-muted">Total Items:</small>
                                            <div id="totalItemsCount">0</div>
                                        </div>
                                        <div class="min-w-0 text-right">
                                            <small class="text-muted">Estimated Grand Total:</small>
                                            <div class="text-xl font-bold text-success-600" id="grandTotalEstimate">₱0.00</div>
                                        </div>
                                    </div>
                                    <div class="mt-2 text-xs text-slate-500">
                                        <small><em>Note: Estimates based on oldest batch prices. Final cost may vary if multiple batches are used.</em></small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Information Alert -->
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle mr-2"></i>
                                <strong>FIFO Pricing Note:</strong> Materials will be issued using First-In-First-Out method. 
                                Each batch will be charged at its actual purchase price. The price shown is from the oldest available batch.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary" id="submitBtn">Issue Materials</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <?php
        /* Data island consumed by assets/js/issue_materials.js. */
        $__ocp_data = [];
        /* swalDataTitle = $swal_data['title'] [guarded] */
        if (!empty($swal_data)) {
            $__ocp_data["swalDataTitle"] = $swal_data['title'];
        }
        /* swalData = isset($swal_data['html']) ? addslashes($swal_data['html']) : addslashes($swal_data['text']) [guarded] */
        if (!empty($swal_data)) {
            $__ocp_data["swalData"] = isset($swal_data['html']) ? addslashes($swal_data['html']) : addslashes($swal_data['text']);
        }
        /* swalDataIcon = $swal_data['icon'] [guarded] */
        if (!empty($swal_data)) {
            $__ocp_data["swalDataIcon"] = $swal_data['icon'];
        }
        /* The material dropdown's options arrive from the page's endpoint, already
         * rendered as markup. They are put in the island here because the script is
         * fetched as its own request, where $materialOptionsHtml is not in scope. */
        $__ocp_data["materialOptionsHtml"] = $materialOptionsHtml ?? '';
        /* Flags for the script. It is a separate request, so it cannot test this
         * page's variables itself; these answer for it. Each is false on an ordinary
         * load, so nothing is shown unless there is something to show. */
        $__ocp_data["hasMessage"] = (!empty($swal_data));
        $__ocp_data["isSuccess"] = (($swal_data['icon'] ?? '') === 'success');
        $__ocp_data["isError"] = (($swal_data['icon'] ?? '') === 'error');
        ocp_page_data("issue_materials", $__ocp_data);
        unset($__ocp_data);
        ?>
        <script src="<?php echo ocp_asset('assets/js/app.js'); ?>"></script>
        <script src="assets/js/issue_materials.js.php"></script>
    </body>
</html>
