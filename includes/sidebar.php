<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : ''; ?>" href="index.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], '/domains/') !== false ? 'active' : ''; ?>" href="domains/">
                    <i class="fas fa-list"></i> Wszystkie domeny
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'favorites.php' ? 'active' : ''; ?>" href="favorites.php">
                    <i class="fas fa-heart"></i> Ulubione
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="domains/?filter=today">
                    <i class="fas fa-calendar-day"></i> Dzisiejsze
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="domains/?filter=interesting">
                    <i class="fas fa-star"></i> Interesujące
                </a>
            </li>
        </ul>

        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Kategorie</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <?php
            try {
                $stmt = $db->prepare("SELECT * FROM categories WHERE active = 1 ORDER BY name");
                $stmt->execute();
                $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $categories = [];
            }
            foreach ($categories as $category):
            ?>
            <li class="nav-item">
                <a class="nav-link" href="domains/?category=<?php echo $category['id']; ?>">
                    <i class="fas fa-tag"></i> <?php echo htmlspecialchars($category['name']); ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>

        <?php if ($_SESSION['role'] === 'admin'): ?>
        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Administracja</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link" href="admin/categories.php">
                    <i class="fas fa-tags"></i> Kategorie
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="admin/users.php">
                    <i class="fas fa-users"></i> Użytkownicy
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="admin/config.php">
                    <i class="fas fa-cog"></i> Konfiguracja
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="admin/logs.php">
                    <i class="fas fa-file-alt"></i> Logi
                </a>
            </li>
        </ul>
        <?php endif; ?>
    </div>
</nav>