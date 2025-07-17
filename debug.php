<?php
// Plik debugowania - usuń po rozwiązaniu problemu
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Debug Domain Monitor</h2>";

// Sprawdź PHP
echo "<h3>Wersja PHP:</h3>";
echo phpversion();

// Sprawdź pliki
echo "<h3>Sprawdzenie plików:</h3>";
$files = [
    'config/config.php',
    'config/database.php',
    'includes/functions.php',
    'includes/navbar.php',
    'includes/sidebar.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✓ $file - OK<br>";
    } else {
        echo "✗ $file - BRAK<br>";
    }
}

// Sprawdź połączenie z bazą
echo "<h3>Test połączenia z bazą:</h3>";
try {
    if (file_exists('config/config.php')) {
        require_once 'config/database.php';
        $database = new Database();
        $db = $database->getConnection();
        echo "✓ Połączenie z bazą - OK<br>";
        
        // Sprawdź tabele
        $stmt = $db->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "Tabele: " . implode(', ', $tables) . "<br>";
        
    } else {
        echo "✗ Brak pliku config.php<br>";
    }
} catch (Exception $e) {
    echo "✗ Błąd bazy: " . $e->getMessage() . "<br>";
}

// Sprawdź sesję
echo "<h3>Sesja:</h3>";
session_start();
if (isset($_SESSION['user_id'])) {
    echo "✓ Użytkownik zalogowany: " . $_SESSION['username'] . "<br>";
} else {
    echo "✗ Brak zalogowanego użytkownika<br>";
}

// Sprawdź uprawnienia folderów
echo "<h3>Uprawnienia folderów:</h3>";
$dirs = ['logs', 'config'];
foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        if (is_writable($dir)) {
            echo "✓ $dir - zapisywalny<br>";
        } else {
            echo "✗ $dir - brak uprawnień zapisu<br>";
        }
    } else {
        echo "✗ $dir - folder nie istnieje<br>";
    }
}
?>