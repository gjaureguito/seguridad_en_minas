<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';

api_guard(function () {
    $u = require_login_api();
    $pdo = db();
    $catalogs = [];
    foreach ($pdo->query('SELECT kind, code, label FROM catalogs ORDER BY kind, sort, code') as $r) {
        $catalogs[$r['kind']][] = ['code' => $r['code'], 'label' => $r['label']];
    }
    json_ok([
        'user'        => ['id' => $u['id'], 'name' => $u['full_name'], 'role' => $u['role']],
        'csrf'        => csrf_token(),
        'categories'  => $pdo->query('SELECT id, code, label FROM categories ORDER BY label')->fetchAll(),
        'severities'  => $pdo->query('SELECT id, code, label FROM severities ORDER BY orden, id')->fetchAll(),
        'catalogs'    => $catalogs,
        'enums'       => [
            'tipo_contingencia' => TIPO_CONTINGENCIA,
            'consecuencia'      => CONSECUENCIA,
            'estado'            => ESTADO_INCIDENTE,
            'metodo'            => METODO_INVESTIGACION,
            'tipo_accion'       => TIPO_ACCION,
            'jerarquia'         => JERARQUIA,
            'estado_accion'     => ESTADO_ACCION,
        ],
    ]);
});
