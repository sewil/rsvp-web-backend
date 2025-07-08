<?php
require_once 'logger.php';
require_once 'config.php';
require_once 'vendor/autoload.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $redis = new Redis();
    try {
        $redis->connect('127.0.0.1', 6379);
        $redis->auth(REDIS_PASSWORD);
    } catch (RedisException $e) {
        $redis = null;
        log_info("Server info - Redis connection exception: " . $e->getMessage());
    }

    function getPlayersOnline($world, $channel) {
        global $redis;
        if (!$redis) return 0;
        $key = "online-players-$world-$channel";
        return $redis->get($key);
    }

    function isServerOnline(string $host, int $port, int $timeout = 2): bool {
        $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if ($fp) {
            fclose($fp);
            return true;
        }
        return false;
    }

    $onlineGame = getPlayersOnline(0, 0);
    $onlineShop = getPlayersOnline(0, 50);
    $onlineLogin = getPlayersOnline(-1, 0);
    $onlineTotal = $onlineGame + $onlineShop + $onlineLogin;

    $centerServerOnline = isServerOnline(CENTER_SERVER_HOST, 8383);
    $loginServerOnline = isServerOnline(LOGIN_SERVER_HOST, 8484);
    $gameServerOnline = isServerOnline(GAME_SERVER_HOST, 8585);

    http_response_code(200);
    echo json_encode([
        "centerOnline" => $centerServerOnline,
        "loginOnline" => $loginServerOnline,
        "gameOnline" => $gameServerOnline,
        "playersOnline" => $onlineTotal
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["error" => "Something went wrong. Please try again later."]);
    log_error("Error fetching server info: " . $e->getMessage());
}
?>
