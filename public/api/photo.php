<?php
declare(strict_types=1);
// Sirve una foto guardada en PostgreSQL (BYTEA)
require __DIR__ . '/../../src/bootstrap.php';

if (!current_user()) { http_response_code(401); exit; }
$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare('SELECT mime, data FROM incident_photos WHERE id = :id');
$st->execute([':id' => $id]);
$st->bindColumn(1, $mime);
$st->bindColumn(2, $data, PDO::PARAM_LOB);
if (!$st->fetch(PDO::FETCH_BOUND)) { http_response_code(404); exit; }
$bytes = is_resource($data) ? stream_get_contents($data) : $data;
header('Content-Type: ' . $mime);
header('Cache-Control: private, max-age=86400');
header('Content-Length: ' . strlen($bytes));
echo $bytes;
