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
$swal_data = array(); // For storing SweetAlert data

// Initialize form values for add operation
$add_subcon_name = '';
$add_contact_person = '';
$add_phone = '';
$add_email = '';
$add_address = '';

// All of this page's actions live in one file. The page's forms post back here,
// so it is pulled in before anything is read or rendered.
if (!defined('OCP_SUBCON_NAME_ACTIONS_RAN')) {
    require __DIR__ . '/actions/subcon_name-actions.php';
}

// All of this page's fetching lives in one file: it returns the variables the
// markup below needs, which are unpacked into this scope.
$ocp_endpoint = require __DIR__ . '/api/subcon_name-endpoint.php';
foreach ($ocp_endpoint as $ocp_key => $ocp_value) {
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
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>Subcontractors - OCP Construction</title>
        <link rel="icon" type="image/png" href="assets/images/logo/OCP.png">
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
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
                            <h1 class="page-title">Subcontractors</h1>
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                <li class="breadcrumb-item active">Subcontractors</li>
                            </ol>
                        </div>
                        
                        <!-- Display all subcontractors in a table -->
                        <div class="card mb-6">
                            <div class="card-header flex justify-between items-center">
                                <div>
                                    <i class="fas fa-table mr-1"></i>
                                    All Subcontractors
                                </div>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSubconModal">
                                    <i class="fas fa-plus mr-1"></i> Add Subcontractor
                                </button>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($subcons)): ?>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover" id="datatablesSimple">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Subcontractor Name</th>
                                                <th>Contact Person</th>
                                                <th>Phone</th>
                                                <th>Email</th>
                                                <th>Address</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($subcons as $subcon): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($subcon['id']); ?></td>
                                                <td><?php echo htmlspecialchars($subcon['subcon_name']); ?></td>
                                                <td><?php echo htmlspecialchars($subcon['contact_person']); ?></td>
                                                <td><?php echo htmlspecialchars($subcon['phone']); ?></td>
                                                <td><?php echo htmlspecialchars($subcon['email']); ?></td>
                                                <td><?php echo htmlspecialchars($subcon['address']); ?></td>
                                                <td>
                                                    <div class="inline-flex gap-2" role="group">
                                                        <form method="POST" class="inline">
                                                            <button type="button" class="btn btn-secondary btn-sm view-subcon" 
                                                                    data-bs-toggle="modal" data-bs-target="#viewSubconModal"
                                                                    data-id="<?php echo $subcon['id']; ?>"
                                                                    data-name="<?php echo htmlspecialchars($subcon['subcon_name']); ?>"
                                                                    data-contact="<?php echo htmlspecialchars($subcon['contact_person']); ?>"
                                                                    data-phone="<?php echo htmlspecialchars($subcon['phone']); ?>"
                                                                    data-email="<?php echo htmlspecialchars($subcon['email']); ?>"
                                                                    data-address="<?php echo htmlspecialchars($subcon['address']); ?>">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" class="inline">
                                                            <button type="button" class="btn btn-primary btn-sm edit-subcon" 
                                                                    data-bs-toggle="modal" data-bs-target="#editSubconModal"
                                                                    data-id="<?php echo $subcon['id']; ?>"
                                                                    data-name="<?php echo htmlspecialchars($subcon['subcon_name']); ?>"
                                                                    data-contact="<?php echo htmlspecialchars($subcon['contact_person']); ?>"
                                                                    data-phone="<?php echo htmlspecialchars($subcon['phone']); ?>"
                                                                    data-email="<?php echo htmlspecialchars($subcon['email']); ?>"
                                                                    data-address="<?php echo htmlspecialchars($subcon['address']); ?>">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" class="inline">
                                                            <button type="button" class="btn btn-danger btn-sm delete-subcon" 
                                                                    data-bs-toggle="modal" data-bs-target="#deleteSubconModal"
                                                                    data-id="<?php echo $subcon['id']; ?>"
                                                                    data-name="<?php echo htmlspecialchars($subcon['subcon_name']); ?>">
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
                                <?php else: ?>
                                <p class="text-center">No subcontractors found. Add your first subcontractor using the button above.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </main>
                <?php include 'includes/footer.php';?>
            </div>
        </div>
        
        <!-- Add Subcontractor Modal -->
        <div class="modal fade" id="addSubconModal" tabindex="-1" aria-labelledby="addSubconModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addSubconModalLabel">Add New Subcontractor</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <div class="modal-body">
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="subcon_name" name="subcon_name" 
                                       value="<?php echo htmlspecialchars($add_subcon_name); ?>" 
                                       required maxlength="255" placeholder="Subcontractor Name">
                                <label for="subcon_name">Subcontractor Name <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="contact_person" name="contact_person" 
                                       value="<?php echo htmlspecialchars($add_contact_person); ?>" 
                                       maxlength="255" placeholder="Contact Person">
                                <label for="contact_person">Contact Person</label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($add_phone); ?>" 
                                       maxlength="50" placeholder="Phone">
                                <label for="phone">Phone</label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($add_email); ?>" 
                                       maxlength="255" placeholder="Email">
                                <label for="email">Email</label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <textarea class="form-control" id="address" name="address" 
                                          style="height: 100px" placeholder="Address"><?php echo htmlspecialchars($add_address); ?></textarea>
                                <label for="address">Address</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Add Subcontractor</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- View Subcontractor Modal -->
        <div class="modal fade" id="viewSubconModal" tabindex="-1" aria-labelledby="viewSubconModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewSubconModalLabel">Subcontractor Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-4">
                            <label class="form-label fw-bold">Subcontractor Name:</label>
                            <p id="view-name" class="text-slate-800"></p>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold">Contact Person:</label>
                            <p id="view-contact" class="text-slate-800"></p>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold">Phone:</label>
                            <p id="view-phone" class="text-slate-800"></p>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold">Email:</label>
                            <p id="view-email" class="text-slate-800"></p>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold">Address:</label>
                            <p id="view-address" class="text-slate-800"></p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Subcontractor Modal -->
        <div class="modal fade" id="editSubconModal" tabindex="-1" aria-labelledby="editSubconModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editSubconModalLabel">Edit Subcontractor</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" id="edit_id" name="edit_id" value="">
                        <div class="modal-body">
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="edit_subcon_name" name="subcon_name" 
                                       required maxlength="255" placeholder="Subcontractor Name">
                                <label for="edit_subcon_name">Subcontractor Name <span class="text-danger">*</span></label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="edit_contact_person" name="contact_person" 
                                       maxlength="255" placeholder="Contact Person">
                                <label for="edit_contact_person">Contact Person</label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="text" class="form-control" id="edit_phone" name="phone" 
                                       maxlength="50" placeholder="Phone">
                                <label for="edit_phone">Phone</label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="email" class="form-control" id="edit_email" name="email" 
                                       maxlength="255" placeholder="Email">
                                <label for="edit_email">Email</label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <textarea class="form-control" id="edit_address" name="address" 
                                          style="height: 100px" placeholder="Address"></textarea>
                                <label for="edit_address">Address</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Update Subcontractor</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div class="modal fade" id="deleteSubconModal" tabindex="-1" aria-labelledby="deleteSubconModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteSubconModalLabel">Confirm Delete</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="">
                        <input type="hidden" id="delete_id" name="delete_id" value="">
                        <div class="modal-body">
                            <p>Are you sure you want to delete the subcontractor: <strong id="delete-name"></strong>?</p>
                            <p class="text-danger">This action cannot be undone.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">Delete Subcontractor</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
        <?php
        /* Data island consumed by assets/js/subcon_name.js. */
        $__ocp_data = [];
        /* swalDataTitle = $swal_data['title'] [guarded] */
        if (!empty($swal_data)) {
            $__ocp_data["swalDataTitle"] = $swal_data['title'];
        }
        /* swalDataText = $swal_data['text'] [guarded] */
        if (!empty($swal_data)) {
            $__ocp_data["swalDataText"] = $swal_data['text'];
        }
        /* swalDataIcon = $swal_data['icon'] [guarded] */
        if (!empty($swal_data)) {
            $__ocp_data["swalDataIcon"] = $swal_data['icon'];
        }
        /* pOSTEditId = $_POST["edit_id"] [guarded] */
        if (!empty($swal_data) && $_SERVER['REQUEST_METHOD'] === 'POST' && $swal_data['icon'] === 'error' && isset($_POST['edit_id']) && isset($edit_subcon_name)) {
            $__ocp_data["pOSTEditId"] = $_POST["edit_id"];
        }
        /* editSubconName = $edit_subcon_name [guarded] */
        if (!empty($swal_data) && $_SERVER['REQUEST_METHOD'] === 'POST' && $swal_data['icon'] === 'error' && isset($_POST['edit_id']) && isset($edit_subcon_name)) {
            $__ocp_data["editSubconName"] = $edit_subcon_name;
        }
        /* editContactPerson = $edit_contact_person [guarded] */
        if (!empty($swal_data) && $_SERVER['REQUEST_METHOD'] === 'POST' && $swal_data['icon'] === 'error' && isset($_POST['edit_id']) && isset($edit_subcon_name)) {
            $__ocp_data["editContactPerson"] = $edit_contact_person;
        }
        /* editPhone = $edit_phone [guarded] */
        if (!empty($swal_data) && $_SERVER['REQUEST_METHOD'] === 'POST' && $swal_data['icon'] === 'error' && isset($_POST['edit_id']) && isset($edit_subcon_name)) {
            $__ocp_data["editPhone"] = $edit_phone;
        }
        /* editEmail = $edit_email [guarded] */
        if (!empty($swal_data) && $_SERVER['REQUEST_METHOD'] === 'POST' && $swal_data['icon'] === 'error' && isset($_POST['edit_id']) && isset($edit_subcon_name)) {
            $__ocp_data["editEmail"] = $edit_email;
        }
        /* editAddress = $edit_address [guarded] */
        if (!empty($swal_data) && $_SERVER['REQUEST_METHOD'] === 'POST' && $swal_data['icon'] === 'error' && isset($_POST['edit_id']) && isset($edit_subcon_name)) {
            $__ocp_data["editAddress"] = $edit_address;
        }
        /* Flags for the script. It is a separate request, so it cannot test this
         * page's variables itself; these answer for it. Each is false on an ordinary
         * load, so nothing is shown unless there is something to show. */
        $__ocp_data["hasMessage"] = (!empty($swal_data));
        $__ocp_data["isError"] = (($swal_data['icon'] ?? '') === 'error');
        ocp_page_data("subcon_name", $__ocp_data);
        unset($__ocp_data);
        ?>
        <script src="<?php echo ocp_asset('assets/js/app.js'); ?>"></script>
        <script src="assets/js/subcon_name.js.php"></script>
    </body>
</html>
