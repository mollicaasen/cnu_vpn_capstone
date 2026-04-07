<?php
session_start();

if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$username = $_SESSION['user_id'];

// Keep these aligned with login.php (or your real MySQL user + database name).
$conn = new mysqli('localhost', 'root', 'root', 'cnu_vpn');
if ($conn->connect_error) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Database connection failed: ' . $conn->connect_error;
    exit;
}

$stmt = $conn->prepare('SELECT public_key, private_key, vpn_ip FROM vpn_clients WHERE username = ? LIMIT 1');
if ($stmt === false) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Database query prepare failed (check that table vpn_clients exists): ' . $conn->error;
    $conn->close();
    exit;
}
$stmt->bind_param('s', $username);
if (!$stmt->execute()) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Database query failed: ' . $stmt->error;
    $stmt->close();
    $conn->close();
    exit;
}

$existing = null;
$stmt->bind_result($colPub, $colPriv, $colIp);
if ($stmt->fetch()) {
    $existing = [
        'public_key' => $colPub,
        'private_key' => $colPriv,
        'vpn_ip' => $colIp,
    ];
}
$stmt->close();

$serverPublicKey = 'OHUKNyWpnv2earIf/Sp7g2S5NWq1ZHS2VYxtujiICzg=';
$endpoint = 'cnusecuresailing.duckdns.org:51820';

if ($existing) {
    $privateKey = $existing['private_key'];
    $publicKey = $existing['public_key'];
    $vpnIp = $existing['vpn_ip'];
} else {
    $privateKey = trim((string) shell_exec('wg genkey 2>/dev/null'));
    if ($privateKey === '') {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Could not generate WireGuard keys. Ensure wg is installed and PHP may run it.';
        $conn->close();
        exit;
    }

    $publicKey = trim((string) shell_exec('echo ' . escapeshellarg($privateKey) . ' | wg pubkey 2>/dev/null'));
    if ($publicKey === '') {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Could not derive WireGuard public key.';
        $conn->close();
        exit;
    }

    $usedOctets = [];
    $ipRes = $conn->query('SELECT vpn_ip FROM vpn_clients');
    if ($ipRes) {
        while ($row = $ipRes->fetch_assoc()) {
            if (preg_match('/^10\.0\.0\.(\d+)$/', (string) $row['vpn_ip'], $m)) {
                $usedOctets[(int) $m[1]] = true;
            }
        }
    }

    $vpnIp = null;
    for ($octet = 2; $octet <= 254; $octet++) {
        if (empty($usedOctets[$octet])) {
            $vpnIp = '10.0.0.' . $octet;
            break;
        }
    }

    if ($vpnIp === null) {
        http_response_code(503);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'No free VPN addresses available in the pool.';
        $conn->close();
        exit;
    }

    $ins = $conn->prepare('INSERT INTO vpn_clients (username, public_key, private_key, vpn_ip) VALUES (?, ?, ?, ?)');
    $ins->bind_param('ssss', $username, $publicKey, $privateKey, $vpnIp);
    if (!$ins->execute()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Could not save VPN client record: ' . $ins->error;
        $ins->close();
        $conn->close();
        exit;
    }
    $ins->close();

    $peerCmd = 'sudo wg set wg0 peer ' . escapeshellarg($publicKey) . ' allowed-ips ' . escapeshellarg($vpnIp . '/32');
    shell_exec($peerCmd);
}

$conn->close();

$config = <<<CONF
[Interface]
PrivateKey = {$privateKey}
Address = {$vpnIp}/24
DNS = 1.1.1.1

[Peer]
PublicKey = {$serverPublicKey}
Endpoint = {$endpoint}
AllowedIPs = 0.0.0.0/0
PersistentKeepalive = 25

CONF;

header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="vpn.conf"');

echo $config;
