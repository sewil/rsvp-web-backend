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
    if (empty($input['token']) || empty($input['subject']) || empty($input['htmlBody']) || empty($input['textBody'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Token is required']);
        exit;
    }

    $token = $input['token'];
    $decryptedToken = decryptToken($token);
    $subject = validateInput($input['subject']);
    $htmlBody = validateInput($input['htmlBody']);
    $textBody = validateInput($input['textBody']);

    if (!$decryptedToken || !$decryptedToken['email']) {
        http_response_code(400);
        echo json_encode(["error" => "Invalid token"]);
    } else if (time() > $decryptedToken['expires_at']) {
        http_response_code(401);
        echo json_encode(["error" => "Expired token"]);
    }

    $email = $decryptedToken['email'];

    // Send verification email
    if (sendEmailWithPHPMailer($email, $subject, $htmlBody, $textBody)) {
        http_response_code(201);
        log_discord("Sent verification email to `$email` for IP `$requestIP`.");
        echo json_encode([
            'success' => true,
            'message' => 'Verification email sent. Please check your inbox to verify your account.'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to send verification email']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error. Please try again later.']);
}
?>
