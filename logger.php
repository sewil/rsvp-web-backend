<?php
require_once 'config.php';

function log_error($text) {
    $text = "[" . date("Y-m-d H:i:s") . "] " . $text;
    if(DEBUG) {
        error_log($text);
    }
    else {
        error_log($text . PHP_EOL, 3, LOG_PATH . "/error.log");
    }
}
function log_info($text) {
    $text = "[" . date("Y-m-d H:i:s") . "] " . $text;
    if(DEBUG) {
        error_log($text);
    }
    else {
        error_log($text . PHP_EOL, 3, LOG_PATH . "/info.log");
    }
}

function log_discord(string $message) {
    $url = DISCORD_WEBHOOK_URL;
    $data = json_encode(["username" => BACKEND_URL, "content" => $message]);
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-type: application/json"));
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

    $json_response = curl_exec($curl);

    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    if (floor($status / 100) != 2) {
        log_error("Error: call to URL $url failed with status $status, response $json_response, curl_error " . curl_error($curl) . ", curl_errno " . curl_errno($curl));
    }

    log_info($message);

    curl_close($curl);

    // $response = json_decode($json_response, true);
}
?>
