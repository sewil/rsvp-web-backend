<?php

use Google\Cloud\Core\Exception\BadRequestException;
require_once 'config.php';
require_once 'email_phpmailer.php';
require_once 'logger.php';

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
            log_error("Connection error: " . $e->getMessage());
        }
        return $this->conn;
    }
}

function validateInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validateDateOfBirth($dateOfBirth) {
    return preg_match('/^\d{4}\d{2}\d{2}$/', $dateOfBirth);
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

// Include Google Cloud dependencies using Composer
use Google\Cloud\RecaptchaEnterprise\V1\Client\RecaptchaEnterpriseServiceClient;
use Google\Cloud\RecaptchaEnterprise\V1\Event;
use Google\Cloud\RecaptchaEnterprise\V1\Assessment;
use Google\Cloud\RecaptchaEnterprise\V1\CreateAssessmentRequest;
use Google\Cloud\RecaptchaEnterprise\V1\TokenProperties\InvalidReason;

/**
  * Create an assessment to analyze the risk of a UI action.
  * @param string $recaptchaKey The reCAPTCHA key associated with the site/app
  * @param string $token The generated token obtained from the client.
  * @param string $project Your Google Cloud Project ID.
  * @param string $action Action name corresponding to the token.
  */
function create_assessment(
  string $recaptchaKey,
  string $token,
  string $project,
  string $action
): Assessment {
  // Create the reCAPTCHA client.
  // TODO: Cache the client generation code (recommended) or call client.close() before exiting the method.
  $client = new RecaptchaEnterpriseServiceClient([
     'credentials' => RECAPTCHA_KEYFILE
  ]);
  $projectName = $client->projectName($project);

  // Set the properties of the event to be tracked.
  $event = (new Event())
    ->setSiteKey($recaptchaKey)
    ->setToken($token);

  // Build the assessment request.
  $assessment = (new Assessment())
    ->setEvent($event);

  $request = (new CreateAssessmentRequest())
  ->setParent($projectName)
  ->setAssessment($assessment);

  $response = $client->createAssessment($request);

  // Check if the token is valid.
  if ($response->getTokenProperties()->getValid() == false) {
    $invalidReason = InvalidReason::name($response->getTokenProperties()->getInvalidReason());
    throw new BadRequestException('The CreateAssessment() call failed because the token was invalid for the following reason: ' . $invalidReason);
  }

  // Check if the expected action was executed.
  if ($response->getTokenProperties()->getAction() == $action) {
    // Get the risk score and the reason(s).
    // For more information on interpreting the assessment, see:
    // https://cloud.google.com/recaptcha-enterprise/docs/interpret-assessment
    // printf('The score for the protection action is:');
    // printf($response->getRiskAnalysis()->getScore());
    return $response;
  } else {
    throw new BadRequestException('The action attribute in your reCAPTCHA tag does not match the action you are expecting to score');
  }
}
?>
