<?php
require_once 'crypto.php';
require_once 'utils.php';
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

    if (empty($input['token']) || empty($input['password']) || empty($input['password2'])) {
        http_response_code(400);
        echo json_encode(["error" => "Verification token and password is required"]);
        exit;
    }

    $password = validateInput($input['password']);
    $password2 = validateInput($input['password2']);

    if (!validatePassword($password)) {
        http_response_code(400);
        echo json_encode(['error' => 'Password must be 4-12 characters with uppercase, lowercase, and number']);
        exit;
    }
    if ($password2 != $password) {
        http_response_code(400);
        echo json_encode(['error' => 'Mismatching passwords']);
        exit;
    }

    $decryptedToken = decryptToken($input['token']);
    if (!$decryptedToken) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid token"]);
        exit;
    } else if (time() > $decryptedToken['expires_at']) {
        http_response_code(401);
        echo json_encode(["error" => "Expired token", "code" => "token_expired"]);
        exit;
    }

    $email = $decryptedToken['email'];

    $db = new Database();
    $conn = $db->getConnection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(["error" => "Database connection failed."]);
        exit;
    }

    // Update user password
    $hashedPassword = hashPassword($password);
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ? AND verified = 1");
    if (!$stmt->execute([$hashedPassword, $email]) || $stmt->affected_rows == 0) {
        http_response_code(403);
        log_discord("Reset password verify: Failed verifying email `" . $email . "`.");
        echo json_encode(["error" => "Verification failed. Please try again later."]);
        exit;
    }

    http_response_code(200);
    echo json_encode(["success" => true, "message" => "Password updated successfully. You may now login with your new password."]);
} catch (Throwable $e) {
    http_response_code(500);
    log_discord("Reset password verify server error: " . $e->getMessage());
    echo json_encode(["error" => "Server error. Please try again later."]);
}
?>
