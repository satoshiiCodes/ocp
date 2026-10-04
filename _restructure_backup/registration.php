<?php
    session_start();
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php');
        exit();
    }

    // Database connection
    require_once 'includes/db_config.php';
    
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
    
    // Handle AJAX requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'get_user') {
            // Get user details for editing
            if (isset($_POST['id'])) {
                $user_id = $_POST['id'];
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
                $stmt->bindParam(':id', $user_id);
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    echo json_encode(['success' => true, 'user' => $user]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'User not found']);
                }
            }
            exit();
        }
        
        if ($action === 'add_user') {
            // Add new user
            $errors = [];
            
            // Collect and sanitize input data
            $lastname = htmlspecialchars(trim($_POST['lastname']));
            $firstname = htmlspecialchars(trim($_POST['firstname']));
            $middlename = htmlspecialchars(trim($_POST['middlename']));
            $suffix = htmlspecialchars(trim($_POST['suffix']));
            $department = htmlspecialchars(trim($_POST['department']));
            $position = htmlspecialchars(trim($_POST['position']));
            $address = htmlspecialchars(trim($_POST['address']));
            $contact = htmlspecialchars(trim($_POST['contact']));
            $status = htmlspecialchars(trim($_POST['status']));
            $accounttype = htmlspecialchars(trim($_POST['accounttype']));
            $email = htmlspecialchars(trim($_POST['email']));
            $username = htmlspecialchars(trim($_POST['username']));
            $password = $_POST['password'];
            $confirmpassword = $_POST['confirmpassword'];
            
            // Basic validation
            if (empty($lastname)) $errors[] = "Last name is required.";
            if (empty($firstname)) $errors[] = "First name is required.";
            if (empty($department)) $errors[] = "Department is required.";
            if (empty($position)) $errors[] = "Position is required.";
            if (empty($address)) $errors[] = "Address is required.";
            if (empty($contact)) $errors[] = "Contact number is required.";
            if (empty($status)) $errors[] = "Status is required.";
            if (empty($accounttype)) $errors[] = "Account type is required.";
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
            if (empty($username)) $errors[] = "Username is required.";
            
            if (strlen($password) < 6) {
                $errors[] = "Password must be at least 6 characters.";
            }
            
            if ($password !== $confirmpassword) {
                $errors[] = "Passwords do not match.";
            }
            
            // Check if username or email already exists
            $check_sql = "SELECT id FROM users WHERE username = :username OR email = :email";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->bindParam(':username', $username);
            $check_stmt->bindParam(':email', $email);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                $errors[] = "Username or email already exists. Please choose different ones.";
            }
            
            if (empty($errors)) {
                try {
                    // Hash the password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Prepare and bind
                    $sql = "INSERT INTO users (lastname, firstname, middlename, suffix, department, position, address, contact, status, accounttype, email, username, password) 
                            VALUES (:lastname, :firstname, :middlename, :suffix, :department, :position, :address, :contact, :status, :accounttype, :email, :username, :password)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindParam(':lastname', $lastname);
                    $stmt->bindParam(':firstname', $firstname);
                    $stmt->bindParam(':middlename', $middlename);
                    $stmt->bindParam(':suffix', $suffix);
                    $stmt->bindParam(':department', $department);
                    $stmt->bindParam(':position', $position);
                    $stmt->bindParam(':address', $address);
                    $stmt->bindParam(':contact', $contact);
                    $stmt->bindParam(':status', $status);
                    $stmt->bindParam(':accounttype', $accounttype);
                    $stmt->bindParam(':email', $email);
                    $stmt->bindParam(':username', $username);
                    $stmt->bindParam(':password', $hashed_password);
                    
                    // Execute the statement
                    if ($stmt->execute()) {
                        echo json_encode(['success' => true, 'message' => 'User registered successfully!']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Error: Failed to register user.']);
                    }
                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => implode('<br>', $errors)]);
            }
            exit();
        }
        
        if ($action === 'update_user') {
            // Update user details
            $errors = [];
            
            // Collect and sanitize input data
            $id = $_POST['id'];
            $lastname = htmlspecialchars(trim($_POST['lastname']));
            $firstname = htmlspecialchars(trim($_POST['firstname']));
            $middlename = htmlspecialchars(trim($_POST['middlename']));
            $suffix = htmlspecialchars(trim($_POST['suffix']));
            $department = htmlspecialchars(trim($_POST['department']));
            $position = htmlspecialchars(trim($_POST['position']));
            $address = htmlspecialchars(trim($_POST['address']));
            $contact = htmlspecialchars(trim($_POST['contact']));
            $status = htmlspecialchars(trim($_POST['status']));
            $accounttype = htmlspecialchars(trim($_POST['accounttype']));
            $email = htmlspecialchars(trim($_POST['email']));
            $username = htmlspecialchars(trim($_POST['username']));
            
            // Basic validation
            if (empty($lastname)) $errors[] = "Last name is required.";
            if (empty($firstname)) $errors[] = "First name is required.";
            if (empty($department)) $errors[] = "Department is required.";
            if (empty($position)) $errors[] = "Position is required.";
            if (empty($address)) $errors[] = "Address is required.";
            if (empty($contact)) $errors[] = "Contact number is required.";
            if (empty($status)) $errors[] = "Status is required.";
            if (empty($accounttype)) $errors[] = "Account type is required.";
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
            if (empty($username)) $errors[] = "Username is required.";
            
            // Check if username or email already exists (excluding current user)
            $check_sql = "SELECT id FROM users WHERE (username = :username OR email = :email) AND id != :id";
            $check_stmt = $pdo->prepare($check_sql);
            $check_stmt->bindParam(':username', $username);
            $check_stmt->bindParam(':email', $email);
            $check_stmt->bindParam(':id', $id);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                $errors[] = "Username or email already exists. Please choose different ones.";
            }
            
            // Handle password change if requested
            $password_update = '';
            if (isset($_POST['change_password']) && $_POST['change_password'] == '1') {
                $password = $_POST['password'];
                $confirmpassword = $_POST['confirmpassword'];
                
                if (strlen($password) < 6) {
                    $errors[] = "Password must be at least 6 characters.";
                }
                
                if ($password !== $confirmpassword) {
                    $errors[] = "Passwords do not match.";
                }
                
                if (empty($errors)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $password_update = ", password = :password";
                }
            }
            
            if (empty($errors)) {
                try {
                    // Prepare update query
                    $sql = "UPDATE users SET 
                            lastname = :lastname, 
                            firstname = :firstname, 
                            middlename = :middlename, 
                            suffix = :suffix, 
                            department = :department, 
                            position = :position, 
                            address = :address, 
                            contact = :contact, 
                            status = :status, 
                            accounttype = :accounttype, 
                            email = :email, 
                            username = :username
                            $password_update 
                            WHERE id = :id";
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindParam(':lastname', $lastname);
                    $stmt->bindParam(':firstname', $firstname);
                    $stmt->bindParam(':middlename', $middlename);
                    $stmt->bindParam(':suffix', $suffix);
                    $stmt->bindParam(':department', $department);
                    $stmt->bindParam(':position', $position);
                    $stmt->bindParam(':address', $address);
                    $stmt->bindParam(':contact', $contact);
                    $stmt->bindParam(':status', $status);
                    $stmt->bindParam(':accounttype', $accounttype);
                    $stmt->bindParam(':email', $email);
                    $stmt->bindParam(':username', $username);
                    $stmt->bindParam(':id', $id);
                    
                    if (!empty($password_update)) {
                        $stmt->bindParam(':password', $hashed_password);
                    }
                    
                    if ($stmt->execute()) {
                        echo json_encode(['success' => true, 'message' => 'User updated successfully!']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Error updating user.']);
                    }
                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => implode('<br>', $errors)]);
            }
            exit();
        }
        
        if ($action === 'delete_user') {
            // Delete user
            if (isset($_POST['id'])) {
                $user_id = $_POST['id'];
                
                try {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
                    $stmt->bindParam(':id', $user_id);
                    
                    if ($stmt->execute()) {
                        echo json_encode(['success' => true, 'message' => 'User deleted successfully!']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Error deleting user.']);
                    }
                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                }
            }
            exit();
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="" />
        <meta name="author" content="" />
        <title>User Registration - SB Admin</title>
        <link href="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/style.min.css" rel="stylesheet" />
        <link href="css/styles.css" rel="stylesheet" />
        <script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
        <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
        <style>
            .password-toggle {
                cursor: pointer;
                position: absolute;
                right: 15px;
                top: 50%;
                transform: translateY(-50%);
                z-index: 5;
            }
            .form-floating.position-relative {
                position: relative;
            }
            .action-buttons {
                display: flex;
                gap: 5px;
            }
            .action-btn {
                padding: 0.25rem 0.5rem;
                font-size: 0.875rem;
            }
            .modal-content {
                border-radius: 0.5rem;
            }
            .search-container {
                margin-bottom: 1rem;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .table-responsive {
                max-height: 500px;
                overflow-y: auto;
            }
            .dataTables_wrapper .dataTables_length,
            .dataTables_wrapper .dataTables_filter,
            .dataTables_wrapper .dataTables_info,
            .dataTables_wrapper .dataTables_paginate {
                margin: 10px 0;
                padding: 10px;
            }
        </style>
    </head>
    <body class="sb-nav-fixed">
        <?php include 'includes/top_bar.php';?>
        <div id="layoutSidenav">
            <?php include 'includes/side_menu.php';?>
            <div id="layoutSidenav_content">
                <main>
                    <div class="container-fluid px-4">
                        <h1 class="mt-4">User Registration</h1>
                        <ol class="breadcrumb mb-4">
                            <li class="breadcrumb-item"><a href="index.html">Dashboard</a></li>
                            <li class="breadcrumb-item active">User Registration</li>
                        </ol>
                        
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-table me-1"></i>
                                    Registered Users
                                </div>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                                    <i class="fas fa-user-plus me-1"></i>Add User
                                </button>
                            </div>

                            <div class="card-body">
                                <?php
                                // Fetch users from database
                                $sql = "SELECT id, lastname, firstname, middlename, suffix, department, position, address, contact, status, accounttype, email, username, registration_date FROM users ORDER BY registration_date DESC";
                                $stmt = $pdo->query($sql);
                                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                
                                if (count($users) > 0) {
                                    echo '<div class="table-responsive">
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
                                                <td>" . $row["registration_date"] . "</td>
                                                <td>
                                                    <div class='action-buttons'>
                                                        <button class='btn btn-info btn-sm action-btn view-btn' data-id='" . $row["id"] . "' title='View User'>
                                                            <i class='fas fa-eye'></i>
                                                        </button>
                                                        <button class='btn btn-primary btn-sm action-btn edit-btn' data-id='" . $row["id"] . "' title='Edit User'>
                                                            <i class='fas fa-edit'></i>
                                                        </button>
                                                        <button class='btn btn-danger btn-sm action-btn delete-btn' data-id='" . $row["id"] . "' title='Delete User'>
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
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <div class="form-floating mb-3 mb-md-0">
                                        <input class="form-control" id="addLastName" name="lastname" type="text" placeholder="Enter your last name" required />
                                        <label for="addLastName">Last name</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating mb-3 mb-md-0">
                                        <input class="form-control" id="addFirstName" name="firstname" type="text" placeholder="Enter your first name" required />
                                        <label for="addFirstName">First name</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input class="form-control" id="addMiddleName" name="middlename" type="text" placeholder="Enter your middle name" />
                                        <label for="addMiddleName">Middle name</label>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-floating mb-3 mb-md-0">
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
                                <div class="col-md-6">
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
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-floating mb-3 mb-md-0">
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
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <select class="form-control" id="addPosition" name="position" required>
                                            <option value="">Select Position</option>
                                            <!-- Positions will be populated dynamically based on department selection -->
                                        </select>
                                        <label for="addPosition">Position</label>
                                    </div>
                                </div>
                            </div>
                            <div class="form-floating mb-3">
                                <input class="form-control" id="addAddress" name="address" type="text" placeholder="Enter your complete address" required />
                                <label for="addAddress">Address</label>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-floating mb-3 mb-md-0">
                                        <input class="form-control" id="addContact" name="contact" type="text" placeholder="Enter contact number" required />
                                        <label for="addContact">Contact Number</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input class="form-control" id="addEmail" name="email" type="email" placeholder="name@example.com" required />
                                        <label for="addEmail">Email address</label>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <select class="form-control" id="addAccountType" name="accounttype" required>
                                            <option value="">Select Account Type</option>
                                            <option value="Admin">Admin</option>
                                            <option value="Staff">Staff</option>
                                        </select>
                                        <label for="addAccountType">Account Type</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating mb-3 mb-md-0">
                                        <input class="form-control" id="addUsername" name="username" type="text" placeholder="Create a username" required />
                                        <label for="addUsername">Username</label>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-floating mb-3 mb-md-0 position-relative">
                                        <input class="form-control" id="addPassword" name="password" type="password" placeholder="Create a password" required />
                                        <label for="addPassword">Password</label>
                                        <span class="password-toggle" onclick="togglePassword('addPassword')">
                                            <i class="fas fa-eye"></i>
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating mb-3 mb-md-0 position-relative">
                                        <input class="form-control" id="addPasswordConfirm" name="confirmpassword" type="password" placeholder="Confirm password" required />
                                        <label for="addPasswordConfirm">Confirm Password</label>
                                        <span class="password-toggle" onclick="togglePassword('addPasswordConfirm')">
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
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <div class="form-floating mb-3 mb-md-0">
                                        <input class="form-control" id="editLastName" name="lastname" type="text" placeholder="Enter your last name" required />
                                        <label for="editLastName">Last name</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating mb-3 mb-md-0">
                                        <input class="form-control" id="editFirstName" name="firstname" type="text" placeholder="Enter your first name" required />
                                        <label for="editFirstName">First name</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-floating">
                                        <input class="form-control" id="editMiddleName" name="middlename" type="text" placeholder="Enter your middle name" />
                                        <label for="editMiddleName">Middle name</label>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-floating mb-3 mb-md-0">
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
                                <div class="col-md-6">
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
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-floating mb-3 mb-md-0">
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
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <select class="form-control" id="editPosition" name="position" required>
                                            <option value="">Select Position</option>
                                            <!-- Positions will be populated dynamically based on department selection -->
                                        </select>
                                        <label for="editPosition">Position</label>
                                    </div>
                                </div>
                            </div>
                            <div class="form-floating mb-3">
                                <input class="form-control" id="editAddress" name="address" type="text" placeholder="Enter your complete address" required />
                                <label for="editAddress">Address</label>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-floating mb-3 mb-md-0">
                                        <input class="form-control" id="editContact" name="contact" type="text" placeholder="Enter contact number" required />
                                        <label for="editContact">Contact Number</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <input class="form-control" id="editEmail" name="email" type="email" placeholder="name@example.com" required />
                                        <label for="editEmail">Email address</label>
                                    </div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-floating">
                                        <select class="form-control" id="editAccountType" name="accounttype" required>
                                            <option value="">Select Account Type</option>
                                            <option value="Admin">Admin</option>
                                            <option value="Staff">Staff</option>
                                        </select>
                                        <label for="editAccountType">Account Type</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating mb-3 mb-md-0">
                                        <input class="form-control" id="editUsername" name="username" type="text" placeholder="Create a username" required />
                                        <label for="editUsername">Username</label>
                                    </div>
                                </div>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="changePasswordCheck">
                                <label class="form-check-label" for="changePasswordCheck">
                                    Change Password
                                </label>
                            </div>
                            <div class="row mb-3" id="passwordFields" style="display: none;">
                                <div class="col-md-6">
                                    <div class="form-floating mb-3 mb-md-0 position-relative">
                                        <input class="form-control" id="editPassword" name="password" type="password" placeholder="Create a password" />
                                        <label for="editPassword">New Password</label>
                                        <span class="password-toggle" onclick="togglePassword('editPassword')">
                                            <i class="fas fa-eye"></i>
                                        </span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-floating mb-3 mb-md-0 position-relative">
                                        <input class="form-control" id="editPasswordConfirm" name="confirmpassword" type="password" placeholder="Confirm password" />
                                        <label for="editPasswordConfirm">Confirm New Password</label>
                                        <span class="password-toggle" onclick="togglePassword('editPasswordConfirm')">
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

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        <script src="js/scripts.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/simple-datatables@7.1.2/dist/umd/simple-datatables.min.js" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
        <script>
            // Initialize DataTable
            document.addEventListener('DOMContentLoaded', function() {
                const dataTable = new simpleDatatables.DataTable("#usersTable", {
                    searchable: true,
                    perPage: 10,
                    perPageSelect: [5, 10, 15, 20],
                    labels: {
                        placeholder: "Search users...",
                        searchTitle: "Search within table",
                        pageTitle: "Page {page}",
                        perPage: "entries per page",
                        noRows: "No users found",
                        info: "Showing {start} to {end} of {rows} users",
                        noResults: "No results match your search query"
                    }
                });
            });

            // Function to toggle password visibility
            function togglePassword(inputId) {
                const passwordInput = document.getElementById(inputId);
                const toggleIcon = passwordInput.nextElementSibling.querySelector('i');
                
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    toggleIcon.classList.remove('fa-eye');
                    toggleIcon.classList.add('fa-eye-slash');
                } else {
                    passwordInput.type = 'password';
                    toggleIcon.classList.remove('fa-eye-slash');
                    toggleIcon.classList.add('fa-eye');
                }
            }
            
            // Define positions for each department
            const departmentPositions = {
                'Engineering': ['Operations Manager', 'Project Manager', 'Jr. Project Manager', 'Project Engineer', 'Technical DOC'],
                'Warehouse': ['Maintenance Engineer', 'Assistant Maintenance', 'Warehouse Manager', 'Warehouse Custodian'],
                'Motorpool': ['Motorpool Manager', 'Motorpool Custodian'],
                'Admin': ['CEO', 'Accounting', 'HR Officer', 'Bookkeeper', 'Cashier', 'Purchaser'],
                'Site': ['Site Supervisor'],
                'IT': ['Assistant IT Programmer'],
                'BAC': ['Chairman', 'Vice Chairman', 'Member']
            };
            
            // Function to update positions based on selected department (for add modal)
            function updateAddPositions() {
                const departmentSelect = document.getElementById('addDepartment');
                const positionSelect = document.getElementById('addPosition');
                const selectedDepartment = departmentSelect.value;
                
                // Clear current options
                positionSelect.innerHTML = '<option value="">Select Position</option>';
                
                // Add positions for the selected department
                if (selectedDepartment && departmentPositions[selectedDepartment]) {
                    departmentPositions[selectedDepartment].forEach(position => {
                        const option = document.createElement('option');
                        option.value = position;
                        option.textContent = position;
                        positionSelect.appendChild(option);
                    });
                }
            }
            
            // Function to update positions in edit modal based on selected department
            function updateEditPositions() {
                const departmentSelect = document.getElementById('editDepartment');
                const positionSelect = document.getElementById('editPosition');
                const selectedDepartment = departmentSelect.value;
                
                // Clear current options
                positionSelect.innerHTML = '<option value="">Select Position</option>';
                
                // Add positions for the selected department
                if (selectedDepartment && departmentPositions[selectedDepartment]) {
                    departmentPositions[selectedDepartment].forEach(position => {
                        const option = document.createElement('option');
                        option.value = position;
                        option.textContent = position;
                        positionSelect.appendChild(option);
                    });
                }
            }
            
            // View user functionality
            document.addEventListener('click', function(e) {
                if (e.target.closest('.view-btn')) {
                    const button = e.target.closest('.view-btn');
                    const userId = button.getAttribute('data-id');
                    viewUser(userId);
                }
                
                if (e.target.closest('.edit-btn')) {
                    const button = e.target.closest('.edit-btn');
                    const userId = button.getAttribute('data-id');
                    editUser(userId);
                }
                
                if (e.target.closest('.delete-btn')) {
                    const button = e.target.closest('.delete-btn');
                    const userId = button.getAttribute('data-id');
                    const userName = button.closest('tr').querySelector('td:nth-child(2)').textContent;
                    deleteUserConfirmation(userId, userName);
                }
            });
            
            function viewUser(userId) {
                // In a real application, you would fetch this data from the server
                // For this example, we'll get it from the table row
                const row = document.querySelector(`.view-btn[data-id="${userId}"]`).closest('tr');
                const cells = row.querySelectorAll('td');
                
                const userDetails = `
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>ID:</strong> ${cells[0].textContent}</p>
                            <p><strong>Name:</strong> ${cells[1].textContent}</p>
                            <p><strong>Department:</strong> ${cells[2].textContent}</p>
                            <p><strong>Position:</strong> ${cells[3].textContent}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Email:</strong> ${cells[4].textContent}</p>
                            <p><strong>Username:</strong> ${cells[5].textContent}</p>
                            <p><strong>Status:</strong> ${cells[6].textContent}</p>
                            <p><strong>Registration Date:</strong> ${cells[7].textContent}</p>
                        </div>
                    </div>
                `;
                
                document.getElementById('viewUserDetails').innerHTML = userDetails;
                const viewModal = new bootstrap.Modal(document.getElementById('viewUserModal'));
                viewModal.show();
            }
            
            function editUser(userId) {
                // Use AJAX to fetch user data from the server
                const formData = new FormData();
                formData.append('action', 'get_user');
                formData.append('id', userId);
                
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const user = data.user;
                        
                        // Populate the form with user data
                        document.getElementById('editUserId').value = user.id;
                        document.getElementById('editLastName').value = user.lastname;
                        document.getElementById('editFirstName').value = user.firstname;
                        document.getElementById('editMiddleName').value = user.middlename || '';
                        document.getElementById('editSuffix').value = user.suffix || '';
                        document.getElementById('editDepartment').value = user.department;
                        
                        // Update positions for the department
                        updateEditPositions();
                        setTimeout(() => {
                            document.getElementById('editPosition').value = user.position;
                        }, 100);
                        
                        document.getElementById('editAddress').value = user.address || '';
                        document.getElementById('editContact').value = user.contact || '';
                        document.getElementById('editStatus').value = user.status;
                        document.getElementById('editAccountType').value = user.accounttype;
                        document.getElementById('editEmail').value = user.email;
                        document.getElementById('editUsername').value = user.username;
                        
                        // Reset password fields
                        document.getElementById('changePasswordCheck').checked = false;
                        document.getElementById('passwordFields').style.display = 'none';
                        document.getElementById('editChangePassword').value = '0';
                        document.getElementById('editPassword').value = '';
                        document.getElementById('editPasswordConfirm').value = '';
                        
                        const editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
                        editModal.show();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Error: ' + data.message
                        });
                    }
                })
                .catch(error => {
                    console.error('Error fetching user data:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Error loading user data. Please try again.'
                    });
                });
            }
            
            // Toggle password fields in edit form
            document.getElementById('changePasswordCheck').addEventListener('change', function() {
                const passwordFields = document.getElementById('passwordFields');
                passwordFields.style.display = this.checked ? 'flex' : 'none';
                document.getElementById('editChangePassword').value = this.checked ? '1' : '0';
                
                // Make password fields required if checked
                document.getElementById('editPassword').required = this.checked;
                document.getElementById('editPasswordConfirm').required = this.checked;
            });
            
            // Save user changes
            document.getElementById('saveUserChanges').addEventListener('click', function() {
                // Validate form
                const form = document.getElementById('editUserForm');
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }
                
                // Check if passwords match if changing password
                if (document.getElementById('changePasswordCheck').checked) {
                    const password = document.getElementById('editPassword').value;
                    const confirmPassword = document.getElementById('editPasswordConfirm').value;
                    
                    if (password !== confirmPassword) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Passwords do not match!'
                        });
                        return;
                    }
                    
                    if (password.length < 6) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Password must be at least 6 characters long!'
                        });
                        return;
                    }
                }
                
                // Collect form data
                const formData = new FormData(document.getElementById('editUserForm'));
                formData.append('action', 'update_user');
                
                // Show loading indicator
                Swal.fire({
                    title: 'Updating User',
                    text: 'Please wait...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Send data to server via AJAX
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    Swal.close();
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: data.message,
                            confirmButtonText: 'OK'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Reload the page to see changes
                                location.reload();
                            }
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            html: data.message
                        });
                    }
                })
                .catch(error => {
                    Swal.close();
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Error updating user. Please try again.'
                    });
                });
            });
            
            // Add new user functionality
            document.getElementById('saveNewUser').addEventListener('click', function() {
                // Validate form
                const form = document.getElementById('addUserForm');
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }
                
                // Check if passwords match
                const password = document.getElementById('addPassword').value;
                const confirmPassword = document.getElementById('addPasswordConfirm').value;
                
                if (password !== confirmPassword) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Passwords do not match!'
                    });
                    return;
                }
                
                if (password.length < 6) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Password must be at least 6 characters long!'
                    });
                    return;
                }
                
                // Collect form data
                const formData = new FormData(document.getElementById('addUserForm'));
                formData.append('action', 'add_user');
                
                // Show loading indicator
                Swal.fire({
                    title: 'Adding User',
                    text: 'Please wait...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Send data to server via AJAX
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    Swal.close();
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: data.message,
                            confirmButtonText: 'OK'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Close the modal and reload the page
                                const addModal = bootstrap.Modal.getInstance(document.getElementById('addUserModal'));
                                addModal.hide();
                                location.reload();
                            }
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            html: data.message
                        });
                    }
                })
                .catch(error => {
                    Swal.close();
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Error adding user. Please try again.'
                    });
                });
            });
            
            // Delete user functionality
            function deleteUserConfirmation(userId, userName) {
                Swal.fire({
                    title: 'Are you sure?',
                    text: `You are about to delete user: ${userName}. This action cannot be undone.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        deleteUser(userId);
                    }
                });
            }
            
            function deleteUser(userId) {
                // Send delete request to the server
                const formData = new FormData();
                formData.append('action', 'delete_user');
                formData.append('id', userId);
                
                // Show loading indicator
                Swal.fire({
                    title: 'Deleting User',
                    text: 'Please wait...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    Swal.close();
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Deleted!',
                            text: data.message,
                            confirmButtonText: 'OK'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Reload the page to see changes
                                location.reload();
                            }
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message
                        });
                    }
                })
                .catch(error => {
                    Swal.close();
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Error deleting user. Please try again.'
                    });
                });
            }
            
            // Reset add user form when modal is closed
            document.getElementById('addUserModal').addEventListener('hidden.bs.modal', function () {
                document.getElementById('addUserForm').reset();
                document.getElementById('addPosition').innerHTML = '<option value="">Select Position</option>';
            });
        </script>
    </body>
</html>