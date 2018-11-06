#!/usr/bin/php
<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once '/usr/local/lib/php/database.php';

require_once 'config.php';
$utility_name = $argv[0];

function print_help()
{
    global $utility_name;
    echo "Usage: $utility_name <path_for_store> <prefix>\n" .
             "\tMake cameras snapshots into <path_for_store>.\n" .
             "\tResult files name used prefix <prefix>\n" .
             "\t\tExample:\n" .
             "\t\t\t $utility_name /var/spool/sr90_automation/images/alarm_actions 12_\n" .
             "\t\tStored files into this directory with names: 12_cam1.jpg, 12_cam2.jpg,\n" .
    	"\n\n";
}



function main($argv)
{
    if (!isset($argv[1]))
        return -1;

    $path_for_store = trim($argv[1]);
    $prefix = isset($argv[2]) ? trim($argv[2]) : "";

    $result = 0;
    foreach (conf_guard()['video_cameras'] as $cam) {
        $cmd = 'ffmpeg -f video4linux2 -i ' . $cam['v4l_dev'] .
               ' -vf scale=' . $cam['resolution'] .
               ' -vframes 1 ' .
               $path_for_store . '/' . $prefix . 'cam_' . $cam['id'] . '.jpeg';
        $ret = run_cmd($cmd);
        if ($ret['rc']) {
            perror("Can't create snapshot for camera %d %s\n",
                                     $cam['id'], $cam['v4l_dev']);
        }
        $result |= $ret['rc'];
    }

    if ($result) {
        perror("Can't create snapshot\n");
        return -1;
    }

    return 0;
}


$rc = main($argv);
if ($rc) {
    print_help();
    exit($rc);
}
