<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}

require_once '../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    die('Błąd połączenia z bazą danych: ' . $e->getMessage());
}

$domainId = (int)$_GET['id'];

// Fetch domain data
$stmt = $db->prepare('SELECT * FROM domains WHERE id = ?');
$stmt->execute([$domainId]);
$domain = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$domain) {
    die('Nie znaleziono domeny.');
}

// Fetch categories and descriptions
$stmt = $db->prepare('
    SELECT c.name AS category, da.description, da.is_interesting
    FROM domain_analysis da
    JOIN categories c ON da.category_id = c.id
    WHERE da.domain_id = ?
');
$stmt->execute([$domainId]);
$analyses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Szczegóły domeny - Domain Monitor</title>
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
                <h1 class="h2"><i class="fas fa-eye"></i> Szczegóły domeny</h1>
                <a href="index.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i> Powrót</a>
            </div>
            <div class="card mb-4">
                <div class="card-body">
                    <h4 class="card-title mb-3"><?php echo htmlspecialchars($domain['domain_name']); ?></h4>
                    <p><strong>Data pobrania:</strong> <?php echo date('d.m.Y', strtotime($domain['fetch_date'])); ?></p>
                    <p><strong>Data rejestracji:</strong> <?php echo date('d.m.Y', strtotime($domain['registration_available_date'])); ?></p>
                    <?php if ($analyses): ?>
                        <h5 class="mt-4 mb-3">Kategorie</h5>
                        <ul class="list-group">
                            <?php foreach ($analyses as $item): ?>
                                <li class="list-group-item">
                                    <span class="badge bg-secondary me-2"><?php echo htmlspecialchars($item['category']); ?></span>
                                    <?php echo htmlspecialchars($item['description']); ?>
                                    <?php if ($item['is_interesting']): ?>
                                        <span class="badge bg-success ms-2"><i class="fas fa-star"></i> Interesująca</span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted">Brak analizy dla tej domeny.</p>
                    <?php endif; ?>
                </div>
            </div>
            <a href="https://<?php echo htmlspecialchars($domain['domain_name']); ?>" target="_blank" class="btn btn-outline-info">
                <i class="fas fa-external-link-alt"></i> Otwórz domenę
            </a>
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/app.js"></script>
</body>
</html>
