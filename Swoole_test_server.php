<?php

# 連線
$__host = '0.0.0.0';
$__port = 10182;

### redis
$redis = new Redis();
$redis->connect('127.0.0.1', 6379);
$redis->auth("f^UgNq%fQxbTcAUQDE8a&zjx#WBdkJ");
$redis->select(15);

$server = new swoole_websocket_server($__host, $__port);

$server->on('open', function (swoole_websocket_server $server, $request) {
    echo "server: handshake success with fd{$request->fd}\n";
});

$server->on('message', function (swoole_websocket_server $server, $frame) {

    // print_r($frame);
    // echo "receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}\n";

    $json = json_decode($frame->data, true);
    global $redis;

    $redis->set("user_id_" . $json['id'], $frame->fd);
    switch ($json['id']) {
        case '2':
            $return_fd = $redis->get("user_id_" . '1');
            break;
        case '1':
            $return_fd = $redis->get("user_id_" . '2');
            break;
        default:
            // $server->push($frame->fd, "This message is from swoole websocket server.");
            return false;
            break;
    }

    ###  發給誰
    @$server->push($return_fd, $frame->data);
    
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
