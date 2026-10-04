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
// Both are filled in by the actions file when it handles a submission. They start
// empty so the markup below always finds them defined.
$success_message = '';
$error_message = '';

// All of this page's actions live in one file: it makes sure the tables exist
// and adds a project together with its engineers. The form posts back to this page,
// so it is pulled in before anything is read or rendered. On a validation error it
// leaves $error_message and $_POST set, and the markup below repopulates the form.
if (!defined('OCP_PROJECTS_ACTIONS_RAN')) {
    require __DIR__ . '/actions/projects-actions.php';
}

// Check for success message in session
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// All of this page's fetching lives in one file: it returns the variables the
// markup below needs, which are unpacked into this scope. A fetch failure comes
// back as error_message, so it is not swallowed.
$ocp_endpoint = require __DIR__ . '/api/projects-endpoint.php';
foreach ($ocp_endpoint as $ocp_key => $ocp_value) {
    if ($ocp_key === 'error_message' && $ocp_value === '') {
        continue;
    }
    ${$ocp_key} = $ocp_value;
}
unset($ocp_endpoint, $ocp_key, $ocp_value);

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

// Helper function to safely get POST values
function getPostValue($key, $default = '') {
    return isset($_POST[$key]) ? htmlspecialchars($_POST[$key]) : $default;
}

// Helper function to format threshold amount for display
function formatThresholdAmount($amount) {
    if ($amount === null || $amount === '') {
        return '';
    }
    return number_format($amount, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Projects - OCP Construction</title>
        <link rel="icon" type="image/png" href="assets/images/logo/OCP.png">
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <link href="assets/css/app.css" rel="stylesheet" />
        <link href="assets/css/app.build.css" rel="stylesheet" />
        <!-- SweetAlert2 CSS -->
        <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    </head>
    <body class="sb-nav-fixed">
        <?php include 'includes/top_bar.php';?>
        <div id="layoutSidenav">
            <?php include 'includes/side_menu.php';?>
            <div id="layoutSidenav_content" class="sb-content">
                <main>
                    <div class="w-full px-6">
                        <div class="mb-6">
                            <h1 class="page-title">Projects</h1>
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Projects</li>
                            </ol>
                        </div>
                        
                        <!-- Display all projects in a table -->
                        <div class="card mb-6">
                            <div class="card-header flex justify-between items-center">
                                <div>
                                    <i class="fas fa-table mr-1"></i>
                                    All Projects
                                </div>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProjectModal">
                                    <i class="fas fa-plus mr-1"></i> Add Project
                                </button>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($projects)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover" id="datatablesSimple">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Project Name</th>
                                                <th>Project Code</th>
                                                <th>Address</th>
                                                <th>Description</th>
                                                <th>Engineers</th>
                                                <th>Start Date</th>
                                                <th>End Date</th>
                                                <th>Threshold Amount</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($projects as $project): 
                                                $status_class = '';
                                                switch($project['status']) {
                                                    case 'planning': $status_class = 'badge-neutral'; break;
                                                    case 'active': $status_class = 'badge-success'; break;
                                                    case 'completed': $status_class = 'badge-primary'; break;
                                                    case 'on-hold': $status_class = 'badge-warning'; break;
                                                }
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($project['id']); ?></td>
                                                <td><?php echo htmlspecialchars($project['project_name']); ?></td>
                                                <td><?php echo htmlspecialchars($project['project_code']); ?></td>
                                                <td><?php echo htmlspecialchars($project['address']); ?></td>
                                                <td><?php echo htmlspecialchars($project['description']); ?></td>
                                                <td><?php echo !empty($project['engineers']) ? htmlspecialchars($project['engineers']) : 'No engineers assigned'; ?></td>
                                                <td><?php echo htmlspecialchars(ocp_date_mdy($project['start_date'])); ?></td>
                                                <td><?php echo htmlspecialchars(ocp_date_mdy($project['end_date'])); ?></td>
                                                <td class="text-right">
                                                    <?php 
                                                    if ($project['threshold_amount'] !== null) {
                                                        echo '₱' . number_format($project['threshold_amount'], 2);
                                                    } else {
                                                        echo '-';
                                                    }
                                                    ?>
                                                </td>
                                                <td><span class="badge <?php echo $status_class; ?>"><?php echo htmlspecialchars(ucfirst($project['status'])); ?></span></td>
                                                <td>
                                                    <div class="inline-flex gap-2" role="group">
                                                        <form method="POST" action="view_project.php" style="display: inline;">
                                                            <input type="hidden" name="id" value="<?php echo $project['id']; ?>">
                                                            <button type="submit" class="btn btn-sm bg-info-600 text-white" data-bs-toggle="tooltip" title="View">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-center">No projects found. Add your first project using the button above.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </main>
                <?php include 'includes/footer.php';?>
            </div>
        </div>
        
        <!-- Add Project Modal -->
        <div class="modal fade" id="addProjectModal" tabindex="-1" aria-labelledby="addProjectModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addProjectModalLabel">Add New Project</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="projectForm">
                        <div class="modal-body">
                            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                <div class="min-w-0">
                                    <div class="form-floating mb-4">
                                        <input type="text" class="form-control" id="project_name" name="project_name" 
                                               value="<?php echo getPostValue('project_name'); ?>" 
                                               required maxlength="255" placeholder="Project Name">
                                        <label for="project_name">Project Name <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="form-floating mb-4">
                                        <input type="text" class="form-control" id="project_code" name="project_code" 
                                               value="<?php echo getPostValue('project_code'); ?>" 
                                               required maxlength="50" placeholder="Project Code">
                                        <label for="project_code">Project Code <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <textarea class="form-control" id="address" name="address" style="height: 100px" placeholder="Address"><?php echo getPostValue('address'); ?></textarea>
                                <label for="address">Address</label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <textarea class="form-control" id="description" name="description" style="height: 100px" placeholder="Description"><?php echo getPostValue('description'); ?></textarea>
                                <label for="description">Description</label>
                            </div>
                            
                            <!-- Engineers Section -->
                            <div class="mb-4">
                                <label class="form-label">Engineers <span class="text-danger">*</span></label>
                                <div id="engineers-container">
                                    <?php
                                    $engineer_count = 1;
                                    if (isset($_POST['engineers']) && is_array($_POST['engineers']) && count($_POST['engineers']) > 0) {
                                        $engineer_count = count($_POST['engineers']);
                                    }
                                    
                                    for ($i = 0; $i < $engineer_count; $i++):
                                        $selected_engineer = isset($_POST['engineers'][$i]) ? (int)$_POST['engineers'][$i] : '';
                                    ?>
                                    <div class="flex w-full gap-2 mb-2 engineer-field" id="engineer-field-<?php echo $i; ?>">
                                        <div class="form-floating flex-grow-1">
                                            <select class="form-select" name="engineers[]" id="engineer-select-<?php echo $i; ?>" <?php echo $i === 0 ? 'required' : ''; ?>>
                                                <option value="">Select an Engineer</option>
                                                <?php foreach ($engineers as $engineer): 
                                                    $full_name = $engineer['firstname'] . ' ' . $engineer['lastname'];
                                                    if (!empty($engineer['middlename'])) {
                                                        $full_name = $engineer['firstname'] . ' ' . substr($engineer['middlename'], 0, 1) . '. ' . $engineer['lastname'];
                                                    }
                                                    if (!empty($engineer['suffix'])) {
                                                        $full_name .= ' ' . $engineer['suffix'];
                                                    }
                                                ?>
                                                <option value="<?php echo $engineer['id']; ?>" <?php echo ($selected_engineer == $engineer['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($full_name); ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <label for="engineer-select-<?php echo $i; ?>">Engineer <?php echo $i + 1; ?></label>
                                        </div>
                                        <?php if ($i > 0): ?>
                                        <button type="button" class="btn btn-danger remove-engineer" data-field-id="engineer-field-<?php echo $i; ?>">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                    <?php endfor; ?>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="add-engineer">
                                    <i class="fas fa-plus mr-1"></i> Add Another Engineer
                                </button>
                                <div class="form-text">You can add up to 3 engineers. At least one is required.</div>
                            </div>
                            
                            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                <div class="min-w-0">
                                    <div class="form-floating mb-4">
                                        <input type="date" class="form-control" id="start_date" name="start_date" 
                                               value="<?php echo getPostValue('start_date'); ?>" placeholder="Start Date">
                                        <label for="start_date">Start Date</label>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="form-floating mb-4">
                                        <input type="date" class="form-control" id="end_date" name="end_date" 
                                               value="<?php echo getPostValue('end_date'); ?>" placeholder="End Date">
                                        <label for="end_date">End Date</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                                <div class="min-w-0">
                                    <div class="form-floating mb-4">
                                        <input type="text" class="form-control" id="threshold_amount" name="threshold_amount" 
                                               value="<?php echo formatThresholdAmount(getPostValue('threshold_amount')); ?>" 
                                               placeholder="0.00" pattern="^\d{1,3}(,\d{3})*(\.\d{2})?$">
                                        <label for="threshold_amount">Threshold Amount (₱)</label>
                                        <div class="form-text">Enter amount (e.g., 10,000.00)</div>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="form-floating mb-4">
                                        <select class="form-select" id="status" name="status">
                                            <option value="planning" <?php echo (getPostValue('status') == 'planning') ? 'selected' : ''; ?>>Planning</option>
                                            <option value="active" <?php echo (getPostValue('status') == 'active') ? 'selected' : ''; ?>>Active</option>
                                            <option value="on-hold" <?php echo (getPostValue('status') == 'on-hold') ? 'selected' : ''; ?>>On Hold</option>
                                            <option value="completed" <?php echo (getPostValue('status') == 'completed') ? 'selected' : ''; ?>>Completed</option>
                                        </select>
                                        <label for="status">Status</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Add Project</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <!-- SweetAlert2 JS -->
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>
        <?php
        /* Data island consumed by assets/js/projects.js. */
        $__ocp_data = [];
        $__ocp_data["engineerCount"] = $engineer_count;
        /* The engineer options, rendered here and not in the script: the browser
         * fetches the script as its own request, where $engineers is not in scope,
         * so building these options there would come out empty. */
        $__ocp_option_html = '';
        foreach ($engineers as $engineer) {
            $full_name = $engineer['firstname'] . ' ' . $engineer['lastname'];
            if (!empty($engineer['middlename'])) {
                $full_name = $engineer['firstname'] . ' ' . substr($engineer['middlename'], 0, 1) . '. ' . $engineer['lastname'];
            }
            if (!empty($engineer['suffix'])) {
                $full_name .= ' ' . $engineer['suffix'];
            }
            $__ocp_option_html .= '<option value="' . (int) $engineer['id'] . '">'
                . htmlspecialchars($full_name, ENT_QUOTES) . '</option>';
        }
        $__ocp_data["engineerOptionsHtml"] = $__ocp_option_html;
        unset($__ocp_option_html, $engineer, $full_name);
        /* successMessage = $success_message [guarded] */
        if (!empty($success_message)) {
            $__ocp_data["successMessage"] = $success_message;
        }
        /* errorMessage = $error_message [guarded] */
        if (!empty($error_message)) {
            $__ocp_data["errorMessage"] = $error_message;
        }
        ocp_page_data("projects", $__ocp_data);
        unset($__ocp_data);
        ?>
        <script src="<?php echo ocp_asset('assets/js/app.js'); ?>"></script>
        <script src="assets/js/projects.js.php"></script>
    </body>
</html>
