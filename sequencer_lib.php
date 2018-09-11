<?php

require_once '/usr/local/lib/php/os.php';

@define("PID_DIR", getenv('HOME') . '/');

function sequncer_start($io_name, $port, $sequence)
{
    $pid_file = sprintf(PID_DIR . 'seq_%s_%d.pid', $io_name, $port);
    stop_daemon($pid_file);

    $cmd = sprintf('./sequencer.php %s %d', $io_name, $port);
    foreach ($sequence as $time)
        $cmd .= ' ' . $time;

    run_daemon($cmd, $pid_file);
}

function sequncer_stop($io_name, $port)
{
    $pid_file = sprintf(PID_DIR . 'seq_%s_%d.pid', $io_name, $port);
    stop_daemon($pid_file);
}