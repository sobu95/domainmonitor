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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_logs'])) {
    try {
        $stmt = $db->prepare('DELETE FROM fetch_logs');
        $stmt->execute();
        logActivity($db, 'clear_fetch_logs');
        $message = 'Logi zostały pomyślnie wyczyszczone.';
    } catch (Exception $e) {
        $error = 'Wystąpił błąd podczas czyszczenia logów.';
    }
}

$stmt = $db->query('SELECT * FROM fetch_logs ORDER BY created_at DESC LIMIT 50');
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logi - Administracja</title>
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
                <h1 class="h2"><i class="fas fa-file-alt"></i> Logi</h1>
                <form method="POST" onsubmit="return confirm('Czy na pewno chcesz wyczyścić wszystkie logi?');">
                    <input type="hidden" name="clear_logs" value="1">
                    <button type="submit" class="btn btn-sm btn-danger">
                        <i class="fas fa-trash"></i> Wyczyść logi
                    </button>
                </form>
            </div>
            <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check"></i> <?php echo htmlspecialchars($message); ?>
            </div>
            <?php elseif ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            <div class="card">
                <div class="card-body table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr><th>Data</th><th>Liczba domen</th><th>Status</th><th>Komunikat</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($log['fetch_date']); ?></td>
                                <td><?php echo $log['domains_count']; ?></td>
                                <td><?php echo $log['status']; ?></td>
                                <td><?php echo htmlspecialchars($log['error_message']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/app.js"></script>
</body>
</html>
