<?php
declare(strict_types=1);
// Gestión de usuarios (solo ADMIN)
require __DIR__ . '/../../src/bootstrap.php';

api_guard(function () {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $me = require_login_api(['ADMIN']);
    $pdo = db();

    if ($method === 'GET') {
        json_ok(['data' => $pdo->query('SELECT id, email, full_name, role, active, created_at FROM users ORDER BY full_name')->fetchAll()]);
    }

    if ($method === 'POST') {
        $b = body_json();
        $email = strtolower(trim((string)($b['email'] ?? '')));
        $name  = trim((string)($b['full_name'] ?? ''));
        $role  = in_array($b['role'] ?? '', ['ADMIN', 'SUPERVISOR', 'LECTOR'], true) ? $b['role'] : 'SUPERVISOR';
        $active = !empty($b['active']);
        $pass  = (string)($b['password'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) && $email !== 'admin@local') json_fail('Email inválido');
        if ($name === '') json_fail('Nombre requerido');
        if ($pass !== '' && strlen($pass) < 8) json_fail('La contraseña debe tener al menos 8 caracteres');

        if (!empty($b['id'])) {
            $id = (int)$b['id'];
            if ($id === (int)$me['id'] && (!$active || $role !== 'ADMIN')) json_fail('No podés quitarte el rol de administrador ni desactivarte');
            $pdo->prepare('UPDATE users SET email=:e, full_name=:n, role=:r, active=:a WHERE id=:id')
                ->execute([':e' => $email, ':n' => $name, ':r' => $role, ':a' => $active ? 'true' : 'false', ':id' => $id]);
            if ($pass !== '') $pdo->prepare('UPDATE users SET password_hash=:h WHERE id=:id')->execute([':h' => password_hash($pass, PASSWORD_DEFAULT), ':id' => $id]);
            json_ok(['id' => $id]);
        }
        if ($pass === '') json_fail('Contraseña requerida para un usuario nuevo');
        $st = $pdo->prepare('INSERT INTO users (email, full_name, password_hash, role, active) VALUES (:e,:n,:h,:r,:a) RETURNING id');
        $st->execute([':e' => $email, ':n' => $name, ':h' => password_hash($pass, PASSWORD_DEFAULT), ':r' => $role, ':a' => $active ? 'true' : 'false']);
        json_ok(['id' => (int)$st->fetchColumn()]);
    }

    json_fail('Método no permitido', 405);
});
