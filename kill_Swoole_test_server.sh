ps aux | grep Swoole_test_server | awk '{print $2}' | xargs kill -9
