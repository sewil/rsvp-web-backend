<?php
require_once 'config.php';
require_once 'email_phpmailer.php';

class Database {
    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $username = DB_USERNAME;
    private $password = DB_PASSWORD;
    private $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = mysqli_connect(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME, DB_PORT);
        } catch(mysqli_sql_exception $e) {
            error_log("Connection error: " . $e->getMessage());
        }
        return $this->conn;
    }
}

function sendVerificationEmail($email, $username, $token) {
    $verifyUrl = FRONTEND_URL . "/verify.php?token=" . urlencode($token);
    
    $subject = "Verify Your Account";
    
    $htmlBody = "
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
    ";
    
    $textBody = "
    Hello $username,
    
    Thank you for registering! Please visit the following URL to verify your email address:
    
    $verifyUrl
    
    This link will expire in 24 hours.
    
    If you didn't create an account, please ignore this email.
    ";
    // return mail($email, $subject, $htmlBody);
    return sendEmailWithPHPMailer($email, $subject, $htmlBody, $textBody);
}

function validateInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validateDateOfBirth($dateOfBirth) {
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateOfBirth);
}

function validateReferralCode($referralCode) {
    return preg_match('/^[A-Fa-f\d]{8}$/', $referralCode);
}

function validatePassword($password) {
    // 4-12 characters, 1 uppercase, 1 lowercase, 1 number
    return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{4,12}$/', $password);
}

function getJobName($job) {
    if ($job == 0)
        return "Beginner";
    else if ($job == 100)
        return "Swordsman";
    else if ($job == 110)
        return "Fighter";
    else if ($job == 111)
        return "Crusader";
    else if ($job == 120)
        return "Page";
    else if ($job == 121)
        return "White Knight";
    else if ($job == 130)
        return "Spearman";
    else if ($job == 131)
        return "Dragon Knight";
    else if ($job == 200)
        return "Magician";
    else if ($job == 210)
        return "F/P Wizard";
    else if ($job == 211)
        return "F/P Mage";
    else if ($job == 220)
        return "I/L Wizard";
    else if ($job == 221)
        return "I/L Mage";
    else if ($job == 230)
        return "Cleric";
    else if ($job == 231)
        return "Priest";
    else if ($job == 300)
        return "Archer";
    else if ($job == 310)
        return "Hunter";
    else if ($job == 311)
        return "Ranger";
    else if ($job == 320)
        return "Crossbowman";
    else if ($job == 321)
        return "Sniper";
    else if ($job == 400)
        return "Rogue";
    else if ($job == 410)
        return "Assassin";
    else if ($job == 411)
        return "Hermit";
    else if ($job == 420)
        return "Bandit";
    else if ($job == 421)
        return "Chief Bandit";
    else if ($job == 500)
        return "GM";
    else
        return "N/A";
}
?>
