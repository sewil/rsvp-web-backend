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
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        $input = $_GET;
    } else if (empty($input['token'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Token is required']);
        exit;
    }

    $token = decryptToken($input['token']); // ['user_id', 'expires_at']
    if (!$token) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid token", "code" => "token_invalid"]);
        exit;
    } else if (time() > $token['expires_at']) {
        http_response_code(403);
        echo json_encode(["error" => "Token has expired", "code" => "token_expired"]);
        exit;
    }

    $database = new Database();
    $conn = $database->getConnection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    }

    $userStmt = $conn->prepare("SELECT * FROM users WHERE ID = ?");
    $userStmt->execute([$token['user_id']]);
    $userRow = $userStmt->get_result()->fetch_assoc();
    if ($userRow == NULL) {
        http_response_code(401);
        echo json_encode(['error' => 'User not found']);
        exit;
    }

    // User authenticated, refresh token
    $expiresAt = date('Y-m-d H:i:s', time() + (30 * 24 * 3600)); // 30 days
    $refreshedToken = generateToken([
        "user_id" => $token['user_id'],
        "expires_at" => $expiresAt
    ]);

    http_response_code(200);
    echo json_encode([
        'username' => $userRow['username'],
        'email' => $userRow['email'],
        'date_of_birth' => $userRow['char_delete_password'],
        'referral_code' => $userRow['referral_code'],
        'token' => $refreshedToken,
        'expires_at' => $expiresAt
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error. Please try again later.']);
}
?>
