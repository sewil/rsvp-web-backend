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
    if (empty($input['username']) || empty($input['email']) || empty($input['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Username, email, and password are required']);
        exit;
    }
    
    // Sanitize input
    $username = validateInput($input['username']);
    $email = validateInput($input['email']);
    $dateOfBirth = validateInput($input['date_of_birth']);
    $referralCode = ($input['referral_code'] ?? NULL) ? validateInput($input['referral_code']) : NULL;
    $password = $input['password'];
    $password2 = $input['password2'];
    
    // Validate email format
    if (!validateEmail($email)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid email format']);
        exit;
    }
    
    // Validate passwords
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

    // Validate date of birth
    if (!validateDateOfBirth($dateOfBirth)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid date of birth, must be YYYY-MM-DD format']);
        exit;
    }

    // Format date of birth
    $dateOfBirth = str_replace("-", "", $dateOfBirth);

    // Validate referral code
    if ($referralCode && !validateReferralCode($referralCode)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid referral code']);
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
    
    // Check if username or email already exists
    $checkQuery = "SELECT id FROM users WHERE username = ? OR email = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->execute([$username, $email]);
    $result = $checkStmt->get_result();
    if ($result->num_rows > 0) {
        http_response_code(409);
        echo json_encode(['error' => 'Username or email already exists']);
        exit;
    }

    $hashedPassword = hashPassword($password);
    $expiresAt = date('Y-m-d H:i:s', time() + (24 * 60 * 60)); // 24 hours
    $userData = [
        'username' => $username,
        'email' => $email,
        'password_hash' => $hashedPassword,
        'expires_at' => $expiresAt,
        'date_of_birth' => $dateOfBirth
    ];

    // Check valid referral code if any
    if ($referralCode) {
        $checkQuery = "SELECT id FROM users WHERE referral_code = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->execute([$referralCode]);
    
        if ($checkStmt->num_rows() == 0) {
            http_response_code(409);
            echo json_encode(['error' => 'Unknown referral code']);
            exit;
        }
        $userData['referral_code'] = $referralCode;
    }
    
    $token = generateToken($userData);

    // Send verification email
    if (sendVerificationEmail($email, $username, $token)) {
        http_response_code(201);
        log_discord("Sent verification email to `$email` for IP `$requestIP`. Username `$username`, DoB `$dateOfBirth`.");
        echo json_encode([
            'success' => true,
            'message' => 'Registration successful. Please check your email to verify your account.'
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
