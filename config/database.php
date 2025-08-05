<?php
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn;
    private $options;

    public function __construct() {
        $config = include __DIR__ . '/config.php';
        $this->host = $config['db_host'];
        $this->db_name = $config['db_name'];
        $this->username = $config['db_username'];
        $this->password = $config['db_password'];
        $this->options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_PERSISTENT => true
        ];
    }

    private function connect() {
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password,
                $this->options
            );
            $this->conn->exec("SET NAMES utf8");
        } catch(PDOException $exception) {
            throw new Exception("Błąd połączenia z bazą danych: " . $exception->getMessage());
        }
    }

    public function getConnection() {
        if ($this->conn === null) {
            $this->connect();
        }
        return $this->conn;
    }

    public function reconnect() {
        $this->conn = null;
        $this->connect();
        return $this->conn;
    }
}
?>