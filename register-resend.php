<?php
require_once 'config.php';
require_once 'utils.php';
require_once 'crypto.php';
require_once 'logger.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$requestIP = $_SERVER['REMOTE_ADDR'];

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        // Fallback to POST data
        $input = $_POST;
    }

    // Validate required fields
    if (empty($input['email'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Email is required']);
        exit;
    }

    // Sanitize input
    $email = validateInput($input['email']);
    if (!validateEmail($email)) {
        http_response_code(400);
        echo json_encode(["email" => "Invalid email"]);
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

    $checkQuery = "SELECT * FROM users WHERE email = ? AND verified = 0";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->execute([$email]);
    $user = $checkStmt->get_result()->fetch_assoc();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'User not found']);
        exit;
    }

    $username = $user['username'];
    $expiresAt = date('Y-m-d H:i:s', time() + (24 * 60 * 60)); // 24 hours
    $emailToken = generateToken([
        'email' => $email,
        'expires_at' => $expiresAt
    ]);

    if (send_register_email($username, $email)) {
        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Verification email sent. Please check your inbox to verify your account.",
        ]);
    } else {
        log_error("Register resend error: Failed to send verification email to `" . $email . "`");
        http_response_code(500);
        echo json_encode(["error" => "Failed to send verification email."]);
    }
} catch (Throwable $e) {
    log_error("Register resend error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error. Please try again later.']);
}
?>
