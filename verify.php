<?php
require_once 'config.php';
require_once 'utils.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo "Method not allowed";
    exit;
}

if (empty($_GET['token'])) {
    http_response_code(400);
    echo "Verification token is required";
    exit;
}

try {
    $token = validateInput($_GET['token']);

    // Check if there's pending registration data in session
    $pendingData = decryptToken($token);
    if (!$pendingData) {
        http_response_code(400);
        echo "
        <!DOCTYPE html>
        <html>
        <head>
            <title>Verification Error</title>
            <meta charset='utf-8'>
            <style>body { font-family: Arial, sans-serif; text-align: center; margin-top: 50px; } .error { color: #dc3545; }</style>
        </head>
        <body>
            <div class='container'>
                <h1 class='error'>Verification Error</h1>
                <p>No pending registration found. Please register again.</p>
                <a href='/index.php?tab=register'>Go to Registration</a>
            </div>
        </body>
        </html>
        ";
        exit;
    }
    
    // Check if token has expired
    if (time() > $pendingData['expires_at']) {
        http_response_code(400);
        echo "
        <!DOCTYPE html>
        <html>
        <head>
            <title>Token Expired</title>
            <meta charset='utf-8'>
            <style>body { font-family: Arial, sans-serif; text-align: center; margin-top: 50px; } .error { color: #dc3545; }</style>
        </head>
        <body>
            <div class='container'>
                <h1 class='error'>Verification Token Expired</h1>
                <p>The verification token has expired. Please register again.</p>
                <a href='/register.html'>Go to Registration</a>
            </div>
        </body>
        </html>
        ";
        exit;
    }
    
    // Connect to database
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        http_response_code(500);
        echo "Database connection failed";
        exit;
    }
    
    // Double-check that username/email is still available
    $checkQuery = "SELECT id FROM users WHERE username = ? OR email = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->execute([$pendingData['username'], $pendingData['email']]);
    $result = $checkStmt->get_result();
    if ($result->num_rows > 0) {
        http_response_code(409);
        echo "
        <!DOCTYPE html>
        <html>
        <head>
            <title>Registration Conflict</title>
            <meta charset='utf-8'>
            <style>body { font-family: Arial, sans-serif; text-align: center; margin-top: 50px; } .error { color: #dc3545; }</style>
        </head>
        <body>
            <div class='container'>
                <h1 class='error'>Registration Conflict</h1>
                <p>Username or email is no longer available. Please register with different credentials.</p>
                <a href='/register.php'>Go to Registration</a>
            </div>
        </body>
        </html>
        ";
        exit;
    }
    
    // Insert verified user into users table
    $insertUserQuery = "INSERT INTO users (username, email, password, gender, char_delete_password, referral_code) 
                       VALUES (?, ?, ?, ?, ?, ?)";
    $insertUserStmt = $conn->prepare($insertUserQuery);
    
    if ($insertUserStmt->execute([
        $pendingData['username'],
        $pendingData['email'],
        $pendingData['password_hash'],
        10,
        $pendingData['date_of_birth'],
        $pendingData['referral_code'] ?? NULL
    ])) {
        // Success page
        echo "
        <!DOCTYPE html>
        <html>
        <head>
            <title>Email Verified</title>
            <meta charset='utf-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1'>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; margin-top: 50px; }
                .success { color: #28a745; }
                .container { max-width: 500px; margin: 0 auto; padding: 20px; }
                .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <h1 class='success'>Email Verified Successfully!</h1>
                <p>Welcome <strong>" . htmlspecialchars($pendingData['username']) . "</strong>!</p>
                <p>Your account has been activated. You can now log in.</p>
                <a href='/login.php' class='btn'>Go to Login</a>
            </div>
        </body>
        </html>
        ";
    } else {
        http_response_code(500);
        echo "Failed to create user account";
    }
    
} catch (Exception $e) {
    http_response_code(500);
    error_log($e);
    echo "Server error. Please try again later.";
}
?>
