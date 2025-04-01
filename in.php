<?php
require_once 'config.php';
require_once 'db_context.php';
require_once 'logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    exit('Not a POST request');
}

$voterIP = $_POST["VoterIP"] ?? null;
$success = abs((int)($_POST["Successful"] ?? 1));
$reason = $_POST["Reason"] ?? null;
$pingUsername = $_POST["pingUsername"] ?? null;
$pingbackkey = $_POST["pingbackkey"] ?? null;

$requestIP = $_SERVER['REMOTE_ADDR'];
// Gtop ip is different
// if ($_SERVER['REMOTE_ADDR'] !== $voterIP) {
//     http_response_code(403);
//     log_info("Mismatching IPs for user $pingUsername, expected ".$_SERVER["REMOTE_ADDR"].", got $voterIP");
//     exit("Wrong IP");
// }

if ($pingbackkey !== PINGBACK_KEY) {
    http_response_code(403);
    log_info("User $pingUsername tried voting with invalid pingback key '$pingbackkey' from IP $voterIP ($requestIP)");
    exit("Invalid pingback key");
}

if ($success != 0) {
    http_response_code(200);
    log_info("User $pingUsername failed vote due to '$reason' from IP $voterIP ($requestIP)");
    exit("Voting failed due to $reason");
}

try {
    $context = new db_context()->open();
    $user_result = $context->prepare("SELECT ID, username, ban_expire FROM users WHERE username = ?", 's', $pingUsername)->execute()->get_result();

    if (sizeof($user_result) == 0) {
        http_response_code(403);
        log_info("Tried to vote with unknown username '$pingUsername' from IP $voterIP ($requestIP)");
        exit("Unknown username '$pingUsername'");
    }

    $userid = $user_result[0]["ID"];
    $username = $user_result[0]["username"];
    $ban_expire = strtotime($user_result[0]["ban_expire"]);

    $now_time = time();
    $now = date("Y-m-d H:i:s", $now_time);

    if ($ban_expire > $now_time) {
        http_response_code(403);
        log_info("Banned user $username (userid $userid) tried voting from IP $voterIP ($requestIP)");
        exit("User is banned");
    }

    $last_vote = $context->prepare("SELECT vote_date FROM gtop_votes WHERE userid = ? ORDER BY vote_date DESC", "i", $userid)->execute()->get_result();
    if (sizeof($last_vote) > 0) {
        $then = strtotime($last_vote[0]["vote_date"]);
        $hours_ago = ($now_time - $then) / 3600;
        if ($hours_ago < 12) {
            http_response_code(403);
            log_info("User $username ($userid) tried voting again after only $hours_ago hours from IP $voterIP ($requestIP)");
            exit("Already voted less than 12 hours ago");
        }
    }

    $context->prepare("INSERT INTO gtop_votes (`vote_date`, `userid`, `voter_ip`) VALUES (?, ?, ?)", "sis", $now, $userid, $voterIP)->execute()->close_stmt();

    log_info("User $username ($userid) voted successfully from IP $voterIP ($requestIP)");
    http_response_code(200);
} catch (Exception $e) {
    log_error("Server error: " . $e->getMessage());
    http_response_code(500);
    exit("Server error, please try again later.");
} finally {
    $context->close();
}
