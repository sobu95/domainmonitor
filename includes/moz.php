<?php
/**
 * Functions for interacting with the Moz Links API.
 */

function fetchMozMetrics(string $domain): ?array
{
    $config = include __DIR__ . '/../config/config.php';
    $keys = $config['moz_api_keys'] ?? [];
    if (!is_array($keys)) {
        $keys = array_filter(array_map('trim', explode("\n", (string)$keys)));
    }

    $payload = json_encode(['targets' => [$domain]]);
    foreach ($keys as $key) {
        $ch = curl_init('https://lsapi.seomoz.com/v2/url_metrics');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Basic ' . $key,
            ],
        ]);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 200 && $response) {
            $json = json_decode($response, true);
            if (isset($json['results'][0])) {
                return $json['results'][0];
            }
            return $json;
        }
    }

    return null;
}

function fetchMozLinkingDomains(string $domain): ?array
{
    $config = include __DIR__ . '/../config/config.php';
    $keys = $config['moz_api_keys'] ?? [];
    if (!is_array($keys)) {
        $keys = array_filter(array_map('trim', explode("\n", (string)$keys)));
    }

    $payload = json_encode([
        'target' => $domain,
        'scope'  => 'domains',
        'limit'  => 50,
        'sort'   => 'page_authority'
    ]);

    foreach ($keys as $key) {
        $ch = curl_init('https://lsapi.seomoz.com/v2/links');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Basic ' . $key,
            ],
        ]);
        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code === 200 && $response) {
            $json = json_decode($response, true);
            if (isset($json['results'])) {
                $domains = [];
                foreach ($json['results'] as $row) {
                    if (isset($row['source_root_domain'])) {
                        $domains[] = $row['source_root_domain'];
                    }
                }
                return $domains;
            }
        }
    }

    return null;
}

function updateMozMetrics(PDO $db, int $domainId, string $domainName): void
{
    $metrics = fetchMozMetrics($domainName);
    $links   = fetchMozLinkingDomains($domainName);

    if (!$metrics && !$links) {
        return;
    }

    $da = isset($metrics['domain_authority']) ? (int)$metrics['domain_authority'] : null;
    $pa = isset($metrics['page_authority']) ? (int)$metrics['page_authority'] : null;

    $linksCount = null;
    if (isset($metrics['root_domains'])) {
        $linksCount = (int)$metrics['root_domains'];
    } elseif (isset($metrics['root_domains_to_root_domain'])) {
        $linksCount = (int)$metrics['root_domains_to_root_domain'];
    }

    $linksText = $links ? implode("\n", $links) : null;

    $stmt = $db->prepare('UPDATE domains SET domain_authority = ?, page_authority = ?, linking_domains = ?, linking_domains_list = ? WHERE id = ?');
    $stmt->execute([
        $da,
        $pa,
        $linksCount,
        $linksText,
        $domainId
    ]);
}

?>
