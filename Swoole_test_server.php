<?php
## 配置错误资讯
ini_set('track_errors', '1');

## load composer
include 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

# connection
// $__host = '0.0.0.0';
// $__port = 10184;
$__host = getenv('WebSocket_host');
$__port = getenv('WebSocket_port');

## redis user_id
$prefix = "user_id_";

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
    global $redis;
    global $prefix;
    // print_r($frame);
    // echo "receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}\n";

    $json = json_decode($frame->data, true);

    ## save talks messages
    if (!isset($json['id'])) {
        $server->push($frame->fd, "This message is from swoole websocket server.");
        return;
    } else {
        $redis->set($prefix . $json['id'], $frame->fd);
        $redis->expire($prefix . $json['id'], 60 * 60 * 24);
    }

    ### redis
    $redis_key = "";
    switch ($json['id']) {
        case '2':
            $user_id = $json['user_id'];

            $return_fd = $redis->get($prefix . $user_id);
            $__user_id = str_replace($prefix, "", $user_id);

            ###  send to ?
            // $server->push($return_fd, $frame->data);

            ## 捕捉错误资讯
            !@$server->push($return_fd, $frame->data);
            // echo $php_errormsg. $user_id. "\n";
            // var_dump($php_errormsg);

            ## 如果發送   [event] => close
            if (isset($json["data"]['event']) && $json["data"]['event'] == 'close') {
                // $frame->data['close'] = true;
                // print_r($frame->data);

                if ($php_errormsg == null) {
                    ## clear customer
                    ## customer Fd
                    $redis->del($prefix . $user_id);
                    ## customer message
                    $redis->del($__user_id . '_msg');
                    var_dump($php_errormsg);
                } else {
                    var_dump($php_errormsg);
                    // echo 'Y';
                }

            } else if ($__user_id != "") {
                $redis_key = str_replace($prefix, "", $user_id) . '_msg';
                $redis->lpush($redis_key, json_encode(['c' => 2, 'msg' => $frame->data]));
            }

            break;
        default:

            // $server->push($frame->fd, "This message is from swoole websocket server.");
            // return false;

            $return_fd        = $redis->get($prefix . '2');
            $frame->client_id = $json['id'];
            $redis_key        = $json['id'] . '_msg';

            ###  send to ?
            @$server->push($return_fd, $frame->data);

            if ($json["data"] != "") {
                $redis->lpush($redis_key, json_encode(['c' => 1, 'msg' => $frame->data]));
            }
            break;

    }
    echo $redis_key . "\n";
    if ($redis_key != "") {
        $redis->expire($redis_key, 60 * 60 * 24);

    }
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
