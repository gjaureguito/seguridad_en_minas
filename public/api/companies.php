<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';

api_guard(function () {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $pdo = db();

    if ($method === 'GET') {
        require_login_api();
        $all = isset($_GET['all']);
        $sql = 'SELECT id, nombre, tipo, cuit, art, activo FROM companies' . ($all ? '' : ' WHERE activo') . ' ORDER BY nombre';
        json_ok(['data' => $pdo->query($sql)->fetchAll()]);
    }

    if ($method === 'POST') {
        require_login_api(['ADMIN', 'SUPERVISOR']);
        $b = body_json();
        $nombre = trim((string)($b['nombre'] ?? ''));
        if ($nombre === '') json_fail('Nombre requerido');
        $tipo = (string)($b['tipo'] ?? 'CONTRATISTA');
        if (!in_array($tipo, ['TITULAR','CONTRATISTA','SUBCONTRATISTA','PROVEEDOR','OTRO'], true)) json_fail('Tipo de empresa inválido');
        $vals = [':n' => $nombre, ':t' => $tipo, ':c' => trim((string)($b['cuit'] ?? '')) ?: null,
                 ':a' => trim((string)($b['art'] ?? '')) ?: null, ':ac' => !empty($b['activo']) ? 'true' : 'false'];
        if (!empty($b['id'])) {
            $vals[':id'] = (int)$b['id'];
            $pdo->prepare('UPDATE companies SET nombre=:n, tipo=:t, cuit=:c, art=:a, activo=:ac WHERE id=:id')->execute($vals);
            json_ok(['id' => (int)$b['id']]);
        }
        $st = $pdo->prepare('INSERT INTO companies (nombre, tipo, cuit, art, activo) VALUES (:n,:t,:c,:a,:ac) RETURNING id');
        $st->execute($vals);
        json_ok(['id' => (int)$st->fetchColumn()]);
    }

    json_fail('Método no permitido', 405);
});
