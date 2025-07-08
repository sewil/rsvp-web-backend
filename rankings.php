<?php
require_once 'config.php';
require_once 'db_context.php';
require_once 'logger.php';
require_once 'utils.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

$page_size = 6;

try {
    $context = new db_context()->open();
    $rankings_all = $context->prepare("
            SELECT `name`, `level`, `job` FROM characters
            JOIN users ON characters.userid = users.id
            WHERE users.admin = 0 AND characters.deleted_at IS NULL
            ORDER BY characters.level DESC, characters.exp DESC
            LIMIT ?
        ", 'i', $page_size)->execute()->get_result();
    $rankings_warrior = $context->prepare("
            SELECT `name`, `level`, `job` FROM characters
            JOIN users ON characters.userid = users.id
            WHERE users.admin = 0 AND characters.deleted_at IS NULL AND FLOOR(characters.job/100) = 1
            ORDER BY characters.level DESC, characters.exp DESC
            LIMIT ?
        ", 'i', $page_size)->execute()->get_result();
    $rankings_magician = $context->prepare("
            SELECT `name`, `level`, `job` FROM characters
            JOIN users ON characters.userid = users.id
            WHERE users.admin = 0 AND characters.deleted_at IS NULL AND FLOOR(characters.job/100) = 2
            ORDER BY characters.level DESC, characters.exp DESC
            LIMIT ?
        ", 'i', $page_size)->execute()->get_result();
    $rankings_bowman = $context->prepare("
            SELECT `name`, `level`, `job` FROM characters
            JOIN users ON characters.userid = users.id
            WHERE users.admin = 0 AND characters.deleted_at IS NULL AND FLOOR(characters.job/100) = 3
            ORDER BY characters.level DESC, characters.exp DESC
            LIMIT ?
        ", 'i', $page_size)->execute()->get_result();
    $rankings_thief = $context->prepare("
            SELECT `name`, `level`, `job` FROM characters
            JOIN users ON characters.userid = users.id
            WHERE users.admin = 0 AND characters.deleted_at IS NULL AND FLOOR(characters.job/100) = 4
            ORDER BY characters.level DESC, characters.exp DESC
            LIMIT ?
        ", 'i', $page_size)->execute()->get_result();
    $rankings_beginner = $context->prepare("
            SELECT `name`, `level`, `job` FROM characters
            JOIN users ON characters.userid = users.id
            WHERE users.admin = 0 AND characters.deleted_at IS NULL AND FLOOR(characters.job/100) = 0
            ORDER BY characters.level DESC, characters.exp DESC
            LIMIT ?
        ", 'i', $page_size)->execute()->get_result();
    $rankings_monsterbook = $context->prepare("
            SELECT `characters`.`name`, `characters`.`level`, `characters`.job, SUM(`monsterbook`.`count`) as cards
            FROM monsterbook
            JOIN characters ON `characters`.ID = `monsterbook`.charid
            JOIN users ON `characters`.userid = `users`.ID
            WHERE users.admin = 0 AND characters.deleted_at IS NULL
            GROUP BY charid
            ORDER BY SUM(`monsterbook`.`count`) DESC, `characters`.`level` DESC, `characters`.`exp` DESC
            LIMIT ?
        ", 'i', $page_size)->execute()->get_result();
    http_response_code(200);
    echo json_encode([
        "all" => $rankings_all,
        "warrior" => $rankings_warrior,
        "magician" => $rankings_magician,
        "bowman" => $rankings_bowman,
        "thief" => $rankings_thief,
        "beginner" => $rankings_beginner,
        "monsterbook" => $rankings_monsterbook,
    ]);
} catch (Throwable $e) {
    log_error("Server error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Server error, please try again later."]);
} finally {
    $context->close();
}
?>