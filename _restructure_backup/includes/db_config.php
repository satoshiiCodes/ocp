
<?php

    // Database configuration
   $host = 'localhost';
    $dbname = 'db_skyline';
    $username = 'root';
    $password = '';

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch(PDOException $e) {
        $response = [
            'success' => false,
            'message' => 'Database connection failed: ' . $e->getMessage()
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
?>