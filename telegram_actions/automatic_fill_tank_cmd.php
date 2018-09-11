#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once 'config.php';
require_once 'guard_lib.php';
require_once 'telegram_api.php';
require_once 'config.php';


define("AUTOMATIC_FILL_TANK_DISABLED_FILE", "/var/run/automatic_fill_tank_disabled");

function enable_automatic_fill_tank()
{
    @unlink(AUTOMATIC_FILL_TANK_DISABLED_FILE);
}

function disable_automatic_fill_tank()
{
    file_put_contents(AUTOMATIC_FILL_TANK_DISABLED_FILE, "");
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

    if ($cmd == 1) {
        enable_automatic_fill_tank();
        $telegram->send_message($chat_id, "Автоматика наполнения воды в бак включена", $msg_id);
        return 0;
    }

    $telegram->send_message($chat_id, "Автоматика наполнения воды в бак отключена", $msg_id);
    disable_automatic_fill_tank();
    return 0;
}


return main($argv);
