<?php
// Viewer-side access control for non-public page sets. admin.php and api.php
// are protected separately at the web server level (see .htaccess).

function check_access(bool $isPublic): void {
    if ($isPublic) return;

    $configFile = __DIR__ . '/config.php';
    $config = file_exists($configFile) ? require $configFile : [];
    $ipRanges  = $config['trusted_ip_ranges'] ?? [];
    $shibUsers = $config['shibboleth_users'] ?? [];

    $remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';
    foreach ($ipRanges as $range) {
        if (ip_in_range($remoteIp, $range)) return;
    }

    $shibUser = $_SERVER['REMOTE_USER'] ?? $_SERVER['eppn'] ?? '';
    if ($shibUser !== '' && in_array($shibUser, $shibUsers, true)) return;

    http_response_code(403);
    header('Content-Type: text/plain');
    echo "Access denied.";
    exit;
}

function ip_in_range(string $ip, string $cidr): bool {
    if (strpos($cidr, '/') === false) return $ip === $cidr;
    [$subnet, $bits] = explode('/', $cidr, 2);
    $bits = (int)$bits;
    $ipLong     = ip2long($ip);
    $subnetLong = ip2long($subnet);
    if ($ipLong === false || $subnetLong === false) return false;
    $mask = $bits === 0 ? 0 : (~0 << (32 - $bits));
    return ($ipLong & $mask) === ($subnetLong & $mask);
}
