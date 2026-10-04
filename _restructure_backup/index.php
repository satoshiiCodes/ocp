<?php
session_start();
// Database connection
require_once 'includes/db_config.php';

// Process login form
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username_input = trim($_POST['username']);
    $password_input = $_POST['password'];
    $remember_me = isset($_POST['rememberMe']);
    
    if (!empty($username_input) && !empty($password_input)) {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT id, lastname, firstname, password, accounttype, status FROM users WHERE username = :username");
        $stmt->bindParam(':username', $username_input);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verify password
            if (password_verify($password_input, $user['password'])) {
                // Check if account is active
                if ($user['status'] === 'active') {
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['fullname'] = $user['firstname'] . ' ' . $user['lastname'];
                    $_SESSION['account_type'] = $user['accounttype'];
                    
                    // Update last login
                    $update_stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
                    $update_stmt->bindParam(':id', $user['id']);
                    $update_stmt->execute();
                    
                    // Set remember me cookie if checked
                    if ($remember_me) {
                        $cookie_value = $user['id'] . ':' . hash('sha256', $user['password']);
                        setcookie('remember_me', $cookie_value, time() + (30 * 24 * 60 * 60), '/');
                    }
                    
                    // Redirect based on account type
                    if ($user['accounttype'] === 'Admin') {
                        header('Location: dashboard.php');
                    } else {
                        header('Location: staff_dashboard.php');
                    }
                    exit();
                } else {
                    $error = 'Your account is not active. Please contact administrator.';
                }
            } else {
                $error = 'Invalid username or password.';
            }
        } else {
            $error = 'Invalid username or password.';
        }
    } else {
        $error = 'Please enter both username and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <link rel="apple-touch-icon" sizes="76x76" href="assets/img/apple-icon.png">
  <link rel="icon" type="image/png" href="img/logo/OCP.png">
  <title>
    OCP System
  </title>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Font Awesome Icons -->
  <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary-color: #2c3e50;
      --accent-color: #3498db;
      --light-color: #ecf0f1;
      --dark-color: #1a2530;
      --success-color: #2ecc71;
      --error-color: #e74c3c;
      --glass-bg: rgba(255, 255, 255, 0.1);
      --glass-border: rgba(255, 255, 255, 0.2);
      --glass-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    }
    
    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #1a2a3a, #2c3e50);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
      position: relative;
      overflow-x: hidden;
    }
    
    body::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: url('img/background/back.jpg') no-repeat center center;
      background-size: cover;
      filter: brightness(0.4);
      z-index: -1;
    }
    
    .login-container {
      width: 100%;
      max-width: 420px;
      margin: 0 auto;
    }
    
    .glass-card {
      background: var(--glass-bg);
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
      border: 1px solid var(--glass-border);
      border-radius: 16px;
      box-shadow: var(--glass-shadow);
      overflow: hidden;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .glass-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
    }
    
    .card-header {
      background: rgba(255, 255, 255, 0.15);
      border-bottom: 1px solid var(--glass-border);
      padding: 25px 20px;
    }
    
    .logo-container {
      display: flex;
      justify-content: center;
      align-items: center;
    }
    
    .logo-img {
      max-width: 150px;
      height: auto;
    }
    
    .card-body {
      padding: 30px;
    }
    
    .form-control {
      background: rgba(255, 255, 255, 0.1);
      border: 1px solid rgba(255, 255, 255, 0.2);
      border-radius: 8px;
      color: var(--light-color);
      padding: 12px 15px;
      transition: all 0.3s ease;
    }
    
    .form-control:focus {
      background: rgba(255, 255, 255, 0.15);
      border-color: rgba(255, 255, 255, 0.4);
      box-shadow: 0 0 0 0.2rem rgba(255, 255, 255, 0.1);
      color: var(--light-color);
    }
    
    .form-control::placeholder {
      color: rgba(255, 255, 255, 0.6);
    }
    
    .form-label {
      color: var(--light-color);
      margin-bottom: 8px;
      font-weight: 500;
    }
    
    .btn-login {
      background: linear-gradient(135deg, var(--accent-color), #2980b9);
      border: none;
      border-radius: 8px;
      color: white;
      font-weight: 600;
      padding: 12px;
      transition: all 0.3s ease;
      box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
    }
    
    .btn-login:hover {
      background: linear-gradient(135deg, #2980b9, var(--accent-color));
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
    }
    
    .form-check-input:checked {
      background-color: var(--accent-color);
      border-color: var(--accent-color);
    }
    
    .form-check-label {
      color: var(--light-color);
    }
    
    .error-message {
      background: rgba(231, 76, 60, 0.2);
      border: 1px solid rgba(231, 76, 60, 0.4);
      border-radius: 8px;
      color: #ff9e9e;
      font-size: 14px;
      padding: 12px;
      margin-bottom: 20px;
      text-align: center;
    }
    
    .success-message {
      background: rgba(46, 204, 113, 0.2);
      border: 1px solid rgba(46, 204, 113, 0.4);
      border-radius: 8px;
      color: #a3ffc6;
      font-size: 14px;
      padding: 12px;
      margin-bottom: 20px;
      text-align: center;
    }
    
    .copyright {
      text-align: center;
      color: rgba(255, 255, 255, 0.7);
      margin-top: 30px;
      font-size: 14px;
    }
    
    .floating-elements {
      position: absolute;
      width: 100%;
      height: 100%;
      top: 0;
      left: 0;
      overflow: hidden;
      z-index: -1;
    }
    
    .floating-element {
      position: absolute;
      background: rgba(255, 255, 255, 0.05);
      border-radius: 50%;
      animation: float 15s infinite linear;
    }
    
    @keyframes float {
      0% {
        transform: translateY(0) rotate(0deg);
        opacity: 1;
      }
      100% {
        transform: translateY(-1000px) rotate(720deg);
        opacity: 0;
      }
    }
    
    /* Responsive adjustments */
    @media (max-width: 576px) {
      .card-body {
        padding: 25px 20px;
      }
      
      .logo-img {
        max-width: 150px;
      }
    }
  </style>
</head>

<body>
  <!-- Floating background elements -->
  <div class="floating-elements">
    <div class="floating-element" style="width: 80px; height: 80px; top: 10%; left: 10%; animation-delay: 0s;"></div>
    <div class="floating-element" style="width: 60px; height: 60px; top: 20%; left: 80%; animation-delay: 2s;"></div>
    <div class="floating-element" style="width: 100px; height: 100px; top: 60%; left: 5%; animation-delay: 4s;"></div>
    <div class="floating-element" style="width: 70px; height: 70px; top: 70%; left: 70%; animation-delay: 6s;"></div>
    <div class="floating-element" style="width: 90px; height: 90px; top: 30%; left: 40%; animation-delay: 8s;"></div>
  </div>
  
  <div class="login-container">
    <div class="glass-card">
      <div class="card-header">
        <div class="logo-container">
          <img src="img/logo/OCP.png" alt="OCP System" class="logo-img">
        </div>
      </div>
      <div class="card-body">
        <?php if (!empty($error)): ?>
          <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form role="form" method="POST" action="">
          <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" class="form-control" name="username" required placeholder="Enter your username">
          </div>
          <div class="mb-4">
            <label class="form-label">Password</label>
            <input type="password" class="form-control" name="password" required placeholder="Enter your password">
          </div>
          <div class="mb-4">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="rememberMe" name="rememberMe">
              <label class="form-check-label" for="rememberMe">Remember me</label>
            </div>
          </div>
          <div class="d-grid">
            <button type="submit" class="btn btn-login">Sign in</button>
          </div>
        </form>
      </div>
    </div>
    
    <div class="copyright">
      &copy; <script>document.write(new Date().getFullYear())</script> OCP Construction. All rights reserved.
    </div>
  </div>

  <!-- Bootstrap JS Bundle -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>