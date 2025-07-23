<?php
/**
 * Fetch exact expiration date for a domain using RDAP.
 * Currently uses the dns.pl RDAP service which covers .pl domains.
 *
 * @param string $domain Domain name (e.g. example.pl)
 * @return string|null   Expiration date in Y-m-d format or null on failure
 */
function fetchRdapExpiration(string $domain): ?string
{
    $domain = trim($domain);
    if ($domain === '') {
        return null;
    }

    $url = 'https://rdap.dns.pl/domain/' . urlencode($domain);
    $context = stream_context_create([
        'http' => [
            'method'  => 'GET',
            'timeout' => 10,
            'header'  => "Accept: application/json\r\n"
        ]
    ]);

    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return null;
    }

    $data = json_decode($response, true);
    if (!isset($data['events']) || !is_array($data['events'])) {
        return null;
    }

    foreach ($data['events'] as $event) {
        if (isset($event['eventAction'], $event['eventDate']) && $event['eventAction'] === 'expiration') {
            return date('Y-m-d', strtotime($event['eventDate']));
        }
    }

    return null;
}
