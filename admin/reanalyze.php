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

require_once '../config/database.php';
require_once '../includes/functions.php';

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';
$progress = [];
$logFile = '';

function logProgress($msg) {
    global $logFile, $progress;
    $timestamp = date('Y-m-d H:i:s');
    if (!empty($logFile)) {
        if (!is_dir(dirname($logFile))) {
            mkdir(dirname($logFile), 0777, true);
        }
        file_put_contents($logFile, "[$timestamp] $msg\n", FILE_APPEND | LOCK_EX);
    }
    $progress[] = "[$timestamp] $msg";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['date'])) {
    $selectedDate = $_POST['date'];
    $logFile = dirname(__DIR__) . '/logs/reanalyze_' . date('Y-m-d_His') . '.log';
    logProgress("Rozpoczęcie ponownej analizy dla daty $selectedDate");

    // Pobierz domeny z wybranej daty
    $stmt = $db->prepare('SELECT id, domain_name FROM domains WHERE fetch_date = ?');
    $stmt->execute([$selectedDate]);
    $domains = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($domains)) {
        logProgress('Brak domen do analizy.');
        $error = 'Brak domen dla wybranej daty.';
    } else {
        $domainIds = array_column($domains, 'id');
        $domainNames = array_column($domains, 'domain_name');
        logProgress('Znaleziono ' . count($domains) . ' domen.');

        // Usuń istniejące wpisy analizy
        $placeholders = implode(',', array_fill(0, count($domainIds), '?'));
        $stmt = $db->prepare("DELETE FROM domain_analysis WHERE domain_id IN ($placeholders)");
        $stmt->execute($domainIds);
        logProgress('Usunięto poprzednie wpisy analizy.');

        // Pobierz aktywne kategorie
        $stmt = $db->query('SELECT * FROM categories WHERE active = 1');
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($categories as $category) {
            logProgress('Analiza kategorii: ' . $category['name']);
            $response = callGeminiAPI($category['prompt'], $domainNames);
            if ($response) {
                $parsed = parseDomainAnalysis($response);
                foreach ($parsed as $item) {
                    $domainId = null;
                    foreach ($domains as $d) {
                        if ($d['domain_name'] === $item['domain']) {
                            $domainId = $d['id'];
                            break;
                        }
                    }
                    if ($domainId) {
                        $stmtIns = $db->prepare("INSERT INTO domain_analysis (domain_id, category_id, description, is_interesting) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE description = VALUES(description), is_interesting = VALUES(is_interesting)");
                        $stmtIns->execute([$domainId, $category['id'], $item['description']]);
                    }
                }
                logProgress('Zapisano ' . count($parsed) . ' wpisów dla kategorii ' . $category['name']);
            } else {
                logProgress('Błąd analizy dla kategorii ' . $category['name']);
            }
            flush();
        }
        $message = 'Analiza zakończona.';
        logProgress('Zakończono ponowną analizę.');
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ponowna analiza - Administracja</title>
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
                <h1 class="h2"><i class="fas fa-sync-alt"></i> Ponowna analiza</h1>
            </div>
            <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check"></i> <?php echo htmlspecialchars($message); ?>
                <?php if ($logFile): ?>
                <div><a href="../logs/<?php echo basename($logFile); ?>" target="_blank">Zobacz logi</a></div>
                <?php endif; ?>
            </div>
            <?php elseif ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            <div class="card mb-4">
                <div class="card-header">Wybierz datę</div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Data</label>
                            <input type="date" name="date" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Przeanalizuj ponownie</button>
                    </form>
                </div>
            </div>
            <?php if (!empty($progress)): ?>
            <div class="card">
                <div class="card-header">Postęp</div>
                <div class="card-body"><pre><?php foreach ($progress as $line) { echo htmlspecialchars($line) . "\n"; } ?></pre></div>
            </div>
            <?php endif; ?>
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/app.js"></script>
</body>
</html>
