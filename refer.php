<?php
require_once 'logger.php';
require_once 'utils.php';
require_once 'crypto.php';

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
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        $input = $_POST;
    } else if (empty($input['token'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Token is required']);
        exit;
    }

    $token = decryptToken($input['token']);
    if (!$token) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid token"]);
        exit;
    } else if (time() > $token['expires_at']) {
        http_response_code(401);
        echo json_encode(["error" => "Expired token"]);
        exit;
    }

    $userID = $token['user_id'];

    $database = new Database();
    $conn = $database->getConnection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(["error" => "Database connection error. Please try again later."]);
        exit;
    }

    // Check if user already has a referral code
    $stmt = $conn->prepare("SELECT ID FROM users WHERE ID = ? AND referral_code IS NOT NULL");
    $stmt->execute([$userID]);
    if ($stmt->get_result()->num_rows > 0) {
        http_response_code(403);
        echo json_encode(["error" => "You already have a referral code set!"]);
        exit;
    }

    $hash = crc32($userID . random_bytes(16));
    $referralCode = strtoupper(str_pad(dechex($hash), 8, '0', STR_PAD_LEFT));
    $stmt = $conn->prepare("UPDATE users SET referral_code = ? WHERE ID = ?");
    $stmt->execute([$referralCode, $userID]);
    if ($stmt->error) {
        http_response_code(500);
        log_error("MySQL error when setting referral code for user $userID: " . $stmt->error);
        echo json_encode(["error" => "Something went wrong. Please try again later."]);
        exit;
    }
    log_discord("Set referral code $referralCode for user $userID");
    http_response_code(200);
    echo json_encode([
        "referral_code" => $referralCode
    ]);
} catch (Exception $e) {
    http_response_code(500);
    log_error("Server error on refer: " . $e->getMessage());
    echo json_encode(["error" => "Something went wrong. Please try again later."]);
}
?>
