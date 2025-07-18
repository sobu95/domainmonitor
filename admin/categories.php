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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_id'], $_POST['name'], $_POST['prompt'])) {
        $active = isset($_POST['active']) ? 1 : 0;
        $stmt = $db->prepare('UPDATE categories SET name = ?, prompt = ?, active = ? WHERE id = ?');
        $stmt->execute([$_POST['name'], $_POST['prompt'], $active, $_POST['update_id']]);
        logActivity($db, 'update_category', $_POST['update_id']);
        $message = 'Zaktualizowano kategorię.';
    } elseif (isset($_POST['delete_id'])) {
        $stmt = $db->prepare('DELETE FROM categories WHERE id = ?');
        $stmt->execute([$_POST['delete_id']]);
        logActivity($db, 'delete_category', $_POST['delete_id']);
        $message = 'Usunięto kategorię.';
    } elseif (isset($_POST['name'], $_POST['prompt'])) {
        $stmt = $db->prepare('INSERT INTO categories (name, prompt, active) VALUES (?, ?, 1)');
        $stmt->execute([$_POST['name'], $_POST['prompt']]);
        logActivity($db, 'add_category', $_POST['name']);
        $message = 'Dodano kategorię.';
    }
}

$editCategory = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare('SELECT * FROM categories WHERE id = ?');
    $stmt->execute([$_GET['edit']]);
    $editCategory = $stmt->fetch(PDO::FETCH_ASSOC);
}

$stmt = $db->query('SELECT * FROM categories ORDER BY created_at DESC');
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kategorie - Administracja</title>
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
                <h1 class="h2"><i class="fas fa-tags"></i> Kategorie</h1>
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
            <div class="card mb-4">
                <div class="card-header"><?php echo $editCategory ? 'Edytuj kategorię' : 'Dodaj kategorię'; ?></div>
                <div class="card-body">
                    <form method="POST">
                        <?php if ($editCategory): ?>
                            <input type="hidden" name="update_id" value="<?php echo $editCategory['id']; ?>">
                        <?php endif; ?>
                        <div class="mb-3">
                            <label class="form-label">Nazwa</label>
                            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($editCategory['name'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Prompt</label>
                            <textarea name="prompt" class="form-control" rows="3" required><?php echo htmlspecialchars($editCategory['prompt'] ?? ''); ?></textarea>
                        </div>
                        <?php if ($editCategory): ?>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="active" name="active" <?php echo $editCategory['active'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="active">Aktywna</label>
                        </div>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-primary">Zapisz</button>
                        <?php if ($editCategory): ?>
                            <a href="categories.php" class="btn btn-secondary">Anuluj</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            <div class="card">
                <div class="card-header">Lista kategorii</div>
                <div class="card-body table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr><th>ID</th><th>Nazwa</th><th>Aktywna</th><th>Akcje</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td><?php echo $cat['id']; ?></td>
                                <td><?php echo htmlspecialchars($cat['name']); ?></td>
                                <td><?php echo $cat['active'] ? 'Tak' : 'Nie'; ?></td>
                                <td>
                                    <a href="categories.php?edit=<?php echo $cat['id']; ?>" class="btn btn-sm btn-warning me-1">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="delete_id" value="<?php echo $cat['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger delete-action">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </td>
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
