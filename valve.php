#!/usr/bin/php
<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once '/usr/local/lib/php/database.php';

require_once 'config.php';
require_once 'httpio_lib.php';
require_once 'valve_lib.php';
$utility_name = $argv[0];

function print_help()
{
    global $utility_name;
    echo "Usage: $utility_name <command> <args>\n" .
             "\tcommands:\n" .
                 "\t\t open: open valve. Args: <valve_name>\n" .
                 "\t\t\texample: $utility_name open tank\n" .
                 "\t\t close: close valve. Args: <valve_name>\n" .
                 "\t\t\texample: $utility_name close tank\n" .
                 "\t\t stat: get current valve status. Args: <valve_name>\n" .
                 "\t\t\texample: $utility_name stat tank\n" .
                 "\n\n";
}



function main($argv)
{
    $rc = 0;
    @$cmd = $argv[1];

    @$valve_name = $argv[2];
    if (!isset(conf_valves()[$valve_name])) {
        printf("Invalid argument: can't find valve with name %s\n", $valve_name);
        print_help();
        goto err;
    }

    $valve = new Valve(conf_valves()[$valve_name]);

    switch ($cmd) {
    case 'open':
        if (!isset($argv[2])) {
            printf("Invalid argument: name argument is not set\n");
            goto err;
        }

        $rc = $valve->open();
        if ($rc) {
            printf("Can't open valve\n");
            return $rc;
        }
        return 0;

    case 'close':
        if (!isset($argv[2])) {
            printf("Invalid argument: name argument is not set\n");
            goto err;
        }

        $rc = $valve->close();
        if ($rc) {
            printf("Can't close valve\n");
            return $rc;
        }
        return 0;

    case 'stat':
        if (!isset($argv[2])) {
            printf("Invalid argument: name argument is not set\n");
            goto err;
        }

        printf("Current status: %s\n", $valve->status());
        return 0;

    default:
        print_help();
    }

    return $rc;

err:
    return -EINVAL;
}



return main($argv);



?>