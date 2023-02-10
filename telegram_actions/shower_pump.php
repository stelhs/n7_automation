#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once 'config.php';
require_once 'guard_lib.php';
require_once 'common_lib.php';
require_once 'telegram_api.php';
require_once 'config.php';


function shower_pump_enable() {
    httpio(conf_guard()['shower_pump_io_port']['io'])->relay_set_state(conf_guard()['shower_pump_io_port']['port'], 1);
}

function shower_pump_disable() {
    httpio(conf_guard()['shower_pump_io_port']['io'])->relay_set_state(conf_guard()['shower_pump_io_port']['port'], 0);
}

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

    if ($cmd == 'enable') {
        shower_pump_enable();
        $telegram->send_message($chat_id, "Насос в душе включен", $msg_id);
        return 0;
    }

    $telegram->send_message($chat_id, "Насос в душе отключен", $msg_id);
    shower_pump_disable();
    return 0;
}


return main($argv);
