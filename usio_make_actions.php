#!/usr/bin/php
<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once 'common_lib.php';

define("MSG_LOG_LEVEL", LOG_NOTICE);

/* Calling by Usio daemon */
function main($argv) {
    if (count($argv) < 2) {
        perror("incorrect arguments\n");
        return;
    }

    $action = $argv[1];
    switch ($action) {
    case 'io_input':
        $action_port = $argv[2];
        $action_state = $argv[3];
        $content = file_get_contents(sprintf("http://localhost:400/ioserver" .
            "?io=usio1&port=%d&state=%d",
            $action_port,
            $action_state));
        pnotice("returned content: %s\n", $content);
        break;

    case 'restart':
        $states_list = get_stored_io_states();
        foreach ($states_list as $row)
            httpio($row['io_name'])->relay_set_state($row['port'], $row['state']);
        break;
    }
}


exit(main($argv));