#!/usr/bin/php
<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once '/usr/local/lib/php/database.php';
require_once 'telegram_api.php';
require_once 'config.php';

define("TELEGRAM_ACTIONS_DIR", "telegram_actions/");
define("MSG_LOG_LEVEL", LOG_NOTICE);


$commands = [
                ['cmd' => ['включи охрану', 'guard on'],
                 'script' => 'guard_commands.php', 'args' => 'on'],

                ['cmd' => ['отключи охрану', 'выключи охрану', 'guard off'],
                 'script' => 'guard_commands.php', 'args' => 'off'],

                ['cmd' => ['перезагрузись', 'reboot'],
                 'script' => 'reboot_cmd.php', 'wr' => 1],

                ['cmd' => ['статус', 'stat'],
                 'script' => 'stat_cmd.php'],

                ['cmd' => ['скажи', 'tell'],
                 'script' => 'tell_cmd.php', 'wr' => 1],

                ['cmd' => ['включи автоматику бака'],
                 'script' => 'automatic_fill_tank_cmd.php', 'args' => 1],

                ['cmd' => ['отключи автоматику бака'],
                 'script' => 'automatic_fill_tank_cmd.php', 'args' => 0, 'wr' => 1],

                ['cmd' => ['открой кран бака'],
                 'script' => 'valve_tank_cmd.php', 'args' => 1],

                ['cmd' => ['закрой кран бака'],
                 'script' => 'valve_tank_cmd.php', 'args' => 0, 'wr' => 1],

                ['cmd' => ['видео'],
                 'script' => 'video_cmd.php', 'args' => 0],

                ['cmd' => ['видео по событию'],
                 'script' => 'video_by_action_cmd.php', 'args' => 0],
];

function mk_help()
{
    global $commands;

    foreach($commands as $row) {
        $msg .= sprintf("   - 'skynet %s'\n", $row['cmd'][0]);
        if (isset($row['wr']))
            for ($i = 0; $i < $row['wr']; $i++)
                $msg .= "\n";
    }

    return $msg;
}

function check_for_marazm($msg_text)
{
    $skynet_found = false;
    if (mb_strstr($msg_text, "скайнет", false, 'utf8') ||
        mb_strstr($msg_text, "skynet", false, 'utf8') ||
        mb_strstr($msg_text, "sky.net", false, 'utf8'))
            $skynet_found = true;

    if (!$skynet_found)
        return NULL;

    if (!mb_strstr($msg_text, "маразм", false, 'utf8'))
        return NULL;

    $content = file_get_contents('marazm_response.txt');
    $rows = string_to_rows($content);
    srand(time());
    $rand_key = array_rand($rows, 1);
    return $rows[$rand_key];
}

function main($argv) {
    global $commands;

    $rc = 0;
    if (count($argv) < 4)
        return -EINVAL;

    $from_user_id = strtolower(trim($argv[1]));
    $chat_id = strtolower(trim($argv[2]));
    $msg_text = mb_strtolower(trim($argv[3]), 'utf8');
    $msg_id = isset($argv[4]) ? strtolower(trim($argv[4])) : 0;

    $telegram = new Telegram_api();

//    $telegram->send_message($chat_id, sprintf("from_user_id = %s, chat_id = %s, msg_text = %s, msg_id = %s",
//                                               $from_user_id, $chat_id, $msg_text, $msg_id), $msg_id);

    $marazm_resp = check_for_marazm($msg_text);
    if ($marazm_resp) {
        $telegram->send_message($chat_id, $marazm_resp . "\n", $msg_id);
        return 0;
    }

    $words = string_to_words($msg_text, " \t:;+-=");
    if (!$words)
        return -EINVAL;

    $arg1 = $words[0];
    $arg2 = $words[1];
    if ($arg1 != "skynet" && $arg1 != "sky.net" && $arg1 != "скайнет")
        return -EINVAL;
    if ($arg2 == "голос") {
        $telegram->send_message($chat_id, "Слушаю вас внимательно\n", $msg_id);
        return 0;
    }

    if ($arg2 == "команды" || $arg2 == "что умеешь?" || $arg2 == "help") {
        $telegram->send_message($chat_id, "Я умею следующее:\n\n" . mk_help(), $msg_id);
        return 0;
    }

    $query = mb_strtolower(array_to_string($words, ' '), 'utf8');
    pnotice("query = %s\n", $query);

    $script = NULL;
    $args = '';
    foreach ($commands as $row)
        foreach ($row['cmd'] as $cmd) {
            $p = strpos($query, $cmd);
            if ($p === FALSE)
                continue;

            $script = $row['script'];
            $args = $row['args'] . substr($query, $p + strlen($cmd));
            break;
        }
    pnotice("script = %s\n", $script);
    pnotice("args = %s\n", $args);

    if (!$script) {
        if (!$arg2)
            $telegram->send_message($chat_id, "Что сделать?\n\nВозможные варианты:\n" . mk_help(), $msg_id);
        else {
            $telegram->send_message($chat_id, "Не поняла\n\n", $msg_id);
            perror("can't recognize query\n");
        }
        return -EINVAL;
    }

    $cmd = sprintf("%s '%s' '%s' '%s' '%s'", TELEGRAM_ACTIONS_DIR . $script,
    $from_user_id, $chat_id, $msg_id, $args);

    $ret = run_cmd($cmd);
    if ($ret['rc']) {
        perror("script %s: return error: %s\n", $script, $ret['log']);
        $telegram->send_message($chat_id, "Ошибка: " . $ret['log'], $msg_id);
        return -EINVAL;
    }

    return 0;
}

exit(main($argv));
