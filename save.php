<?php
/*
 * save.php — co-located progress store for the HTB tracker.
 * Place this in the SAME folder as index.html.
 * Requires: a PHP-enabled host AND write permission to this folder.
 *
 * GET  save.php?action=load   -> returns progress.json (or {} if none yet)
 * POST save.php  body: {"token":"...","state":{...}}  -> writes progress.json
 */

header("Content-Type: application/json");
header("X-Content-Type-Options: nosniff");

$FILE  = __DIR__ . "/progress.json";
$TOKEN = "CHANGE_ME";     // <-- set this, and make the TOKEN in index.html match exactly
$MAX   = 262144;          // 256 KB hard cap on writes

$method = $_SERVER["REQUEST_METHOD"] ?? "GET";

if ($method === "GET") {
    if (!file_exists($FILE)) { echo "{}"; exit; }
    readfile($FILE);
    exit;
}

if ($method === "POST") {
    $raw = file_get_contents("php://input");
    if (strlen($raw) > $MAX) { http_response_code(413); echo '{"error":"too large"}'; exit; }

    $body = json_decode($raw, true);
    if (!is_array($body) || !array_key_exists("state", $body) || !is_array($body["state"])) {
        http_response_code(400); echo '{"error":"bad payload"}'; exit;
    }
    // Token check is bot-deterrence only — it is visible in the client JS.
    // For real privacy, put this folder behind HTTP Basic Auth (.htaccess). See README.
    if (!isset($body["token"]) || !hash_equals($TOKEN, (string)$body["token"])) {
        http_response_code(403); echo '{"error":"forbidden"}'; exit;
    }

    $json = json_encode($body["state"], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $tmp  = $FILE . ".tmp";
    if (file_put_contents($tmp, $json, LOCK_EX) === false || !rename($tmp, $FILE)) {
        http_response_code(500); echo '{"error":"write failed — check folder permissions"}'; exit;
    }
    echo '{"ok":true}';
    exit;
}

http_response_code(405);
echo '{"error":"method not allowed"}';
