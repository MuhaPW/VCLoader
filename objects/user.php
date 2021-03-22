<?php
    // Инициализация database.php
    include_once $_SERVER['DOCUMENT_ROOT'] . '/debug/config/database.php';
    include_once $_SERVER['DOCUMENT_ROOT'] . '/debug/config/api.php';
    
    class User {
        private static $table_name = "Users";
        
        public $id;
        public function __construct($id) {
            $this->id = $id;
        }

        function getCountUse() : Int {
            $sql = "SELECT count_use FROM " . self::$table_name . " WHERE id = $this->id";
            $values = Database::getInstance()->fetch($sql);

            if (empty($values)) return 0;
            return $values['count_use'];
        }
    
        function increaseCountUse() {
            $sql = null;

            // Если колличество использований равно 0, значит в базе еще нет данных о пользователе
            if ($this->getCountUse() == 0) {
                $sql = "INSERT INTO " . self::$table_name . " (id, count_use) VALUES ($this->id, 1)";
            }
            else {
                $sql = "UPDATE " . self::$table_name . " SET count_use = count_use + 1 WHERE id = $this->id";
            }
        
            Database::getInstance()->query($sql);
        }

        // Проверяет и возвращает подписчик ли пользователь группы
        function isMember() : Bool {
            // С помощью groups.isMember получаем подписан ли пользователь в группу
            $request_params = array(
                'group_id' => GROUP_ID,
                'user_id' => $this->id,
                'access_token' => TOKEN,
                'v' => '5.130'
            );

            $get_params = http_build_query($request_params);

            $url = 'https://api.vk.com/method/groups.isMember?' . $get_params; 

            return json_decode(file_get_contents($url))->response; 
        }
    }
?>