<?php
require_once 'config.php';
require_once 'utils.php';
require_once 'crypto.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        // Fallback to POST data
        $input = $_GET;
    }
    
    // Validate required fields
    if (empty($input['username']) || empty($input['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Username and password are required']);
        exit;
    }
    
    // Sanitize input
    $username = validateInput($input['username']);
    $password = $input['password'];
    $securityPin = $input['security_pin'] ?? NULL;
    
    // Validate password
    if (!validatePassword($password)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid password']);
        exit;
    }
    
    // Connect to database
    $database = new Database();
    $conn = $database->getConnection();
    
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    }
    
    $userStmt = $conn->prepare("SELECT * FROM users WHERE LOWER(username) = LOWER(?)");
    $userStmt->execute([$username]);
    $userRow = $userStmt->get_result()->fetch_assoc();
    if (!$userRow) {
        http_response_code(401);
        echo json_encode(['error' => 'User not found']);
        exit;
    }
    
    if (!verifyPassword($password, $userRow['password'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Wrong password']);
        exit;
    }

    if ($userRow['pin_secret']) {
        if (!$securityPin) {
            http_response_code(401);
            echo json_encode(['error' => 'Security pin required (6 digits)', 'code'=>'totp_required']);
            exit;
        } else if (!verifyTOTP($userRow['pin_secret'], $securityPin)) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid security pin']);
            exit;
        }
    }

    $expiresAt = date('Y-m-d H:i:s', time() + (30 * 24 * 3600)); // 30 days
    http_response_code(200);
    $token = generateToken([
        'user_id' => $userRow['ID'],
        'expires_at' => $expiresAt,
    ]);
    echo json_encode([
        'username' => $userRow['username'],
        'email' => $userRow['email'],
        'date_of_birth' => $userRow['char_delete_password'],
        'referral_code' => $userRow['referral_code'],
        'token' => $token,
        'expires_at' => $expiresAt
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error. Please try again later.']);
}
?>
