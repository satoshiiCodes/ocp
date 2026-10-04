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
// Process form submission
$message = '';
$message_type = ''; // success or danger
$swal_data = []; // For storing SweetAlert data

// Initialize variables to avoid undefined errors
$employee_id = $firstname = $middlename = $lastname = $suffix = $address = $contact_number = $birth_date = $marital_status = $position = $hire_date = $daily_wage = $effectivity_date = '';

// Initialize deduction variables
$cash_advance_amount = $cash_advance_from = $cash_advance_to = $cash_advance_notes = '';
$sss_amount = $sss_effectivity = $sss_notes = '';
$pagibig_amount = $pagibig_effectivity = $pagibig_notes = '';
$philhealth_amount = $philhealth_effectivity = $philhealth_notes = '';

// All of this page's actions live in one file: updating an employee's details, wage
// or deductions, deleting one, and the view and edit requests its buttons make. The
// forms post back to this page, so it is pulled in before anything is read or
// rendered - and after the initialisation above, because the handlers assign to
// those variables and the markup below reads them, which is what lets a rejected
// form come back filled in.
if (!defined('OCP_EMPLOYEE_REGISTRATION_ACTIONS_RAN')) {
    require __DIR__ . '/actions/employee_registration-actions.php';
}

// All of this page's fetching lives in one file: it returns the variables the
// markup below needs, which are unpacked into this scope.
$ocp_endpoint = require __DIR__ . '/api/employee_registration-endpoint.php';
foreach ($ocp_endpoint as $ocp_key => $ocp_value) {
    ${$ocp_key} = $ocp_value;
}
unset($ocp_endpoint, $ocp_key, $ocp_value);
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Employee Registration - OCP Construction</title>
        <link rel="icon" type="image/png" href="assets/images/logo/OCP.png">
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                            <h1 class="page-title">Employee Registration</h1>
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Employee Registration</li>
                            </ol>
                        </div>
                        
                        <!-- Employee Table -->
                        <div class="card mb-6">
                            <div class="card-header flex justify-between items-center">
                                <div>
                                    <i class="fas fa-users mr-1"></i>
                                    Employee List
                                </div>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                                    <i class="fas fa-user-plus mr-1"></i> Add Employee
                                </button>
                            </div>
                            <div class="card-body">
                                <?php if (isset($employee_error)): ?>
                                    <div class="alert alert-danger"><?php echo $employee_error; ?></div>
                                <?php elseif (empty($employees)): ?>
                                    <div class="alert alert-info">No employees found.</div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-striped table-bordered" id="employeeTable">
                                            <thead>
                                                <tr>
                                                    <th>Employee ID</th>
                                                    <th>Name</th>
                                                    <th>Position</th>
                                                    <th>Daily Wage</th>
                                                    <th>Deductions</th>
                                                    <th>Status</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($employees as $employee): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($employee['employee_id']); ?></td>
                                                        <td>
                                                            <?php 
                                                                echo htmlspecialchars($employee['lastname']) . ', ' . 
                                                                    htmlspecialchars($employee['firstname']);
                                                                if (!empty($employee['middlename'])) {
                                                                    echo ' ' . substr(htmlspecialchars($employee['middlename']), 0, 1) . '.';
                                                                }
                                                                if (!empty($employee['suffix'])) {
                                                                    echo ' ' . htmlspecialchars($employee['suffix']);
                                                                }
                                                            ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($employee['position']); ?></td>
                                                        <td>₱<?php echo number_format($employee['daily_wage'], 2); ?></td>
                                                        <td>
                                                            <div class="max-w-xs">
                                                                <?php if (!empty($employee['cash_advance_amount'])): ?>
                                                                    <span class="badge badge-info mr-1 mb-1" 
                                                                        title="From: <?php echo $employee['cash_advance_from'] ? date('m-d-Y', strtotime($employee['cash_advance_from'])) : 'N/A'; ?> 
                                                                                To: <?php echo $employee['cash_advance_to'] ? date('m-d-Y', strtotime($employee['cash_advance_to'])) : 'N/A'; ?>">
                                                                        CA: ₱<?php echo number_format($employee['cash_advance_amount'], 2); ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                                
                                                                <?php if (!empty($employee['sss_amount'])): ?>
                                                                    <span class="badge badge-warning mr-1 mb-1" 
                                                                        title="Effectivity: <?php echo $employee['sss_effectivity'] ? date('m-d-Y', strtotime($employee['sss_effectivity'])) : 'N/A'; ?>">
                                                                        SSS: ₱<?php echo number_format($employee['sss_amount'], 2); ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                                
                                                                <?php if (!empty($employee['pagibig_amount'])): ?>
                                                                    <span class="badge badge-success mr-1 mb-1" 
                                                                        title="Effectivity: <?php echo $employee['pagibig_effectivity'] ? date('m-d-Y', strtotime($employee['pagibig_effectivity'])) : 'N/A'; ?>">
                                                                        PAG-IBIG: ₱<?php echo number_format($employee['pagibig_amount'], 2); ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                                
                                                                <?php if (!empty($employee['philhealth_amount'])): ?>
                                                                    <span class="badge badge-danger mr-1 mb-1" 
                                                                        title="Effectivity: <?php echo $employee['philhealth_effectivity'] ? date('m-d-Y', strtotime($employee['philhealth_effectivity'])) : 'N/A'; ?>">
                                                                        PHILHEALTH: ₱<?php echo number_format($employee['philhealth_amount'], 2); ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                                
                                                                <?php if (empty($employee['cash_advance_amount']) && empty($employee['sss_amount']) && empty($employee['pagibig_amount']) && empty($employee['philhealth_amount'])): ?>
                                                                    <span class="text-muted">No deductions</span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="badge <?php echo $employee['status'] == 'active' ? 'badge-success' : 'badge-neutral'; ?>">
                                                                <?php echo ucfirst($employee['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="flex gap-1" role="group" aria-label="Employee actions">
                                                                <!-- Manage Dropdown - Fixed with consistent styling -->
                                                                <div class="relative inline-block">
                                                                    <button type="button" class="btn btn-sm btn-primary rounded" data-bs-toggle="dropdown" aria-expanded="false">
                                                                        <i class="fas fa-cog"></i> Manage
                                                                    </button>
                                                                    <ul class="dropdown-menu app-dropdown">
                                                                        <li>
                                                                            <form method="POST" class="p-0">
                                                                                <input type="hidden" name="employee_id" value="<?php echo $employee['id']; ?>">
                                                                                <input type="hidden" name="edit_wage" value="1">
                                                                                <button type="submit" class="app-dropdown-item">
                                                                                    <i class="fas fa-money-bill-wave"></i> Wage
                                                                                </button>
                                                                            </form>
                                                                        </li>
                                                                        <li>
                                                                            <form method="POST" class="p-0">
                                                                                <input type="hidden" name="employee_id" value="<?php echo $employee['id']; ?>">
                                                                                <input type="hidden" name="view_wage_history" value="1">
                                                                                <button type="submit" class="app-dropdown-item">
                                                                                    <i class="fas fa-chart-line"></i> Wage History
                                                                                </button>
                                                                            </form>
                                                                        </li>
                                                                        <li><hr class="my-1"></li>
                                                                        <li>
                                                                            <form method="POST" class="p-0">
                                                                                <input type="hidden" name="employee_id" value="<?php echo $employee['id']; ?>">
                                                                                <input type="hidden" name="edit_deductions" value="1">
                                                                                <button type="submit" class="app-dropdown-item">
                                                                                    <i class="fas fa-calculator"></i> Deductions
                                                                                </button>
                                                                            </form>
                                                                        </li>
                                                                        <li>
                                                                            <form method="POST" class="p-0">
                                                                                <input type="hidden" name="employee_id" value="<?php echo $employee['id']; ?>">
                                                                                <input type="hidden" name="view_deduction_history" value="1">
                                                                                <button type="submit" class="app-dropdown-item">
                                                                                    <i class="fas fa-history"></i> Deduction History
                                                                                </button>
                                                                            </form>
                                                                        </li>
                                                                    </ul>
                                                                </div>
                                                                
                                                                <!-- View Button -->
                                                                <form method="POST" class="inline">
                                                                    <input type="hidden" name="employee_id" value="<?php echo $employee['id']; ?>">
                                                                    <input type="hidden" name="view_employee" value="1">
                                                                    <button type="submit" class="btn btn-sm bg-info-600 text-white rounded" title="View">
                                                                        <i class="fas fa-eye"></i>
                                                                    </button>
                                                                </form>
                                                                
                                                                <!-- Edit Details Button -->
                                                                <form method="POST" class="inline">
                                                                    <input type="hidden" name="employee_id" value="<?php echo $employee['id']; ?>">
                                                                    <input type="hidden" name="edit_details" value="1">
                                                                    <button type="submit" class="btn btn-sm btn-warning rounded" title="Edit Details">
                                                                        <i class="fas fa-edit"></i>
                                                                    </button>
                                                                </form>
                                                                
                                                                <!-- Delete Button -->
                                                                <form method="POST" class="inline">
                                                                    <input type="hidden" name="employee_id" value="<?php echo $employee['id']; ?>">
                                                                    <input type="hidden" name="delete_employee" value="1">
                                                                    <button type="submit" class="btn btn-sm btn-danger rounded" title="Delete" onclick="return confirmDelete(event)">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </main>
                <?php include 'includes/footer.php';?>
            </div>
        </div>

        <!-- Add Employee Modal -->
        <div class="modal" id="addEmployeeModal" tabindex="-1" aria-labelledby="addEmployeeModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addEmployeeModalLabel">Register New Employee</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <div class="modal-body">
                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="modal_employee_id" name="employee_id" 
                                               value="<?php echo htmlspecialchars($employee_id); ?>" 
                                               required maxlength="50" placeholder="Employee ID">
                                        <label for="modal_employee_id" class="form-label">Employee ID <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <input type="date" class="form-control" id="modal_hire_date" name="hire_date" 
                                               value="<?php echo htmlspecialchars($hire_date); ?>" 
                                               required placeholder="Hire Date">
                                        <label for="modal_hire_date" class="form-label">Hire Date <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="modal_lastname" name="lastname" 
                                               value="<?php echo htmlspecialchars($lastname); ?>" 
                                               required maxlength="100" placeholder="Last Name">
                                        <label for="modal_lastname" class="form-label">Last Name <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="modal_firstname" name="firstname" 
                                               value="<?php echo htmlspecialchars($firstname); ?>" 
                                               required maxlength="100" placeholder="First Name">
                                        <label for="modal_firstname" class="form-label">First Name <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="modal_middlename" name="middlename" 
                                               value="<?php echo htmlspecialchars($middlename); ?>" 
                                               maxlength="100" placeholder="Middle Name">
                                        <label for="modal_middlename" class="form-label">Middle Name</label>
                                    </div>
                                </div>
                                
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <select class="form-select" id="modal_suffix" name="suffix" aria-label="Suffix">
                                            <option value="">Select Suffix</option>
                                            <option value="Jr." <?php echo ($suffix == 'Jr.') ? 'selected' : ''; ?>>Jr.</option>
                                            <option value="Sr." <?php echo ($suffix == 'Sr.') ? 'selected' : ''; ?>>Sr.</option>
                                            <option value="II" <?php echo ($suffix == 'II') ? 'selected' : ''; ?>>II</option>
                                            <option value="III" <?php echo ($suffix == 'III') ? 'selected' : ''; ?>>III</option>
                                            <option value="IV" <?php echo ($suffix == 'IV') ? 'selected' : ''; ?>>IV</option>
                                        </select>
                                        <label for="modal_suffix" class="form-label">Suffix</label>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <select class="form-select" id="modal_marital_status" name="marital_status" aria-label="Marital Status">
                                            <option value="">Select Marital Status</option>
                                            <option value="Single" <?php echo ($marital_status == 'Single') ? 'selected' : ''; ?>>Single</option>
                                            <option value="Married" <?php echo ($marital_status == 'Married') ? 'selected' : ''; ?>>Married</option>
                                            <option value="Divorced" <?php echo ($marital_status == 'Divorced') ? 'selected' : ''; ?>>Divorced</option>
                                            <option value="Widowed" <?php echo ($marital_status == 'Widowed') ? 'selected' : ''; ?>>Widowed</option>
                                        </select>
                                        <label for="modal_marital_status" class="form-label">Marital Status</label>
                                    </div>
                                </div>
                                
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="modal_contact_number" name="contact_number" 
                                               value="<?php echo htmlspecialchars($contact_number); ?>" 
                                               maxlength="20" placeholder="Contact Number">
                                        <label for="modal_contact_number" class="form-label">Contact Number</label>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <input type="date" class="form-control" id="modal_birth_date" name="birth_date" 
                                               value="<?php echo htmlspecialchars($birth_date); ?>" placeholder="Birth Date">
                                        <label for="modal_birth_date" class="form-label">Birth Date</label>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <input type="number" step="0.01" min="0" class="form-control" id="modal_daily_wage" name="daily_wage" 
                                               value="<?php echo htmlspecialchars($daily_wage); ?>" required placeholder="Daily Wage">
                                        <label for="modal_daily_wage" class="form-label">Daily Wage <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <input type="date" class="form-control" id="modal_effectivity_date" name="effectivity_date" 
                                               value="<?php echo !empty($effectivity_date) ? htmlspecialchars($effectivity_date) : date('Y-m-d'); ?>" 
                                               required placeholder="Effectivity Date">
                                        <label for="modal_effectivity_date" class="form-label">Wage Effectivity Date <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                
                                <div class="min-w-0 xl:col-span-2">
                                    <div class="form-floating">
                                        <textarea class="form-control" id="modal_address" name="address" rows="3" placeholder="Address"><?php echo htmlspecialchars($address); ?></textarea>
                                        <label for="modal_address" class="form-label">Address</label>
                                    </div>
                                </div>
                                
                                <div class="min-w-0 xl:col-span-2">
                                    <div class="form-floating">
                                        <select class="form-select" id="modal_position" name="position" required aria-label="Position">
                                            <option value="">Select Position</option>
                                            <option value="Operator" <?php echo ($position == 'Operator') ? 'selected' : ''; ?>>Operator</option>
                                            <option value="Driver" <?php echo ($position == 'Driver') ? 'selected' : ''; ?>>Driver</option>
                                            <option value="Chief Mechanic" <?php echo ($position == 'Chief Mechanic') ? 'selected' : ''; ?>>Chief Mechanic</option>
                                            <option value="Mechanic" <?php echo ($position == 'Mechanic') ? 'selected' : ''; ?>>Mechanic</option>
                                            <option value="Welder" <?php echo ($position == 'Welder') ? 'selected' : ''; ?>>Welder</option>
                                            <option value="Foreman" <?php echo ($position == 'Foreman') ? 'selected' : ''; ?>>Foreman</option>
                                            <option value="Skilled" <?php echo ($position == 'Skilled') ? 'selected' : ''; ?>>Skilled</option>
                                            <option value="Helper" <?php echo ($position == 'Helper') ? 'selected' : ''; ?>>Helper</option>
                                            <option value="Labor" <?php echo ($position == 'Labor') ? 'selected' : ''; ?>>Labor</option>
                                            <option value="Cook | Office Helper" <?php echo ($position == 'Cook | Office Helper') ? 'selected' : ''; ?>>Cook | Office Helper</option>
                                            <option value="Guard" <?php echo ($position == 'Guard') ? 'selected' : ''; ?>>Guard</option>
                                            <option value="Human Resources Officer" <?php echo ($position == 'Human Resources Officer') ? 'selected' : ''; ?>>Human Resources Officer</option>
                                            <option value="Document Controller" <?php echo ($position == 'Document Controller') ? 'selected' : ''; ?>>Document Controller</option>
                                            <option value="Purchasing Officer" <?php echo ($position == 'Purchasing Officer') ? 'selected' : ''; ?>>Purchasing Officer</option>
                                            <option value="Disbursing Officer" <?php echo ($position == 'Disbursing Officer') ? 'selected' : ''; ?>>Disbursing Officer</option>
                                            <option value="Warehouseman" <?php echo ($position == 'Warehouseman') ? 'selected' : ''; ?>>Warehouseman</option>
                                            <option value="Site Engineer" <?php echo ($position == 'Site Engineer') ? 'selected' : ''; ?>>Site Engineer</option>
                                            <option value="Liaison Officer" <?php echo ($position == 'Liaison Officer') ? 'selected' : ''; ?>>Liaison Officer</option>
                                            <option value="Assistant Project Manager" <?php echo ($position == 'Assistant Project Manager') ? 'selected' : ''; ?>>Assistant Project Manager</option>
                                            <option value="Checker" <?php echo ($position == 'Checker') ? 'selected' : ''; ?>>Checker</option>
                                        </select>
                                        <label for="modal_position" class="form-label">Position <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Register Employee</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- View Employee Modal -->
        <?php if (isset($view_employee)): ?>
        <div class="modal is-open" id="viewEmployeeModal" tabindex="-1" aria-labelledby="viewEmployeeModalLabel" aria-hidden="false">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewEmployeeModalLabel">Employee Details</h5>
                        <a href="employee_registration.php" class="btn-close" aria-label="Close"></a>
                    </div>
                    <div class="modal-body">
                        <!-- Removed tabs - only showing personal information -->
                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                            <div class="min-w-0">
                                <div class="mb-4">
                                    <label class="form-label fw-bold">Employee ID</label>
                                    <p class="text-sm text-slate-700"><?php echo htmlspecialchars($view_employee['employee_id']); ?></p>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label fw-bold">Name</label>
                                    <p class="text-sm text-slate-700">
                                        <?php 
                                            echo htmlspecialchars($view_employee['lastname']) . ', ' . 
                                                 htmlspecialchars($view_employee['firstname']);
                                            if (!empty($view_employee['middlename'])) {
                                                echo ' ' . substr(htmlspecialchars($view_employee['middlename']), 0, 1) . '.';
                                            }
                                            if (!empty($view_employee['suffix'])) {
                                                echo ' ' . htmlspecialchars($view_employee['suffix']);
                                            }
                                        ?>
                                    </p>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label fw-bold">Contact Number</label>
                                    <p class="text-sm text-slate-700"><?php echo !empty($view_employee['contact_number']) ? htmlspecialchars($view_employee['contact_number']) : 'N/A'; ?></p>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label fw-bold">Birth Date</label>
                                    <p class="text-sm text-slate-700"><?php echo !empty($view_employee['birth_date']) ? date('m-d-Y', strtotime($view_employee['birth_date'])) : 'N/A'; ?></p>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label fw-bold">Marital Status</label>
                                    <p class="text-sm text-slate-700"><?php echo !empty($view_employee['marital_status']) ? htmlspecialchars($view_employee['marital_status']) : 'N/A'; ?></p>
                                </div>
                            </div>
                            <div class="min-w-0">
                                <div class="mb-4">
                                    <label class="form-label fw-bold">Position</label>
                                    <p class="text-sm text-slate-700"><?php echo htmlspecialchars($view_employee['position']); ?></p>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label fw-bold">Hire Date</label>
                                    <p class="text-sm text-slate-700"><?php echo date('m-d-Y', strtotime($view_employee['hire_date'])); ?></p>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label fw-bold">Daily Wage</label>
                                    <p class="text-sm text-slate-700">₱<?php echo number_format($view_employee['daily_wage'], 2); ?></p>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label fw-bold">Status</label>
                                    <p class="text-sm text-slate-700">
                                        <span class="badge <?php echo $view_employee['status'] == 'active' ? 'badge-success' : 'badge-neutral'; ?>">
                                            <?php echo ucfirst($view_employee['status']); ?>
                                        </span>
                                    </p>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label fw-bold">Address</label>
                                    <p class="text-sm text-slate-700"><?php echo !empty($view_employee['address']) ? htmlspecialchars($view_employee['address']) : 'N/A'; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="employee_registration.php" class="btn btn-secondary">Close</a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Edit Employee Details Modal -->
        <?php if (isset($edit_employee_details)): ?>
        <div class="modal is-open" id="editEmployeeModal" tabindex="-1" aria-labelledby="editEmployeeModalLabel" aria-hidden="false">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editEmployeeModalLabel">Edit Employee Details</h5>
                        <a href="employee_registration.php" class="btn-close" aria-label="Close"></a>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="update_employee_details" value="1">
                        <input type="hidden" name="employee_id" value="<?php echo $edit_employee_details['id']; ?>">
                        <div class="modal-body">
                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="edit_employee_id" name="employee_id_field" 
                                               value="<?php echo htmlspecialchars($edit_employee_details['employee_id']); ?>" 
                                               required maxlength="50" placeholder="Employee ID">
                                        <label for="edit_employee_id" class="form-label">Employee ID <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <input type="date" class="form-control" id="edit_hire_date" name="hire_date" 
                                               value="<?php echo htmlspecialchars($edit_employee_details['hire_date']); ?>" 
                                               required placeholder="Hire Date">
                                        <label for="edit_hire_date" class="form-label">Hire Date <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="edit_lastname" name="lastname" 
                                               value="<?php echo htmlspecialchars($edit_employee_details['lastname']); ?>" 
                                               required maxlength="100" placeholder="Last Name">
                                        <label for="edit_lastname" class="form-label">Last Name <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="edit_firstname" name="firstname" 
                                               value="<?php echo htmlspecialchars($edit_employee_details['firstname']); ?>" 
                                               required maxlength="100" placeholder="First Name">
                                        <label for="edit_firstname" class="form-label">First Name <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="edit_middlename" name="middlename" 
                                               value="<?php echo htmlspecialchars($edit_employee_details['middlename']); ?>" 
                                               maxlength="100" placeholder="Middle Name">
                                        <label for="edit_middlename" class="form-label">Middle Name</label>
                                    </div>
                                </div>
                                
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <select class="form-select" id="edit_suffix" name="suffix" aria-label="Suffix">
                                            <option value="">Select Suffix</option>
                                            <option value="Jr." <?php echo ($edit_employee_details['suffix'] == 'Jr.') ? 'selected' : ''; ?>>Jr.</option>
                                            <option value="Sr." <?php echo ($edit_employee_details['suffix'] == 'Sr.') ? 'selected' : ''; ?>>Sr.</option>
                                            <option value="II" <?php echo ($edit_employee_details['suffix'] == 'II') ? 'selected' : ''; ?>>II</option>
                                            <option value="III" <?php echo ($edit_employee_details['suffix'] == 'III') ? 'selected' : ''; ?>>III</option>
                                            <option value="IV" <?php echo ($edit_employee_details['suffix'] == 'IV') ? 'selected' : ''; ?>>IV</option>
                                        </select>
                                        <label for="edit_suffix" class="form-label">Suffix</label>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <select class="form-select" id="edit_marital_status" name="marital_status" aria-label="Marital Status">
                                            <option value="">Select Marital Status</option>
                                            <option value="Single" <?php echo ($edit_employee_details['marital_status'] == 'Single') ? 'selected' : ''; ?>>Single</option>
                                            <option value="Married" <?php echo ($edit_employee_details['marital_status'] == 'Married') ? 'selected' : ''; ?>>Married</option>
                                            <option value="Divorced" <?php echo ($edit_employee_details['marital_status'] == 'Divorced') ? 'selected' : ''; ?>>Divorced</option>
                                            <option value="Widowed" <?php echo ($edit_employee_details['marital_status'] == 'Widowed') ? 'selected' : ''; ?>>Widowed</option>
                                        </select>
                                        <label for="edit_marital_status" class="form-label">Marital Status</label>
                                    </div>
                                </div>
                                
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <input type="text" class="form-control" id="edit_contact_number" name="contact_number" 
                                               value="<?php echo htmlspecialchars($edit_employee_details['contact_number']); ?>" 
                                               maxlength="20" placeholder="Contact Number">
                                        <label for="edit_contact_number" class="form-label">Contact Number</label>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <input type="date" class="form-control" id="edit_birth_date" name="birth_date" 
                                               value="<?php echo htmlspecialchars($edit_employee_details['birth_date']); ?>" placeholder="Birth Date">
                                        <label for="edit_birth_date" class="form-label">Birth Date</label>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <select class="form-select" id="edit_status" name="status" required aria-label="Status">
                                            <option value="active" <?php echo ($edit_employee_details['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo ($edit_employee_details['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                        </select>
                                        <label for="edit_status" class="form-label">Status</label>
                                    </div>
                                </div>
                                
                                <div class="min-w-0 xl:col-span-2">
                                    <div class="form-floating">
                                        <textarea class="form-control" id="edit_address" name="address" rows="3" placeholder="Address"><?php echo htmlspecialchars($edit_employee_details['address']); ?></textarea>
                                        <label for="edit_address" class="form-label">Address</label>
                                    </div>
                                </div>
                                
                                <div class="min-w-0 xl:col-span-2">
                                    <div class="form-floating">
                                        <select class="form-select" id="edit_position" name="position" required aria-label="Position">
                                            <option value="">Select Position</option>
                                            <option value="Operator" <?php echo ($edit_employee_details['position'] == 'Operator') ? 'selected' : ''; ?>>Operator</option>
                                            <option value="Driver" <?php echo ($edit_employee_details['position'] == 'Driver') ? 'selected' : ''; ?>>Driver</option>
                                            <option value="Chief Mechanic" <?php echo ($edit_employee_details['position'] == 'Chief Mechanic') ? 'selected' : ''; ?>>Chief Mechanic</option>
                                            <option value="Mechanic" <?php echo ($edit_employee_details['position'] == 'Mechanic') ? 'selected' : ''; ?>>Mechanic</option>
                                            <option value="Welder" <?php echo ($edit_employee_details['position'] == 'Welder') ? 'selected' : ''; ?>>Welder</option>
                                            <option value="Foreman" <?php echo ($edit_employee_details['position'] == 'Foreman') ? 'selected' : ''; ?>>Foreman</option>
                                            <option value="Skilled" <?php echo ($edit_employee_details['position'] == 'Skilled') ? 'selected' : ''; ?>>Skilled</option>
                                            <option value="Helper" <?php echo ($edit_employee_details['position'] == 'Helper') ? 'selected' : ''; ?>>Helper</option>
                                            <option value="Labor" <?php echo ($edit_employee_details['position'] == 'Labor') ? 'selected' : ''; ?>>Labor</option>
                                            <option value="Cook | Office Helper" <?php echo ($edit_employee_details['position'] == 'Cook | Office Helper') ? 'selected' : ''; ?>>Cook | Office Helper</option>
                                            <option value="Guard" <?php echo ($edit_employee_details['position'] == 'Guard') ? 'selected' : ''; ?>>Guard</option>
                                            <option value="Human Resources Officer" <?php echo ($edit_employee_details['position'] == 'Human Resources Officer') ? 'selected' : ''; ?>>Human Resources Officer</option>
                                            <option value="Document Controller" <?php echo ($edit_employee_details['position'] == 'Document Controller') ? 'selected' : ''; ?>>Document Controller</option>
                                            <option value="Purchasing Officer" <?php echo ($edit_employee_details['position'] == 'Purchasing Officer') ? 'selected' : ''; ?>>Purchasing Officer</option>
                                            <option value="Disbursing Officer" <?php echo ($edit_employee_details['position'] == 'Disbursing Officer') ? 'selected' : ''; ?>>Disbursing Officer</option>
                                            <option value="Warehouseman" <?php echo ($edit_employee_details['position'] == 'Warehouseman') ? 'selected' : ''; ?>>Warehouseman</option>
                                            <option value="Site Engineer" <?php echo ($edit_employee_details['position'] == 'Site Engineer') ? 'selected' : ''; ?>>Site Engineer</option>
                                            <option value="Liaison Officer" <?php echo ($edit_employee_details['position'] == 'Liaison Officer') ? 'selected' : ''; ?>>Liaison Officer</option>
                                            <option value="Assistant Project Manager" <?php echo ($edit_employee_details['position'] == 'Assistant Project Manager') ? 'selected' : ''; ?>>Assistant Project Manager</option>
                                            <option value="Checker" <?php echo ($edit_employee_details['position'] == 'Checker') ? 'selected' : ''; ?>>Checker</option>
<?php /* The register list is the list. If the employee being edited holds a
                                             * position it does not offer, that one value is added so opening this modal can
                                             * never silently blank a saved position. */ ?>
                                            <?php $__ocp_edit_pos = $edit_employee_details['position'] ?? ''; ?>
                                            <?php if ($__ocp_edit_pos !== '' && !in_array($__ocp_edit_pos, ['Operator', 'Driver', 'Chief Mechanic', 'Mechanic', 'Welder', 'Foreman', 'Skilled', 'Helper', 'Labor', 'Cook | Office Helper', 'Guard', 'Human Resources Officer', 'Document Controller', 'Purchasing Officer', 'Disbursing Officer', 'Warehouseman', 'Site Engineer', 'Liaison Officer', 'Assistant Project Manager', 'Checker'], true)): ?>
                                            <option value="<?php echo htmlspecialchars($__ocp_edit_pos, ENT_QUOTES); ?>" selected><?php echo htmlspecialchars($__ocp_edit_pos); ?></option>
                                            <?php endif; ?>
                                        </select>
                                        <label for="edit_position" class="form-label">Position <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <a href="employee_registration.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Update Details</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Edit Daily Wage Modal -->
        <?php if (isset($edit_employee_wage)): ?>
        <div class="modal is-open" id="editWageModal" tabindex="-1" aria-labelledby="editWageModalLabel" aria-hidden="false">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editWageModalLabel">Daily Wage</h5>
                        <a href="employee_registration.php" class="btn-close" aria-label="Close"></a>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="update_employee_wage" value="1">
                        <input type="hidden" name="employee_id" value="<?php echo $edit_employee_wage['id']; ?>">
                        <div class="modal-body">
                            <div class="mb-4">
                                <label class="form-label"><strong>Employee</strong></label>
                                <p class="text-sm text-slate-700">
                                    <?php 
                                        echo htmlspecialchars($edit_employee_wage['lastname']) . ', ' . 
                                             htmlspecialchars($edit_employee_wage['firstname']);
                                        if (!empty($edit_employee_wage['middlename'])) {
                                            echo ' ' . substr(htmlspecialchars($edit_employee_wage['middlename']), 0, 1) . '.';
                                        }
                                        if (!empty($edit_employee_wage['suffix'])) {
                                            echo ' ' . htmlspecialchars($edit_employee_wage['suffix']);
                                        }
                                    ?>
                                </p>
                            </div>
                            <div class="mb-4">
                                <label class="form-label"><strong>Position</strong></label>
                                <p class="text-sm text-slate-700"><?php echo htmlspecialchars($edit_employee_wage['position']); ?></p>
                            </div>
                            <div class="mb-4">
                                <label class="form-label"><strong>Current Daily Wage</strong></label>
                                <p class="text-sm text-slate-700">₱<?php echo number_format($edit_employee_wage['daily_wage'], 2); ?></p>
                            </div>
                            <div class="form-floating mb-4">
                                <input type="number" step="0.01" min="0" class="form-control" id="edit_daily_wage" name="daily_wage" 
                                       value="<?php echo htmlspecialchars($edit_employee_wage['daily_wage']); ?>" required placeholder="New Daily Wage">
                                <label for="edit_daily_wage" class="form-label">New Daily Wage</label>
                            </div>
                            <div class="form-floating mb-4">
                                <input type="date" class="form-control" id="edit_effectivity_date" name="effectivity_date" 
                                       value="<?php echo date('Y-m-d'); ?>" required placeholder="Effectivity Date">
                                <label for="edit_effectivity_date" class="form-label">Effectivity Date <span class="text-danger">*</span></label>
                            </div>
                            <div class="form-floating mb-4">
                                <textarea class="form-control" id="change_reason" name="change_reason" rows="3" placeholder="Enter reason for wage change"></textarea>
                                <label for="change_reason" class="form-label">Reason for Change</label>
                            </div>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle mr-2"></i>
                                <small>Note: If you edit the wage multiple times today, only the last edit will be saved in the history.</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <a href="employee_registration.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Update Wage</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Edit Deductions Modal -->
        <?php if (isset($edit_employee_deductions)): ?>
        <div class="modal is-open" id="editDeductionsModal" tabindex="-1" aria-labelledby="editDeductionsModalLabel" aria-hidden="false">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editDeductionsModalLabel">Employee Deductions</h5>
                        <a href="employee_registration.php" class="btn-close" aria-label="Close"></a>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" name="update_employee_deductions" value="1">
                        <input type="hidden" name="employee_id" value="<?php echo $edit_employee_deductions['id']; ?>">
                        <div class="modal-body">
                            <div class="mb-4">
                                <h6 class="text-base font-semibold">Employee: 
                                    <?php 
                                        echo htmlspecialchars($edit_employee_deductions['lastname']) . ', ' . 
                                             htmlspecialchars($edit_employee_deductions['firstname']);
                                        if (!empty($edit_employee_deductions['middlename'])) {
                                            echo ' ' . substr(htmlspecialchars($edit_employee_deductions['middlename']), 0, 1) . '.';
                                        }
                                        if (!empty($edit_employee_deductions['suffix'])) {
                                            echo ' ' . htmlspecialchars($edit_employee_deductions['suffix']);
                                        }
                                    ?>
                                </h6>
                                <p class="text-muted">Position: <?php echo htmlspecialchars($edit_employee_deductions['position']); ?></p>
                            </div>
                            
                            <!-- Deduction Type Selector -->
                            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 mb-6">
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <select class="form-select" id="deduction_type_selector" onchange="showDeductionSection(this.value)">
                                            <option value="">-- Select Deduction Type --</option>
                                            <option value="cash_advance">Cash Advance</option>
                                            <option value="sss">SSS Contribution</option>
                                            <option value="pag_ibig">Pag-IBIG Contribution</option>
                                            <option value="philhealth">PhilHealth Contribution</option>
                                        </select>
                                        <label for="deduction_type_selector">Select Deduction Type</label>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-muted mt-2">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        Select a deduction type from the dropdown to add or edit its details.
                                    </p>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 gap-6">
                                <!-- Cash Advance Section -->
                                <div class="min-w-0 border border-slate-200 rounded bg-slate-50 p-4" id="cash_advance_section" style="display: none;">
                                    <h6 class="text-base font-semibold border-b border-slate-200 pb-2 mb-4">Cash Advance Details</h6>
                                    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                        <div class="min-w-0">
                                            <div class="form-floating mb-4">
                                                <input type="number" step="0.01" min="0" class="form-control" id="edit_cash_advance_amount" name="cash_advance_amount" 
                                                       value="<?php echo isset($edit_deductions_data['cash_advance']['amount']) ? $edit_deductions_data['cash_advance']['amount'] : ''; ?>" 
                                                       placeholder="Cash Advance Amount">
                                                <label for="edit_cash_advance_amount">Amount</label>
                                            </div>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="form-floating mb-4">
                                                <input type="date" class="form-control" id="edit_cash_advance_from" name="cash_advance_from" 
                                                       value="<?php echo isset($edit_deductions_data['cash_advance']['from_date']) ? $edit_deductions_data['cash_advance']['from_date'] : ''; ?>" 
                                                       placeholder="From Date">
                                                <label for="edit_cash_advance_from">From Date</label>
                                            </div>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="form-floating mb-4">
                                                <input type="date" class="form-control" id="edit_cash_advance_to" name="cash_advance_to" 
                                                       value="<?php echo isset($edit_deductions_data['cash_advance']['to_date']) ? $edit_deductions_data['cash_advance']['to_date'] : ''; ?>" 
                                                       placeholder="To Date">
                                                <label for="edit_cash_advance_to">To Date</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- SSS Section -->
                                <div class="min-w-0 border border-slate-200 rounded bg-slate-50 p-4" id="sss_section" style="display: none;">
                                    <h6 class="text-base font-semibold border-b border-slate-200 pb-2 mb-4">SSS Contribution Details</h6>
                                    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                        <div class="min-w-0">
                                            <div class="form-floating mb-4">
                                                <input type="number" step="0.01" min="0" class="form-control" id="edit_sss_amount" name="sss_amount" 
                                                       value="<?php echo isset($edit_deductions_data['sss']['amount']) ? $edit_deductions_data['sss']['amount'] : ''; ?>" 
                                                       placeholder="SSS Amount">
                                                <label for="edit_sss_amount">Monthly Contribution</label>
                                            </div>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="form-floating mb-4">
                                                <input type="date" class="form-control" id="edit_sss_effectivity" name="sss_effectivity" 
                                                       value="<?php echo isset($edit_deductions_data['sss']['effectivity_date']) ? $edit_deductions_data['sss']['effectivity_date'] : ''; ?>" 
                                                       placeholder="Effectivity Date">
                                                <label for="edit_sss_effectivity">Effectivity Date</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Pag-IBIG Section -->
                                <div class="min-w-0 border border-slate-200 rounded bg-slate-50 p-4" id="pag_ibig_section" style="display: none;">
                                    <h6 class="text-base font-semibold border-b border-slate-200 pb-2 mb-4">Pag-IBIG Contribution Details</h6>
                                    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                        <div class="min-w-0">
                                            <div class="form-floating mb-4">
                                                <input type="number" step="0.01" min="0" class="form-control" id="edit_pagibig_amount" name="pagibig_amount" 
                                                       value="<?php echo isset($edit_deductions_data['pag_ibig']['amount']) ? $edit_deductions_data['pag_ibig']['amount'] : ''; ?>" 
                                                       placeholder="Pag-IBIG Amount">
                                                <label for="edit_pagibig_amount">Monthly Contribution</label>
                                            </div>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="form-floating mb-4">
                                                <input type="date" class="form-control" id="edit_pagibig_effectivity" name="pagibig_effectivity" 
                                                       value="<?php echo isset($edit_deductions_data['pag_ibig']['effectivity_date']) ? $edit_deductions_data['pag_ibig']['effectivity_date'] : ''; ?>" 
                                                       placeholder="Effectivity Date">
                                                <label for="edit_pagibig_effectivity">Effectivity Date</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- PhilHealth Section -->
                                <div class="min-w-0 border border-slate-200 rounded bg-slate-50 p-4" id="philhealth_section" style="display: none;">
                                    <h6 class="text-base font-semibold border-b border-slate-200 pb-2 mb-4">PhilHealth Contribution Details</h6>
                                    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                                        <div class="min-w-0">
                                            <div class="form-floating mb-4">
                                                <input type="number" step="0.01" min="0" class="form-control" id="edit_philhealth_amount" name="philhealth_amount" 
                                                       value="<?php echo isset($edit_deductions_data['philhealth']['amount']) ? $edit_deductions_data['philhealth']['amount'] : ''; ?>" 
                                                       placeholder="PhilHealth Amount">
                                                <label for="edit_philhealth_amount">Monthly Contribution</label>
                                            </div>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="form-floating mb-4">
                                                <input type="date" class="form-control" id="edit_philhealth_effectivity" name="philhealth_effectivity" 
                                                       value="<?php echo isset($edit_deductions_data['philhealth']['effectivity_date']) ? $edit_deductions_data['philhealth']['effectivity_date'] : ''; ?>" 
                                                       placeholder="Effectivity Date">
                                                <label for="edit_philhealth_effectivity">Effectivity Date</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Common Fields -->
                                <div class="min-w-0 mt-4">
                                    <div class="form-floating mb-4">
                                        <textarea class="form-control" id="edit_change_reason" name="change_reason" rows="2" placeholder="Reason for changes"></textarea>
                                        <label for="edit_change_reason">Reason for Changes (optional)</label>
                                    </div>
                                </div>
                                
                                <!-- Current Deductions Summary -->
                                <div class="min-w-0 mt-4">
                                    <div class="alert alert-info flex-col">
                                        <h6 class="text-base font-semibold">Current Deductions Summary</h6>
                                        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2 w-full">
                                            <div class="min-w-0">
                                                <strong>CA:</strong> 
                                                <?php echo isset($edit_deductions_data['cash_advance']['amount']) ? '₱' . number_format($edit_deductions_data['cash_advance']['amount'], 2) : 'None'; ?>
                                            </div>
                                            <div class="min-w-0">
                                                <strong>SSS:</strong> 
                                                <?php echo isset($edit_deductions_data['sss']['amount']) ? '₱' . number_format($edit_deductions_data['sss']['amount'], 2) : 'None'; ?>
                                            </div>
                                            <div class="min-w-0">
                                                <strong>Pag-IBIG:</strong> 
                                                <?php echo isset($edit_deductions_data['pag_ibig']['amount']) ? '₱' . number_format($edit_deductions_data['pag_ibig']['amount'], 2) : 'None'; ?>
                                            </div>
                                            <div class="min-w-0">
                                                <strong>PhilHealth:</strong> 
                                                <?php echo isset($edit_deductions_data['philhealth']['amount']) ? '₱' . number_format($edit_deductions_data['philhealth']['amount'], 2) : 'None'; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="alert alert-warning mt-4">
                                    <i class="fas fa-info-circle mr-2"></i>
                                    <small>Set amount to 0 to remove the deduction. All deduction changes are tracked with change amounts, percentages, and reasons - same as wage changes.</small>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <a href="employee_registration.php" class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Update Deductions</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php
        /* Data island consumed by assets/js/employee_registration.js.
         * This sits outside every modal's guard on purpose: inside the deductions modal's
         * `if (isset($edit_employee_deductions))` block it was only printed for that one
         * submission, so on an ordinary load - and on every other action - the script found
         * no island at all and none of its messages could be shown. */
        $__ocp_data = [];
        /* swalDataIcon = $swal_data['icon'] [guarded] */
        if (!empty($swal_data)) {
            $__ocp_data["swalDataIcon"] = $swal_data['icon'];
        }
        /* swalDataTitle = $swal_data['title'] [guarded] */
        if (!empty($swal_data)) {
            $__ocp_data["swalDataTitle"] = $swal_data['title'];
        }
        /* swalDataText = $swal_data['text'] [guarded] */
        if (!empty($swal_data)) {
            $__ocp_data["swalDataText"] = $swal_data['text'];
        }
        /* Flags for the script. It is a separate request, so it cannot test this
         * page's variables itself; these answer for it. Each is false on an ordinary
         * load, so nothing is shown unless there is something to show. */
        $__ocp_data["hasMessage"] = (!empty($swal_data));
        $__ocp_data["isError"] = (($swal_data['icon'] ?? '') === 'error');
        $__ocp_data["openEditDeductions"] = (!empty($edit_deductions_data));
        $__ocp_data["deductionKind"] = (!empty($edit_deductions_data['cash_advance']) ? 'cash_advance' : (!empty($edit_deductions_data['sss']) ? 'sss' : (!empty($edit_deductions_data['pag_ibig']) ? 'pag_ibig' : (!empty($edit_deductions_data['philhealth']) ? 'philhealth' : ''))));
        $__ocp_data["postedForm"] = (isset($_POST['edit_deductions']) ? 'edit_deductions' : (isset($_POST['edit_details']) ? 'edit_details' : (isset($_POST['edit_wage']) ? 'edit_wage' : '')));
        $__ocp_data["postedIsUpdate"] = (isset($_POST['update_employee_details']) || isset($_POST['update_employee_wage']) || isset($_POST['update_employee_deductions']) ? 1 : '');
        ocp_page_data("employee_registration", $__ocp_data);
        unset($__ocp_data);
        ?>
        <script src="<?php echo ocp_asset('assets/js/app.js'); ?>"></script>

        <!-- View Wage History Modal. Eight columns of history; the default dialog caps the content
             at 672px while the table needs ~863px, so it scrolled sideways. -->
        <?php if (isset($view_wage_history_employee)): ?>
        <div class="modal is-open" id="viewWageHistoryModal" tabindex="-1" aria-labelledby="viewWageHistoryModalLabel" aria-hidden="false">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewWageHistoryModalLabel">Wage History for 
                            <?php 
                                echo htmlspecialchars($view_wage_history_employee['lastname']) . ', ' . 
                                     htmlspecialchars($view_wage_history_employee['firstname']);
                                if (!empty($view_wage_history_employee['middlename'])) {
                                    echo ' ' . substr(htmlspecialchars($view_wage_history_employee['middlename']), 0, 1) . '.';
                                }
                                if (!empty($view_wage_history_employee['suffix'])) {
                                    echo ' ' . htmlspecialchars($view_wage_history_employee['suffix']);
                                }
                            ?>
                        </h5>
                        <a href="employee_registration.php" class="btn-close" aria-label="Close"></a>
                    </div>
                    <div class="modal-body">
                        <?php
                            try {
                                $historyStmt = $pdo->prepare("
                                    SELECT wh.*, u.firstname as changed_by_firstname, u.lastname as changed_by_lastname
                                    FROM wage_history wh
                                    JOIN users u ON wh.changed_by = u.id
                                    WHERE wh.employee_id = :employee_id
                                    ORDER BY wh.changed_at DESC
                                ");
                                $historyStmt->bindParam(':employee_id', $view_wage_history_employee['id']);
                                $historyStmt->execute();
                                $employee_wage_history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
                            } catch(PDOException $e) {
                                $employee_wage_history = [];
                                $employee_wage_error = "Error fetching wage history: " . $e->getMessage();
                            }
                        ?>
                        
                        <?php if (isset($employee_wage_error)): ?>
                            <div class="alert alert-danger"><?php echo $employee_wage_error; ?></div>
                        <?php elseif (empty($employee_wage_history)): ?>
                            <div class="alert alert-info">No wage history found for this employee.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped" id="employeeWageHistoryTable">
                                    <thead>
                                        <tr>
                                            <th>Date Changed</th>
                                            <th>Effectivity Date</th>
                                            <th>Old Wage</th>
                                            <th>New Wage</th>
                                            <th>Change Amount</th>
                                            <th>Type</th>
                                            <th>Changed By</th>
                                            <th>Reason</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($employee_wage_history as $history): ?>
                                            <tr>
                                                <td><?php echo date('m-d-Y', strtotime($history['changed_at'])); ?></td>
                                                <td><?php echo !empty($history['effectivity_date']) ? date('m-d-Y', strtotime($history['effectivity_date'])) : 'N/A'; ?></td>
                                                <td>₱<?php echo number_format($history['old_wage'], 2); ?></td>
                                                <td>₱<?php echo number_format($history['new_wage'], 2); ?></td>
                                                <td class="<?php echo $history['change_type'] == 'increase' ? 'text-success-600! font-bold' : ($history['change_type'] == 'decrease' ? 'text-danger-600! font-bold' : ''); ?>">
                                                    <?php echo $history['change_type'] == 'increase' ? '+' : ($history['change_type'] == 'decrease' ? '-' : ''); ?>
                                                    ₱<?php echo number_format(abs($history['change_amount']), 2); ?>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $history['change_type'] == 'increase' ? 'badge-success' : ($history['change_type'] == 'decrease' ? 'badge-danger' : 'badge-neutral'); ?>">
                                                        <?php echo ucfirst($history['change_type']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($history['changed_by_firstname'] . ' ' . $history['changed_by_lastname']); ?></td>
                                                <td><?php echo !empty($history['change_reason']) ? htmlspecialchars($history['change_reason']) : 'N/A'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <a href="employee_registration.php" class="btn btn-secondary">Close</a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- View Deduction History Modal. Nine columns; the table needs ~972px, well past the
             default dialog's 672px, so it scrolled sideways. -->
        <?php if (isset($view_deduction_history_employee)): ?>
        <div class="modal is-open" id="viewDeductionHistoryModal" tabindex="-1" aria-labelledby="viewDeductionHistoryModalLabel" aria-hidden="false">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewDeductionHistoryModalLabel">Deduction History for 
                            <?php 
                                echo htmlspecialchars($view_deduction_history_employee['lastname']) . ', ' . 
                                    htmlspecialchars($view_deduction_history_employee['firstname']);
                                if (!empty($view_deduction_history_employee['middlename'])) {
                                    echo ' ' . substr(htmlspecialchars($view_deduction_history_employee['middlename']), 0, 1) . '.';
                                }
                                if (!empty($view_deduction_history_employee['suffix'])) {
                                    echo ' ' . htmlspecialchars($view_deduction_history_employee['suffix']);
                                }
                            ?>
                        </h5>
                        <a href="employee_registration.php" class="btn-close" aria-label="Close"></a>
                    </div>
                    <div class="modal-body">
                        <?php if (empty($view_deduction_history)): ?>
                            <div class="alert alert-info">No deduction history found for this employee.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped" id="viewDeductionHistoryTable">
                                    <thead>
                                        <tr>
                                            <th>Date Changed</th>
                                            <th>Deduction Type</th>
                                            <th>Old Amount</th>
                                            <th>New Amount</th>
                                            <th>Change Amount</th>
                                            <th>Type</th>
                                            <th>Details</th>
                                            <th>Changed By</th>
                                            <th>Reason</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($view_deduction_history as $history): ?>
                                            <tr>
                                                <td><?php echo date('m-d-Y', strtotime($history['changed_at'])); ?></td>
                                                <td>
                                                    <?php
                                                        // Set badge color based on deduction type
                                                        $badge_color = 'badge-neutral'; // default
                                                        $deduction_type_display = '';
                                                        
                                                        switch($history['deduction_type']) {
                                                            case 'cash_advance':
                                                                $badge_color = 'badge-primary';
                                                                $deduction_type_display = 'CASH ADVANCE';
                                                                break;
                                                            case 'sss':
                                                                $badge_color = 'badge-warning';
                                                                $deduction_type_display = 'SSS';
                                                                break;
                                                            case 'pag_ibig':
                                                                $badge_color = 'badge-success';
                                                                $deduction_type_display = 'PAG IBIG';
                                                                break;
                                                            case 'philhealth':
                                                                $badge_color = 'badge-danger';
                                                                $deduction_type_display = 'PHILHEALTH';
                                                                break;
                                                            default:
                                                                $deduction_type_display = strtoupper(str_replace('_', ' ', $history['deduction_type']));
                                                        }
                                                    ?>
                                                    <span class="badge <?php echo $badge_color; ?>">
                                                        <?php echo $deduction_type_display; ?>
                                                    </span>
                                                </td>
                                                <td>₱<?php echo number_format($history['old_amount'], 2); ?></td>
                                                <td>₱<?php echo number_format($history['new_amount'], 2); ?></td>
                                                <td class="<?php echo $history['change_type'] == 'increase' ? 'text-success-600! font-bold' : ($history['change_type'] == 'decrease' ? 'text-danger-600! font-bold' : ''); ?>">
                                                    <?php echo $history['change_type'] == 'increase' ? '+' : ($history['change_type'] == 'decrease' ? '-' : ''); ?>
                                                    ₱<?php echo number_format(abs($history['change_amount']), 2); ?>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $history['change_type'] == 'increase' ? 'badge-success' : ($history['change_type'] == 'decrease' ? 'badge-danger' : 'badge-neutral'); ?>">
                                                        <?php echo ucfirst($history['change_type']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($history['deduction_type'] == 'cash_advance'): ?>
                                                        <?php if (!empty($history['new_from_date']) || !empty($history['new_to_date'])): ?>
                                                            <small>
                                                                From: <?php echo !empty($history['new_from_date']) ? date('m-d-Y', strtotime($history['new_from_date'])) : 'N/A'; ?><br>
                                                                To: <?php echo !empty($history['new_to_date']) ? date('m-d-Y', strtotime($history['new_to_date'])) : 'N/A'; ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <?php if (!empty($history['new_effectivity_date'])): ?>
                                                            <small>Effectivity: <?php echo date('m-d-Y', strtotime($history['new_effectivity_date'])); ?></small>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                    <?php if (!empty($history['new_notes'])): ?>
                                                        <br><small class="text-muted">Notes: <?php echo htmlspecialchars($history['new_notes']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($history['changed_by_firstname'] . ' ' . $history['changed_by_lastname']); ?></td>
                                                <td><?php echo !empty($history['change_reason']) ? htmlspecialchars($history['change_reason']) : 'N/A'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <a href="employee_registration.php" class="btn btn-secondary">Close</a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <script src="assets/js/employee_registration.js.php"></script>
    </body>
</html>
