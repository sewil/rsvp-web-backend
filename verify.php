<?php
require_once 'config.php';
require_once 'utils.php';
require_once 'crypto.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method not allowed";
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        // Fallback to POST data
        $input = $_POST;
    }

    if (empty($input['token'])) {
        http_response_code(400);
        echo json_encode(["error" => "Verification token is required" ]);
        exit;
    }

    $token = validateInput($input['token']);

    // Check if there's pending registration data in session
    $pendingData = decryptToken($token);
    if (!$pendingData) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid token", "code" => "token_invalid"]);
        exit;
    }
    
    // Check if token has expired
    if (time() > $pendingData['expires_at']) {
        http_response_code(400);
        echo json_encode(["error" => "Expired token", "code" => "token_expired"]);
        exit;
    }
    
    // Connect to database
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        http_response_code(500);
        echo json_encode(["error" => "Database connection failed"]);
        exit;
    }
    
    // Double-check that username/email is still available
    $checkQuery = "SELECT id FROM users WHERE username = ? OR email = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->execute([$pendingData['username'], $pendingData['email']]);
    $result = $checkStmt->get_result();
    if ($result->num_rows > 0) {
        http_response_code(409);
        echo json_encode(["error" => "Username or email is no longer available. Please register with different credentials.", "code" => "registration_conflict"]);
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
        http_response_code(201);
        echo json_encode(["success" => true, "message" => "Your account has been activated. You can now log in."]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Failed to create user account. Please try again later."]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    error_log($e);
    echo json_encode(["error" => "Server error. Please try again later."]);
}
?>
