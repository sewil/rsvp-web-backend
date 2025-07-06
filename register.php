<?php

use Google\Cloud\Core\Exception\BadRequestException;
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
    if (empty($input['token']) || empty($input['username']) || empty($input['email']) || empty($input['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Username, email, and password are required']);
        exit;
    }

    // Sanitize input
    $token = $input['token'];
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

    $assessment = create_assessment(
        RECAPTCHA_SECRET,
        $token,
        'rsvp-454314',
        'signup'
    );
    $score = $assessment->getRiskAnalysis()->getScore();
    if ($score < 0.6) {
        http_response_code(403);
        log_discord("Register: Verify reCAPTCHA failed for IP '" . $_SERVER['REMOTE_ADDR'] . "' with a score of $score.");
        echo json_encode([
            "error" => "Suspicious activity detected. Please try again later."
        ]);
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
    $emailToken = generateToken([
        'email' => $email,
        'expires_at' => $expiresAt
    ]);

    // Check valid referral code if any
    if ($referralCode) {
        $checkQuery = "SELECT ID FROM users WHERE referral_code = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->execute([$referralCode]);

        if ($checkStmt->num_rows() == 0) {
            http_response_code(409);
            echo json_encode(['error' => 'Unknown referral code']);
            exit;
        }
    }

    // Insert user in table
    $insertUserQuery = "INSERT INTO users (username, email, password, gender, char_delete_password, referral_code)
                       VALUES (?, ?, ?, ?, ?, ?)";
    $insertUserStmt = $conn->prepare($insertUserQuery);

    if (
        $insertUserStmt->execute([
            $username,
            $email,
            $hashedPassword,
            11,
            $dateOfBirth,
            $referralCode ?? NULL,
        ])
    ) {
        http_response_code(201);
        log_discord("IP `$requestIP` registered a new account with email `$email`, username `$username`, DoB `$dateOfBirth`, and referral code `$referralCode`.");
        echo json_encode([
            "success" => true,
            "message" => "Registration successful. Please check your email to verify your account.",
            "token" => $emailToken,
            "user" => [
                "username" => $username,
                "email" => $email,
                "char_delete_password" => $dateOfBirth,
                "referral_code" => $referralCode
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Registration failed. Please try again later."]);
    }
} catch (BadRequestException $e) {
  http_response_code(400);
  log_error("Bad request: " . $e->getMessage());
  echo json_encode(["error" => "Bad request. Please try again later."]);
} catch (Exception $e) {
    log_error("Register error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error. Please try again later.']);
}
?>
