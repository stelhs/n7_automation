#!/usr/bin/php
<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once '/usr/local/lib/php/database.php';

require_once 'config.php';
require_once 'httpio_lib.php';
require_once 'guard_lib.php';
require_once 'sequencer_lib.php';
require_once 'common_lib.php';

$utility_name = $argv[0];

function print_help()
{
    global $utility_name;
    echo "Usage: $utility_name <command> <args>\n" .
             "\tcommands:\n" .
                 "\t\t enable: enable pump system.\n" .
                 "\t\t\texample: $utility_name enable\n" .
                 "\t\t close: disable pump system.\n" .
                 "\t\t\texample: $utility_name disable\n" .
                 "\t\t stat: return current status.\n" .
                 "\t\t\texample: $utility_name stat\n" .
                 "\t\t restore_last_state: actualize pump system state after reboot.\n" .
                 "\t\t\texample: $utility_name restore_last_state\n" .
    "\n\n";
}


function main($argv)
{
    if (!isset($argv[1])) {
        print_help();
        return -EINVAL;
    }
    $cmd = strtolower($argv[1]);

    switch ($cmd) {
    case "enable":
    case "disable":
        $io = conf_guard()['home_water_io_port'];
        $new_port_state = $cmd == "enable" ? 1 : 0;

        $ok = false;

        $rc = httpio($io['io'])->relay_set_state($io['port'], $new_port_state);
        if ($rc < 0)
            perror("Can't set relay state %d\n", $io['port']);

        return 0;

    case "stat":
        $io = conf_guard()['home_water_io_port'];
        $ret = httpio($io['io'])->relay_get_state($io['port']);
        if ($ret < 0) {
            perror("Can't get relay state %d\n", $io['port']);
            continue;
        }
        perror("\tpump system is %s\n", ($ret == "1" ? "enabled" : "disabled"));
        return 0;

    case "restore_last_state":
        $io = conf_guard()['home_water_io_port'];
        $result = db()->query(sprintf("SELECT state FROM io_output_actions " .
                                      "WHERE io_name='%s' AND port=%d " .
                                      "ORDER BY id DESC",
                                      $io['io'], $io['port']));
        if (!is_array($result) || (!isset($result['state'])))
            continue;

        $rc = httpio($io['io'])->relay_set_state($io['port'], $result['state']);
        if ($rc < 0)
            perror("Can't set relay state %d\n", $io['port']);
        return 0;

    default:
        perror("Invalid arguments\n");
        $rc = -EINVAL;
    }

    return 0;
}


exit(main($argv));

