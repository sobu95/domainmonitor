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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['config']) && is_array($_POST['config'])) {
    foreach ($_POST['config'] as $key => $value) {
        $stmt = $db->prepare('UPDATE system_config SET config_value = ? WHERE config_key = ?');
        $stmt->execute([$value, $key]);
    }
    logActivity($db, 'update_config');
}

$stmt = $db->query('SELECT config_key, config_value FROM system_config ORDER BY config_key');
$config = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfiguracja - Administracja</title>
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
                <h1 class="h2"><i class="fas fa-cog"></i> Konfiguracja</h1>
            </div>
            <div class="card">
                <div class="card-body">
                    <form method="POST">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr><th>Klucz</th><th>Wartość</th></tr>
                                </thead>
                                <tbody>
                                <?php foreach ($config as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['config_key']); ?></td>
                                        <td>
                                            <input type="text" name="config[<?php echo htmlspecialchars($row['config_key']); ?>]" value="<?php echo htmlspecialchars($row['config_value']); ?>" class="form-control">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <button type="submit" class="btn btn-primary">Zapisz</button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/app.js"></script>
</body>
</html>
