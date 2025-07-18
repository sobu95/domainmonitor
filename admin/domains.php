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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_all'])) {
    try {
        // Delete all domains (cascades to related tables)
        $stmt = $db->prepare('DELETE FROM domains');
        $stmt->execute();
        logActivity($db, 'delete_all_domains');
        $message = 'Wszystkie domeny zostały usunięte.';
    } catch (Exception $e) {
        $error = 'Wystąpił błąd podczas usuwania domen.';
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Domeny - Administracja</title>
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
                <h1 class="h2"><i class="fas fa-database"></i> Domeny</h1>
                <form method="POST" onsubmit="return confirm('Czy na pewno chcesz usunąć wszystkie domeny?');">
                    <input type="hidden" name="delete_all" value="1">
                    <button type="submit" class="btn btn-sm btn-danger">
                        <i class="fas fa-trash"></i> Usuń wszystkie domeny
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
            <p>Ta operacja spowoduje usunięcie wszystkich domen wraz z powiązanymi danymi.</p>
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/app.js"></script>
</body>
</html>
