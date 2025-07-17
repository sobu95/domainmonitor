-- Tabela użytkowników
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela kategorii
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    prompt TEXT NOT NULL,
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela domen
CREATE TABLE IF NOT EXISTS domains (
    id INT AUTO_INCREMENT PRIMARY KEY,
    domain_name VARCHAR(255) NOT NULL,
    fetch_date DATE NOT NULL,
    registration_available_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_domain_fetch (domain_name, fetch_date),
    INDEX idx_registration_date (registration_available_date)
);

-- Tabela analizy domen
CREATE TABLE IF NOT EXISTS domain_analysis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    domain_id INT NOT NULL,
    category_id INT NOT NULL,
    description TEXT,
    is_interesting BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    UNIQUE KEY unique_domain_category (domain_id, category_id)
);

-- Tabela ulubionych domen
CREATE TABLE IF NOT EXISTS favorite_domains (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    domain_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_domain (user_id, domain_id)
);

-- Tabela logów pobierania
CREATE TABLE IF NOT EXISTS fetch_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fetch_date DATE NOT NULL,
    domains_count INT DEFAULT 0,
    status ENUM('success', 'error') DEFAULT 'success',
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabela konfiguracji systemu
CREATE TABLE IF NOT EXISTS system_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) UNIQUE NOT NULL,
    config_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela powiadomień
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    domain_id INT NOT NULL,
    type ENUM('reminder', 'summary') DEFAULT 'reminder',
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE
);

-- Wstaw domyślne kategorie
INSERT INTO categories (name, prompt) VALUES 
('Moda', 'Przeanalizuj przesłaną listę domen, czy na tej liście znajdują się domeny, które pasują pod stronę o tematyce: moda. Wynik przedstaw w tabeli podając domenę oraz jej krótki opis, dlaczego jest interesująca. Tabela powinna być w formacie HTML.'),
('Uroda', 'Przeanalizuj przesłaną listę domen, czy na tej liście znajdują się domeny, które pasują pod stronę o tematyce: uroda. Wynik przedstaw w tabeli podając domenę oraz jej krótki opis, dlaczego jest interesująca. Tabela powinna być w formacie HTML.'),
('Wnętrza', 'Przeanalizuj przesłaną listę domen, czy na tej liście znajdują się domeny, które pasują pod stronę o tematyce: wnętrza. Wynik przedstaw w tabeli podając domenę oraz jej krótki opis, dlaczego jest interesująca. Tabela powinna być w formacie HTML.'),
('Popularne historycznie', 'Przeanalizuj przesłaną listę domen, czy na tej liście znajdują się domeny, które historycznie były popularną stroną w internecie? Wynik przedstaw w tabeli podając domenę oraz jej krótki opis, dlaczego jest interesująca. Tabela powinna być w formacie HTML.');

-- Wstaw domyślną konfigurację
INSERT INTO system_config (config_key, config_value) VALUES 
('last_fetch_date', ''),
('email_notifications_enabled', '1'),
('gemini_api_enabled', '1'),
('fetch_time', '09:05');