#!/usr/bin/php
<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';

require_once 'config.php';
require_once 'avreg_lib.php';
$utility_name = $argv[0];


function print_help()
{
    global $utility_name;
    echo "Usage: $utility_name <command> <args>\n" .
             "\tcommands:\n" .
             "\t$utility_name alarm <alarm_id> [alarm_timestamp] - Send all camera videos associated with alarm_id to sr90.org and send links to Telegram\n" .
             "\t\tExample:\n" .
             "\t\t\t $utility_name alarm 27 timestamp\n" .
    "\n\n";
}


function main($argv)
{
    $rc = 0;
    if (!isset($argv[1])) {
        return -EINVAL;
    }

    $mode = $argv[1];

    switch ($mode) {
    case 'alarm':
        $alarm_id = $argv[2];
        $alarm_timestamp = isset($argv[3]) ? $argv[3] : time();

        run_cmd(sprintf("./telegram.php msg_send_all 'Загружаю видео файлы по событию %d, ожидайте...'",
                        $alarm_id));
        foreach(conf_guard()['video_cameras'] as $cam) {
            $video_files = get_video_files($alarm_timestamp - 10, 20, $cam['name']);
            if (!$video_files || !count($video_files)) {
                run_cmd(sprintf("./telegram.php msg_send_all 'Неудалось получить видеофайлы для камеры %s'",
                                $cam['name']));
                perror("Can't get videos for camera %s\n", $cam['name']);
                continue;
            }
            $cnt = 0;
            foreach ($video_files as $file) {
                $cnt ++;
                if ($cnt > 15) {
                     perror("To many video files\n");
                     return -1;
                }
                dump($file);
                $server_filename = sprintf("%d_%d_%s", $alarm_id, $cam['id'], basename($file['file']));
                $ret = run_cmd(sprintf('scp %s stelhs@sr90.org:/storage/www/plato_marazm/alarm_video/%s',
                                   $file['file'], $server_filename));
                if ($ret['rc']) {
                    run_cmd(sprintf("./telegram.php msg_send_all 'Неудалось загрузить видеофайл %s для камеры %s: %s'",
                                    $file['file'], $cam['name'], $ret['log']));
                    perror("Can't upload videos for camera %s: %s\n", $cam['name'], $ret['log']);
                    continue;
                }

                run_cmd(sprintf("./telegram.php msg_send_all 'Видео запись события %d: Камера %d:\n http://sr90.org/plato_marazm/alarm_video/%s'",
                                    $alarm_id, $cam['id'], $server_filename));
            }
        }
        run_cmd(sprintf("./telegram.php msg_send_all 'Процесс загрузки видео по событию %d завершен'",
                        $alarm_id));
        goto out;

    default:
        $rc = -EINVAL;
    }

out:
    return $rc;
}

$rc = main($argv);
if ($rc) {
    print_help();
    exit($rc);
}
