<?php
declare(strict_types=1);
/*
 * api/actions.php — seguimiento de acciones correctivas/preventivas
 *   GET  (filtros: estado, vencidas=1, responsable) → listado con datos del incidente
 *   POST JSON {id, estado, fecha_cumplimiento, verificacion, responsable, fecha_compromiso} → actualiza
 */
require __DIR__ . '/../../src/bootstrap.php';

api_guard(function () {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $pdo = db();

    if ($method === 'GET') {
        require_login_api();
        $w = ["a.estado <> 'ANULADA'"]; $p = [];
        if (!empty($_GET['estado'])) { $w[] = 'a.estado = :e'; $p[':e'] = $_GET['estado']; }
        if (!empty($_GET['vencidas'])) $w[] = "a.estado IN ('PENDIENTE','EN_CURSO') AND a.fecha_compromiso < CURRENT_DATE";
        if (!empty($_GET['responsable'])) { $w[] = 'a.responsable ILIKE :r'; $p[':r'] = '%' . $_GET['responsable'] . '%'; }
        $st = $pdo->prepare("
            SELECT a.*, i.title AS incident_title, i.event_datetime, co.nombre AS company,
                   (a.estado IN ('PENDIENTE','EN_CURSO') AND a.fecha_compromiso < CURRENT_DATE) AS vencida,
                   (a.fecha_compromiso - CURRENT_DATE) AS dias_restantes
            FROM incident_actions a
            JOIN incidents i ON i.id = a.incident_id
            LEFT JOIN companies co ON co.id = i.company_id
            WHERE " . implode(' AND ', $w) . "
            ORDER BY (a.estado IN ('PENDIENTE','EN_CURSO')) DESC, a.fecha_compromiso NULLS LAST, a.id");
        $st->execute($p);
        json_ok(['data' => $st->fetchAll()]);
    }

    if ($method === 'POST') {
        require_login_api();
        $b = body_json();
        $id = (int)($b['id'] ?? 0);
        if (!$id) json_fail('id requerido');
        $estado = (string)($b['estado'] ?? '');
        if (!array_key_exists($estado, ESTADO_ACCION)) json_fail('Estado inválido');
        if ($estado === 'VERIFICADA' && trim((string)($b['verificacion'] ?? '')) === '') {
            json_fail('Para marcar como verificada, describí cómo se comprobó la eficacia');
        }
        $fcu = !empty($b['fecha_cumplimiento']) ? $b['fecha_cumplimiento'] : (in_array($estado, ['CUMPLIDA', 'VERIFICADA'], true) ? date('Y-m-d') : null);
        $st = $pdo->prepare("UPDATE incident_actions SET estado=:e, fecha_cumplimiento=:f,
                               verificacion=COALESCE(:v, verificacion),
                               responsable=COALESCE(:r, responsable),
                               fecha_compromiso=COALESCE(CAST(:fc AS DATE), fecha_compromiso)
                             WHERE id=:id");
        $st->execute([':e' => $estado, ':f' => $fcu, ':v' => ($b['verificacion'] ?? null) ?: null,
                      ':r' => ($b['responsable'] ?? null) ?: null, ':fc' => ($b['fecha_compromiso'] ?? null) ?: null, ':id' => $id]);
        json_ok(['id' => $id]);
    }

    json_fail('Método no permitido', 405);
});
