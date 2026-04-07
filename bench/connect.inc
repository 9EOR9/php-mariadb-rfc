<?php

    $host      = getenv("MYSQL_TEST_HOST")     ?: "127.0.0.1";
    $port      = getenv("MYSQL_TEST_PORT")     ?: 3306;
    $user      = getenv("MYSQL_TEST_USER")     ?: "root";
    $passwd    = getenv("MYSQL_TEST_PASSWD")   ?: "";
    $db        = getenv("MYSQL_TEST_DB")       ?: "test";
    $engine    = getenv("MYSQL_TEST_ENGINE")   ?: "InnoDB";
    $socket    = getenv("MYSQL_TEST_SOCKET")   ?: null;

?>
