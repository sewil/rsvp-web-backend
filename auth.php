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
    if (empty($_GET['token'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Token is required']);
        exit;
    }

    $token = validateInput($_GET['token']);
    $data = decryptToken($token); // ['user_id', 'expires_at']
    if (!$data) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid token", "code" => "token_invalid"]);
        exit;
    } else if (time() > $data['expires_at']) {
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

    $userStmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $userStmt->execute([$data['user_id']]);
    $userRow = $userStmt->get_result()->fetch_assoc();
    if ($userRow == NULL) {
        http_response_code(401);
        echo json_encode(['error' => 'User not found']);
        exit;
    }

    http_response_code(200);
    echo json_encode([
        'username' => $userRow['username'],
        'email' => $userRow['email'],
        'date_of_birth' => $userRow['char_delete_password'],
        'referral_code' => $userRow['referral_code'],
        'token' => urlencode($token),
        'expires_at' => $data['expires_at']
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error. Please try again later.']);
}
?>
