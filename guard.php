#!/usr/bin/php
<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once '/usr/local/lib/php/database.php';

require_once 'config.php';
require_once 'mod_io_lib.php';
require_once 'guard_lib.php';
require_once 'sequencer_lib.php';

$utility_name = $argv[0];

function print_help()
{
    global $utility_name;
    echo "Usage: $utility_name <command> <args>\n" . 
             "\tcommands:\n" .
                 "\t\t state: set guard state. Args: sleep/ready, method\n" . 
                 "\t\t\texample: $utility_name state sleep\n" .
                 "\t\t alarm: Execute ALARM. Args: action_id\n" . 
                 "\t\t\texample: $utility_name alarm 71\n" .
    
             "\n\n";
}



function main($argv)
{
    $rc = 0;
    if (!isset($argv[1])) {
        return -EINVAL;
    }

    $cmd = $argv[1];
    
    $db = new Database;
    $rc = $db->connect(conf_db());
    if ($rc) {
        printf("can't connect to database");
        return -EBASE;
    }
    
    $mio = new Mod_io($db);
    
    switch ($cmd) {
    case "state":
        if (!isset($argv[2])) {
            printf("Invalid arguments: sleep/ready argument is not set\n");
            $rc = -EINVAL;
            goto out;
        }        
        $new_mode = $argv[2];
        
        $method = 'cli';
        if (isset($argv[3]))
            $method = $argv[3];
        
        switch ($new_mode) {
        case "sleep":
            msg_log(LOG_NOTICE, "Guard stoped by " . $method);
            // two beep by sirena
            sequncer_stop(conf_guard()['sirena_io_port']);
            sequncer_start(conf_guard()['sirena_io_port'],
                           array(100, 100, 100, 0));
                           
            notify_send_by_sms('guard_disable', array('method' => $method));

            $db->insert('guard_states', 
                  array('state' => 'sleep',
                        'method' => $method));
            goto out;

            
        case "ready":
            msg_log(LOG_NOTICE, "Guard started by " . $method);
            
            sequncer_stop(conf_guard()['sirena_io_port']);
            $sensors = $db->query('SELECT * FROM sensors');
            
            // check for incorrect sensor value state
            $ignore_sensors_list = [];
            foreach ($sensors as $sensor) {
                if (get_sensor_locking_mode($db, $sensor['id']) == 'lock')
                    continue;
    
                $port_state = $mio->input_get_state($sensor['port']);
                if ($port_state != $sensor['normal_state'])
                    $ignore_sensors_list[] = $sensor['id'];
            }
            
            if (!count($ignore_sensors_list))
                // one beep by sirena
                sequncer_start(conf_guard()['sirena_io_port'],
                               array(200, 0));
            else            
                // two beep by sirena
                sequncer_start(conf_guard()['sirena_io_port'],
                               array(200, 200, 1000, 0));
            
                           
            notify_send_by_sms('guard_enable', 
                               array('method' => $method,
                                     'ignore_sensors' => $ignore_sensors_list));

            $db->insert('guard_states', 
                        array('state' => 'ready',
                              'method' => $method,
                              'ignore_sensors' => $ignore_sensors_list));
            goto out;
            
        default:
            printf("Invalid arguments: sleep/ready argument is not correct\n");
            $rc = -EINVAL;
        }

        
    case "alarm":
        if (!isset($argv[2])) {
            printf("Invalid arguments: action_id argument is not set\n");
            $rc = -EINVAL;
            goto out;
        }        
        $action_id = $argv[2];
        
        $action = $db->query('SELECT * FROM sensor_actions WHERE id = ' . $action_id);
        if (!is_array($action)) {
            printf("Invalid arguments: Incorrect action_id. action_id not found in DB\n");
            $rc = -EINVAL;
            goto out;
        }
        
        $sensor = sensor_get_by_io_id($action['sense_id']);
        
        run_cmd(sprintf('../snapshot.php %s %d_',
                        conf_guard()['camera_dir'], $action_id));
                        
        // run sirena
        sequncer_stop(conf_guard()['sirena_io_port']);
        sequncer_start(conf_guard()['sirena_io_port'],
                       array(conf_guard()['sirena_timeout'] * 1000, 0));

        // run lighter if night
        $day_night = get_day_night($db);
        if ($day_night == 'night') {
            $light_interval = conf_guard()['light_ready_timeout'] * 1000;
            sequncer_stop(conf_guard()['lamp_io_port']);
            sequncer_start(conf_guard()['lamp_io_port'], 
                           array($light_interval, 0));
        }
                       
        $db->insert('guard_alarms', 
                    array('action_id' => $action_id));
    
        // send SMS
        notify_send_by_sms('alarm', array('sensor' => $sensor['name'],
                                          'action_id' => $action_id));
        goto out;
    }

out:    
    $db->close();
    return $rc;    
}


$rc = main($argv);
if ($rc) {
    print_help();
}
