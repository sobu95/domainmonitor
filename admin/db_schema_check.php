<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$config = include __DIR__ . '/../config/config.php';

try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
        $config['db_username'],
        $config['db_password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('Błąd połączenia z bazą danych: ' . $e->getMessage());
}

$sqlQueries = [
    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'user') DEFAULT 'user',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",

    "CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        prompt TEXT NOT NULL,
        active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    "CREATE TABLE IF NOT EXISTS domains (
        id INT AUTO_INCREMENT PRIMARY KEY,
        domain_name VARCHAR(255) NOT NULL,
        fetch_date DATE NOT NULL,
        registration_available_date DATE NOT NULL,
        dr INT NULL,
        linking_domains INT NULL,
        domain_authority INT NULL,
        page_authority INT NULL,
        linking_domains_list TEXT NULL,
        link_profile_strength VARCHAR(20) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_domain_fetch (domain_name, fetch_date),
        INDEX idx_registration_date (registration_available_date)
    )",

    "CREATE TABLE IF NOT EXISTS domain_analysis (
        id INT AUTO_INCREMENT PRIMARY KEY,
        domain_id INT NOT NULL,
        category_id INT NOT NULL,
        description TEXT,
        is_interesting BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
        UNIQUE KEY unique_domain_category (domain_id, category_id)
    )",

    "CREATE TABLE IF NOT EXISTS favorite_domains (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        domain_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE,
        UNIQUE KEY unique_user_domain (user_id, domain_id)
    )",

    "CREATE TABLE IF NOT EXISTS fetch_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fetch_date DATE NOT NULL,
        domains_count INT DEFAULT 0,
        status ENUM('success', 'error') DEFAULT 'success',
        error_message TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",

    "CREATE TABLE IF NOT EXISTS system_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        config_key VARCHAR(100) UNIQUE NOT NULL,
        config_value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",

    "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        domain_id INT NOT NULL,
        type ENUM('reminder', 'summary') DEFAULT 'reminder',
        sent_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE
    )",

    "CREATE TABLE IF NOT EXISTS activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        action VARCHAR(100) NOT NULL,
        details TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )"
];

function parseRequiredSchema(array $queries): array {
    $schema = [];
    foreach ($queries as $query) {
        $q = trim($query);
        if (stripos($q, 'CREATE TABLE') !== 0) {
            continue;
        }
        if (preg_match('/CREATE TABLE IF NOT EXISTS\s+`?(\w+)`?\s*\((.*)\)/si', $q, $matches)) {
            $table = $matches[1];
            $body = $matches[2];
            $lines = preg_split('/,\n/', trim($body));
            $columns = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                if (preg_match('/^(PRIMARY|UNIQUE|FOREIGN|CONSTRAINT|INDEX|KEY)\s/i', $line)) {
                    continue;
                }
                if (preg_match('/^`?(\w+)`?\s+(.*)$/', $line, $colMatch)) {
                    $colName = $colMatch[1];
                    $definition = rtrim($line, ',');
                    $columns[$colName] = $definition;
                }
            }
            $schema[$table] = ['sql' => $q, 'columns' => $columns];
        }
    }
    return $schema;
}

function getExistingSchema(PDO $pdo, string $dbName): array {
    $stmt = $pdo->prepare('SELECT TABLE_NAME, COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ?');
    $stmt->execute([$dbName]);
    $existing = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existing[$row['TABLE_NAME']][] = $row['COLUMN_NAME'];
    }
    return $existing;
}

function diffSchema(array $required, array $existing): array {
    $missingTables = [];
    $missingColumns = [];
    $queries = [];
    foreach ($required as $table => $info) {
        if (!isset($existing[$table])) {
            $missingTables[] = $table;
            $queries[] = $info['sql'];
        } else {
            foreach ($info['columns'] as $col => $def) {
                if (!in_array($col, $existing[$table])) {
                    $missingColumns[$table][] = $col;
                    $queries[] = "ALTER TABLE $table ADD $def";
                }
            }
        }
    }
    return [$missingTables, $missingColumns, $queries];
}

$requiredSchema = parseRequiredSchema($sqlQueries);
$existingSchema = getExistingSchema($pdo, $config['db_name']);
list($missingTables, $missingColumns, $queriesToRun) = diffSchema($requiredSchema, $existingSchema);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update']) && !empty($queriesToRun)) {
    try {
        $pdo->beginTransaction();
        foreach ($queriesToRun as $sql) {
            $pdo->exec($sql);
        }
        $pdo->commit();
        $message = 'Baza danych została zaktualizowana.';
        $existingSchema = getExistingSchema($pdo, $config['db_name']);
        list($missingTables, $missingColumns, $queriesToRun) = diffSchema($requiredSchema, $existingSchema);
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = 'Błąd aktualizacji: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sprawdź schemat bazy danych</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="container-fluid">
    <div class="row">
        <?php include '../includes/sidebar.php'; ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-database"></i> Sprawdź schemat bazy danych</h1>
            </div>
            <?php if ($message): ?>
            <div class="alert alert-success"><i class="fas fa-check"></i> <?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (empty($missingTables) && empty($missingColumns)): ?>
                <div class="alert alert-success"><i class="fas fa-check"></i> Baza danych jest aktualna.</div>
            <?php else: ?>
                <div class="alert alert-warning"><i class="fas fa-exclamation-circle"></i> Brakujące elementy schematu:</div>
                <ul>
                    <?php foreach ($missingTables as $table): ?>
                        <li>Brak tabeli <strong><?php echo htmlspecialchars($table); ?></strong></li>
                    <?php endforeach; ?>
                    <?php foreach ($missingColumns as $table => $cols): ?>
                        <li>Tabela <strong><?php echo htmlspecialchars($table); ?></strong> - brakujące kolumny: <?php echo htmlspecialchars(implode(', ', $cols)); ?></li>
                    <?php endforeach; ?>
                </ul>
                <form method="POST">
                    <button type="submit" name="update" class="btn btn-primary">
                        <i class="fas fa-sync-alt"></i> Aktualizuj bazę danych
                    </button>
                </form>
            <?php endif; ?>
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/app.js"></script>
</body>
</html>
