<?php
declare(strict_types=1);
// Sugeridor de personas para autocompletar
require __DIR__ . '/../../src/bootstrap.php';

api_guard(function () {
    require_login_api();
    $q = trim((string)($_GET['q'] ?? ''));
    $st = db()->prepare("
        SELECT p.full_name, COALESCE(co.nombre,'') AS company, COALESCE(p.puesto,'') AS puesto
        FROM persons p LEFT JOIN companies co ON co.id = p.company_id
        WHERE p.full_name ILIKE :q
        ORDER BY p.full_name LIMIT 50");
    $st->execute([':q' => '%' . $q . '%']);
    json_ok(['data' => $st->fetchAll()]);
});
