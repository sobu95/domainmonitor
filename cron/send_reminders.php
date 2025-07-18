<?php
// Cron job do wysyłania przypomnień
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$database = new Database();
$db = $database->getConnection();

$logFile = dirname(__DIR__) . '/logs/reminders_' . date('Y-m-d') . '.log';

function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

try {
    writeLog("Rozpoczęcie wysyłania przypomnień");
    
    // Znajdź domeny, które będą dostępne do rejestracji jutro
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    
    $stmt = $db->prepare("
        SELECT 
            u.email, u.username,
            d.domain_name, d.registration_available_date,
            c.name as category_name,
            da.description
        FROM favorite_domains fd
        JOIN users u ON fd.user_id = u.id
        JOIN domains d ON fd.domain_id = d.id
        LEFT JOIN domain_analysis da ON d.id = da.domain_id AND da.is_interesting = 1
        LEFT JOIN categories c ON da.category_id = c.id
        WHERE d.registration_available_date = ?
        AND NOT EXISTS (
            SELECT 1 FROM notifications n 
            WHERE n.user_id = u.id 
            AND n.domain_id = d.id 
            AND n.type = 'reminder' 
            AND DATE(n.created_at) = CURDATE()
        )
    ");
    $stmt->execute([$tomorrow]);
    $reminders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    writeLog("Znaleziono " . count($reminders) . " przypomnień do wysłania");
    
    $sentCount = 0;
    $groupedReminders = [];
    
    // Grupuj przypomnienia według użytkownika
    foreach ($reminders as $reminder) {
        $email = $reminder['email'];
        if (!isset($groupedReminders[$email])) {
            $groupedReminders[$email] = [
                'username' => $reminder['username'],
                'domains' => []
            ];
        }
        $groupedReminders[$email]['domains'][] = $reminder;
    }
    
    foreach ($groupedReminders as $email => $data) {
        $subject = "Przypomnienie - domeny dostępne jutro do rejestracji";
        
        $body = "<h2>Cześć " . htmlspecialchars($data['username']) . "!</h2>";
        $body .= "<p>Przypominamy, że jutro (" . date('d.m.Y', strtotime($tomorrow)) . ") będą dostępne do rejestracji następujące domeny z Twoich ulubionych:</p>";
        
        $body .= "<table border='1' cellpadding='10' cellspacing='0' style='border-collapse: collapse; width: 100%;'>";
        $body .= "<tr style='background-color: #f8f9fa;'>";
        $body .= "<th>Domena</th><th>Kategoria</th><th>Opis</th>";
        $body .= "</tr>";
        
        foreach ($data['domains'] as $domain) {
            $body .= "<tr>";
            $body .= "<td><strong>" . htmlspecialchars($domain['domain_name']) . "</strong></td>";
            $body .= "<td>" . htmlspecialchars($domain['category_name'] ?? 'Brak kategorii') . "</td>";
            $body .= "<td>" . htmlspecialchars($domain['description'] ?? 'Brak opisu') . "</td>";
            $body .= "</tr>";
        }
        
        $body .= "</table>";
        $body .= "<p><strong>Pamiętaj:</strong> Domeny będą dostępne do rejestracji od jutrzejszego dnia. Zalecamy szybkie działanie!</p>";
        $body .= "<hr>";
        $body .= "<p><small>To jest automatyczne przypomnienie z systemu Domain Monitor.</small></p>";
        
        if (sendEmail($email, $subject, $body, true)) {
            $sentCount++;
            
            // Zapisz informację o wysłanym powiadomieniu
            foreach ($data['domains'] as $domain) {
                $stmt = $db->prepare("INSERT INTO notifications (user_id, domain_id, type, sent_at) SELECT u.id, d.id, 'reminder', NOW() FROM users u, domains d WHERE u.email = ? AND d.domain_name = ?");
                $stmt->execute([$email, $domain['domain_name']]);
            }
            
            writeLog("Wysłano przypomnienie do: $email (" . count($data['domains']) . " domen)");
        } else {
            writeLog("Błąd wysyłania przypomnienia do: $email");
        }
        
        // Pauza między emailami
        sleep(1);
    }
    
    writeLog("Wysłano $sentCount przypomnień");
    
} catch (Exception $e) {
    writeLog("BŁĄD: " . $e->getMessage());
}
?>