<?php
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn;

    public function __construct() {
        $config = include __DIR__ . '/config.php';
        $this->host = $config['db_host'];
        $this->db_name = $config['db_name'];
        $this->username = $config['db_username'];
        $this->password = $config['db_password'];
    }

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("SET NAMES utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch(PDOException $exception) {
            throw new Exception("Błąd połączenia z bazą danych: " . $exception->getMessage());
        }
        return $this->conn;
    }
}
?>