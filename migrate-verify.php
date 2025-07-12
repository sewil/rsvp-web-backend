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
    echo "Method not allowed";
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

    if (empty($input['token'])) {
        http_response_code(400);
        echo json_encode(["error" => "Verification token is required"]);
        exit;
    }

    $token = validateInput($input['token']);
    $decryptedToken = decryptToken($token);
    if (!$decryptedToken || !($decryptedToken['expires_at'] ?? NULL)) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid token", "code" => "token_invalid"]);
        exit;
    } else if (time() > $decryptedToken['expires_at']) {
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

    $updateStmt = $conn->prepare("UPDATE users SET verified = 1, email = ? WHERE ID = ? AND verified = 0");
    if (!$updateStmt->execute([$decryptedToken['email'], $decryptedToken['user_id']]) || $updateStmt->affected_rows == 0) {
        http_response_code(403);
        log_error("Migration error for user id " . $decryptedToken['user_id'] . " with email " . $decryptedToken['email']);
        echo json_encode(["error" => "Failed migrating account. Please check that the account has not already been migrated."]);
        exit;
    }

    http_response_code(200);
    echo json_encode(["success" => true, "message" => "Account migrated successfully. You may now login with your account credentials."]);
} catch (Throwable $e) {
    http_response_code(500);
    log_error($e);
    echo json_encode(["error" => "Server error. Please try again later."]);
}
?>
