<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalacja Domain Monitor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0"><i class="fas fa-cog"></i> Instalacja Domain Monitor</h3>
                    </div>
                    <div class="card-body">
                        <?php
                        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                            $config = [
                                'db_host' => $_POST['db_host'],
                                'db_name' => $_POST['db_name'],
                                'db_username' => $_POST['db_username'],
                                'db_password' => $_POST['db_password'],
                                'gemini_api_key' => $_POST['gemini_api_key'],
                                'gemini_model' => $_POST['gemini_model'],
                                'email_smtp_host' => $_POST['email_smtp_host'],
                                'email_smtp_port' => $_POST['email_smtp_port'],
                                'email_username' => $_POST['email_username'],
                                'email_password' => $_POST['email_password'],
                                'email_from_name' => $_POST['email_from_name'],
                                'site_url' => $_POST['site_url'],
                                'site_name' => $_POST['site_name'],
                                'timezone' => $_POST['timezone']
                            ];

                            // Zapisz konfigurację
                            $configContent = "<?php\nreturn " . var_export($config, true) . ";\n?>";
                            file_put_contents('config/config.php', $configContent);

                            // Utwórz tabele
                            try {
                                $pdo = new PDO("mysql:host={$config['db_host']};dbname={$config['db_name']}", 
                                              $config['db_username'], $config['db_password']);
                                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                                // SQL do utworzenia tabel
                                // SQL do utworzenia tabel - wbudowany w kod
                                $sqlQueries = [
                                    "CREATE TABLE IF NOT EXISTS users (
                                        id INT AUTO_INCREMENT PRIMARY KEY,
                                        username VARCHAR(50) UNIQUE NOT NULL,
                                        email VARCHAR(100) UNIQUE NOT NULL,
                                        password VARCHAR(255) NOT NULL,
                                        role ENUM('admin', 'user') DEFAULT 'user',
                                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                                    )",
                                    
                                    "CREATE TABLE IF NOT EXISTS categories (
                                        id INT AUTO_INCREMENT PRIMARY KEY,
                                        name VARCHAR(100) NOT NULL,
                                        prompt TEXT NOT NULL,
                                        active BOOLEAN DEFAULT TRUE,
                                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                    )",
                                    
                                    "CREATE TABLE IF NOT EXISTS domains (
                                        id INT AUTO_INCREMENT PRIMARY KEY,
                                        domain_name VARCHAR(255) NOT NULL,
                                        fetch_date DATE NOT NULL,
                                        registration_available_date DATE NOT NULL,
                                        dr INT NULL,
                                        linking_domains INT NULL,
                                        link_profile_strength VARCHAR(20) NULL,
                                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                        INDEX idx_domain_fetch (domain_name, fetch_date),
                                        INDEX idx_registration_date (registration_available_date)
                                    )",
                                    
                                    "CREATE TABLE IF NOT EXISTS domain_analysis (
                                        id INT AUTO_INCREMENT PRIMARY KEY,
                                        domain_id INT NOT NULL,
                                        category_id INT NOT NULL,
                                        description TEXT,
                                        is_interesting BOOLEAN DEFAULT FALSE,
                                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                        FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE,
                                        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
                                        UNIQUE KEY unique_domain_category (domain_id, category_id)
                                    )",
                                    
                                    "CREATE TABLE IF NOT EXISTS favorite_domains (
                                        id INT AUTO_INCREMENT PRIMARY KEY,
                                        user_id INT NOT NULL,
                                        domain_id INT NOT NULL,
                                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                                        FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE,
                                        UNIQUE KEY unique_user_domain (user_id, domain_id)
                                    )",
                                    
                                    "CREATE TABLE IF NOT EXISTS fetch_logs (
                                        id INT AUTO_INCREMENT PRIMARY KEY,
                                        fetch_date DATE NOT NULL,
                                        domains_count INT DEFAULT 0,
                                        status ENUM('success', 'error') DEFAULT 'success',
                                        error_message TEXT,
                                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                    )",
                                    
                                    "CREATE TABLE IF NOT EXISTS system_config (
                                        id INT AUTO_INCREMENT PRIMARY KEY,
                                        config_key VARCHAR(100) UNIQUE NOT NULL,
                                        config_value TEXT,
                                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                                    )",
                                    
                                    "CREATE TABLE IF NOT EXISTS notifications (
                                        id INT AUTO_INCREMENT PRIMARY KEY,
                                        user_id INT NOT NULL,
                                        domain_id INT NOT NULL,
                                        type ENUM('reminder', 'summary') DEFAULT 'reminder',
                                        sent_at TIMESTAMP NULL,
                                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                                        FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE
                                    )",

                                    "CREATE TABLE IF NOT EXISTS activity_logs (
                                        id INT AUTO_INCREMENT PRIMARY KEY,
                                        user_id INT NOT NULL,
                                        action VARCHAR(100) NOT NULL,
                                        details TEXT,
                                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                                    )",
                                    
                                    "INSERT IGNORE INTO categories (name, prompt) VALUES 
                                    ('Moda', 'Przeanalizuj przesłaną listę domen, czy na tej liście znajdują się domeny, które pasują pod stronę o tematyce: moda. Wynik przedstaw w tabeli podając domenę oraz jej krótki opis, dlaczego jest interesująca. Tabela powinna być w formacie HTML.'),
                                    ('Uroda', 'Przeanalizuj przesłaną listę domen, czy na tej liście znajdują się domeny, które pasują pod stronę o tematyce: uroda. Wynik przedstaw w tabeli podając domenę oraz jej krótki opis, dlaczego jest interesująca. Tabela powinna być w formacie HTML.'),
                                    ('Wnętrza', 'Przeanalizuj przesłaną listę domen, czy na tej liście znajdują się domeny, które pasują pod stronę o tematyce: wnętrza. Wynik przedstaw w tabeli podając domenę oraz jej krótki opis, dlaczego jest interesująca. Tabela powinna być w formacie HTML.'),
                                    ('Popularne historycznie', 'Przeanalizuj przesłaną listę domen, czy na tej liście znajdują się domeny, które historycznie były popularną stroną w internecie? Wynik przedstaw w tabeli podając domenę oraz jej krótki opis, dlaczego jest interesująca. Tabela powinna być w formacie HTML.')",
                                    
                                    "INSERT IGNORE INTO system_config (config_key, config_value) VALUES 
                                    ('last_fetch_date', ''),
                                    ('email_notifications_enabled', '1'),
                                    ('gemini_api_enabled', '1'),
                                    ('fetch_time', '09:05')"
                                ];
                                
                                foreach ($sqlQueries as $query) {
                                    try {
                                        $pdo->exec($query);
                                    } catch (PDOException $e) {
                                        // Ignoruj błędy dla INSERT IGNORE (mogą już istnieć)
                                        if (strpos($query, 'INSERT IGNORE') === false && strpos($query, 'CREATE TABLE IF NOT EXISTS') === false) {
                                            throw $e;
                                        }
                                    }
                                }

                                // Utwórz domyślnego administratora
                                $adminPassword = password_hash($_POST['admin_password'], PASSWORD_DEFAULT);
                                
                                // Sprawdź czy admin już istnieje
                                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
                                $stmt->execute();
                                $adminExists = $stmt->fetchColumn() > 0;
                                
                                if (!$adminExists) {
                                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, created_at) VALUES (?, ?, ?, 'admin', NOW())");
                                    $stmt->execute(['admin', $_POST['admin_email'], $adminPassword]);
                                }

                                echo '<div class="alert alert-success">
                                        <h4><i class="fas fa-check"></i> Instalacja zakończona pomyślnie!</h4>
                                        <p>System został zainstalowany. Teraz skonfiguruj zadania cron:</p>
                                      </div>';
                                
                                include 'install/cron_instructions.php';
                                
                            } catch (Exception $e) {
                                echo '<div class="alert alert-danger">
                                        <h4>Błąd instalacji:</h4>
                                        <p>' . htmlspecialchars($e->getMessage()) . '</p>
                                        <small>Sprawdź czy:</small>
                                        <ul>
                                            <li>Baza danych istnieje i jest dostępna</li>
                                            <li>Użytkownik ma uprawnienia do tworzenia tabel</li>
                                            <li>Dane połączenia są poprawne</li>
                                        </ul>
                                      </div>';
                            }
                        } else {
                        ?>
                        <form method="POST">
                            <h5 class="mb-3">Konfiguracja bazy danych</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Host bazy danych</label>
                                        <input type="text" class="form-control" name="db_host" value="localhost" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Nazwa bazy danych</label>
                                        <input type="text" class="form-control" name="db_name" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Użytkownik bazy danych</label>
                                        <input type="text" class="form-control" name="db_username" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Hasło bazy danych</label>
                                        <input type="password" class="form-control" name="db_password">
                                    </div>
                                </div>
                            </div>

                            <h5 class="mb-3 mt-4">Konfiguracja Gemini API</h5>
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label class="form-label">Klucz API Gemini</label>
                                        <input type="text" class="form-control" name="gemini_api_key" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Model Gemini</label>
                                        <input type="text" class="form-control" name="gemini_model" value="gemini-2.5-flash" required>
                                    </div>
                                </div>
                            </div>

                            <h5 class="mb-3 mt-4">Konfiguracja email</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">SMTP Host</label>
                                        <input type="text" class="form-control" name="email_smtp_host" value="smtp.gmail.com" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">SMTP Port</label>
                                        <input type="number" class="form-control" name="email_smtp_port" value="587" required>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Email użytkownika</label>
                                        <input type="email" class="form-control" name="email_username" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Hasło email</label>
                                        <input type="password" class="form-control" name="email_password" required>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Nazwa nadawcy</label>
                                <input type="text" class="form-control" name="email_from_name" value="Domain Monitor" required>
                            </div>

                            <h5 class="mb-3 mt-4">Konfiguracja strony</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">URL strony</label>
                                        <input type="url" class="form-control" name="site_url" value="<?php echo 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Nazwa strony</label>
                                        <input type="text" class="form-control" name="site_name" value="Domain Monitor" required>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Strefa czasowa</label>
                                <select class="form-control" name="timezone" required>
                                    <option value="Europe/Warsaw">Europe/Warsaw</option>
                                    <option value="Europe/London">Europe/London</option>
                                    <option value="America/New_York">America/New_York</option>
                                    <option value="UTC">UTC</option>
                                </select>
                            </div>

                            <h5 class="mb-3 mt-4">Konto administratora</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Email administratora</label>
                                        <input type="email" class="form-control" name="admin_email" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Hasło administratora</label>
                                        <input type="password" class="form-control" name="admin_password" required>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg w-100">
                                <i class="fas fa-download"></i> Zainstaluj system
                            </button>
                        </form>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>