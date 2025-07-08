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

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        // Fallback to POST data
        $input = $_GET;
    }
    
    // Validate required fields
    if (empty($input['username']) || empty($input['password'])/* || empty($input['token'])*/) {
        http_response_code(400);
        echo json_encode(['error' => 'Username and password are required']);
        exit;
    }
    
    // Sanitize input
    // $token = $input['token'];
    $username = validateInput($input['username']);
    $password = $input['password'];
    $securityPin = $input['security_pin'] ?? NULL;

    // $assessment = create_assessment(
    //     RECAPTCHA_SECRET,
    //     $token,
    //     'rsvp-454314',
    //     'login'
    // );
    // $score = $assessment->getRiskAnalysis()->getScore();
    // if ($score < 0.6) {
    //     http_response_code(403);
    //     log_discord("Login: Verify reCAPTCHA failed for IP '" . $_SERVER['REMOTE_ADDR'] . "' with a score of $score.");
    //     echo json_encode([
    //         "error" => "Suspicious activity detected. Please try again later."
    //     ]);
    //     exit;
    // }
    
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
    } else if ($userRow['verified'] == 0) {
        http_response_code(401);
        echo json_encode(['error' => 'Email not verified.', 'code' => 'email_not_verified']);
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
            echo json_encode(['error' => 'Security pin required (6 digits)', 'code' => 'totp_required']);
            exit;
        } else if (!verifyTOTP($userRow['pin_secret'], $securityPin)) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid security pin']);
            exit;
        }
    }

    $refreshAt = date('Y-m-d H:i:s', time() + (24 * 3600)); // 24 hours
    $expiresAt = date('Y-m-d H:i:s', time() + (30 * 24 * 3600)); // 30 days
    $token = generateToken([
        'expires_at' => $expiresAt,
        'refresh_at' => $refreshAt,
    ]);

    $tokenStmt = $conn->prepare("UPDATE users SET remember_token = ? WHERE ID = ?");
    if (!$tokenStmt->execute([$token, $userRow['ID']]) || $tokenStmt->affected_rows == 0) {
        http_response_code(403);
        log_discord("Login error: Failed setting remember_token for user " . $userRow['ID']);
        echo json_encode(["error" => "Login failed. Please try again later."]);
        exit;
    }

    http_response_code(200);
    echo json_encode([
        'username' => $userRow['username'],
        'email' => $userRow['email'],
        'date_of_birth' => $userRow['char_delete_password'],
        'referral_code' => $userRow['referral_code'],
        'token' => $token,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    log_error("Server error on login: " . $e->getMessage());
    echo json_encode(['error' => 'Server error. Please try again later.']);
}
?>
