<?php
// Database connection
require_once '../includes/db_config.php';

// Get form data
$id = $_POST['id'];
$lastname = $_POST['lastname'];
$firstname = $_POST['firstname'];
$middlename = $_POST['middlename'];
$suffix = $_POST['suffix'];
$department = $_POST['department'];
$position = $_POST['position'];
$address = $_POST['address'];
$contact = $_POST['contact'];
$status = $_POST['status'];
$accounttype = $_POST['accounttype'];
$email = $_POST['email'];
$username = $_POST['username'];

// Check if password should be updated
if (isset($_POST['password']) && !empty($_POST['password'])) {
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $sql = "UPDATE users SET lastname=?, firstname=?, middlename=?, suffix=?, department=?, position=?, address=?, contact=?, status=?, accounttype=?, email=?, username=?, password=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssssssssi", $lastname, $firstname, $middlename, $suffix, $department, $position, $address, $contact, $status, $accounttype, $email, $username, $password, $id);
} else {
    $sql = "UPDATE users SET lastname=?, firstname=?, middlename=?, suffix=?, department=?, position=?, address=?, contact=?, status=?, accounttype=?, email=?, username=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssssssssi", $lastname, $firstname, $middlename, $suffix, $department, $position, $address, $contact, $status, $accounttype, $email, $username, $id);
}

// Execute the statement
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $stmt->error]);
}

$stmt->close();
$conn->close();
?>