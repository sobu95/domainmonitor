<?php
// Plik testowy do sprawdzenia połączenia z bazą danych
// Usuń ten plik po zakończeniu testów!

if (!file_exists('config/config.php')) {
    die('Brak pliku konfiguracyjnego. Uruchom najpierw install.php');
}

try {
    require_once 'config/database.php';
    
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<h2>Test połączenia z bazą danych</h2>";
    echo "<p style='color: green;'>✓ Połączenie z bazą danych działa poprawnie!</p>";
    
    // Sprawdź tabele
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h3>Istniejące tabele:</h3>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>$table</li>";
    }
    echo "</ul>";
    
    // Sprawdź kategorie
    if (in_array('categories', $tables)) {
        $stmt = $db->query("SELECT COUNT(*) as count FROM categories");
        $count = $stmt->fetch()['count'];
        echo "<p>Liczba kategorii: $count</p>";
    }
    
    // Sprawdź użytkowników
    if (in_array('users', $tables)) {
        $stmt = $db->query("SELECT COUNT(*) as count FROM users");
        $count = $stmt->fetch()['count'];
        echo "<p>Liczba użytkowników: $count</p>";
    }
    
} catch (Exception $e) {
    echo "<h2>Błąd połączenia</h2>";
    echo "<p style='color: red;'>✗ " . htmlspecialchars($e->getMessage()) . "</p>";
    
    echo "<h3>Sprawdź:</h3>";
    echo "<ul>";
    echo "<li>Czy baza danych istnieje?</li>";
    echo "<li>Czy dane logowania są poprawne?</li>";
    echo "<li>Czy użytkownik ma uprawnienia?</li>";
    echo "</ul>";
}
?>