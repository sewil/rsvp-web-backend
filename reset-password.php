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

    if (empty($input['email']) || empty($input['username'])) {
        http_response_code(400);
        echo json_encode(["error" => "Email and username are required"]);
        exit;
    }

    $email = validateInput($input['email']);
    $username = validateInput($input['username']);

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

    $stmt = $conn->prepare("SELECT ID, username FROM users WHERE LOWER(username) = LOWER(?) AND LOWER(email) = LOWER(?)");
    $stmt->execute([$username, $email]);
    $user = $stmt->get_result()->fetch_assoc();
    if (!$user) {
        http_response_code(401);
        echo json_encode(["error" => "User not found"]);
        exit;
    }
    $username = $user['username'];
    $expiresAt = date('Y-m-d H:i:s', time() + (24 * 60 * 60)); // 24 hours
    $token = generateToken([
        "expires_at" => $expiresAt,
        "user_id" => $user["ID"],
        "username" => $username
    ]);
    $verifyUrl = FRONTEND_URL . "/reset-password-verify.php?token=" . urlencode($token);

    $subject = "Reset your password";
    $htmlBody = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='utf-8'>
        <title>Reset Your Password</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #007bff; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .button { display: inline-block; padding: 12px 24px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
            .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Reset Your Password</h1>
            </div>
            <div class='content'>
                <h2>Hello $username,</h2>
                <p>To reset your password, please click the button below to verify your email address:</p>
                <p style='text-align: center;'>
                    <a href='$verifyUrl' class='button'>Verify Email Address</a>
                </p>
                <p>Or copy and paste this URL into your browser:</p>
                <p><a href='$verifyUrl'>$verifyUrl</a></p>
                <p><strong>This link will expire in 24 hours.</strong></p>
            </div>
            <div class='footer'>
                <p>If you didn't request to reset your password, please ignore this email.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    $textBody = "";
    sendEmailWithPHPMailer($email, $subject, $htmlBody, $textBody);
    http_response_code(200);
    echo json_encode(["success" => true, "message" => "Verification email sent. Please check your inbox to verify your account."]);
} catch (Exception $e) {
    log_error("Server error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Server error. Please try again later."]);
}
?>
