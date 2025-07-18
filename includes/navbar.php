<?php
$config = include __DIR__ . '/../config/config.php';
$baseUrl = rtrim($config['site_url'], '/');
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?= $baseUrl ?>/index.php">
            <i class="fas fa-globe"></i> Domain Monitor
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?= $baseUrl ?>/index.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= $baseUrl ?>/domains/">
                        <i class="fas fa-list"></i> Domeny
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= $baseUrl ?>/favorites.php">
                        <i class="fas fa-heart"></i> Ulubione
                    </a>
                </li>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-cog"></i> Administracja
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= $baseUrl ?>/admin/categories.php">Kategorie</a></li>
                        <li><a class="dropdown-item" href="<?= $baseUrl ?>/admin/users.php">Użytkownicy</a></li>
                        <li><a class="dropdown-item" href="<?= $baseUrl ?>/admin/config.php">Konfiguracja</a></li>
                        <li><a class="dropdown-item" href="<?= $baseUrl ?>/admin/domains.php">Domeny</a></li>
                        <li><a class="dropdown-item" href="<?= $baseUrl ?>/admin/logs.php">Logi</a></li>
                    </ul>
                </li>
                <?php endif; ?>
            </ul>
            
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= $baseUrl ?>/profile.php">Profil</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= $baseUrl ?>/auth/logout.php">Wyloguj</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>