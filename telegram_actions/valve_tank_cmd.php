#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once 'config.php';
require_once 'guard_lib.php';
require_once 'telegram_api.php';
require_once 'config.php';
require_once 'valve_lib.php';
require_once 'httpio_lib.php';



function main($argv) {
    global $commands;

    if (count($argv) < 3) {
        printf("a few scripts parameters\n");
        return -EINVAL;
    }

    $user_id = strtolower(trim($argv[1]));
    $chat_id = strtolower(trim($argv[2]));
    $msg_id = strtolower(trim($argv[3]));
    $cmd = strtolower(trim($argv[4]));

    printf("user: %d, cmd: %s\n", $user_id, $cmd);

    $telegram = new Telegram_api();

    if ($user_id == 0) {
            $telegram->send_message($chat_id,
                "У вас недостаточно прав чтобы выполнить эту операцию\n", $msg_id);
            return 0;
    }

    $valve = new Valve(conf_valves()['tank']);

    if ($cmd == 1) {
        $telegram->send_message($chat_id, "Открываю...", $msg_id);
        $rc = $valve->open();
        switch ($rc) {
            case 0:
                $telegram->send_message($chat_id, "Кран бака успешно открыт", $msg_id);
                return 0;
            case 2:
                $telegram->send_message($chat_id, "Ошибка: кран остался в закрытом положении ", $msg_id);
                return 0;
            case 3:
                $telegram->send_message($chat_id, "Ошибка: кран остался в среднем положении ", $msg_id);
                return 0;
        }
        return 0;
    }

    $telegram->send_message($chat_id, "Закрываю...", $msg_id);
    $rc = $valve->close();
    switch ($rc) {
        case 0:
            $telegram->send_message($chat_id, "Кран бака успешно закрыт", $msg_id);
            return 0;
        case 2:
            $telegram->send_message($chat_id, "Ошибка: кран остался в открытом положении ", $msg_id);
            return 0;
        case 3:
            $telegram->send_message($chat_id, "Ошибка: кран остался в среднем положении ", $msg_id);
            return 0;
    }
    return 0;
}

return main($argv);
