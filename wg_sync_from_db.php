#!/usr/bin/env php
<?php
/**
 * Build a full WireGuard config from:
 *   - a static "base" file ([Interface] only — server keys, Address, ListenPort, PostUp/PostDown)
 *   - all rows in vpn_clients (client PublicKey + AllowedIPs from vpn_ip)
 *
 * Run on the Ubuntu server as root (to write under /etc/wireguard). CLI only — do not expose via the web.
 *
 *   sudo php /path/to/scripts/wg_sync_from_db.php /etc/wireguard/wg0.base.conf /etc/wireguard/wg0.conf
 *
 * DB: same env vars as db_config.php (CNU_DB_HOST, CNU_DB_USER, CNU_DB_PASS, CNU_DB_NAME).
 *
 * After writing, apply without dropping connections (if your wg-quick supports it):
 *   sudo wg syncconf wg0 <(wg-quick strip /etc/wireguard/wg0.conf)
 * Or bounce the interface:
 *   sudo wg-quick down wg0 && sudo wg-quick up wg0
 */

if (php_sapi_name() !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

require_once __DIR__ . '/../db_config.php';

$usage = <<<TXT
Usage: php wg_sync_from_db.php <base.conf> <out.conf>

  base.conf  Your [Interface] block only (server PrivateKey, Address, ListenPort, PostUp/PostDown).
  out.conf   Full config to write (e.g. /etc/wireguard/wg0.conf).

TXT;

if ($argc < 3) {
    fwrite(STDERR, $usage);
    exit(1);
}

$basePath = $argv[1];
$outPath = $argv[2];

if (!is_readable($basePath)) {
    fwrite(STDERR, "Cannot read base file: {$basePath}\n");
    exit(1);
}

$base = file_get_contents($basePath);
if ($base === false) {
    fwrite(STDERR, "Failed to read: {$basePath}\n");
    exit(1);
}
$base = rtrim($base) . "\n\n";

$conn = cnu_db_connect();
if ($conn->connect_error) {
    fwrite(STDERR, 'Database connection failed: ' . $conn->connect_error . "\n");
    exit(1);
}

$res = $conn->query('SELECT public_key, vpn_ip FROM vpn_clients ORDER BY id ASC');
if ($res === false) {
    fwrite(STDERR, 'Query failed: ' . $conn->error . "\n");
    $conn->close();
    exit(1);
}

$peerBlocks = '';
$peerCount = 0;
while ($row = $res->fetch_assoc()) {
    $pub = trim((string) $row['public_key']);
    $ip = trim((string) $row['vpn_ip']);
    if ($pub === '' || $ip === '') {
        continue;
    }
    if (strlen($pub) < 40 || strlen($pub) > 50) {
        fwrite(STDERR, "Skipping row with invalid key length (username check DB id).\n");
        continue;
    }
    if (!preg_match('/^(?:\d{1,3}\.){3}\d{1,3}$/', $ip)) {
        fwrite(STDERR, "Skipping invalid vpn_ip: {$ip}\n");
        continue;
    }

    $peerBlocks .= "[Peer]\n";
    $peerBlocks .= "PublicKey = {$pub}\n";
    $peerBlocks .= "AllowedIPs = {$ip}/32\n\n";
    $peerCount++;
}
$conn->close();

$full = rtrim($base . $peerBlocks) . "\n";

$dir = dirname($outPath);
if (!is_dir($dir)) {
    fwrite(STDERR, "Directory does not exist: {$dir}\n");
    exit(1);
}

$tmp = tempnam($dir, 'wgconf_');
if ($tmp === false) {
    fwrite(STDERR, "Could not create temp file in {$dir}\n");
    exit(1);
}

if (file_put_contents($tmp, $full) === false) {
    unlink($tmp);
    fwrite(STDERR, "Write failed: {$tmp}\n");
    exit(1);
}

chmod($tmp, 0600);
if (!@rename($tmp, $outPath)) {
    if (!@copy($tmp, $outPath)) {
        unlink($tmp);
        fwrite(STDERR, "Could not write {$outPath}\n");
        exit(1);
    }
    unlink($tmp);
    chmod($outPath, 0600);
}

echo "Wrote {$peerCount} peer(s) to {$outPath}\n";
echo "Apply: sudo wg syncconf wg0 <(wg-quick strip {$outPath})\n";
echo "   or: sudo wg-quick down wg0 && sudo wg-quick up wg0\n";
