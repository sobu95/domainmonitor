<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

require_once '../config/database.php';
require_once '../includes/functions.php';

$database = new Database();
$db = $database->getConnection();

$domainId = intval($_GET['id'] ?? 0);

if (!$domainId) {
    header('Location: index.php');
    exit;
}

try {
    // Pobierz szczegóły domeny
    $stmt = $db->prepare("
        SELECT d.*, 
               CASE WHEN fd.id IS NOT NULL THEN 1 ELSE 0 END as is_favorite
        FROM domains d
        LEFT JOIN favorite_domains fd ON d.id = fd.domain_id AND fd.user_id = ?
        WHERE d.id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $domainId]);
    $domain = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$domain) {
        header('Location: index.php');
        exit;
    }

    // Pobierz analizy domeny
    $stmt = $db->prepare("
        SELECT da.*, c.name as category_name
        FROM domain_analysis da
        JOIN categories c ON da.category_id = c.id
        WHERE da.domain_id = ?
        ORDER BY c.name
    ");
    $stmt->execute([$domainId]);
    $analyses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Pobierz dane widoczności z Semstorm
    $visibility = getSemstormVisibility($domain['domain_name']);

} catch (Exception $e) {
    error_log("Error in domains/view.php: " . $e->getMessage());
    header('Location: index.php');
    exit;
}

$regDate = strtotime($domain['registration_available_date']);
$today = time();
$daysLeft = ceil(($regDate - $today) / (60 * 60 * 24));
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($domain['domain_name']); ?> - Domain Monitor</title>
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
                    <h1 class="h2">
                        <i class="fas fa-globe"></i> <?php echo htmlspecialchars($domain['domain_name']); ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="index.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Powrót
                            </a>
                            <button class="btn btn-sm btn-outline-warning toggle-favorite <?php echo $domain['is_favorite'] ? 'active' : ''; ?>" 
                                    data-domain-id="<?php echo $domain['id']; ?>">
                                <i class="<?php echo $domain['is_favorite'] ? 'fas' : 'far'; ?> fa-heart"></i>
                                <?php echo $domain['is_favorite'] ? 'Usuń z ulubionych' : 'Dodaj do ulubionych'; ?>
                            </button>
                            <a href="https://<?php echo htmlspecialchars($domain['domain_name']); ?>" target="_blank" class="btn btn-sm btn-outline-info">
                                <i class="fas fa-external-link-alt"></i> Otwórz domenę
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Informacje o domenie -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-info-circle"></i> Informacje o domenie</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong>Nazwa domeny:</strong><br>
                                        <span class="h4"><?php echo htmlspecialchars($domain['domain_name']); ?></span>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Status:</strong><br>
                                        <?php if ($domain['is_favorite']): ?>
                                            <span class="badge bg-danger fs-6"><i class="fas fa-heart"></i> Ulubiona</span>
                                        <?php endif; ?>
                                        <?php if (!empty($analyses)): ?>
                                            <span class="badge bg-success fs-6"><i class="fas fa-star"></i> Interesująca</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <hr>
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong>Data pobrania:</strong><br>
                                        <?php echo date('d.m.Y', strtotime($domain['fetch_date'])); ?>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Data dostępności rejestracji:</strong><br>
                                        <?php echo date('d.m.Y', $regDate); ?>
                                        <?php if ($daysLeft <= 7 && $daysLeft > 0): ?>
                                            <br><small class="text-warning"><i class="fas fa-clock"></i> Za <?php echo $daysLeft; ?> dni</small>
                                        <?php elseif ($daysLeft <= 0): ?>
                                            <br><small class="text-success"><i class="fas fa-check"></i> Dostępna do rejestracji</small>
                                        <?php else: ?>
                                            <br><small class="text-muted">Za <?php echo $daysLeft; ?> dni</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-clock"></i> Countdown</h5>
                            </div>
                            <div class="card-body text-center">
                                <?php if ($daysLeft > 0): ?>
                                    <div class="display-4 text-primary"><?php echo $daysLeft; ?></div>
                                    <p class="text-muted">dni do rejestracji</p>
                                <?php else: ?>
                                    <div class="display-4 text-success">0</div>
                                    <p class="text-success">Dostępna do rejestracji!</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($visibility): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-line"></i> Widoczność</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6 col-md-3 mb-3">
                                <div class="h4 mb-0"><?php echo $visibility['current']['top3'] ?? 0; ?></div>
                                <div class="text-muted">TOP3</div>
                            </div>
                            <div class="col-6 col-md-3 mb-3">
                                <div class="h4 mb-0"><?php echo $visibility['current']['top10'] ?? 0; ?></div>
                                <div class="text-muted">TOP10</div>
                            </div>
                            <div class="col-6 col-md-3 mb-3">
                                <div class="h4 mb-0"><?php echo $visibility['current']['top20'] ?? 0; ?></div>
                                <div class="text-muted">TOP20</div>
                            </div>
                            <div class="col-6 col-md-3 mb-3">
                                <div class="h4 mb-0"><?php echo $visibility['current']['traffic'] ?? 0; ?></div>
                                <div class="text-muted">Ruch</div>
                            </div>
                        </div>
                        <?php if (!empty($visibility['peak'])): ?>
                        <hr>
                        <p class="mb-0 text-muted small">
                            Maksimum z <?php echo date('Y-m-d', strtotime($visibility['peak']['date'])); ?>:
                            TOP3 <?php echo $visibility['peak']['top3'] ?? 0; ?>,
                            TOP10 <?php echo $visibility['peak']['top10'] ?? 0; ?>,
                            TOP20 <?php echo $visibility['peak']['top20'] ?? 0; ?>,
                            ruch <?php echo $visibility['peak']['traffic'] ?? 0; ?>
                        </p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Analiza AI -->
                <?php if (!empty($analyses)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-robot"></i> Analiza AI</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($analyses as $analysis): ?>
                        <div class="mb-4">
                            <h6 class="text-primary">
                                <span class="badge bg-primary"><?php echo htmlspecialchars($analysis['category_name']); ?></span>
                            </h6>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($analysis['description'])); ?></p>
                        </div>
                        <?php if ($analysis !== end($analyses)): ?>
                        <hr>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="card mb-4">
                    <div class="card-body text-center">
                        <i class="fas fa-robot fa-3x text-muted mb-3"></i>
                        <h5>Brak analizy AI</h5>
                        <p class="text-muted">Ta domena nie została jeszcze przeanalizowana przez system AI.</p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Akcje -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-tools"></i> Akcje</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <a href="https://who.is/whois/<?php echo urlencode($domain['domain_name']); ?>" target="_blank" class="btn btn-outline-primary w-100 mb-2">
                                    <i class="fas fa-search"></i> Sprawdź WHOIS
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="https://web.archive.org/web/*/<?php echo urlencode($domain['domain_name']); ?>" target="_blank" class="btn btn-outline-info w-100 mb-2">
                                    <i class="fas fa-history"></i> Historia strony
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="https://www.google.com/search?q=site:<?php echo urlencode($domain['domain_name']); ?>" target="_blank" class="btn btn-outline-success w-100 mb-2">
                                    <i class="fab fa-google"></i> Szukaj w Google
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js"></script>
</body>
</html>