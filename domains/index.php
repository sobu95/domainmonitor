<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

require_once '../config/database.php';
require_once '../includes/functions.php';

$database = new Database();
$db = $database->getConnection();

// Parametry filtrowania
$category = $_GET['category'] ?? '';
$filter = $_GET['filter'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Buduj zapytanie
$whereConditions = [];
$params = [];

if ($category) {
    $whereConditions[] = "da.category_id = ?";
    $params[] = $category;
}

if ($filter === 'today') {
    $whereConditions[] = "d.fetch_date = CURDATE()";
} elseif ($filter === 'interesting') {
    $whereConditions[] = "da.is_interesting = 1";
} elseif ($filter === 'favorites') {
    $whereConditions[] = "fd.user_id = ?";
    $params[] = $_SESSION['user_id'];
}

if ($search) {
    $whereConditions[] = "d.domain_name LIKE ?";
    $params[] = "%$search%";
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Pobierz domeny
$sql = "
    SELECT DISTINCT d.*, 
           GROUP_CONCAT(DISTINCT c.name) as categories,
           GROUP_CONCAT(DISTINCT da.description SEPARATOR ' | ') as descriptions,
           CASE WHEN fd.id IS NOT NULL THEN 1 ELSE 0 END as is_favorite
    FROM domains d
    LEFT JOIN domain_analysis da ON d.id = da.domain_id
    LEFT JOIN categories c ON da.category_id = c.id
    LEFT JOIN favorite_domains fd ON d.id = fd.domain_id AND fd.user_id = ?
    $whereClause
    GROUP BY d.id
    ORDER BY d.created_at DESC
    LIMIT ? OFFSET ?
";

$countSql = "
    SELECT COUNT(DISTINCT d.id) as total
    FROM domains d
    LEFT JOIN domain_analysis da ON d.id = da.domain_id
    LEFT JOIN categories c ON da.category_id = c.id
    LEFT JOIN favorite_domains fd ON d.id = fd.domain_id AND fd.user_id = ?
    $whereClause
";

$allParams = array_merge([$_SESSION['user_id']], $params, [$perPage, $offset]);
$countParams = array_merge([$_SESSION['user_id']], $params);

$stmt = $db->prepare($sql);
$stmt->execute($allParams);
$domains = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $db->prepare($countSql);
$stmt->execute($countParams);
$totalDomains = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$totalPages = ceil($totalDomains / $perPage);

// Pobierz kategorie dla filtra
$stmt = $db->prepare("SELECT * FROM categories WHERE active = 1 ORDER BY name");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Domeny - Domain Monitor</title>
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
                    <h1 class="h2"><i class="fas fa-list"></i> Domeny</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-download"></i> Export
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Filtry i wyszukiwanie -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <div class="search-box">
                                    <i class="fas fa-search search-icon"></i>
                                    <input type="text" class="form-control" name="search" placeholder="Szukaj domen..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <select class="form-control" name="category">
                                    <option value="">Wszystkie kategorie</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-control" name="filter">
                                    <option value="">Wszystkie</option>
                                    <option value="today" <?php echo $filter === 'today' ? 'selected' : ''; ?>>Dzisiejsze</option>
                                    <option value="interesting" <?php echo $filter === 'interesting' ? 'selected' : ''; ?>>Interesujące</option>
                                    <option value="favorites" <?php echo $filter === 'favorites' ? 'selected' : ''; ?>>Ulubione</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search"></i> Filtruj
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Statystyki -->
                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            Znaleziono <strong><?php echo number_format($totalDomains); ?></strong> domen
                            <?php if ($search): ?>
                                dla frazy "<strong><?php echo htmlspecialchars($search); ?></strong>"
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Lista domen -->
                <div class="card">
                    <div class="card-body">
                        <?php if (empty($domains)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-search fa-3x text-muted mb-3"></i>
                            <h5>Brak domen</h5>
                            <p class="text-muted">Nie znaleziono domen spełniających kryteria wyszukiwania.</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Domena</th>
                                        <th>Data pobrania</th>
                                        <th>Data rejestracji</th>
                                        <th>Kategorie</th>
                                        <th>Status</th>
                                        <th>Akcje</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($domains as $domain): ?>
                                    <tr class="domain-row" data-categories="<?php echo htmlspecialchars($domain['categories'] ?? ''); ?>">
                                        <td>
                                            <strong class="domain-name"><?php echo htmlspecialchars($domain['domain_name']); ?></strong>
                                            <?php if ($domain['descriptions']): ?>
                                            <br><small class="text-muted domain-description"><?php echo htmlspecialchars(substr($domain['descriptions'], 0, 100)); ?>...</small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('d.m.Y', strtotime($domain['fetch_date'])); ?></td>
                                        <td>
                                            <?php 
                                            $regDate = strtotime($domain['registration_available_date']);
                                            $today = time();
                                            $daysLeft = ceil(($regDate - $today) / (60 * 60 * 24));
                                            ?>
                                            <?php echo date('d.m.Y', $regDate); ?>
                                            <?php if ($daysLeft <= 7 && $daysLeft > 0): ?>
                                                <br><small class="text-warning"><i class="fas fa-clock"></i> <?php echo $daysLeft; ?> dni</small>
                                            <?php elseif ($daysLeft <= 0): ?>
                                                <br><small class="text-success"><i class="fas fa-check"></i> Dostępna</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($domain['categories']): ?>
                                                <?php foreach (explode(',', $domain['categories']) as $cat): ?>
                                                    <span class="badge bg-secondary me-1"><?php echo htmlspecialchars(trim($cat)); ?></span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="text-muted">Brak analizy</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($domain['is_favorite']): ?>
                                                <span class="badge bg-danger"><i class="fas fa-heart"></i> Ulubiona</span>
                                            <?php endif; ?>
                                            <?php if ($domain['categories']): ?>
                                                <span class="badge bg-success"><i class="fas fa-star"></i> Interesująca</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="view.php?id=<?php echo $domain['id']; ?>" class="btn btn-sm btn-outline-primary" title="Zobacz szczegóły">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <button class="btn btn-sm btn-outline-warning toggle-favorite <?php echo $domain['is_favorite'] ? 'active' : ''; ?>" 
                                                        data-domain-id="<?php echo $domain['id']; ?>" 
                                                        title="<?php echo $domain['is_favorite'] ? 'Usuń z ulubionych' : 'Dodaj do ulubionych'; ?>">
                                                    <i class="<?php echo $domain['is_favorite'] ? 'fas' : 'far'; ?> fa-heart"></i>
                                                </button>
                                                <a href="https://<?php echo htmlspecialchars($domain['domain_name']); ?>" target="_blank" class="btn btn-sm btn-outline-info" title="Otwórz domenę">
                                                    <i class="fas fa-external-link-alt"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginacja -->
                        <?php if ($totalPages > 1): ?>
                        <nav aria-label="Paginacja domen" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                        <i class="fas fa-chevron-left"></i> Poprzednia
                                    </a>
                                </li>
                                <?php endif; ?>

                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                        Następna <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
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