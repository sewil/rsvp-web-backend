<?php
require_once 'email_phpmailer.php';
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

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        // Fallback to POST data
        $input = $_POST;
    }

    if (empty($input['email'])) {
        http_response_code(400);
        echo json_encode(["error" => "Email is required"]);
        exit;
    }

    $email = validateInput($input['email']);

    if (!validateEmail($email)) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid email"]);
        exit;
    }

    $db = new Database();
    $conn = $db->getConnection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(["error" => "Database connection failed. Please try again later."]);
        exit;
    }

    $stmt = $conn->prepare("SELECT * FROM users WHERE LOWER(email) = LOWER(?)");
    $stmt->execute([$email]);
    $user = $stmt->get_result()->fetch_assoc();
    if (!$user) {
        http_response_code(401);
        echo json_encode(["error" => "Couldn't find a user with that email."]);
        exit;
    }
    $expiresAt = date('Y-m-d H:i:s', time() + (24 * 60 * 60)); // 24 hours
    $token = generateToken([
        "expires_at" => $expiresAt,
        'email' => $email
    ]);

    http_response_code(200);
    echo json_encode([
        "success" => true,
        "message" => "Verification email sent. Please check your inbox to verify your account.",
    ]);
} catch (Throwable $e) {
    log_error("Server error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Server error. Please try again later."]);
}
?>
