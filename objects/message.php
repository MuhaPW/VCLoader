<?php
    // Инициализируем api config
    include_once $_SERVER['DOCUMENT_ROOT'] . '/debug/config/api.php';
    // Инициализируем user
    include_once $_SERVER['DOCUMENT_ROOT'] . '/debug/objects/user.php';

    function onMessageCreated($message) {
        // Создаём переменную для сообщения ответа
        $response_message = null;
        $isUsed = false;

        $user = new User($message->user_id);
        $countUse = $user->getCountUse();
        $isMember = $user->isMember();

        // Получаем voice_messages
        $voice_messages = findVoiceMessages(array($message));
        $voice_count = count($voice_messages);
        if ($voice_count == 0) { // Если голосовых вообще не найдено
            $response_message = "Голосовые сообщения не найдены :(";
        } 
        else if ($countUse >= 11 && !$isMember) {
            if ($voice_count == 1) {
                $response_message = "Я нашел голосовое сообщение.";
                $response_message .= " Но я его тебе не дам!";
            }
            else {
                $response_message = "Я нашел $voice_count голосовых сообщений.";
                $response_message .= " Но я их тебе не дам!";
            }
            
            $response_message .= " Столько раз помог, просто подпишись на меня.";
            $response_message .= " И я снова продолжу отправлять тебе ссылки на гс :)";
        }
        else if ($voice_count == 1) { // Если найден только один, достаточно краткого текста
            $response_message = "Ссылка на загрузку гс:\n" . $voice_messages[0]->voice_link;

            $isUsed = true;
        } 
        else {
            // Получаем список id отправителей гс
            $senders_id = '';
            foreach ($voice_messages as &$voice_message) {
                if (strpos($senders_id, $voice_message->sender_id)) continue;
                $senders_id = $senders_id . $voice_message->sender_id . ',';
            }
            
            // С помощью users.get получаем данные о отправителях
            $request_params = array(
                'user_ids' => $senders_id,
                'name_case' => 'gen',
                'access_token' => TOKEN,
                'v' => '5.130'
            );

            $url = "https://api.vk.com/method/users.get?" . http_build_query($request_params); 
            $response = json_decode(file_get_contents($url))->response; 
            
            // Извлекаем имена в отдельный массив    
            $users_name = array();
            foreach ($response as &$user_info) {
                $users_name[$user_info->id] = $user_info->first_name;
            }
            
            $response_message = "Найдено $voice_count голосовых сообщений.\n";
            for ($i = 0; $i < $voice_count; $i++) {
                // Получаем VoiceMessage
                $voice_message = $voice_messages[$i];
                // Считаем порядок сообщения
                $number = $i + 1;
                // Собираем сообщение ответа
                $response_message .= "$number. От " . $users_name[$voice_message->sender_id] . "\n";
                $response_message .= $voice_message->voice_link . "\n\n";
            }

            $isUsed = true;
        }

        // С помощью messages.send отправляем ответное сообщение
        $request_params = array(
            'message' => $response_message,
            'peer_id' => $user->id,
            'access_token' => TOKEN,
            'v' => '5.130',
            'random_id' => '0'
        );
    
        $get_params = http_build_query($request_params);
        file_get_contents('https://api.vk.com/method/messages.send?'. $get_params);

        // Если все прошло успешно, голосовые сообщения найдены и отправлены,
        // увеличиваем колличество использований бота
        if ($isUsed) intercaseCountUse($user, $countUse);   
    }

    function intercaseCountUse($user, $countUse) {
        // Увеличеваем колличество использований в базе данных
        $user->increaseCountUse();

        // Если пользователь подписан завершаем метод
        if ($isMember) return; 

        // Если пользователь не подписан отправляем еще  одно сообщение 
        // о просьбе подписаться если он использует бота 1, 5 или 10 раз
        $message = null;
        switch ($countUse + 1) {
            case 1:
                $message = "Если я тебе понравился, подпишись на меня пожалуйста :)";
                break;
            case 5:
                $message = "Мой создатель вообще не получает деньги за создания меня.";
                $message .= " Ему достаточно было бы одного, если ты просто подписался бы на меня :)";
                break;
            case 10:
                $message = "Я тебе столько раз помог, а ты даже не подписался на меня.\nНу все! 😠\n";
                $message .= "Я обиделся на тебя, и больше не дам тебе ссылку пока ты не подпишешься на меня!";
                break;
        }
        
        if ($message == null) return;

        // С помощью messages.send отправляем дополнительное сообщение о просьбе подписаться
        $request_params = array(
            'message' => $message,
            'peer_id' => $user->id,
            'access_token' => TOKEN,
            'v' => '5.130',
            'random_id' => '0'
        );

        $get_params = http_build_query($request_params);
        file_get_contents('https://api.vk.com/method/messages.send?'. $get_params);
    }
    
    function findVoiceMessages($messages): Array {
        $voice_messages = array();

        foreach ($messages as &$message) {
            if (isset($message->attachments)) {
                foreach ($message->attachments as &$attachment) {
                    if (!isset($attachment->doc->preview->audio_msg->link_mp3)) continue;
                        
                    $voice_messages[] = new VoiceMessage($message->user_id, $attachment->doc->preview->audio_msg->link_mp3);
                }
            }

            if (isset($message->fwd_messages)) {
                $voice_messages = array_merge($voice_messages, findVoiceMessages($message->fwd_messages));
            }
        }

        return $voice_messages;
    }

    class VoiceMessage {
        public $sender_id = null;
        public $voice_link = null;

        public function __construct($sender_id, $voice_link) {
            $this->sender_id = $sender_id;
            $this->voice_link = $voice_link;
        }
    }
?>