<?php
declare(strict_types=1);
/*
 * api/hours.php — horas-hombre trabajadas (HHT) y dotación por empresa y mes.
 *   GET ?anio=2026            → grilla del año
 *   POST JSON {rows:[{company_id, periodo:'YYYY-MM', hht, dotacion}]} → upsert
 */
require __DIR__ . '/../../src/bootstrap.php';

api_guard(function () {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $pdo = db();

    if ($method === 'GET') {
        require_login_api();
        $anio = (int)($_GET['anio'] ?? date('Y'));
        $st = $pdo->prepare("SELECT company_id, to_char(periodo,'YYYY-MM') AS periodo, hht::float AS hht, dotacion::float AS dotacion
                             FROM work_hours WHERE extract(year FROM periodo) = :a ORDER BY periodo");
        $st->execute([':a' => $anio]);
        json_ok(['anio' => $anio, 'data' => $st->fetchAll()]);
    }

    if ($method === 'POST') {
        require_login_api(['ADMIN', 'SUPERVISOR']);
        $rows = body_json()['rows'] ?? [];
        $up = $pdo->prepare("INSERT INTO work_hours (company_id, periodo, hht, dotacion) VALUES (:c, :p, :h, :d)
                             ON CONFLICT (company_id, periodo) DO UPDATE SET hht = EXCLUDED.hht, dotacion = EXCLUDED.dotacion");
        $del = $pdo->prepare('DELETE FROM work_hours WHERE company_id = :c AND periodo = :p');
        $pdo->beginTransaction();
        $n = 0;
        foreach ($rows as $r) {
            $c = (int)($r['company_id'] ?? 0);
            $per = (string)($r['periodo'] ?? '');
            if (!$c || !preg_match('/^\d{4}-\d{2}$/', $per)) continue;
            $hht = $r['hht'] ?? ''; $dot = $r['dotacion'] ?? '';
            if ($hht === '' || $hht === null) { $del->execute([':c' => $c, ':p' => $per . '-01']); continue; }
            $hht = (float)$hht; $dot = (float)$dot;
            if ($hht < 0 || $dot < 0) json_fail("Valores negativos en {$per}");
            // Control de razonabilidad: > 744 h/persona/mes es imposible (31 días × 24 h)
            if ($dot > 0 && $hht / $dot > 744) json_fail("HHT/dotación incoherente en {$per}: más de 744 h por persona");
            $up->execute([':c' => $c, ':p' => $per . '-01', ':h' => $hht, ':d' => $dot]);
            $n++;
        }
        $pdo->commit();
        json_ok(['guardados' => $n]);
    }

    json_fail('Método no permitido', 405);
});
