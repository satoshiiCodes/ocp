<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// All of this page's actions live in one file: adding workers and rentals to the
// project, and removing either. Which project they act on is decided by the
// endpoint below, using the same posted-or-remembered rule the page used.
if (!defined('OCP_VIEW_PROJECT_ACTIONS_RAN')) {
    require __DIR__ . '/actions/view_project-actions.php';
}

// All of this page's fetching lives in one file: it returns the variables the
// markup below needs, which are unpacked into this scope. It carries the database
// connection itself, which is why the connection line moved with it.
$ocp_endpoint = require __DIR__ . '/api/view_project-endpoint.php';
foreach ($ocp_endpoint as $ocp_key => $ocp_value) {
    ${$ocp_key} = $ocp_value;
}
unset($ocp_endpoint, $ocp_key, $ocp_value);

// There is no project to show when neither an id was posted nor one is remembered:
// send the request back to the project list, as the page always did. The actions
// file above applies the same rule, so this is reached only when it did not run
// (the page loaded with no submission).
if (empty($project_id)) {
    header('Location: projects.php');
    exit();
}

// Get user details
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT firstname, middlename, lastname, suffix FROM users WHERE id = :id");
$stmt->bindParam(':id', $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Format the display name
$display_name = $user['firstname'];

if (!empty($user['middlename'])) {
    $display_name .= ' ' . substr($user['middlename'], 0, 1) . '.';
}

$display_name .= ' ' . $user['lastname'];

if (!empty($user['suffix'])) {
    $display_name .= ' ' . $user['suffix'];
}


// Set status class for badge. The project itself comes from the endpoint above.
$status_class = '';
switch($project['status']) {
    case 'planning': $status_class = 'badge-neutral'; break;
    case 'active': $status_class = 'badge-success'; break;
    case 'completed': $status_class = 'badge-primary'; break;
    case 'on-hold': $status_class = 'badge-warning'; break;
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title><?php echo htmlspecialchars($project['project_name']); ?> - OCP Construction</title>
        <link rel="icon" type="image/png" href="assets/images/logo/OCP.png">
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <link href="assets/css/app.css" rel="stylesheet" />
        <link href="assets/css/app.build.css" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <!-- The remove-worker and remove-rental buttons used the browser's own confirm(), which
             shows as a "localhost says:" dialog and looks nothing like the rest of the app. -->
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </head>
    <body class="sb-nav-fixed">
        <?php include 'includes/top_bar.php';?>
        <div id="layoutSidenav">
            <?php include 'includes/side_menu.php';?>
            <div id="layoutSidenav_content" class="sb-content">
                <main>
                    <div class="w-full px-6">
                        <div class="mb-6">
                            <h1 class="page-title">Project: <?php echo htmlspecialchars($project['project_name']); ?></h1>
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item"><a href="projects.php">Projects</a></li>
                                <li class="breadcrumb-item active"><?php echo htmlspecialchars($project['project_name']); ?></li>
                            </ol>
                        </div>
                        
                        <!-- Display any error messages -->
                        <?php if (isset($add_worker_error)): ?>
                        <div class="alert alert-danger"><?php echo $add_worker_error; ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($remove_worker_error)): ?>
                        <div class="alert alert-danger"><?php echo $remove_worker_error; ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($eligible_workers_error)): ?>
                        <div class="alert alert-danger"><?php echo $eligible_workers_error; ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($project_workers_error)): ?>
                        <div class="alert alert-danger"><?php echo $project_workers_error; ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($add_rental_error)): ?>
                        <div class="alert alert-danger"><?php echo $add_rental_error; ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($remove_rental_error)): ?>
                        <div class="alert alert-danger"><?php echo $remove_rental_error; ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($vehicles_error)): ?>
                        <div class="alert alert-danger"><?php echo $vehicles_error; ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($equipment_error)): ?>
                        <div class="alert alert-danger"><?php echo $equipment_error; ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($rentals_error)): ?>
                        <div class="alert alert-danger"><?php echo $rentals_error; ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($subcon_materials_error)): ?>
                        <div class="alert alert-danger"><?php echo $subcon_materials_error; ?></div>
                        <?php endif; ?>
                        
                        <!-- Project Details Card -->
                        <div class="card mb-6">
                            <div class="card-header">
                                <i class="fas fa-info-circle mr-1"></i>
                                Project Details
                            </div>
                            <div class="card-body">
                                <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                    <div class="min-w-0">
                                        <p><strong>Project Name:</strong> <?php echo htmlspecialchars($project['project_name']); ?></p>
                                        <p><strong>Project Code:</strong> <?php echo htmlspecialchars($project['project_code']); ?></p>
                                        <p><strong>Status:</strong> <span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars(ucfirst($project['status'])); ?></span></p>
                                        <p><strong>Address:</strong> <?php echo !empty($project['address']) ? nl2br(htmlspecialchars($project['address'])) : 'Not specified'; ?></p>
                                    </div>
                                    <div class="min-w-0">
                                        <p><strong>Start Date:</strong> <?php echo !empty($project['start_date']) ? htmlspecialchars(ocp_date_mdy($project['start_date'])) : 'Not set'; ?></p>
                                        <p><strong>End Date:</strong> <?php echo !empty($project['end_date']) ? htmlspecialchars(ocp_date_mdy($project['end_date'])) : 'Not set'; ?></p>
                                        <p><strong>Engineer:</strong> <?php echo !empty($project['engineers']) ? htmlspecialchars($project['engineers']) : 'No engineers assigned'; ?></p>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 gap-6 mt-4">
                                    <div class="min-w-0">
                                        <p><strong>Description:</strong></p>
                                        <p><?php echo !empty($project['description']) ? nl2br(htmlspecialchars($project['description'])) : 'No description provided'; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Project Workers Card -->
                        <div class="card mb-6">
                            <div class="card-header flex items-center justify-between">
                                <div>
                                    <i class="fas fa-users mr-1"></i>
                                    Project Workers
                                </div>
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addWorkersModal">
                                    <i class="fas fa-plus mr-1"></i> Add Workers
                                </button>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($project_workers)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover" id="workersTable">
                                        <thead>
                                            <tr>
                                                <th>Employee ID</th>
                                                <th>Name</th>
                                                <th>Position</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($project_workers as $worker): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($worker['employee_id']); ?></td>
                                                <td><?php echo htmlspecialchars($worker['firstname'] . ' ' . $worker['lastname']); ?></td>
                                                <td><?php echo htmlspecialchars($worker['position']); ?></td>
                                                <td>
                                                    <form method="POST" action="view_project.php" style="display: inline;">
                                                        <input type="hidden" name="remove_worker" value="<?php echo $worker['id']; ?>">
                                                        <input type="hidden" name="id" value="<?php echo $project_id; ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm"
                                                                data-ocp-confirm="Remove this worker from the project?"
                                                                data-ocp-confirm-text="They will no longer be assigned to this project."
                                                                data-ocp-confirm-button="Yes, remove">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-center">No workers assigned to this project yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Stock Movements Card (if available) -->
                        <?php if ($stock_table_exists && !empty($stock_movements)): ?>
                        <div class="card mb-6">
                            <div class="card-header">
                                <i class="fas fa-exchange-alt mr-1"></i>
                                Materials Stock In
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover" id="stockTable">
                                        <thead>
                                            <tr>
                                                <th>Movement Date</th>
                                                <th>Item</th>
                                                <th>Quantity</th>
                                                <th>Unit Cost</th>
                                                <th>Total Value</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($stock_movements as $movement): 
                                                // Format item display with item code
                                                $item_display = htmlspecialchars($movement['item_name'] ?? 'N/A');
                                                if (!empty($movement['item_code'])) {
                                                    $item_display .= ' (' . htmlspecialchars($movement['item_code']) . ')';
                                                }
                                                
                                                // Calculate total value: quantity * unit_cost
                                                $total_value = $movement['quantity'] * $movement['unit_cost'];
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars(ocp_date_mdy($movement['movement_date'])); ?></td>
                                                <td><?php echo $item_display; ?></td>
                                                <td><?php echo htmlspecialchars($movement['quantity']); ?></td>
                                                <td>₱<?php echo number_format($movement['unit_cost'], 2); ?></td>
                                                <td>₱<?php echo number_format($total_value, 2); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php elseif ($stock_table_exists): ?>
                        <div class="card mb-6">
                            <div class="card-header">
                                <i class="fas fa-exchange-alt mr-1"></i>
                                Stock Movements
                            </div>
                            <div class="card-body">
                                <p class="text-center">No stock movements found for this project.</p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Subcon Materials Card -->
                        <?php if (!empty($subcon_materials)): ?>
                        <div class="card mb-6">
                            <div class="card-header">
                                <i class="fas fa-truck-loading mr-1"></i>
                                Subcon Materials
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover" id="subconTable">
                                        <thead>
                                            <tr>
                                                <th>Movement Date</th>
                                                <th>Item</th>
                                                <th>Quantity</th>
                                                <th>Unit Cost</th>
                                                <th>Total Value</th>
                                                <th>Subcontractor</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($subcon_materials as $material): 
                                                // Format item display with item code
                                                $item_display = htmlspecialchars($material['item_name'] ?? 'N/A');
                                                if (!empty($material['item_code'])) {
                                                    $item_display .= ' (' . htmlspecialchars($material['item_code']) . ')';
                                                }
                                                
                                                // Calculate total value: quantity * unit_cost
                                                $total_value = $material['quantity'] * $material['unit_cost'];
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars(ocp_date_mdy($material['movement_date'])); ?></td>
                                                <td><?php echo $item_display; ?></td>
                                                <td><?php echo htmlspecialchars($material['quantity']); ?></td>
                                                <td>₱<?php echo number_format($material['unit_cost'], 2); ?></td>
                                                <td>₱<?php echo number_format($total_value, 2); ?></td>
                                                <td><?php echo htmlspecialchars($material['subcon_name'] ?? 'N/A'); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Vehicle and Equipment Rental Card -->
                        <div class="card mb-6">
                            <div class="card-header flex items-center justify-between">
                                <div>
                                    <i class="fas fa-truck mr-1"></i>
                                    Vehicle & Heavy Equipment Rental
                                </div>
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addRentalModal">
                                    <i class="fas fa-plus mr-1"></i> Add Rental
                                </button>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($project_rentals)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover" id="rentalsTable">
                                        <thead>
                                            <tr>
                                                <th>Vehicle/Heavy Equipment</th>
                                                <th>Plate Number</th>
                                                <th>Start Date</th>
                                                <th>End Date</th>
                                                <th>Rate Type</th>
                                                <th>Rate</th>
                                                <th>Total Cost</th>
                                                <th>Notes</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($project_rentals as $rental): 
                                                // Format dates based on rate type
                                                $start_date_display = ($rental['rate_type'] == 'daily') 
                                                    ? date('m-d-Y', strtotime($rental['start_date']))
                                                    : date('m-d-Y g:i A', strtotime($rental['start_date']));
                                                
                                                $end_date_display = ($rental['rate_type'] == 'daily') 
                                                    ? date('m-d-Y', strtotime($rental['end_date']))
                                                    : date('m-d-Y g:i A', strtotime($rental['end_date']));
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($rental['item_name']); ?></td>
                                                <td><?php echo htmlspecialchars($rental['plate_number']); ?></td>
                                                <td><?php echo $start_date_display; ?></td>
                                                <td><?php echo $end_date_display; ?></td>
                                                <td><?php echo htmlspecialchars(ucfirst($rental['rate_type'])); ?></td>
                                                <td>₱<?php echo number_format($rental['rate'], 2); ?></td>
                                                <td>₱<?php echo number_format($rental['total_cost'], 2); ?></td>
                                                <td><?php echo !empty($rental['notes']) ? htmlspecialchars($rental['notes']) : 'N/A'; ?></td>
                                                <td>
                                                    <form method="POST" action="view_project.php" style="display: inline;">
                                                        <input type="hidden" name="remove_rental" value="<?php echo $rental['id']; ?>">
                                                        <input type="hidden" name="id" value="<?php echo $project_id; ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm"
                                                                data-ocp-confirm="Remove this rental from the project?"
                                                                data-ocp-confirm-text="The rental record will be deleted."
                                                                data-ocp-confirm-button="Yes, remove">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-center">No vehicle or equipment rentals for this project yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <form method="POST" action="projects.php" style="display: inline;">
                                <button type="submit" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left mr-1"></i> Back to Projects
                                </button>
                            </form>
                        </div>
                    </div>
                </main>
                <?php include 'includes/footer.php';?>
            </div>
        </div>

        <!-- Add Workers Modal -->
        <div class="modal" id="addWorkersModal" tabindex="-1" aria-labelledby="addWorkersModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addWorkersModalLabel">Add Workers to Project</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="view_project.php">
                        <div class="modal-body">
                            <input type="hidden" name="id" value="<?php echo $project_id; ?>">
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="workerSearch" placeholder="Search workers...">
                                <label for="workerSearch">Search Workers</label>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Select Workers (Only showing Foreman, Skilled, Welder, and Helper positions)</label>
                                <div class="worker-list-container border rounded p-2" style="max-height: 300px; overflow-y: auto;">
                                    <?php if (!empty($eligible_workers)): ?>
                                        <?php foreach ($eligible_workers as $worker): 
                                            // Check if worker is already assigned to this project
                                            $is_assigned = false;
                                            foreach ($project_workers as $project_worker) {
                                                if ($project_worker['id'] == $worker['id']) {
                                                    $is_assigned = true;
                                                    break;
                                                }
                                            }
                                            
                                            if (!$is_assigned): 
                                            $worker_display = $worker['employee_id'] . ' - ' . $worker['firstname'] . ' ' . $worker['lastname'] . ' (' . $worker['position'] . ')';
                                            ?>
                                            <div class="form-floating worker-item mb-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="worker_ids[]" value="<?php echo $worker['id']; ?>" id="worker_<?php echo $worker['id']; ?>">
                                                    <label class="form-check-label" for="worker_<?php echo $worker['id']; ?>">
                                                        <?php echo htmlspecialchars($worker_display); ?>
                                                    </label>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="text-muted">No eligible workers found</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="add_workers" class="btn btn-primary">Add Selected Workers</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Add Rental Modal -->
        <div class="modal" id="addRentalModal" tabindex="-1" aria-labelledby="addRentalModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addRentalModalLabel">Add Vehicle/Equipment Rental</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="view_project.php">
                        <div class="modal-body">
                            <input type="hidden" name="id" value="<?php echo $project_id; ?>">
                            <div class="mb-4">
                                <label for="rental_type" class="form-label">Rental Type</label>
                                <select class="form-select" id="rental_type" name="rental_type" required>
                                    <option value="">Select Type</option>
                                    <option value="vehicle">Vehicle</option>
                                    <option value="equipment">Equipment</option>
                                </select>
                            </div>
                            
                            <div class="mb-4" id="vehicle_select_container" style="display: none;">
                                <label for="vehicle_id" class="form-label">Select Vehicle</label>
                                <select class="form-select" id="vehicle_id" name="item_id">
                                    <option value="">Select Vehicle</option>
                                    <?php foreach ($vehicles as $vehicle): ?>
                                    <option value="<?php echo $vehicle['id']; ?>" data-plate="<?php echo $vehicle['plate_number']; ?>">
                                        <?php echo htmlspecialchars($vehicle['vehicle_name'] . ' (' . $vehicle['plate_number'] . ')'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-4" id="equipment_select_container" style="display: none;">
                                <label for="equipment_id" class="form-label">Select Equipment</label>
                                <select class="form-select" id="equipment_id" name="item_id">
                                    <option value="">Select Equipment</option>
                                    <?php foreach ($equipment as $item): ?>
                                    <option value="<?php echo $item['id']; ?>">
                                        <?php echo htmlspecialchars($item['equipment_name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-4">
                                <label for="rate_type" class="form-label">Rate Type</label>
                                <select class="form-select" id="rate_type" name="rate_type" required>
                                    <option value="daily">Daily</option>
                                    <option value="hourly">Hourly</option>
                                </select>
                            </div>
                            
                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                <div class="min-w-0">
                                    <div class="mb-4">
                                        <label for="start_date" class="form-label">Start Date</label>
                                        <input type="text" class="form-control date-input" id="start_date" name="start_date" required>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="mb-4">
                                        <label for="end_date" class="form-label">End Date</label>
                                        <input type="text" class="form-control date-input" id="end_date" name="end_date" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="rate" class="form-label">Rate (₱)</label>
                                <input type="number" class="form-control" id="rate" name="rate" step="0.01" min="0" required>
                            </div>
                            
                            <div class="mb-4">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                            </div>
                            
                            <div class="mb-4">
                                <div class="alert alert-info">
                                    <strong>Estimated Total Cost: </strong>
                                    <span id="total_cost_display">₱0.00</span>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="add_rental" class="btn btn-primary">Add Rental</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <script src="<?php echo ocp_asset('assets/js/app.js'); ?>"></script>
        <script src="<?php echo ocp_asset('assets/js/view_project.js'); ?>"></script>
    </body>
</html>
