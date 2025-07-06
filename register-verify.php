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
    if (!$decryptedToken) {
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

    $updateStmt = $conn->prepare("UPDATE users SET gender = 10 WHERE email = ? AND gender = 11");
    if (!$updateStmt->execute([$decryptedToken['email']])) {
        http_response_code(403);
        echo json_encode(["error" => "Failed verifying account. Check that the email exists and the account is not already verified."]);
    }

    http_response_code(200);
    echo json_encode(["success" => true, "message" => "Account verified successfully. You may now login with your account credentials."]);
} catch (Exception $e) {
    http_response_code(500);
    log_error($e);
    echo json_encode(["error" => "Server error. Please try again later."]);
}
?>
