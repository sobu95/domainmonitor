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

$logDir = realpath(__DIR__ . '/../logs');
$files = [];

if ($logDir && is_dir($logDir)) {
    $logDir .= DIRECTORY_SEPARATOR;
    $files = array_values(array_filter(scandir($logDir), function ($file) use ($logDir) {
        return is_file($logDir . $file);
    }));
}

$selectedFile = isset($_GET['file']) ? basename($_GET['file']) : null;
$fileContent = '';

if ($selectedFile) {
    $filePath = realpath($logDir . $selectedFile);
    if ($filePath && strpos($filePath, $logDir) === 0 && file_exists($filePath)) {
        $fileContent = file_get_contents($filePath);
    }
}
?>
<!DOCTYPE html>
<html lang='pl'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Logi błędów - Administracja</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css' rel='stylesheet'>
    <link href='../assets/css/style.css' rel='stylesheet'>
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class='container-fluid'>
    <div class='row'>
        <?php include '../includes/sidebar.php'; ?>
        <main class='col-md-9 ms-sm-auto col-lg-10 px-md-4'>
            <div class='d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom'>
                <h1 class='h2'><i class='fas fa-bug'></i> Logi błędów</h1>
            </div>
            <div class='row'>
                <div class='col-md-3'>
                    <div class='list-group'>
                        <?php foreach ($files as $file): ?>
                            <a href='?file=<?php echo urlencode($file); ?>' class='list-group-item list-group-item-action <?php echo $selectedFile === $file ? 'active' : ''; ?>'>
                                <?php echo htmlspecialchars($file); ?>
                            </a>
                        <?php endforeach; ?>
                        <?php if (empty($files)): ?>
                            <div class='list-group-item'>Brak plików logów</div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class='col-md-9'>
                    <?php if ($selectedFile && $fileContent !== ''): ?>
                        <div class='card'>
                            <div class='card-header'>
                                <?php echo htmlspecialchars($selectedFile); ?>
                            </div>
                            <div class='card-body'>
                                <pre class='mb-0'><?php echo nl2br(htmlspecialchars($fileContent)); ?></pre>
                            </div>
                        </div>
                    <?php elseif ($selectedFile): ?>
                        <div class='alert alert-warning'>Nie można odczytać pliku.</div>
                    <?php else: ?>
                        <div class='alert alert-info'>Wybierz plik logu z listy po lewej.</div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>
<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
<script src='../assets/js/app.js'></script>
</body>
</html>
