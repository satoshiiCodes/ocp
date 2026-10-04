<?php
    session_start();
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php');
        exit();
    }

    // Database connection
    require_once 'config/db_config.php';
    
    // All of this page's actions live in one file: it looks a user up for the edit
    // form and adds, updates and deletes users. registration.js posts straight to
    // that file, and this page pulls it in too, so it runs in this scope.
    if (!defined('OCP_REGISTRATION_ACTIONS_RAN')) {
        require __DIR__ . '/actions/registration-actions.php';
    }

    // All of this page's fetching lives in one file: it returns the variables the
    // markup below needs, which are unpacked into this scope. The user list and the
    // edit modal's record are not here - the page's script fetches those from the
    // actions file - so what this supplies is the name the side menu prints.
    $ocp_endpoint = require __DIR__ . '/api/registration-endpoint.php';
    foreach ($ocp_endpoint as $ocp_key => $ocp_value) {
        ${$ocp_key} = $ocp_value;
    }
    unset($ocp_endpoint, $ocp_key, $ocp_value);
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="" />
        <meta name="author" content="" />
        <title>User Registration - OCP Construction</title>
        <link rel="icon" type="image/png" href="assets/images/logo/OCP.png">
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
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
                        <h1 class="page-title">User Registration</h1>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.html">Dashboard</a></li>
                            <li class="breadcrumb-item active">User Registration</li>
                        </ol>
                        </div>
                        
                        <div class="card mb-6">
                            <div class="card-header flex justify-between items-center">
                                <div>
                                    <i class="fas fa-table mr-1"></i>
                                    Registered Users
                                </div>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                    <i class="fas fa-user-plus mr-1"></i>Add User
                                </button>
                            </div>

                            <div class="card-body">
                                <?php
                                // Fetch users from database
                                $sql = "SELECT id, lastname, firstname, middlename, suffix, department, position, address, contact, status, accounttype, email, username, registration_date FROM users ORDER BY registration_date DESC";
                                $stmt = $pdo->query($sql);
                                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                if (count($users) > 0) {
                                    echo '<div class="table-responsive max-h-[500px] overflow-y-auto">
                                            <table class="table table-bordered table-striped" id="usersTable">
                                                <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>Name</th>
                                                        <th>Department</th>
                                                        <th>Position</th>
                                                        <th>Email</th>
                                                        <th>Username</th>
                                                        <th>Status</th>
                                                        <th>Registration Date</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>';
                                    
                                    // Output data of each row
                                    foreach($users as $row) {
                                        $fullname = $row["lastname"] . ", " . $row["firstname"];
                                        if (!empty($row["middlename"])) {
                                            $fullname .= " " . $row["middlename"];
                                        }
                                        if (!empty($row["suffix"])) {
                                            $fullname .= " " . $row["suffix"];
                                        }
                                        
                                        echo "<tr>
                                                <td>" . $row["id"] . "</td>
                                                <td>" . $fullname . "</td>
                                                <td>" . $row["department"] . "</td>
                                                <td>" . $row["position"] . "</td>
                                                <td>" . $row["email"] . "</td>
                                                <td>" . $row["username"] . "</td>
                                                <td>" . ucfirst($row["status"]) . "</td>
                                                <td>" . htmlspecialchars(ocp_datetime_mdy($row["registration_date"])) . "</td>
                                                <td>
                                                    <div class='flex gap-1'>
                                                        <button class='btn bg-info-600 text-white btn-sm view-btn' data-id='" . $row["id"] . "' title='View User'>
                                                            <i class='fas fa-eye'></i>
                                                        </button>
                                                        <button class='btn btn-primary btn-sm edit-btn' data-id='" . $row["id"] . "' title='Edit User'>
                                                            <i class='fas fa-edit'></i>
                                                        </button>
                                                        <button class='btn btn-danger btn-sm delete-btn' data-id='" . $row["id"] . "' title='Delete User'>
                                                            <i class='fas fa-trash'></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>";
                                    }
                                    
                                    echo '</tbody></table></div>';
                                } else {
                                    echo "<p>No registered users found.</p>";
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </main>
                <?php include 'includes/footer.php';?>
            </div>
        </div>

        <!-- Add User Modal -->
        <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="addUserForm">
                            <!-- Row 1: name -->
                            <div class="reg-form-row--4 mb-4">
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <input class="form-control" id="addLastName" name="lastname" type="text" placeholder="Enter your last name" required />
                                        <label for="addLastName">Last name</label>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <input class="form-control" id="addFirstName" name="firstname" type="text" placeholder="Enter your first name" required />
                                        <label for="addFirstName">First name</label>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <input class="form-control" id="addMiddleName" name="middlename" type="text" placeholder="Enter your middle name" />
                                        <label for="addMiddleName">Middle name</label>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <select class="form-control" id="addSuffix" name="suffix">
                                            <option value="">Select Suffix</option>
                                            <option value="Sr.">Sr.</option>
                                            <option value="Jr.">Jr.</option>
                                            <option value="III">III</option>
                                            <option value="IV">IV</option>
                                        </select>
                                        <label for="addSuffix">Suffix</label>
                                    </div>
                                </div>
                            </div>
                            <!-- Row 2: department, position, status -->
                            <div class="reg-form-row--3 mb-4">
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <select class="form-control" id="addDepartment" name="department" required onchange="updateAddPositions()">
                                            <option value="">Select Department</option>
                                            <option value="Engineering">Engineering</option>
                                            <option value="Warehouse">Warehouse</option>
                                            <option value="Motorpool">Motorpool</option>
                                            <option value="Admin">Admin</option>
                                            <option value="Site">Site</option>
                                            <option value="IT">IT</option>
                                            <option value="BAC">BAC</option>
                                        </select>
                                        <label for="addDepartment">Department</label>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <select class="form-control" id="addPosition" name="position" required>
                                            <option value="">Select Position</option>
                                            <!-- Positions will be populated dynamically based on department selection -->
                                        </select>
                                        <label for="addPosition">Position</label>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <select class="form-control" id="addStatus" name="status" required>
                                            <option value="">Select Status</option>
                                            <option value="active">Active</option>
                                            <option value="inactive">Inactive</option>
                                            <option value="on-leave">On Leave</option>
                                        </select>
                                        <label for="addStatus">Status</label>
                                    </div>
                                </div>
                            </div>
                            <!-- Row 3: contact, email -->
                            <div class="reg-form-row--2 mb-4">
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <input class="form-control" id="addContact" name="contact" type="text" placeholder="Enter contact number" required />
                                        <label for="addContact">Contact Number</label>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <input class="form-control" id="addEmail" name="email" type="email" placeholder="name@example.com" required />
                                        <label for="addEmail">Email address</label>
                                    </div>
                                </div>
                            </div>
                            <!-- Row 4: address, on its own line -->
                            <div class="form-floating mb-4">
                                <input class="form-control" id="addAddress" name="address" type="text" placeholder="Enter your complete address" required />
                                <label for="addAddress">Address</label>
                            </div>
                            <!-- Row 5: account type, username -->
                            <div class="reg-form-row--2 mb-4">
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <select class="form-control" id="addAccountType" name="accounttype" required>
                                            <option value="">Select Account Type</option>
                                            <option value="Admin">Admin</option>
                                            <option value="Staff">Staff</option>
                                        </select>
                                        <label for="addAccountType">Account Type</label>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <input class="form-control" id="addUsername" name="username" type="text" placeholder="Create a username" required />
                                        <label for="addUsername">Username</label>
                                    </div>
                                </div>
                            </div>
                            <!-- Row 6: password, confirm password -->
                            <div class="reg-form-row--2 mb-4">
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <input class="form-control" id="addPassword" name="password" type="password" placeholder="Create a password" required />
                                        <label for="addPassword">Password</label>
                                        <span class="cursor-pointer absolute right-4 top-1/2 -translate-y-1/2 z-10" onclick="togglePassword('addPassword')">
                                            <i class="fas fa-eye"></i>
                                        </span>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <input class="form-control" id="addPasswordConfirm" name="confirmpassword" type="password" placeholder="Confirm password" required />
                                        <label for="addPasswordConfirm">Confirm Password</label>
                                        <span class="cursor-pointer absolute right-4 top-1/2 -translate-y-1/2 z-10" onclick="togglePassword('addPasswordConfirm')">
                                            <i class="fas fa-eye"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="saveNewUser">Add User</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- View User Modal -->
        <div class="modal fade" id="viewUserModal" tabindex="-1" aria-labelledby="viewUserModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="viewUserModalLabel">User Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="viewUserDetails">
                        <!-- User details will be loaded here -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit User Modal -->
        <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="editUserForm">
                            <input type="hidden" id="editUserId" name="id">
                            <input type="hidden" id="editChangePassword" name="change_password" value="0">
                            <!-- Row 1: name -->
                            <div class="reg-form-row--4 mb-4">
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <input class="form-control" id="editLastName" name="lastname" type="text" placeholder="Enter your last name" required />
                                        <label for="editLastName">Last name</label>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <input class="form-control" id="editFirstName" name="firstname" type="text" placeholder="Enter your first name" required />
                                        <label for="editFirstName">First name</label>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <input class="form-control" id="editMiddleName" name="middlename" type="text" placeholder="Enter your middle name" />
                                        <label for="editMiddleName">Middle name</label>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <select class="form-control" id="editSuffix" name="suffix">
                                            <option value="">Select Suffix</option>
                                            <option value="Sr.">Sr.</option>
                                            <option value="Jr.">Jr.</option>
                                            <option value="III">III</option>
                                            <option value="IV">IV</option>
                                        </select>
                                        <label for="editSuffix">Suffix</label>
                                    </div>
                                </div>
                            </div>
                            <!-- Row 2: department, position, status -->
                            <div class="reg-form-row--3 mb-4">
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <select class="form-control" id="editDepartment" name="department" required onchange="updateEditPositions()">
                                            <option value="">Select Department</option>
                                            <option value="Engineering">Engineering</option>
                                            <option value="Warehouse">Warehouse</option>
                                            <option value="Motorpool">Motorpool</option>
                                            <option value="Admin">Admin</option>
                                            <option value="Site">Site</option>
                                            <option value="IT">IT</option>
                                            <option value="BAC">BAC</option>
                                        </select>
                                        <label for="editDepartment">Department</label>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <select class="form-control" id="editPosition" name="position" required>
                                            <option value="">Select Position</option>
                                            <!-- Positions will be populated dynamically based on department selection -->
                                        </select>
                                        <label for="editPosition">Position</label>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <select class="form-control" id="editStatus" name="status" required>
                                            <option value="">Select Status</option>
                                            <option value="active">Active</option>
                                            <option value="inactive">Inactive</option>
                                            <option value="on-leave">On Leave</option>
                                        </select>
                                        <label for="editStatus">Status</label>
                                    </div>
                                </div>
                            </div>
                            <!-- Row 3: contact, email -->
                            <div class="reg-form-row--2 mb-4">
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <input class="form-control" id="editContact" name="contact" type="text" placeholder="Enter contact number" required />
                                        <label for="editContact">Contact Number</label>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <input class="form-control" id="editEmail" name="email" type="email" placeholder="name@example.com" required />
                                        <label for="editEmail">Email address</label>
                                    </div>
                                </div>
                            </div>
                            <!-- Row 4: address, on its own line -->
                            <div class="form-floating mb-4">
                                <input class="form-control" id="editAddress" name="address" type="text" placeholder="Enter your complete address" required />
                                <label for="editAddress">Address</label>
                            </div>
                            <!-- Row 5: account type, username -->
                            <div class="reg-form-row--2 mb-4">
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <select class="form-control" id="editAccountType" name="accounttype" required>
                                            <option value="">Select Account Type</option>
                                            <option value="Admin">Admin</option>
                                            <option value="Staff">Staff</option>
                                        </select>
                                        <label for="editAccountType">Account Type</label>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <input class="form-control" id="editUsername" name="username" type="text" placeholder="Create a username" required />
                                        <label for="editUsername">Username</label>
                                    </div>
                                </div>
                            </div>
                            <!-- Row 6: password, confirm password -->
                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" id="changePasswordCheck">
                                <label class="form-check-label" for="changePasswordCheck">
                                    Change Password
                                </label>
                            </div>
                            <div class="reg-form-row--2 mb-4" id="passwordFields" style="display: none;">
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <input class="form-control" id="editPassword" name="password" type="password" placeholder="Create a password" />
                                        <label for="editPassword">New Password</label>
                                        <span class="cursor-pointer absolute right-4 top-1/2 -translate-y-1/2 z-10" onclick="togglePassword('editPassword')">
                                            <i class="fas fa-eye"></i>
                                        </span>
                                    </div>
                                </div>
                                <div class="min-w-0">
                                    <div class="form-floating">
                                        <input class="form-control" id="editPasswordConfirm" name="confirmpassword" type="password" placeholder="Confirm password" />
                                        <label for="editPasswordConfirm">Confirm New Password</label>
                                        <span class="cursor-pointer absolute right-4 top-1/2 -translate-y-1/2 z-10" onclick="togglePassword('editPasswordConfirm')">
                                            <i class="fas fa-eye"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="saveUserChanges">Save Changes</button>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
        <script src="<?php echo ocp_asset('assets/js/app.js'); ?>"></script>
        <script src="<?php echo ocp_asset('assets/js/registration.js'); ?>"></script>
    </body>
</html>
