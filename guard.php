#!/usr/bin/php
<?php
require_once '/usr/local/lib/php/common.php';
require_once '/usr/local/lib/php/os.php';
require_once '/usr/local/lib/php/database.php';

require_once 'config.php';
require_once 'common_lib.php';
require_once 'httpio_lib.php';
require_once 'guard_lib.php';
require_once 'player_lib.php';
require_once 'sequencer_lib.php';

$utility_name = $argv[0];

function print_help()
{
    global $utility_name;
    echo "Usage: $utility_name <command> <args>\n" .
             "\tcommands:\n" .
                 "\t\t state: set guard state. Args: sleep/ready, method, user_id, [sms]\n" .
                 "\t\t\texample: $utility_name state sleep cli 1 sms\n" .
                 "\t\t alarm: Execute ALARM. Args: action_id\n" .
                 "\t\t\texample: $utility_name alarm 71\n" .
                 "\t\t stat: Return status information about Guard system\n" .

             "\n\n";
}



function main($argv)
{
    $rc = 0;
    if (!isset($argv[1])) {
        return -EINVAL;
    }

    $cmd = $argv[1];

    switch ($cmd) {
    case "state":
        if (!isset($argv[2])) {
            perror("Invalid arguments: sleep/ready argument is not set\n");
            return -EINVAL;
        }
        $new_mode = $argv[2];

        $method = 'cli';
        if (isset($argv[3]))
            $method = $argv[3];

        switch ($new_mode) {
        case "sleep":
            $user_id = 1; // stelhs
            if (isset($argv[4]))
                $user_id = $argv[4];

            $with_sms = false;
            if (isset($argv[5]) && trim($argv[5]) == 'sms')
                $with_sms = true;

            $user = user_get_by_id($user_id);
            $user_name = $user['name'];

            player_start('sounds/unlock.wav', 90);

            pnotice("Guard stoped by %s\n", $method);

            // open all padlocks
            $ret = run_cmd('./padlock.php open');
            pnotice("open all padlocks: %s\n", $ret['log']);

            // enable well pump system
 //           httpio(conf_guard()['home_water_io_port']['io'])->relay_set_state(conf_guard()['home_water_io_port']['port'], 1);
            run_cmd(sprintf("./pump_system.php enable"));

            // two beep by sirena
            $io_port = conf_guard()['sirena_io_port'];
            sequncer_stop($io_port['io'], $io_port['port']);
            sequncer_start($io_port['io'], $io_port['port'],
                array(100, 100, 100, 0));

            $state_id = db()->insert('guard_states',
                                    ['state' => 'sleep',
                                     'user_id' => $user_id,
                                     'method' => $method]);

            $stat_text = format_global_status_for_sms(get_global_status());
            perror("Guard set sleep\n");

            telegram_send('guard_disable', ['user' => $user['name'],
                                            'method' => $method]);
            run_cmd(sprintf("./image_sender.php current"));

            if ($method == 'cli') {
                perror("stat: %s\n", $stat_text);
                return 0;
            }

            if ($with_sms)
                sms_send('guard_disable',
                         ['user_id' => $user_id, 'groups' => ['sms_observer']],
                         ['method' => $method,
                          'user_name' => $user_name,
                          'state_id' => $state_id,
                          'global_status' => $stat_text]);

            return 0;


        case "ready":
            $user_id = 1; // stelhs
            if (isset($argv[4]))
                $user_id = $argv[4];

            $with_sms = false;
            if (isset($argv[5]) && trim($argv[5]) == 'sms')
                $with_sms = true;

            $user = user_get_by_id($user_id);
            $user_name = $user['name'];

            player_start('sounds/lock.wav', 75);

            pnotice("Guard started by %s\n", $method);

            // close all padlocks
            $ret = run_cmd('./padlock.php close');
            perror("close all padlocks: %s\n", $ret['log']);

            // disable well pump system
//            httpio(conf_guard()['home_water_io_port']['io'])->relay_set_state(conf_guard()['home_water_io_port']['port'], 0);
            run_cmd(sprintf("./pump_system.php disable"));

            // check for incorrect sensors value state
            $zones = conf_guard()['zones'];
            $ignore_zones_list = [];
            foreach ($zones as $zone) {
                if (get_zone_locking_mode($zone['id']) == 'lock')
                        continue;

                $total_ports_cnt = 0;
                $incorrect_ports_cnt = 0;
                foreach ($zone['sensors'] as $row) {
                    $total_ports_cnt++;
                    $state = httpio($row['io'])->input_get_state($row['port']);
                    if ($state != $row['normal_state'])
                        $incorrect_ports_cnt++;
                }
                if ($incorrect_ports_cnt == $total_ports_cnt)
                    $ignore_zones_list[] = $zone;
            }

            if (count($ignore_zones_list)) {
                $text = "не закрыты ";
                foreach ($ignore_zones_list as $zone)
                    $text .= $zone['name'] . ' ';
                run_cmd(sprintf('./text_spech.php \'%s\' 0', $text));
                player_start(['sounds/text.wav'], 75);
            }

            $io_port = conf_guard()['sirena_io_port'];
            sequncer_stop($io_port['io'], $io_port['port']);
            if (!count($ignore_zones_list)) {
                // one beep by sirena
                sequncer_start($io_port['io'], $io_port['port'], array(200, 0));
            } else {
                // two beep by sirena
                sequncer_start($io_port['io'], $io_port['port'],
                               array(200, 200, 1000, 0));
            }


            $ignore_zones_list_id = [];
            foreach ($ignore_zones_list as $zone)
                $ignore_sensors_list_id[] = $zone['id'];

            $state_id = db()->insert('guard_states',
                                    ['state' => 'ready',
                                     'method' => $method,
                                     'user_id' => $user_id,
                                     'ignore_zones' => array_to_string($ignore_zones_list_id)]);

            $stat_text = format_global_status_for_sms(get_global_status());
            perror("Guard set ready\n");

            telegram_send('guard_enable', ['user' => $user['name'],
                                           'method' => $method]);
            run_cmd(sprintf("./image_sender.php current"));

            if ($method == 'cli') {
                perror("stat: %s\n", $stat_text);
                return 0;
            }

            if (($with_sms || count($ignore_zones_list_names)) &&
                                                    $argv[3] != 'telegram')
                sms_send('guard_enable',
                         ['user_id' => $user_id, 'groups' => ['sms_observer']],
                         ['method' => $method,
                          'user_name' => $user_name,
                          'state_id' => $state_id,
                          'global_status' => $stat_text]);

            return 0;

        default:
            perror("Invalid arguments: sleep/ready argument is not correct\n");
            return -EINVAL;
        }


    case "alarm":
        if (!isset($argv[2])) {
            perror("Invalid arguments: action_id argument is not set\n");
            return -EINVAL;
        }
        $guard_action_id = $argv[2];

        $action = db()->query('SELECT * FROM guard_actions WHERE id = ' . $guard_action_id);
        if (!is_array($action)) {
            perror("Invalid arguments: Incorrect guard_action_id. guard_action_id not found in DB\n");
            return -EINVAL;
        }

        // run sirena
        $zone = zone_get_by_io_id($action['zone_id']);
        //player_start('sounds/siren.wav', 100, $zone['alarm_time']);
        sequncer_stop(conf_guard()['sirena_io_port']['io'], conf_guard()['sirena_io_port']['port']);
        sequncer_start(conf_guard()['sirena_io_port']['io'],
                       conf_guard()['sirena_io_port']['port'],
                       [$zone['alarm_time'] * 1000, 0]);

        // run lighter if night
        $day_night = get_day_night();
        if ($day_night == 'night' &&
                conf_guard()['light_mode'] == 'by_sensors') {
            $ret = run_cmd(sprintf("./street_light.php enable %d",
                                   conf_guard()['light_ready_timeout']));
            pnotice("enable lighter for timeout %d: %s\n",
                    conf_guard()['light_ready_timeout'], $ret['log']);
        }

        // make snapshots
        $ret = run_cmd(sprintf("./snapshot.php '%s' %d_",
                        conf_guard()['alarm_snapshot_dir'], $guard_action_id));
        pnotice("make snapshots: %s\n", $ret['log']);

        // send to Telegram
        telegram_send('alarm', ['zone' => $zone['name'],
                                'action_id' => $guard_action_id]);

        // send photos
        $ret = run_cmd(sprintf("./image_sender.php alarm %d", $guard_action_id));
        pnotice("send images to sr90: %s\n", $ret['log']);

        // send videos
        $row = db()->query('SELECT UNIX_TIMESTAMP(created) as timestamp ' .
                           'FROM guard_actions WHERE id = ' . $guard_action_id);
/*        run_cmd(sprintf("./video_sender.php alarm %d %d",
                        $guard_action_id,
                        $row['timestamp']),
                        true);*/

        // send SMS
        sms_send('alarm',
                 ['groups' => ['guard_alarm']],
                 ['zone' => $zone['name'],
                  'action_id' => $guard_action_id]);

        perror("Guard set Alarm\n");
        return 0;

    case 'stat':
        $guard_state = get_guard_state();
        dump($guard_state);
        $stat_text = format_global_status_for_sms(get_global_status());
        perror("%s\n", $stat_text);
        return 0;
    }

    return $rc;
}


$rc = main($argv);
if ($rc) {
    print_help();
    exit($rc);
}

