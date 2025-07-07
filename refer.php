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

    $token = $input['token'];
    $decryptedToken = decryptToken($token);
    if (!$decryptedToken) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid token"]);
        exit;
    } else if (time() > $decryptedToken['expires_at']) {
        http_response_code(401);
        echo json_encode(["error" => "Expired token"]);
        exit;
    }

    $database = new Database();
    $conn = $database->getConnection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(["error" => "Database connection error. Please try again later."]);
        exit;
    }

    // Check if user already has a referral code
    $stmt = $conn->prepare("SELECT * FROM users WHERE remember_token = ? AND verified = 1");
    $stmt->execute([$token]);
    $userRow = $stmt->get_result()->fetch_assoc();
    if (!$userRow) {
        http_response_code(403);
        echo json_encode(["error" => "User not found."]);
        exit;
    } else if ($userRow['referral_code']) {
        http_response_code(200);
        echo json_encode([
            "referral_code" => $referralCode
        ]);
        exit;
    }

    $userID = $userRow['ID'];

    $hash = crc32($userID . random_bytes(16));
    $referralCode = strtoupper(str_pad(dechex($hash), 8, '0', STR_PAD_LEFT));
    $stmt = $conn->prepare("UPDATE users SET referral_code = ? WHERE ID = ?");
    if (!$stmt->execute([$referralCode, $userID]) || $stmt->affected_rows == 0) {
        http_response_code(403);
        log_discord("Failed setting referral code `$referralCode` for user $userID");
        echo json_encode(["error" => "Something went wrong. Please try again later."]);
        exit;
    }
    log_discord("Set referral code `$referralCode` for user $userID");
    http_response_code(200);
    echo json_encode([
        "referral_code" => $referralCode
    ]);
} catch (Exception $e) {
    http_response_code(500);
    log_discord("Server error on refer: " . $e->getMessage());
    echo json_encode(["error" => "Something went wrong. Please try again later."]);
}
?>
