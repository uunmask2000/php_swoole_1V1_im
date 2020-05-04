<?php
## load composer
include 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

# 連線
// $__host = '0.0.0.0';
// $__port = 10184;
$__host = getenv('WebSocket_host');
$__port = getenv('WebSocket_port');

echo $__host . ':' . $__port . "\n";
### redis

$Redis_host = getenv('Redis_host');
$Redis_port = getenv('Redis_port');
$Redis_db   = getenv('Redis_db');
$Redis_Auth = getenv('Redis_Auth');

$redis = new Redis();
$redis->connect($Redis_host, $Redis_port);
$redis->auth($Redis_Auth);
$redis->select($Redis_db);

$server = new swoole_websocket_server($__host, $__port);

$server->on('open', function (swoole_websocket_server $server, $request) {
    echo "server: handshake success with fd{$request->fd}\n";
});

$server->on('message', function (swoole_websocket_server $server, $frame) {

    // print_r($frame);
    // echo "receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}\n";

    $json = json_decode($frame->data, true);
    global $redis;

    ## 存放對話資料
    if (!isset($json['id'])) {
        $server->push($frame->fd, "This message is from swoole websocket server.");
        return;
    } else {
        $redis->set("user_id_" . $json['id'], $frame->fd);
        $redis->expire("user_id_" . $json['id'], 60 * 60 * 24);
    }

    print_r($json);

    ### redis

    switch ($json['id']) {
        case '2':
            // $return_fd = $redis->get("user_id_" . '1');
            echo $user_id = $json['user_id'];
            $return_fd    = $redis->get($user_id);
            $__user_id    = str_replace("user_id_", "", $user_id);
            if ($__user_id != "") {
                $redis_key = str_replace("user_id_", "", $user_id) . '_msg';
                $redis->lpush($redis_key, json_encode(['c' => 2, 'msg' => $frame->data]));
            }

            break;
        default:

            // $server->push($frame->fd, "This message is from swoole websocket server.");
            // return false;

            $return_fd        = $redis->get("user_id_" . '2');
            $frame->client_id = $json['id'];
            $redis_key        = $json['id'] . '_msg';
            if ($json["data"] != "") {
                $redis->lpush($redis_key, json_encode(['c' => 1, 'msg' => $frame->data]));
            }
            break;
    }

    ###  發給誰
    $server->push($return_fd, $frame->data);

    // $info  = [] ;
    // $info[$json['id']] = [
    //     'fd' => $frame->fd,
    // ];
    // $server->push($frame->fd, "This message is from swoole websocket server.");

});

$server->on('close', function ($ser, $fd) {

    echo "client {$fd} closed\n";
});

$server->start();
