<?php
session_start();

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

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['remove_weak_favorites'])) {
        $stmt = $db->prepare(
            "DELETE fd FROM favorite_domains fd " .
            "JOIN domains d ON fd.domain_id = d.id " .
            "WHERE fd.user_id = ? AND (d.link_profile_strength IS NULL OR d.link_profile_strength = '' OR d.link_profile_strength = 'Słaby')"
        );
        $stmt->execute([$_SESSION['user_id']]);
        logActivity($db, 'delete_weak_favorites');
        $message = 'Usunięto domeny o słabym profilu linkowania.';
    } elseif (isset($_POST['dr'], $_POST['links'], $_POST['strength'])) {
        $update = $db->prepare('UPDATE domains SET dr = ?, linking_domains = ?, link_profile_strength = ? WHERE id = ?');
        foreach ((array)$_POST['dr'] as $id => $drVal) {
            $linkVal = $_POST['links'][$id] ?? null;
            $strengthVal = $_POST['strength'][$id] ?? null;
            $drVal = $drVal === '' ? null : $drVal;
            $linkVal = $linkVal === '' ? null : $linkVal;
            $strengthVal = $strengthVal === '' ? null : $strengthVal;
            $update->execute([$drVal, $linkVal, $strengthVal, $id]);
        }
        logActivity($db, 'update_domain_metrics', 'favorites');
        $message = 'Zapisano zmiany.';
    }
}

$stmt = $db->prepare("SELECT
        d.id,
        d.domain_name,
        d.fetch_date,
        d.registration_available_date,
        d.created_at,
        d.dr,
        d.linking_domains,
        d.link_profile_strength,
        GROUP_CONCAT(DISTINCT c.name) AS categories,
        GROUP_CONCAT(DISTINCT da.description SEPARATOR ' | ') AS descriptions
     FROM favorite_domains fd
     JOIN domains d ON fd.domain_id = d.id
     LEFT JOIN domain_analysis da ON d.id = da.domain_id
     LEFT JOIN categories c ON da.category_id = c.id
     WHERE fd.user_id = ?
     GROUP BY
        d.id,
        d.domain_name,
        d.fetch_date,
        d.registration_available_date,
        d.created_at,
        d.dr,
        d.linking_domains,
        d.link_profile_strength
     ORDER BY fd.created_at DESC");

$stmt->execute([$_SESSION['user_id']]);
$domains = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ulubione domeny - Domain Monitor</title>
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
                    <h1 class="h2"><i class="fas fa-heart"></i> Ulubione domeny</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <form method="POST" class="ms-2" onsubmit="return confirm('Czy na pewno wszystkie domeny mają odpowiednie statusy?');">
                            <input type="hidden" name="remove_weak_favorites" value="1">
                            <button type="submit" class="btn btn-sm btn-danger">
                                <i class="fas fa-trash"></i> Usuń słabe domeny
                            </button>
                        </form>
                    </div>
                </div>

                <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check"></i> <?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <?php if (empty($domains)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-heart-broken fa-3x text-muted mb-3"></i>
                            <h5>Brak ulubionych domen</h5>
                            <p class="text-muted">Dodaj domeny do ulubionych, aby szybciej je odnaleźć.</p>
                        </div>
                        <?php else: ?>
                        <form method="POST">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Domena</th>
                                        <th>Data pobrania</th>
                                        <th>Data rejestracji</th>
                                        <th>Kategorie</th>
                                        <th>DR</th>
                                        <th>L. domen</th>
                                        <th>Siła profilu linkowania</th>
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
                                        <td><?php echo date('d.m.Y', strtotime($domain['registration_available_date'])); ?></td>
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
                                            <input type="number" class="form-control form-control-sm" name="dr[<?php echo $domain['id']; ?>]" value="<?php echo htmlspecialchars($domain['dr']); ?>">
                                        </td>
                                        <td>
                                            <input type="number" class="form-control form-control-sm" name="links[<?php echo $domain['id']; ?>]" value="<?php echo htmlspecialchars($domain['linking_domains']); ?>">
                                        </td>
                                        <td>
                                            <select class="form-select form-select-sm" name="strength[<?php echo $domain['id']; ?>]">
                                                <option value="" <?php echo $domain['link_profile_strength'] === null ? 'selected' : ''; ?>>-</option>
                                                <option value="Silny" <?php echo $domain['link_profile_strength'] === 'Silny' ? 'selected' : ''; ?>>Silny</option>
                                                <option value="Przyzwoity" <?php echo $domain['link_profile_strength'] === 'Przyzwoity' ? 'selected' : ''; ?>>Przyzwoity</option>
                                                <option value="Słaby" <?php echo $domain['link_profile_strength'] === 'Słaby' ? 'selected' : ''; ?>>Słaby</option>
                                            </select>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="domains/view.php?id=<?php echo $domain['id']; ?>" class="btn btn-sm btn-outline-primary" title="Zobacz szczegóły">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <button class="btn btn-sm btn-outline-warning toggle-favorite active" data-domain-id="<?php echo $domain['id']; ?>" title="Usuń z ulubionych">
                                                    <i class="fas fa-heart"></i>
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
                        <button type="submit" class="btn btn-primary mt-2">Zapisz zmiany</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
