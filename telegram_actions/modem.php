#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once 'config.php';
require_once 'guard_lib.php';
require_once 'common_lib.php';
require_once 'telegram_api.php';
require_once 'config.php';

function main($argv) {
    $user_id = strtolower(trim($argv[1]));
    $chat_id = strtolower(trim($argv[2]));
    $msg_id = strtolower(trim($argv[3]));
    $cmd = strtolower(trim($argv[4]));

    $telegram = new Telegram_api();
    if ($user_id == 0) {
        $telegram->send_message($chat_id,
            "У вас недостаточно прав чтобы выполнить эту операцию\n", $msg_id);
        return 0;
    }

    switch ($cmd) {
    case 'modem_2':
        $msg = "Готово. Переключен на основной модем.";
        run_cmd("./inet_switch.sh 2");
        run_cmd("killall -9 ssh");
        $telegram->send_message($chat_id, $msg, $msg_id);
        break;

    case 'modem_1':
        $msg = "Готово. Переключен на дополнительный модем.";
        run_cmd("./inet_switch.sh 1");
        run_cmd("killall -9 ssh");
        $telegram->send_message($chat_id, $msg, $msg_id);
        break;
    default:
    }
}

exit(main($argv));
