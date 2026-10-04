<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Database connection
require_once 'includes/db_config.php';

// Create projects table if it doesn't exist (updated with threshold_amount)
$createTableSQL = "CREATE TABLE IF NOT EXISTS projects (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    project_name VARCHAR(255) NOT NULL,
    project_code VARCHAR(50) NOT NULL,
    address TEXT,
    description TEXT,
    start_date DATE,
    end_date DATE,
    status ENUM('planning', 'active', 'completed', 'on-hold') DEFAULT 'planning',
    threshold_amount DECIMAL(15,2) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

try {
    $pdo->exec($createTableSQL);
} catch(PDOException $e) {
    die("Error creating table: " . $e->getMessage());
}

// Create project_engineers table if it doesn't exist (fixed version)
$createEngineersTableSQL = "CREATE TABLE IF NOT EXISTS project_engineers (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    project_id INT(11) NOT NULL,
    user_id INT(11) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

try {
    $pdo->exec($createEngineersTableSQL);
} catch(PDOException $e) {
    die("Error creating engineers table: " . $e->getMessage());
}

// Process form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['project_name'])) {
    // Use null coalescing operator to provide default values if keys don't exist
    $project_name = trim($_POST['project_name'] ?? '');
    $project_code = trim($_POST['project_code'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');
    $status = trim($_POST['status'] ?? 'planning');
    $threshold_amount = isset($_POST['threshold_amount']) && $_POST['threshold_amount'] !== '' ? 
                       floatval(str_replace(',', '', $_POST['threshold_amount'])) : NULL;
    
    // Get engineers from form
    $engineers = [];
    if (isset($_POST['engineers']) && is_array($_POST['engineers'])) {
        foreach ($_POST['engineers'] as $engineer_id) {
            if (!empty($engineer_id)) {
                $engineers[] = (int)$engineer_id;
            }
        }
    }
    
    // Basic validation
    if (empty($project_name)) {
        $error_message = 'Project name is required.';
    } elseif (empty($project_code)) {
        $error_message = 'Project code is required.';
    } elseif (count($engineers) === 0) {
        $error_message = 'At least one engineer is required.';
    } else {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Insert new project
            $insertStmt = $pdo->prepare("INSERT INTO projects (project_name, project_code, address, description, start_date, end_date, status, threshold_amount) 
                                       VALUES (:project_name, :project_code, :address, :description, :start_date, :end_date, :status, :threshold_amount)");
            $insertStmt->bindParam(':project_name', $project_name);
            $insertStmt->bindParam(':project_code', $project_code);
            $insertStmt->bindParam(':address', $address);
            $insertStmt->bindParam(':description', $description);
            $insertStmt->bindParam(':start_date', $start_date);
            $insertStmt->bindParam(':end_date', $end_date);
            $insertStmt->bindParam(':status', $status);
            $insertStmt->bindParam(':threshold_amount', $threshold_amount, PDO::PARAM_STR);
            
            if ($insertStmt->execute()) {
                $project_id = $pdo->lastInsertId();
                
                // Insert engineers
                $engineerStmt = $pdo->prepare("INSERT INTO project_engineers (project_id, user_id) VALUES (:project_id, :user_id)");
                
                foreach ($engineers as $user_id) {
                    $engineerStmt->bindParam(':project_id', $project_id);
                    $engineerStmt->bindParam(':user_id', $user_id);
                    $engineerStmt->execute();
                }
                
                // Commit transaction
                $pdo->commit();
                
                $success_message = 'Project added successfully!';
                
                // Store success message in session for display after redirect
                $_SESSION['success_message'] = $success_message;
                
                // Redirect to prevent form resubmission
                header("Location: " . $_SERVER['PHP_SELF']);
                exit();
            } else {
                $pdo->rollBack();
                $error_message = 'Error adding project. Please try again.';
            }
        } catch(PDOException $e) {
            $pdo->rollBack();
            $error_message = 'Database error: ' . $e->getMessage();
        }
    }
}

// Check for success message in session
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Get all projects from the database with their engineers (fixed query)
try {
    $projectsStmt = $pdo->prepare("
        SELECT p.*, GROUP_CONCAT(CONCAT(u.firstname, ' ', u.lastname) SEPARATOR ', ') as engineers 
        FROM projects p 
        LEFT JOIN project_engineers pe ON p.id = pe.project_id 
        LEFT JOIN users u ON pe.user_id = u.id 
        GROUP BY p.id 
        ORDER BY p.created_at DESC
    ");
    $projectsStmt->execute();
    $projects = $projectsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $projects = [];
    $error_message = 'Error fetching projects: ' . $e->getMessage();
}

// Get engineers from the Engineering department
try {
    $engineersStmt = $pdo->prepare("
        SELECT id, firstname, middlename, lastname, suffix 
        FROM users 
        WHERE department = 'Engineering' AND status = 'active' 
        ORDER BY firstname, lastname
    ");
    $engineersStmt->execute();
    $engineers = $engineersStmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $engineers = [];
    $error_message = 'Error fetching engineers: ' . $e->getMessage();
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
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <link href="css/styles.css" rel="stylesheet" />
        <!-- SweetAlert2 CSS -->
        <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
    </head>
    <body class="sb-nav-fixed">
        <?php include 'includes/top_bar.php';?>
        <div id="layoutSidenav">
            <?php include 'includes/side_menu.php';?>
            <div id="layoutSidenav_content">
                <main>
                    <div class="container-fluid px-4">
                        <h1 class="mt-4">Projects</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Projects</li>
                        </ol>
                        
                        <!-- Display all projects in a table -->
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-table me-1"></i>
                                    All Projects
                                </div>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProjectModal">
                                    <i class="fas fa-plus me-1"></i> Add Project
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
                                                    case 'planning': $status_class = 'bg-secondary'; break;
                                                    case 'active': $status_class = 'bg-success'; break;
                                                    case 'completed': $status_class = 'bg-primary'; break;
                                                    case 'on-hold': $status_class = 'bg-warning'; break;
                                                }
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($project['id']); ?></td>
                                                <td><?php echo htmlspecialchars($project['project_name']); ?></td>
                                                <td><?php echo htmlspecialchars($project['project_code']); ?></td>
                                                <td><?php echo htmlspecialchars($project['address']); ?></td>
                                                <td><?php echo htmlspecialchars($project['description']); ?></td>
                                                <td><?php echo !empty($project['engineers']) ? htmlspecialchars($project['engineers']) : 'No engineers assigned'; ?></td>
                                                <td><?php echo htmlspecialchars($project['start_date']); ?></td>
                                                <td><?php echo htmlspecialchars($project['end_date']); ?></td>
                                                <td class="text-end">
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
                                                    <div class="btn-group" role="group">
                                                        <form method="POST" action="view_project.php" style="display: inline;">
                                                            <input type="hidden" name="id" value="<?php echo $project['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="View">
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
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addProjectModalLabel">Add New Project</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="" id="projectForm">
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="text" class="form-control" id="project_name" name="project_name" 
                                               value="<?php echo getPostValue('project_name'); ?>" 
                                               required maxlength="255" placeholder="Project Name">
                                        <label for="project_name">Project Name <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="text" class="form-control" id="project_code" name="project_code" 
                                               value="<?php echo getPostValue('project_code'); ?>" 
                                               required maxlength="50" placeholder="Project Code">
                                        <label for="project_code">Project Code <span class="text-danger">*</span></label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <textarea class="form-control" id="address" name="address" style="height: 100px" placeholder="Address"><?php echo getPostValue('address'); ?></textarea>
                                <label for="address">Address</label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <textarea class="form-control" id="description" name="description" style="height: 100px" placeholder="Description"><?php echo getPostValue('description'); ?></textarea>
                                <label for="description">Description</label>
                            </div>
                            
                            <!-- Engineers Section -->
                            <div class="mb-3">
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
                                    <div class="input-group mb-2 engineer-field" id="engineer-field-<?php echo $i; ?>">
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
                                    <i class="fas fa-plus me-1"></i> Add Another Engineer
                                </button>
                                <div class="form-text">You can add up to 3 engineers. At least one is required.</div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="date" class="form-control" id="start_date" name="start_date" 
                                               value="<?php echo getPostValue('start_date'); ?>" placeholder="Start Date">
                                        <label for="start_date">Start Date</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="date" class="form-control" id="end_date" name="end_date" 
                                               value="<?php echo getPostValue('end_date'); ?>" placeholder="End Date">
                                        <label for="end_date">End Date</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <input type="text" class="form-control" id="threshold_amount" name="threshold_amount" 
                                               value="<?php echo formatThresholdAmount(getPostValue('threshold_amount')); ?>" 
                                               placeholder="0.00" pattern="^\d{1,3}(,\d{3})*(\.\d{2})?$">
                                        <label for="threshold_amount">Threshold Amount (₱)</label>
                                        <div class="form-text">Enter amount (e.g., 10,000.00)</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
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

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <!-- SweetAlert2 JS -->
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>
        <script>
            // Simple sidebar toggle functionality
            document.getElementById('sidebarToggle').addEventListener('click', function() {
                document.body.classList.toggle('sb-sidenav-toggled');
            });
            
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
                let engineerCount = <?php echo $engineer_count; ?>;
                
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
                        
                        <?php foreach ($engineers as $engineer): 
                            $full_name = $engineer['firstname'] . ' ' . $engineer['lastname'];
                            if (!empty($engineer['middlename'])) {
                                $full_name = $engineer['firstname'] . ' ' . substr($engineer['middlename'], 0, 1) . '. ' . $engineer['lastname'];
                            }
                            if (!empty($engineer['suffix'])) {
                                $full_name .= ' ' . $engineer['suffix'];
                            }
                        ?>
                        fieldHtml += `<option value="<?php echo $engineer['id']; ?>"><?php echo htmlspecialchars($full_name); ?></option>`;
                        <?php endforeach; ?>
                        
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
                
                // Show success message using SweetAlert2
                <?php if (!empty($success_message)): ?>
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: '<?php echo addslashes($success_message); ?>',
                    showConfirmButton: true,
                    timer: 3000,
                    timerProgressBar: true,
                    toast: false,
                    position: 'center'
                });
                <?php endif; ?>
                
                // Show error message using SweetAlert2 and reopen modal if there was an error
                <?php if (!empty($error_message)): ?>
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: '<?php echo addslashes($error_message); ?>',
                    showConfirmButton: true,
                    confirmButtonColor: '#d33'
                }).then((result) => {
                    // Reopen modal after error alert
                    const addProjectModal = new bootstrap.Modal(document.getElementById('addProjectModal'));
                    addProjectModal.show();
                });
                <?php endif; ?>
                
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
                
                // Show modal if there was an error with form submission (backup for direct POST)
                <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($error_message)): ?>
                    const addProjectModal = new bootstrap.Modal(document.getElementById('addProjectModal'));
                    addProjectModal.show();
                <?php endif; ?>
            });
        </script>
    </body>
</html>