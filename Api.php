
<?php
## UTF-8
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: *");
header("Access-Control-Allow-Headers: Origin, Methods, Content-Type");

## load composer
include 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

## redis user_id 
$prefix = "user_id_";

$Redis_host = getenv('Redis_host');
$Redis_port = getenv('Redis_port');
$Redis_db   = getenv('Redis_db');
$Redis_Auth = getenv('Redis_Auth');

$redis = new Redis();
$redis->connect($Redis_host, $Redis_port);
$redis->auth($Redis_Auth);
$redis->select($Redis_db);
##################################
$data = [
    "code" => 999,
    "msg"  => 'not working',
    "data" => [],
];
if (!isset($_GET['event'])) {
    echo json_encode($data);
    return;
}
switch ($_GET['event']) {
    case 'check_key':
        $id           = $_GET['id'];
        $redis_key    = str_replace($prefix, "", $id) . '_msg';
        $lists        = $redis->keys($redis_key);
        $data['code'] = 0;
        $data['msg']  = 'success';
        $data['data'] = [
            "lists" => $lists,
        ];
        break;
    case 'lists':
        $lists        = $redis->keys($prefix."*");
        $data['code'] = 0;
        $data['msg']  = 'success';
        $key          = array_search($prefix."2", $lists);
        // print_r($lists);
        unset($lists[$key]);

        $lists = array_values($lists);
        ###
        $data['data'] = [
            "lists" => $lists,
        ];

        break;
    case 'getlist':
        if (isset($_GET['id'])) {
            $id           = $_GET['id'];
            $redis_key    = str_replace($prefix, "", $id) . '_msg';
            $lists        = $redis->lrange($redis_key, 0, 100);
            $data['code'] = 0;
            $data['msg']  = 'success';
            $data['data'] = [
                "lists" => array_reverse($lists),
            ];
        }

        break;
}

echo json_encode($data);
return;