#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/database.php';
require_once 'config.php';
$utility_name = $argv[0];

function main($argv) {
    if (count($argv) < 3) {
        printf("a few scripts parameters\n");
        return -EINVAL;
    }

    $io_name = $argv[1];
    $port = $argv[2];
    $port_state = $argv[3];

    if ($io_name != 'usio1' || $port != 3)
        return -1;

    if ($port_state == 0) {
        // enable guard
        $ret = run_cmd("./guard.php state ready ext_guard 3");
        if ($ret['rc']) {
            msg_log(LOG_ERR, sprintf("script %s: return error: %s\n",
                                     $script, $ret['log']));
            return -1;
        }
        return 0;
    }

    // disable guard
    $ret = run_cmd("./guard.php state sleep ext_guard 3");
    if ($ret['rc']) {
        msg_log(LOG_ERR, sprintf("script %s: return error: %s\n",
                                 $script, $ret['log']));
        return -1;
    }

    return 0;
}


return main($argv);
