<?php
session_start();

// Enable verbose error reporting in non-production environments
if (getenv('APP_ENV') !== 'production') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
}

// Sprawdź czy system jest zainstalowany
if (!file_exists('config/config.php')) {
    header('Location: install.php');
    exit;
}

// Sprawdź czy użytkownik jest zalogowany
if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

require_once 'config/database.php';
require_once 'includes/functions.php';

try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    die('Błąd połączenia z bazą danych: ' . $e->getMessage());
}

// Pobierz statystyki
$stats = getDashboardStats($db);
$recentDomains = getRecentDomains($db, 10);
$upcomingRegistrations = getUpcomingRegistrations($db, $_SESSION['user_id'], 7);
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Domain Monitor - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-download"></i> Export
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Statystyki -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Domeny dzisiaj
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format($stats['today_domains']); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-globe fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Interesujące domeny
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format($stats['interesting_domains']); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-star fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Ulubione
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format($stats['favorite_domains']); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-heart fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Przypomnienia
                                        </div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo number_format(count($upcomingRegistrations)); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-bell fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Nadchodzące rejestracje -->
                <?php if (!empty($upcomingRegistrations)): ?>
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card shadow">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-clock"></i> Nadchodzące rejestracje
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Domena</th>
                                                <th>Kategoria</th>
                                                <th>Data rejestracji</th>
                                                <th>Dni do rejestracji</th>
                                                <th>Akcje</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($upcomingRegistrations as $domain): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($domain['domain_name']); ?></strong></td>
                                                <td><span class="badge bg-info"><?php echo htmlspecialchars($domain['category_name']); ?></span></td>
                                                <td><?php echo date('d.m.Y', strtotime($domain['registration_available_date'])); ?></td>
                                                <td>
                                                    <?php 
                                                    $days = (strtotime($domain['registration_available_date']) - time()) / (60 * 60 * 24);
                                                    echo ceil($days) . ' dni';
                                                    ?>
                                                </td>
                                                <td>
                                                    <a href="domains/view.php?id=<?php echo $domain['domain_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Ostatnie domeny -->
                <div class="row">
                    <div class="col-12">
                        <div class="card shadow">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-list"></i> Ostatnie domeny
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Domena</th>
                                                <th>Data pobrania</th>
                                                <th>Data rejestracji</th>
                                                <th>Kategorie</th>
                                                <th>Akcje</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentDomains as $domain): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($domain['domain_name']); ?></strong></td>
                                                <td><?php echo date('d.m.Y', strtotime($domain['fetch_date'])); ?></td>
                                                <td><?php echo date('d.m.Y', strtotime($domain['registration_available_date'])); ?></td>
                                                <td>
                                                    <?php if (!empty($domain['categories'])): ?>
                                                        <?php foreach (explode(',', $domain['categories']) as $category): ?>
                                                            <span class="badge bg-secondary me-1"><?php echo htmlspecialchars($category); ?></span>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Brak analizy</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="domains/view.php?id=<?php echo $domain['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <button class="btn btn-sm btn-outline-warning toggle-favorite" data-domain-id="<?php echo $domain['id']; ?>">
                                                        <i class="fas fa-heart"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="domains/" class="btn btn-primary">
                                        <i class="fas fa-list"></i> Zobacz wszystkie domeny
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>