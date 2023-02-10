#!/usr/bin/php
<?php

require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/database.php';
require_once 'config.php';
require_once 'httpio_lib.php';
require_once 'valve_lib.php';
require_once 'guard_lib.php';
require_once 'common_lib.php';

define("PUMP_WELL_ENABLED_FILE", "/tmp/well_pump");


function is_filling_enable()
{
    return file_exists(PUMP_WELL_ENABLED_FILE);
}

function disable_automatic_fill_tank()
{
    file_put_contents(AUTOMATIC_FILL_TANK_DISABLED_FILE, "");
    run_cmd(sprintf("./telegram.php msg_send_all 'Автоматика наполнения бака отключена'"));
}

function filling_enable()
{
    file_put_contents(PUMP_WELL_ENABLED_FILE, time());
    httpio(conf_guard()['shower_pump_io_port']['io'])->relay_set_state(conf_guard()['shower_pump_io_port']['port'], 1);
}

function filling_disable($valve)
{
    httpio(conf_guard()['shower_pump_io_port']['io'])->relay_set_state(conf_guard()['shower_pump_io_port']['port'], 0);
    unlink(PUMP_WELL_ENABLED_FILE);
}


function main($argv) {
    global $guard_state;
    $guard_state = get_global_status()['guard_stat']['state'];

    if (is_automatic_fill_tank_disable()) {
	printf("filling tank is disabled\n");
        if (is_filling_enable())
            filling_disable();
        return 0;
    }

    // tank is filling now?
    if (is_filling_enable()) {
	printf("filling enabled\n");
        $start_time = file_get_contents(PUMP_WELL_ENABLED_FILE);
        if ((time() - $start_time) > (1 * 60 * 60)) {
            run_cmd(sprintf("./telegram.php msg_send_all 'Отключен скваженный насос: За два часа бак не наполнился'"));
            disable_automatic_fill_tank();
            return 0;
        }

        // top float is achieved?
        $top_float_state = httpio(conf_tank()['top_float_port']['io'])->input_get_state(conf_tank()['top_float_port']['port']);
        if ($top_float_state == 1)
            filling_disable();
        return 0;
    }

    // bottom float is not achieved?
    $bottom_float_state = httpio(conf_tank()['bottom_float_port']['io'])->input_get_state(conf_tank()['bottom_float_port']['port']);
    if ($bottom_float_state == 1) {
        filling_enable();
        return 0;
    }
}

return main($argv);
