<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once '../config/database.php';
require_once '../includes/moz.php';

$database = new Database();
$db = $database->getConnection();

$input = json_decode(file_get_contents('php://input'), true);
$domainId = $input['domain_id'] ?? null;

if (!$domainId) {
    echo json_encode(['success' => false, 'error' => 'Missing domain ID']);
    exit;
}

try {
    $stmt = $db->prepare('SELECT id, user_id, domain_name FROM domains WHERE id = ?');
    $stmt->execute([$domainId]);
    $domain = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$domain) {
        echo json_encode(['success' => false, 'error' => 'Domain not found']);
        exit;
    }

    // Check if user has access - domain must be favorite or user is admin.
    $isAdmin = $_SESSION['role'] ?? '' === 'admin';
    $authorized = $isAdmin;
    if (!$authorized) {
        $stmt = $db->prepare('SELECT 1 FROM favorite_domains WHERE user_id = ? AND domain_id = ?');
        $stmt->execute([$_SESSION['user_id'], $domainId]);
        $authorized = (bool) $stmt->fetchColumn();
    }

    if (!$authorized) {
        echo json_encode(['success' => false, 'error' => 'Forbidden']);
        exit;
    }

    updateMozMetrics($db, $domainId, $domain['domain_name']);

    $stmt = $db->prepare('SELECT domain_authority, page_authority, linking_domains, linking_domains_list FROM domains WHERE id = ?');
    $stmt->execute([$domainId]);
    $metrics = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'metrics' => $metrics]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
