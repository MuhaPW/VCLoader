<?php
    class Database {
        private static $instance;
        private $conn;

        public function __construct() {
            // Учётные данные базы данных
            static $host = "localhost";
            static $db_name = "f0517110_voice_loader";
            static $username = "f0517110_root"; 
            static $password = "nkK7PnjaeqdMGHt";
            
            try {
                $this->conn = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
            } catch (PDOException $pe) {
                http_response_code(500);
                die("Could not connect to the database $dbname :" . $pe->getMessage());
            }
        }

        public static function getInstance() {
            if (self::$instance === NULL) self::$instance = new Database();
            return self::$instance;
        }

        public function query($sql): Response {
            $stmt = $this->conn->prepare($sql);
            
            $response = new Response();
            $response->stmt = $stmt;
            $response->isSuccessfully = $stmt->execute();
            
            return $response;
        }

        public function fetch($sql) {
            return $this->query($sql)->stmt->fetch(PDO::FETCH_ASSOC);
        }

        public function fetchAll($sql) {
            return $this->query($sql)->stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    class Response {
        public $isSuccessfully;
        public $stmt;
    }
?>