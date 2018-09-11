<?php

require_once '/usr/local/lib/php/os.php';
require_once '/usr/local/lib/php/database.php';

class Valve {
    private $db;
    private $mio;

    function __construct($conf) {
        $this->conf = $conf;
    }

    function open()
    {
        $rc = 1;
        $state = httpio($this->conf['open_in_port']['io'])->input_get_state($this->conf['open_in_port']['port']);
        if ($state) {
            $status = "valve already opened";
            $rc = 0;
            goto out;
        }

        httpio($this->conf['open_out_port']['io'])->relay_set_state($this->conf['open_out_port']['port'], 1);
        $timeout = time() + 4;
        while (time() <= $timeout)
            sleep(1);
        httpio($this->conf['open_out_port']['io'])->relay_set_state($this->conf['open_out_port']['port'], 0);

        $open_state = httpio($this->conf['open_in_port']['io'])->input_get_state($this->conf['open_in_port']['port']);
        if ($open_state) {
            $status = "ok";
            $rc = 0;
            goto out;
        }

        $state = httpio($this->conf['close_in_port']['io'])->input_get_state($this->conf['close_in_port']['port']);
        if ($state) {
            $status = sprintf("error: can't open valve '%s': valve is closed now", $this->conf['name']);
            $rc = 2;
        } else {
            $status = sprintf("error: can't open valve '%s': valve is not closed now", $this->conf['name']);
            $rc = 3;
        }

out:
        printf("%s\n", $status);
        db()->insert('valves_actions', ['name' => $this->conf['name'],
                                        'action' => 'open',
                                        'status' => $status]);
        return $rc;
    }


    function close()
    {
        $rc = 1;
        $state = httpio($this->conf['close_in_port']['io'])->input_get_state($this->conf['close_in_port']['port']);
        if ($state) {
            $status = "valve already closed";
            $rc = 0;
            goto out;
        }

        httpio($this->conf['close_out_port']['io'])->relay_set_state($this->conf['close_out_port']['port'], 1);
        $timeout = time() + 4;
        while (time() <= $timeout)
            sleep(1);
        httpio($this->conf['close_out_port']['io'])->relay_set_state($this->conf['close_out_port']['port'], 0);

        $open_state = httpio($this->conf['close_in_port']['io'])->input_get_state($this->conf['close_in_port']['port']);
        if ($open_state) {
            $status = "ok";
            $rc = 0;
            goto out;
        }

        $state = httpio($this->conf['open_in_port']['io'])->input_get_state($this->conf['open_in_port']['port']);
        if ($state) {
            $status = sprintf("error: can't close valve '%s': valve is opened now", $this->conf['name']);
            $rc = 2;
        } else {
            $status = sprintf("error: can't close valve '%s': valve is not opened now", $this->conf['name']);
            $rc = 3;
        }

out:
        printf("%s\n", $status);
        db()->insert('valves_actions', ['name' => $this->conf['name'],
                                        'action' => 'close',
                                        'status' => $status]);
        return $rc;
    }


    function status()
    {
        $state_open = httpio($this->conf['open_in_port']['io'])->input_get_state($this->conf['open_in_port']['port']);
        $state_close = httpio($this->conf['open_in_port']['io'])->input_get_state($this->conf['close_in_port']['port']);

        if (!$state_open && !$state_close)
            return "not_defined";

        if ($state_open && $state_close)
            return "error";

        if ($state_open && !$state_close)
            return "opened";

        if (!$state_open && $state_close)
            return "close";
    }
}
