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

require_once '../includes/functions.php';

$date = $_GET['date'] ?? date('Y-m-d');
$category = $_GET['category'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$logFile = __DIR__ . '/../logs/ai_' . $date . '.log';
$entries = [];
if (file_exists($logFile)) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $entry = json_decode($line, true);
        if (!$entry) {
            continue;
        }
        if ($category && ($entry['category'] ?? '') !== $category) {
            continue;
        }
        $entries[] = $entry;
    }
}

$total = count($entries);
$totalPages = max(1, (int)ceil($total / $perPage));
$entries = array_slice($entries, ($page - 1) * $perPage, $perPage);
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Logs - Administracja</title>
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
                <h1 class="h2"><i class="fas fa-robot"></i> AI Logs</h1>
            </div>
            <div class="mb-3">
                <form class="row row-cols-lg-auto g-3 align-items-end" method="GET">
                    <div class="col-12">
                        <label for="date" class="form-label">Data</label>
                        <input type="date" id="date" name="date" value="<?php echo htmlspecialchars($date); ?>" class="form-control">
                    </div>
                    <div class="col-12">
                        <label for="category" class="form-label">Kategoria</label>
                        <select id="category" name="category" class="form-select">
                            <option value="" <?php echo $category === '' ? 'selected' : ''; ?>>Wszystkie</option>
                            <option value="success" <?php echo $category === 'success' ? 'selected' : ''; ?>>Sukces</option>
                            <option value="error" <?php echo $category === 'error' ? 'selected' : ''; ?>>Błąd</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filtruj</button>
                    </div>
                </form>
            </div>
            <div class="card">
                <div class="card-body table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Czas</th>
                                <th>Kategoria</th>
                                <th>HTTP</th>
                                <th>Zapytanie</th>
                                <th>Odpowiedź</th>
                                <th>Błąd</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($entries as $entry): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($entry['time'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($entry['category'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($entry['http_code'] ?? ''); ?></td>
                                <td><pre class="mb-0 text-wrap" style="white-space: pre-wrap; max-width:300px;"><?php echo htmlspecialchars(print_r(['prompt'=>$entry['prompt'] ?? '', 'domains'=>$entry['domains'] ?? []], true)); ?></pre></td>
                                <td><pre class="mb-0 text-wrap" style="white-space: pre-wrap; max-width:300px;"><?php echo htmlspecialchars($entry['response'] ?? ''); ?></pre></td>
                                <td><pre class="mb-0 text-wrap" style="white-space: pre-wrap; max-width:200px;"><?php echo htmlspecialchars($entry['error'] ?? ''); ?></pre></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if ($totalPages > 1): ?>
                    <nav>
                        <ul class="pagination">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?date=<?php echo urlencode($date); ?>&category=<?php echo urlencode($category); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/app.js"></script>
</body>
</html>
