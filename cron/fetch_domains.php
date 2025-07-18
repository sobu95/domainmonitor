<?php
// Cron job do pobierania domen
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$database = new Database();
$db = $database->getConnection();

$logFile = dirname(__DIR__) . '/logs/fetch_domains_' . date('Y-m-d') . '.log';

function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

try {
    writeLog("Rozpoczęcie pobierania domen");
    
    // Pobierz plik z domenami
    $url = 'https://www.dns.pl/deleted_domains.txt';
    $content = file_get_contents($url);
    
    if ($content === false) {
        throw new Exception("Nie można pobrać pliku z $url");
    }
    
    writeLog("Pobrano plik z domenami, rozmiar: " . strlen($content) . " bajtów");
    
    // Przetwórz domeny
    $domains = array_filter(array_map('trim', explode("\n", $content)));
    $domainsCount = count($domains);
    
    writeLog("Znaleziono $domainsCount domen");
    
    $today = date('Y-m-d');
    $registrationDate = date('Y-m-d', strtotime('+30 days'));
    
    $insertedCount = 0;
    $duplicateCount = 0;
    
    foreach ($domains as $domain) {
        if (empty($domain) || strpos($domain, '.') === false) {
            continue;
        }
        
        // Sprawdź czy domena już istnieje
        $stmt = $db->prepare("SELECT id FROM domains WHERE domain_name = ? AND fetch_date = ?");
        $stmt->execute([$domain, $today]);
        
        if ($stmt->fetch()) {
            $duplicateCount++;
            continue;
        }
        
        // Wstaw domenę
        $stmt = $db->prepare("INSERT INTO domains (domain_name, fetch_date, registration_available_date) VALUES (?, ?, ?)");
        if ($stmt->execute([$domain, $today, $registrationDate])) {
            $insertedCount++;
        }
    }
    
    writeLog("Wstawiono $insertedCount nowych domen, pominięto $duplicateCount duplikatów");
    
    // Zapisz log pobierania
    $stmt = $db->prepare("INSERT INTO fetch_logs (fetch_date, domains_count, status) VALUES (?, ?, 'success')");
    $stmt->execute([$today, $insertedCount]);
    
    // Uruchom analizę Gemini dla nowych domen
    if ($insertedCount > 0) {
        writeLog("Rozpoczęcie analizy Gemini");
        
        // Pobierz kategorie
        $stmt = $db->prepare("SELECT * FROM categories WHERE active = 1");
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Pobierz dzisiejsze domeny
        $stmt = $db->prepare("SELECT id, domain_name FROM domains WHERE fetch_date = ?");
        $stmt->execute([$today]);
        $todayDomains = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $domainNames = array_column($todayDomains, 'domain_name');
        
        foreach ($categories as $category) {
            writeLog("Analiza kategorii: " . $category['name']);
            
            $response = callGeminiAPI($category['prompt'], $domainNames);
            
            if ($response) {
                $analyzedDomains = parseDomainAnalysis($response);
                
                foreach ($analyzedDomains as $analyzedDomain) {
                    // Znajdź ID domeny
                    $domainId = null;
                    foreach ($todayDomains as $domain) {
                        if ($domain['domain_name'] === $analyzedDomain['domain']) {
                            $domainId = $domain['id'];
                            break;
                        }
                    }
                    
                    if ($domainId) {
                        $stmt = $db->prepare("INSERT INTO domain_analysis (domain_id, category_id, description, is_interesting) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE description = VALUES(description)");
                        $stmt->execute([$domainId, $category['id'], $analyzedDomain['description']]);
                    }
                }
                
                writeLog("Przeanalizowano " . count($analyzedDomains) . " domen dla kategorii " . $category['name']);
            } else {
                writeLog("Błąd analizy Gemini dla kategorii " . $category['name']);
            }
            
            // Pauza między zapytaniami
            sleep(2);
        }
        
        // Wyślij podsumowanie email
        sendDailySummary($db, $today);
        writeLog("Wysłano podsumowanie email");
    }
    
    writeLog("Zakończenie pobierania domen");
    
} catch (Exception $e) {
    writeLog("BŁĄD: " . $e->getMessage());
    
    // Zapisz błąd w bazie
    $stmt = $db->prepare("INSERT INTO fetch_logs (fetch_date, domains_count, status, error_message) VALUES (?, 0, 'error', ?)");
    $stmt->execute([date('Y-m-d'), $e->getMessage()]);
}

function sendDailySummary($db, $date) {
    // Pobierz wszystkich użytkowników
    $stmt = $db->prepare("SELECT email FROM users WHERE role IN ('admin', 'user')");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Pobierz statystyki dnia
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT d.id) as total_domains,
            COUNT(DISTINCT da.domain_id) as interesting_domains,
            GROUP_CONCAT(DISTINCT c.name) as categories
        FROM domains d
        LEFT JOIN domain_analysis da ON d.id = da.domain_id AND da.is_interesting = 1
        LEFT JOIN categories c ON da.category_id = c.id
        WHERE d.fetch_date = ?
    ");
    $stmt->execute([$date]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Pobierz interesujące domeny
    $stmt = $db->prepare("
        SELECT d.domain_name, c.name as category, da.description
        FROM domains d
        JOIN domain_analysis da ON d.id = da.domain_id
        JOIN categories c ON da.category_id = c.id
        WHERE d.fetch_date = ? AND da.is_interesting = 1
        ORDER BY c.name, d.domain_name
    ");
    $stmt->execute([$date]);
    $interestingDomains = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $subject = "Podsumowanie domen - " . date('d.m.Y', strtotime($date));
    
    $body = "<h2>Podsumowanie domen z dnia " . date('d.m.Y', strtotime($date)) . "</h2>";
    $body .= "<h3>Statystyki:</h3>";
    $body .= "<ul>";
    $body .= "<li>Łączna liczba domen: " . $stats['total_domains'] . "</li>";
    $body .= "<li>Interesujące domeny: " . $stats['interesting_domains'] . "</li>";
    $body .= "</ul>";
    
    if (!empty($interestingDomains)) {
        $body .= "<h3>Interesujące domeny:</h3>";
        $body .= "<table border='1' cellpadding='5' cellspacing='0'>";
        $body .= "<tr><th>Domena</th><th>Kategoria</th><th>Opis</th></tr>";
        
        foreach ($interestingDomains as $domain) {
            $body .= "<tr>";
            $body .= "<td>" . htmlspecialchars($domain['domain_name']) . "</td>";
            $body .= "<td>" . htmlspecialchars($domain['category']) . "</td>";
            $body .= "<td>" . htmlspecialchars($domain['description']) . "</td>";
            $body .= "</tr>";
        }
        
        $body .= "</table>";
    }
    
    foreach ($users as $user) {
        sendEmail($user['email'], $subject, $body, true);
    }
}
?>