<?php

function getDashboardStats($db) {
    $stats = [];
    
    // Domeny dzisiaj
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM domains WHERE DATE(fetch_date) = CURRENT_DATE");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['today_domains'] = $result ? $result['count'] : 0;
    
    // Interesujące domeny
    $stmt = $db->prepare("SELECT COUNT(DISTINCT domain_id) as count FROM domain_analysis WHERE is_interesting = 1");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['interesting_domains'] = $result ? $result['count'] : 0;
    
    // Ulubione domeny
    if (isset($_SESSION['user_id'])) {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM favorite_domains WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['favorite_domains'] = $result ? $result['count'] : 0;
    } else {
        $stats['favorite_domains'] = 0;
    }
    
    return $stats;
}

function getRecentDomains($db, $limit = 10) {
    $limit = (int) $limit;
    $stmt = $db->prepare(
        "SELECT
            d.id,
            d.domain_name,
            d.fetch_date,
            d.registration_available_date,
            d.created_at,
            GROUP_CONCAT(c.name) AS categories
         FROM domains d
         LEFT JOIN domain_analysis da ON d.id = da.domain_id AND da.is_interesting = 1
         LEFT JOIN categories c ON da.category_id = c.id
         GROUP BY
            d.id,
            d.domain_name,
            d.fetch_date,
            d.registration_available_date,
            d.created_at
         ORDER BY d.created_at DESC
         LIMIT $limit"
    );
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUpcomingRegistrations($db, $userId, $days = 7) {
    $stmt = $db->prepare("
        SELECT d.*, c.name as category_name, da.description
        FROM domains d
        JOIN favorite_domains fd ON d.id = fd.domain_id
        JOIN domain_analysis da ON d.id = da.domain_id
        JOIN categories c ON da.category_id = c.id
        WHERE fd.user_id = ? 
        AND d.registration_available_date BETWEEN CURRENT_DATE AND CURRENT_DATE + INTERVAL ? DAY
        ORDER BY d.registration_available_date ASC
    ");
    $stmt->execute([$userId, $days]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function sendEmail($to, $subject, $body, $isHtml = true) {
    $config = include __DIR__ . '/../config/config.php';
    
    // Sprawdź czy PHPMailer jest zainstalowany
    if (!file_exists(__DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php')) {
        error_log("PHPMailer not installed. Run: composer install");
        return false;
    }

    require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
    require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';
    require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host = $config['email_smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['email_username'];
        $mail->Password = $config['email_password'];
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $config['email_smtp_port'];
        $mail->CharSet = 'UTF-8';
        
        $mail->setFrom($config['email_username'], $config['email_from_name']);
        $mail->addAddress($to);
        
        $mail->isHTML($isHtml);
        $mail->Subject = $subject;
        $mail->Body = $body;
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email error: " . $mail->ErrorInfo);
        return false;
    }
}

function callGeminiAPI($prompt, $domains) {
    $config = include __DIR__ . '/../config/config.php';
    
    $data = [
        'contents' => [
            [
                'role' => 'user',
                'parts' => [
                    ['text' => $prompt . "\n\nLista domen:\n" . implode("\n", $domains)]
                ]
            ]
        ],
        'tools' => [
            ['urlContext' => new stdClass()],
            ['googleSearch' => new stdClass()]
        ],
        'generationConfig' => [
            'thinkingConfig' => ['thinkingBudget' => -1],
            'responseMimeType' => 'text/plain'
        ]
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://generativelanguage.googleapis.com/v1beta/models/{$config['gemini_model']}:generateContent?key={$config['gemini_api_key']}");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            return $result['candidates'][0]['content']['parts'][0]['text'];
        }
    }
    
    return false;
}

function parseDomainAnalysis($htmlResponse) {
    $domains = [];
    
    // Prosta analiza HTML - szukamy tabel
    if (preg_match_all('/<tr[^>]*>.*?<\/tr>/is', $htmlResponse, $matches)) {
        foreach ($matches[0] as $row) {
            if (preg_match_all('/<td[^>]*>(.*?)<\/td>/is', $row, $cells)) {
                if (count($cells[1]) >= 2) {
                    $domain = strip_tags($cells[1][0]);
                    $description = strip_tags($cells[1][1]);
                    
                    if (!empty($domain) && !empty($description) && strpos($domain, '.') !== false) {
                        $domains[] = [
                            'domain' => trim($domain),
                            'description' => trim($description)
                        ];
                    }
                }
            }
        }
    }
    
    return $domains;
}

function getSemstormAccessToken() {
    static $cache = null;
    if ($cache && $cache['expire'] > time()) {
        return $cache['token'];
    }

    $config = include __DIR__ . '/../config/config.php';
    if (empty($config['semstorm_client_id']) || empty($config['semstorm_client_secret'])) {
        return false;
    }

    $payload = ['grant_type' => 'client_credentials'];
    $auth = base64_encode($config['semstorm_client_id'] . ':' . $config['semstorm_client_secret']);

    $ch = curl_init('https://api.semstorm.com/authorization/token');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . $auth,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    if (!empty($data['access_token'])) {
        $cache = [
            'token' => $data['access_token'],
            'expire' => time() + ($data['expires_in'] ?? 3600)
        ];
        return $cache['token'];
    }

    return false;
}

function getSemstormDomainVisibility($domain) {
    $token = getSemstormAccessToken();
    if (!$token) {
        return null;
    }

    $payload = [
        'domains' => [$domain],
        'dates' => ['20120101', date('Ymd')],
        'version' => 1
    ];

    $ch = curl_init('https://api.semstorm.com/semstorm/v4/explorer/domain-stats');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    if (empty($data['results'][$domain])) {
        return null;
    }

    $rows = $data['results'][$domain];
    ksort($rows);

    $maxTop3 = $maxTop10 = $maxTop20 = 0;
    $maxDateTop3 = $maxDateTop10 = $maxDateTop20 = null;
    $currentRow = null;
    $currentDate = null;

    foreach ($rows as $date => $row) {
        $kdata = $row['keywords_data'] ?? [];
        if (isset($kdata['top3']) && $kdata['top3'] > $maxTop3) {
            $maxTop3 = $kdata['top3'];
            $maxDateTop3 = $date;
        }
        if (isset($kdata['top10']) && $kdata['top10'] > $maxTop10) {
            $maxTop10 = $kdata['top10'];
            $maxDateTop10 = $date;
        }
        if (isset($kdata['top20']) && $kdata['top20'] > $maxTop20) {
            $maxTop20 = $kdata['top20'];
            $maxDateTop20 = $date;
        }

        if (!$currentDate || $date > $currentDate) {
            $currentDate = $date;
            $currentRow = $row;
        }
    }

    $current = null;
    if ($currentRow) {
        $current = [
            'date' => $currentDate,
            'top3' => $currentRow['keywords_data']['top3'] ?? 0,
            'top10' => $currentRow['keywords_data']['top10'] ?? 0,
            'top20' => $currentRow['keywords_data']['top20'] ?? 0,
            'traffic' => $currentRow['traffic'] ?? 0,
        ];
    }

    return [
        'max' => [
            'top3' => $maxTop3,
            'date_top3' => $maxDateTop3,
            'top10' => $maxTop10,
            'date_top10' => $maxDateTop10,
            'top20' => $maxTop20,
            'date_top20' => $maxDateTop20,
        ],
        'current' => $current
    ];
}

function logActivity($db, $action, $details = '') {
    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$_SESSION['user_id'] ?? 0, $action, $details]);
}
?>