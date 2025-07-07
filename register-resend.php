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
    if (empty($input['email'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Email is required']);
        exit;
    }

    // Sanitize input
    $email = validateInput($input['email']);
    if (!validateEmail($email)) {
        http_response_code(400);
        echo json_encode(["email" => "Invalid email"]);
        exit;
    }

    // Connect to database
    $database = new Database();
    $conn = $database->getConnection();

    if (!$conn) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    }

    $checkQuery = "SELECT * FROM users WHERE email = ? AND verified = 0";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->execute([$email]);
    $user = $checkStmt->get_result()->fetch_assoc();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'User not found']);
        exit;
    }

    $username = $user['username'];
    $expiresAt = date('Y-m-d H:i:s', time() + (24 * 60 * 60)); // 24 hours
    $emailToken = generateToken([
        'email' => $email,
        'expires_at' => $expiresAt
    ]);

    $verifyUrl = FRONTEND_URL . "/register-confirmation.php?token=" . urlencode($emailToken);
    $success = sendEmailWithPHPMailer($email, "Verify Your Account", "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Verify Your Account</title>
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
                    <h1>Welcome to OpenMG!</h1>
                </div>
                <div class='content'>
                    <h2>Hello $username,</h2>
                    <p>Thank you for registering! Please click the button below to verify your email address:</p>
                    <p style='text-align: center;'>
                        <a href='$verifyUrl' class='button'>Verify Email Address</a>
                    </p>
                    <p>Or copy and paste this URL into your browser:</p>
                    <p><a href='$verifyUrl'>$verifyUrl</a></p>
                    <p><strong>This link will expire in 24 hours.</strong></p>
                </div>
                <div class='footer'>
                    <p>If you didn't create an account, please ignore this email.</p>
                </div>
            </div>
        </body>
        </html>
    ", "
        Hello $username,

        Thank you for registering! Please visit the following URL to verify your email address:
        
        $verifyUrl
        
        This link will expire in 24 hours.
        
        If you didn't create an account, please ignore this email.
    ");

    if ($success) {
        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Verification email sent. Please check your inbox to verify your account.",
        ]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Failed to send verification email."]);
    }
} catch (Exception $e) {
    log_error("Register resend error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error. Please try again later.']);
}
?>
