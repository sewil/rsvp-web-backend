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
?>
