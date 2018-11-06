<?php

require_once '/usr/local/lib/php/database.php';
require_once '/usr/local/lib/php/common.php';
require_once 'config.php';
require_once 'modem3g.php';
require_once 'httpio_lib.php';
require_once 'valve_lib.php';
require_once 'guard_lib.php';


function db()
{
    static $db = NULL;

    if ($db)
        return $db;

    $db = new Database;
    $rc = $db->connect(conf_db());
    if ($rc)
        throw new Exception("can't connect to database");
    return $db;
}



function sms_send($type, $recepient, $args = array())
{
    $sms_text = '';

    switch ($type) {
    case 'reboot':
        if (isset($recepient['user_id'])) {
            $user = user_get_by_id($recepient['user_id']);
            $sms_text = sprintf("%s отправил сервер на перезагрузку через %s",
                                $user['name'], $args);
            break;
        }
        $sms_text = sprintf("Сервер ушел на перезагрузку по запросу %s", $args);
        break;

    case 'status':
        $sms_text = $args;
        break;

    case 'alarm':
        $sms_text = sprintf("Внимание!\nСработала %s, событие: %d",
                                $args['zone'], $args['action_id']);
        break;

    case 'guard_disable':
        $sms_text = sprintf("Метод: %s, state_id: %s.",
                            $args['method'], $args['state_id']);

        if (isset($args['global_status']))
            $sms_text .= $args['global_status'];
        break;

    case 'guard_enable':
        $sms_text = sprintf("Метод: %s, state_id: %s.",
                            $args['method'], $args['state_id']);

        if (isset($args['global_status']))
            $sms_text .= $args['global_status'];
        break;

    default:
        return -EINVAL;
    }

    $modem = new Modem3G(conf_modem()['ip_addr']);

    // creating phones list
    $list_phones = [];
    if (isset($recepient['user_id']) && $recepient['user_id']) {
        $user = user_get_by_id($recepient['user_id']);
        $list_phones = $user['phones'];
    }

    // applying phones list by groups
    if (isset($recepient['groups']))
        foreach ($recepient['groups'] as $group) {
            $group_phones = get_users_phones_by_access_type($group);
            $list_phones = array_unique(array_merge($list_phones, $group_phones));
        }

    if (!count($list_phones))
        return -EINVAL;

    foreach ($list_phones as $phone) {
        $ret = $modem->send_sms($phone, $sms_text);
        if ($ret) {
            msg_log(LOG_ERR, "Can't send SMS: " . $ret);
            return -EBUSY;
        }
    }
}


function telegram_send($type, $args = array())
{
    switch ($type) {
    case 'reboot':
        if (isset($args['user_id']) && $args['user_id']) {
            $user = user_get_by_id($args['user_id']);
            $text = sprintf("%s отправил сервер на перезагрузку через %s",
                                $user['name'], $args['method']);
            break;
        }
        $text = sprintf("Сервер ушел на перезагрузку по запросу %s", $args['method']);
        break;

    case 'false_alarm':
        $text = sprintf("Срабатал датчик на порту %s:%d из группы \"%s\".\n" .
                        "(Поскольку сработал только один датчик из данной группы, то скорее всего это ложное срабатывание)\n",
                                $args['io'], $args['port'], $args['name']);
        break;

    case 'alarm':
        $text = sprintf("!!! Внимание, Тревога !!!\nСработала %s, событие: %d\n",
                                $args['zone'], $args['action_id']);
        break;

    case 'guard_disable':
        $text = sprintf("Охрана отключена, отключил %s с помощью %s.",
                            $args['user'], $args['method']);
        break;

    case 'guard_enable':
        $text = sprintf("Охрана включена, включил %s с помощью %s.",
                            $args['user'], $args['method']);
        break;

    default:
        $text = $type;
    }

    run_cmd(sprintf("./telegram.php msg_send_all \"%s\"", $text));
}


function server_reboot($method, $user_id = NULL)
{
    if ($method == "SMS")
        sms_send('reboot',
                 ['user_id' => $user_id,
                  'groups' => ['sms_observer']],
                 $method);

    telegram_send('reboot', ['method' => $method,
                             'user_id' => $user_id]);
    if(DISABLE_HW)
        return;
    run_cmd('halt');
    for(;;);
}

function get_day_night()
{
    $sun_info = date_sun_info(time(), 54.014634, 28.013484);
    $curr_time = time();

    if ($curr_time > $sun_info['nautical_twilight_begin'] &&
        $curr_time < ($sun_info['nautical_twilight_end'] - 3600))
            return 'day';

    return 'night';
}

function user_get_by_phone($phone)
{
    $user = db()->query("SELECT * FROM users " .
                      "WHERE phones LIKE \"%" . $phone . "%\" AND enabled = 1");

    if (!$user)
        return NULL;

    $user['phones'] = string_to_array($user['phones']);
    return $user;
}

function user_get_by_id($user_id)
{
    $user = db()->query(sprintf("SELECT * FROM users " .
                              "WHERE id = %d AND enabled = 1", $user_id));

    if (!$user)
        return NULL;

    $user['phones'] = string_to_array($user['phones']);
    return $user;
}

function user_get_by_telegram_id($telegram_user_id)
{
    $user = db()->query(sprintf("SELECT * FROM users " .
                              "WHERE telegram_id = %d AND enabled = 1", $telegram_user_id));

    if (!$user)
        return NULL;

    $user['phones'] = string_to_array($user['phones']);
    return $user;
}


function get_users_phones_by_access_type($type)
{
    $users = db()->query_list(sprintf('SELECT * FROM users '.
                             'WHERE %s = 1 AND enabled = 1', $type));
    $list_phones = array();
    foreach ($users as $user)
        $list_phones[] = string_to_array($user['phones'])[0];

    return $list_phones;
}

function get_all_users_phones_by_access_type($type)
{
    $users = db()->query_list(sprintf('SELECT * FROM users '.
                              'WHERE %s = 1 AND enabled = 1', $type));
    $list_phones = array();
    foreach ($users as $user) {
        $phones = string_to_array($user['phones']);
        foreach ($phones as $phone)
            $list_phones[] = $phone;
    }

    return $list_phones;
}


function get_global_status()
{
    $modem = new Modem3G(conf_modem()['ip_addr']);

    $guard_stat = get_guard_state();
    $balance = $modem->get_sim_balanse();
    $modem_stat = $modem->get_status();
    $lighting_stat = get_street_light_stat();
    $padlocks_stat = get_padlocks_stat();
    $termosensors = get_termosensors_stat();

    $ret = run_cmd('uptime');
    preg_match('/up (.+),/U', $ret['log'], $mathes);
    $uptime = $mathes[1];


    $well_pump_state = httpio(conf_guard()['pump_well_io_port']['io'])->relay_get_state(conf_guard()['pump_well_io_port']['port']);

    $automatic_fill_tank_stat = !is_automatic_fill_tank_disable();

    $valve_tank = new Valve(conf_valves()['tank']);

    return ['guard_stat' => $guard_stat,
            'modem_stat' => $modem_stat,
            'uptime' => $uptime,
            'padlocks_stat' => $padlocks_stat,
            'termo_sensors' => $termosensors,
            'well_pump' => $well_pump_state,
            'automatic_fill_tank' => $automatic_fill_tank_stat,
            'valve_tank' => $valve_tank->status(),
    ];
}


function format_global_status_for_sms($stat)
{
    $text = '';
    if (isset($stat['guard_stat'])) {
        $text_who = '';
        switch ($stat['guard_stat']['state']) {
        case 'sleep':
            $mode = "откл.";
            break;

        case 'ready':
            $mode = "вкл.";
            break;
        }
        $text .= sprintf("Охрана: %s, ", $mode);

        if (count($stat['guard_stat']['ignore_zones'])) {
            $text .= sprintf("Игнор: ");
            foreach ($stat['guard_stat']['ignore_zones'] as $zone_id) {
                $zone = zone_get_by_io_id($zone_id);
                $text .= sprintf("%s, ", $zone['name']);
            }
            $text .= '.';
        }

        if (count($stat['guard_stat']['blocking_zones'])) {
            $text .= sprintf("Заблокир: ");
            foreach ($stat['guard_stat']['blocking_zones'] as $zone) {
                $text .= sprintf("%s, ", $zone['name']);
            }
            $text .= '.';
        }

        if (isset($stat['guard_stat']['user_name']) && $mode)
            $text .= sprintf("%s: %s, ", $mode,
                                              $stat['guard_stat']['user_name']);
    }

    if (isset($stat['padlocks_stat'])) {
        foreach ($stat['padlocks_stat'] as $row) {
            switch ($row['state']) {
            case 0:
                $mode = "закр.";
                break;

            case 1:
                $mode = "откр.";
                break;
            }
            $text .= sprintf("Замок '%s': %s, ", $row['name'], $mode);
        }
    }

    if (isset($stat['uptime'])) {
        $text .= sprintf("Uptime: %s, ", $stat['uptime']);
    }

    if (isset($stat['balance'])) {
        $text .= sprintf("Баланс: %s, ", $stat['balance']);
    }

    return $text;
}


function format_global_status_for_telegram($stat)
{
    $text = '';
    if (isset($stat['guard_stat'])) {
        $text_who = '';
        switch ($stat['guard_stat']['state']) {
        case 'sleep':
            $mode = "отключена";
            $text_who = "Отключил охрану";
            break;

        case 'ready':
            $mode = "включена";
            $text_who = "Включил охрану";
            break;
        }
        $text .= sprintf("Охрана: %s\n", $mode);

        if (count($stat['guard_stat']['ignore_zones'])) {
            $text .= sprintf("Игнорированные зоны:\n");
            foreach ($stat['guard_stat']['ignore_zones'] as $zone_id) {
                $zone = zone_get_by_io_id($zone_id);
                $text .= sprintf("               %s\n", $zone['name']);
            }
        }

        if (count($stat['guard_stat']['blocking_zones'])) {
            $text .= sprintf("Заблокированные зоны: ");
            foreach ($stat['guard_stat']['blocking_zones'] as $zone)
                $text .= sprintf("               %s\n", $zone['name']);
        }

        if (isset($stat['guard_stat']['user_name']) && $text_who)
            $text .= sprintf("%s: %s через %s в %s\n", $text_who,
                                              $stat['guard_stat']['user_name'],
                                              $stat['guard_stat']['method'],
                                              $stat['guard_stat']['created']);
    }

    if (isset($stat['padlocks_stat'])) {
        foreach ($stat['padlocks_stat'] as $row) {
            switch ($row['state']) {
            case 0:
                $mode = "закрыт";
                break;

            case 1:
                $mode = "открыт";
                break;
            }
            $text .= sprintf("Замок '%s': %s\n", $row['name'], $mode);
        }
    }

    if (isset($stat['uptime'])) {
        $text .= sprintf("Uptime: %s\n", $stat['uptime']);
    }

    if (isset($stat['balance'])) {
        $text .= sprintf("Баланс счета SIM карты: %s\n", $stat['balance']);
    }

    if (isset($stat['termo_sensors'])) {
        foreach($stat['termo_sensors'] as $sensor)
            $text .= sprintf("Температура %s: %.01f градусов\n", $sensor['name'], $sensor['value']);
    }

    if (isset($stat['well_pump'])) {
        switch ($stat['well_pump']) {
            case 0:
                $mode = "отключена";
                break;

            case 1:
                $mode = "включена";
                break;
        }
        $text .= sprintf("Насосная система: %s\n", $mode);
    }

    if (isset($stat['automatic_fill_tank'])) {
        switch ($stat['automatic_fill_tank']) {
            case TRUE:
                $mode = "включена";
                break;

            case FALSE:
                $mode = "отключена";
                break;
        }
        $text .= sprintf("Автоматика бака: %s\n", $mode);
    }

    if (isset($stat['valve_tank'])) {
        switch($stat['valve_tank']) {
        case "not_defined":
            $mode = "в непонятках";
            break;
        case "error":
            $mode = "полное гавно";
            break;
        case "opened":
            $mode = "открыт";
            break;
        case "close":
            $mode = "закрыт";
            break;
        }
        $text .= sprintf("Кран бака: %s\n", $mode);
    }

    return $text;
}


function get_street_light_stat()
{
    $report = [];
    foreach (conf_street_light() as $zone) {
        $zone['state'] = httpio($zone['io'])->relay_get_state($zone['io_port']);
        if ($zone['state'] < 0)
            perror("Can't get relay state %d\n", $zone['io_port']);

        $report[] = $zone;
    }

    return $report;
}

function get_padlocks_stat()
{
    $report = [];
    foreach (conf_padlocks() as $zone) {
        $zone['state'] = httpio($zone['io'])->relay_get_state($zone['io_port']);
        if ($zone['state'] < 0)
            perror("Can't get relay state %d\n", $zone['io_port']);

        $report[] = $zone;
    }

    return $report;
}

function get_termosensors_stat()
{
    $query = 'SELECT sensor_name, temperaure FROM `termo_sensors_log` LEFT JOIN ' .
                 '(SELECT sensor_name, MAX(id) as max '.
                        'FROM `termo_sensors_log` GROUP BY sensor_name) as s ' .
             'USING (sensor_name) WHERE ' .
                    'termo_sensors_log.id >= s.max AND ' .
                    'created > now() - INTERVAL 2 MINUTE';

    $rows = db()->query_list($query);
    if (!is_array($rows) || !count($rows))
        return [];

    $list = [];
    foreach ($rows as $row) {
        if (!isset(conf_termo_sensors()[$row['sensor_name']]))
            continue;
            $list[] = ['name' => conf_termo_sensors()[$row['sensor_name']],
                       'value' => $row['temperaure']];
    }
    return $list;
}

function get_stored_io_states()
{
    $query = 'SELECT io_output_actions.io_name, ' .
                    'io_output_actions.port, ' .
                    'io_output_actions.state ' .
             'FROM io_output_actions ' .
             'INNER JOIN ' .
                '( SELECT io_name, port, max(id) as last_id ' .
                  'FROM io_output_actions ' .
                  'GROUP BY io_name, port ) as b '.
             'ON io_output_actions.port = b.port AND ' .
                'io_output_actions.io_name = b.io_name AND ' .
                'io_output_actions.id = b.last_id ' .
             'ORDER BY io_output_actions.io_name, io_output_actions.port';

    $rows = db()->query_list($query);
    if (!is_array($rows) || !count($rows))
        return [];

    return $rows;
}


define("AUTOMATIC_FILL_TANK_DISABLED_FILE", "/var/local/automatic_fill_tank_disabled");
function is_automatic_fill_tank_disable()
{
    return file_exists(AUTOMATIC_FILL_TANK_DISABLED_FILE);
}

