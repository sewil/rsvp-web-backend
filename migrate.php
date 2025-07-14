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
    if (empty($input['username']) || empty($input['password']) || empty($input['email']) || empty($input['email2'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Username, password and email are required']);
        exit;
    }
    
    // Sanitize input
    $username = validateInput($input['username']);
    $password = $input['password'];
    $email = validateInput($input['email']);
    $email2 = validateInput($input['email2']);
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
        echo json_encode(['error' => 'User not found.']);
        exit;
    } else if (validateEmail($userRow['email'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Account has already been migrated.']);
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

    $expiresAt = date('Y-m-d H:i:s', time() + (30 * 24 * 3600)); // 30 days
    $emailToken = generateToken([
        'user_id' => $userRow['ID'],
        'email' => $email,
        'expires_at' => $expiresAt,
    ]);

    $verifyUrl = FRONTEND_URL . "/migrate-confirmation.php?token=" . urlencode($emailToken);
    $success = sendEmailWithPHPMailer($email, "Migrate Your Account", "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Migrate Your Account</title>
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
                    <h1>Complete Account Migration</h1>
                </div>
                <div class='content'>
                    <h2>Hello $username,</h2>
                    <p>Please click the button below to complete the account migration:</p>
                    <p style='text-align: center;'>
                        <a href='$verifyUrl' class='button'>Migrate Account</a>
                    </p>
                    <p>Or copy and paste this URL into your browser:</p>
                    <p><a href='$verifyUrl'>$verifyUrl</a></p>
                    <p><strong>This link will expire in 24 hours.</strong></p>
                </div>
                <div class='footer'>
                    <p>If you didn't start an account migration, please ignore this email.</p>
                </div>
            </div>
        </body>
        </html>
    ", "
        Hello $username,

        Please visit the following URL to complete the account migration:
        
        $verifyUrl
        
        This link will expire in 24 hours.
        
        If you didn't start an account migration, please ignore this email.
    ");

    if ($success) {
        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Verification email sent. Please check your inbox to verify your account. If you didn't receive an email, please wait a few minutes and check your spam folder.",
        ]);
    } else {
        http_response_code(500);
        log_error("Migrate error: Failed to send verification email to `" . $email . "`");
        echo json_encode(["error" => "Failed to send verification email."]);
    }
} catch (Throwable $e) {
    http_response_code(500);
    log_error("Migrate server error: " . $e->getMessage());
    echo json_encode(['error' => 'Server error. Please try again later.']);
}
?>
