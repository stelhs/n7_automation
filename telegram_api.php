<?php
require_once 'config.php';

class Telegram_api {
    private $db;

    function __construct($db)
    {
        $this->db = $db;
    }

    function post_request($method_name, $params = [])
    {
        $full_url = sprintf('https://api.telegram.org/bot%s/%s', 
                                    conf_telegram_bot()['token'], $method_name);

        $query = http_build_query($params);

        $options = array(
            'http' => array(
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => $query,
            )
        );
        $context = stream_context_create($options);
        @$result = file_get_contents($full_url, false, $context);
        if ($result == FALSE)
            return -EPARSE;

        return json_decode($result, true);
    }

    function get_new_messages()
    {
        $last_msg = $this->db->query('SELECT * FROM telegram_msg ' .
                               'ORDER BY update_id DESC LIMIT 1');
        $last_update_id = 0;

        if ($last_msg)
            $last_update_id = $last_msg['update_id'];

        $resp = $this->post_request('getUpdates', ['offset' => $last_update_id + 1,
                                                   'limit' => 10,
                                                   'timeout' => 10]);
        if (!is_array($resp)) {
            msg_log(LOG_ERR, "telegram: Can't make POST request " . $resp);
            return $resp;
        }

        if ($resp['ok'] != 1) {
            msg_log(LOG_ERR, "telegram: error POST request, ok=" . $resp['ok']);
            return -EBUSY;
        }

        if (!count($resp['result']))
            return [];

        $list_msg = [];
        foreach ($resp['result'] as $row) {
            dump($row);
            //TODO: edited_message
            $msg = ['update_id' =>   $row['update_id'],
                    'msg_id' =>      $row['message']['message_id'],
                    'date' =>        $row['message']['date'],
                    'from_name' =>   $row['message']['from']['first_name'],
                    'from_id' =>     $row['message']['from']['id'],
                    'chat_id' =>     $row['message']['chat']['id'],
                    'chat_type' =>   $row['message']['chat']['type'],
                    'chat_name' =>   ($row['message']['chat']['type'] == 'private' ? 
                                              $row['message']['chat']['first_name'] :
                                              $row['message']['chat']['title']),
                    'text'      =>   $row['message']['text'],
            ];

            $this->db->insert('telegram_msg', $msg);
            $list_msg[] = $msg;
        }

        return $list_msg;
    }

    function send_message($chat_id, $text, 
                $reply_to_message_id = 0, $disable_notification = false)
    {
        $params = ['chat_id' => $chat_id, 'text' => $text];
        if ($reply_to_message_id)
            $params['reply_to_message_id'] = $reply_to_message_id;
        if ($disable_notification)
            $params['disable_notification'] = $disable_notification;

        $resp = $this->post_request('sendMessage', $params);
        if (!is_array($resp)) {
            msg_log(LOG_ERR, "telegram: Can't make POST request " . $resp);
            return $resp;
        }

        if ($resp['ok'] != 1) {
            msg_log(LOG_ERR, "telegram: error POST request, ok=" . $resp['ok']);
            return -EBUSY;
        }

        return 0;
    }
}
