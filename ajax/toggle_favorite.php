<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once '../config/database.php';
require_once '../includes/rdap.php';

$database = new Database();
$db = $database->getConnection();

$input = json_decode(file_get_contents('php://input'), true);
$domainId = $input['domain_id'] ?? null;

if (!$domainId) {
    echo json_encode(['success' => false, 'error' => 'Missing domain ID']);
    exit;
}

try {
    // Sprawdź czy domena jest już w ulubionych
    $stmt = $db->prepare("SELECT id FROM favorite_domains WHERE user_id = ? AND domain_id = ?");
    $stmt->execute([$_SESSION['user_id'], $domainId]);
    $favorite = $stmt->fetch();
    
    if ($favorite) {
        // Usuń z ulubionych
        $stmt = $db->prepare("DELETE FROM favorite_domains WHERE user_id = ? AND domain_id = ?");
        $stmt->execute([$_SESSION['user_id'], $domainId]);
        $isFavorite = false;
    } else {
        // Dodaj do ulubionych
        $stmt = $db->prepare("INSERT INTO favorite_domains (user_id, domain_id) VALUES (?, ?)");
        $stmt->execute([$_SESSION['user_id'], $domainId]);
        $isFavorite = true;

        // Pobierz nazwę domeny do aktualizacji daty wygaśnięcia
        $stmt = $db->prepare("SELECT domain_name FROM domains WHERE id = ?");
        $stmt->execute([$domainId]);
        $domain = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($domain) {
            $rdapDate = fetchRdapExpiration($domain['domain_name']);
            if ($rdapDate) {
                $update = $db->prepare("UPDATE domains SET registration_available_date = ? WHERE id = ?");
                $update->execute([$rdapDate, $domainId]);
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'is_favorite' => $isFavorite
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>