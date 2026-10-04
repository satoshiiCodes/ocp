<?php
/**
 * actions/registration-actions.php
 *
 * Every action for registration.php lives in this one file: looking a user up for
 * the edit form, and adding, updating and deleting users. All four answer JSON,
 * because the page's JavaScript reads the response.
 *
 * registration.js posts straight to this file, so the bootstrap below opens the
 * session and the database handle when they are not already open. The page also
 * pulls this file in, in which case they are already there.
 *
 * The handler bodies below are lifted verbatim from registration.php: the queries,
 * the validation messages and the JSON shape are unchanged.
 */

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isset($_POST['action'])) {
    return;
}
if (defined('OCP_REGISTRATION_ACTIONS_RAN')) {
    return;
}
define('OCP_REGISTRATION_ACTIONS_RAN', true);

// Works both ways: pulled in by the page, or posted to directly by the page's JS.
$ocp_dir = __DIR__;
for ($ocp_i = 0; $ocp_i < 4 && !is_file($ocp_dir . '/config/db_config.php'); $ocp_i++) {
    $ocp_parent = dirname($ocp_dir);
    if ($ocp_parent === $ocp_dir) {
        break;
    }
    $ocp_dir = $ocp_parent;
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($pdo)) {
    require_once $ocp_dir . '/config/db_config.php';
}
unset($ocp_dir, $ocp_i, $ocp_parent);

if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$action = $_POST['action'] ?? '';

// -------------------------------------------------------------------- get_user
        if ($action === 'get_user') {
            // Get user details for editing
            if (isset($_POST['id'])) {
                $user_id = $_POST['id'] ?? 0;
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

// -------------------------------------------------------------------- add_user
        if ($action === 'add_user') {
            // Add new user
            $errors = [];
            
            // Collect and sanitize input data
            $lastname = htmlspecialchars(trim($_POST['lastname'] ?? ''));
            $firstname = htmlspecialchars(trim($_POST['firstname'] ?? ''));
            $middlename = htmlspecialchars(trim($_POST['middlename'] ?? ''));
            $suffix = htmlspecialchars(trim($_POST['suffix'] ?? ''));
            $department = htmlspecialchars(trim($_POST['department'] ?? ''));
            $position = htmlspecialchars(trim($_POST['position'] ?? ''));
            $address = htmlspecialchars(trim($_POST['address'] ?? ''));
            $contact = htmlspecialchars(trim($_POST['contact'] ?? ''));
            $status = htmlspecialchars(trim($_POST['status'] ?? ''));
            $accounttype = htmlspecialchars(trim($_POST['accounttype'] ?? ''));
            $email = htmlspecialchars(trim($_POST['email'] ?? ''));
            $username = htmlspecialchars(trim($_POST['username'] ?? ''));
            $password = $_POST['password'] ?? '';
            $confirmpassword = $_POST['confirmpassword'] ?? '';
            
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

// -------------------------------------------------------------------- update_user
        if ($action === 'update_user') {
            // Update user details
            $errors = [];
            
            // Collect and sanitize input data
            $id = $_POST['id'] ?? 0;
            $lastname = htmlspecialchars(trim($_POST['lastname'] ?? ''));
            $firstname = htmlspecialchars(trim($_POST['firstname'] ?? ''));
            $middlename = htmlspecialchars(trim($_POST['middlename'] ?? ''));
            $suffix = htmlspecialchars(trim($_POST['suffix'] ?? ''));
            $department = htmlspecialchars(trim($_POST['department'] ?? ''));
            $position = htmlspecialchars(trim($_POST['position'] ?? ''));
            $address = htmlspecialchars(trim($_POST['address'] ?? ''));
            $contact = htmlspecialchars(trim($_POST['contact'] ?? ''));
            $status = htmlspecialchars(trim($_POST['status'] ?? ''));
            $accounttype = htmlspecialchars(trim($_POST['accounttype'] ?? ''));
            $email = htmlspecialchars(trim($_POST['email'] ?? ''));
            $username = htmlspecialchars(trim($_POST['username'] ?? ''));
            
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
                $password = $_POST['password'] ?? '';
                $confirmpassword = $_POST['confirmpassword'] ?? '';
                
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

// -------------------------------------------------------------------- delete_user
        if ($action === 'delete_user') {
            // Delete user
            if (isset($_POST['id'])) {
                $user_id = $_POST['id'] ?? 0;
                
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

