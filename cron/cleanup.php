<?php
// Cron job do czyszczenia starych danych
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$config = include dirname(__DIR__) . '/config/config.php';

$database = new Database();
$db = $database->getConnection();

$logFile = dirname(__DIR__) . '/logs/cleanup_' . date('Y-m-d') . '.log';

function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

try {
    writeLog("Rozpoczęcie czyszczenia danych");
    
    // Usuń stare logi (starsze niż 90 dni)
    $stmt = $db->prepare("DELETE FROM fetch_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    $stmt->execute();
    $deletedLogs = $stmt->rowCount();
    writeLog("Usunięto $deletedLogs starych logów pobierania");
    
    // Usuń stare powiadomienia (starsze niż 30 dni)
    $stmt = $db->prepare("DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute();
    $deletedNotifications = $stmt->rowCount();
    writeLog("Usunięto $deletedNotifications starych powiadomień");
    
    // Usuń domeny starsze niż 6 miesięcy (wraz z powiązanymi danymi)
    $stmt = $db->prepare("DELETE FROM domains WHERE fetch_date < DATE_SUB(CURDATE(), INTERVAL 6 MONTH)");
    $stmt->execute();
    $deletedDomains = $stmt->rowCount();
    writeLog("Usunięto $deletedDomains starych domen");
    
    // Wyczyść stare pliki logów
    $logsDir = dirname(__DIR__) . '/logs/';
    if (is_dir($logsDir)) {
        $files = glob($logsDir . '*.log');
        $deletedFiles = 0;
        
        foreach ($files as $file) {
            if (filemtime($file) < strtotime('-90 days')) {
                if (unlink($file)) {
                    $deletedFiles++;
                }
            }
        }
        
        writeLog("Usunięto $deletedFiles starych plików logów");
    }
    
    // Optymalizuj tabele
    $tables = ['domains', 'domain_analysis', 'favorite_domains', 'fetch_logs', 'notifications'];
    foreach ($tables as $table) {
        $db->exec("OPTIMIZE TABLE $table");
    }
    writeLog("Zoptymalizowano tabele bazy danych");
    
    writeLog("Zakończenie czyszczenia danych");
    
} catch (Exception $e) {
    writeLog("BŁĄD: " . $e->getMessage());
    $date = date('Y-m-d H:i:s');
    $body = "Data: {$date}<br>Błąd: " . $e->getMessage() . "<br>Log: {$logFile}";
    sendEmail($config['admin_email'], 'Błąd crona', $body);
}
?>