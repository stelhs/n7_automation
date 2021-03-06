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
                 "\t\t open: open padlock. Args: [padlock number]\n" .
                 "\t\t\texample: $utility_name open 2\n" .
                 "\t\t close: close padlock. Args: [padlock number]\n" .
                 "\t\t\texample: $utility_name close\n" .
                 "\t\t stat: return current status.\n" .
                 "\t\t\texample: $utility_name stat\n" .
                 "\t\t restore_last_state: actualize padlock state after reboot.\n" .
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
    case "open":
    case "close":
        $padlock_num = isset($argv[2]) ? $argv[2] : 0;
        $new_port_state = $cmd == "open" ? 1 : 0;

        $ok = false;
        foreach (conf_padlocks() as $row) {
            if ($padlock_num && $padlock_num != $row['num'])
                continue;

            $ok = true;
            $rc = httpio($row['io'])->relay_set_state($row['io_port'], $new_port_state);
            if ($rc < 0)
                perror("Can't set relay state %d\n", $row['io_port']);
        }
        if (!$ok) {
            perror("Incorrect padlock number %d\n", $padlock_num);
            return -EINVAL;
        }
        return 0;

    case "stat":
        foreach (conf_padlocks() as $row) {
            $ret = httpio($row['io'])->relay_get_state($row['io_port']);
            if ($ret < 0) {
                perror("Can't get relay state %d\n", $row['io_port']);
                continue;
            }
            perror("\tpadlock %s %s\n", $row['name'], ($ret == "1" ? "opened" : "close"));
        }
        return 0;

    case "restore_last_state":
        foreach (conf_padlocks() as $row) {
            $result = db()->query(sprintf("SELECT state FROM io_output_actions " .
                                          "WHERE io_name='%s' AND port=%d " .
                                          "ORDER BY id DESC",
                                          $row['io'], $row['io_port']));
            if (!is_array($result) || (!isset($result['state'])))
                continue;

            $rc = httpio($row['io'])->relay_set_state($row['io_port'], $result['state']);
            if ($rc < 0)
                perror("Can't set relay state %d\n", $row['io_port']);
        }
        return 0;

    default:
        perror("Invalid arguments\n");
        $rc = -EINVAL;
    }

    return 0;
}


exit(main($argv));

