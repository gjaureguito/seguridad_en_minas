<?php
declare(strict_types=1);
/*
 * api/incidents.php
 *   GET              → listado (filtros: desde, hasta, company_id, tipo, estado)
 *   GET ?id=N        → detalle con empresas, personas, fotos, investigación y acciones
 *   POST (multipart) → alta / edición (campo id para editar)
 *   DELETE ?id=N     → baja (solo ADMIN)
 */
require __DIR__ . '/../../src/bootstrap.php';

const MAX_PHOTO_BYTES = 10 * 1024 * 1024;
const PHOTO_MAX_SIDE  = 1600;

function enum_or(string $v, array $allowed, string $default): string {
    return array_key_exists($v, $allowed) ? $v : $default;
}
function nn(?string $v): ?string { $v = trim((string)$v); return $v === '' ? null : $v; }
function int_or_null($v, int $min, int $max): ?int {
    if ($v === null || $v === '') return null;
    $i = (int)$v; return ($i >= $min && $i <= $max) ? $i : null;
}
function dt_or_null(?string $v): ?string {
    $v = trim((string)$v); if ($v === '') return null;
    $t = strtotime($v); return $t ? date('Y-m-d H:i:s', $t) : null;
}
function date_or_null(?string $v): ?string {
    $v = trim((string)$v); if ($v === '') return null;
    $t = strtotime($v); return $t ? date('Y-m-d', $t) : null;
}

/** Reduce la foto a PHOTO_MAX_SIDE px (si GD está disponible) y devuelve [bytes, mime]. */
function prepare_photo(string $tmp, string $mime): array {
    $raw = file_get_contents($tmp);
    if (!function_exists('imagecreatefromstring')) return [$raw, $mime];
    $img = @imagecreatefromstring($raw);
    if (!$img) return [$raw, $mime];
    $w = imagesx($img); $h = imagesy($img);
    $scale = min(1, PHOTO_MAX_SIDE / max($w, $h));
    if ($scale >= 1 && strlen($raw) < 1.5 * 1024 * 1024) { imagedestroy($img); return [$raw, $mime]; }
    $nw = (int)round($w * $scale); $nh = (int)round($h * $scale);
    $dst = imagecreatetruecolor($nw, $nh);
    imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    ob_start(); imagejpeg($dst, null, 82); $out = ob_get_clean();
    imagedestroy($img); imagedestroy($dst);
    return [$out, 'image/jpeg'];
}

api_guard(function () {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $pdo = db();

    /* ======================= GET detalle ======================= */
    if ($method === 'GET' && isset($_GET['id'])) {
        require_login_api();
        $id = (int)$_GET['id'];
        $st = $pdo->prepare("
            SELECT i.*, to_char(i.event_datetime,'YYYY-MM-DD\"T\"HH24:MI') AS event_datetime_local,
                   c.code AS category_code, c.label AS category_label,
                   s.code AS severity_code, s.label AS severity_label, co.nombre AS company
            FROM incidents i
            JOIN categories c ON c.id = i.category_id
            JOIN severities s ON s.id = i.severity_id
            LEFT JOIN companies co ON co.id = i.company_id
            WHERE i.id = :id");
        $st->execute([':id' => $id]);
        $d = $st->fetch();
        if (!$d) json_fail('No existe el incidente', 404);

        $q = $pdo->prepare("SELECT ic.company_id AS id, co.nombre AS name, co.tipo, ic.role
                            FROM incident_companies ic JOIN companies co ON co.id = ic.company_id
                            WHERE ic.incident_id = :id ORDER BY ic.id");
        $q->execute([':id' => $id]); $d['companies'] = $q->fetchAll();

        $q = $pdo->prepare("SELECT p.full_name, co.nombre AS company, p.puesto, ip.role
                            FROM incident_persons ip JOIN persons p ON p.id = ip.person_id
                            LEFT JOIN companies co ON co.id = p.company_id
                            WHERE ip.incident_id = :id ORDER BY ip.id");
        $q->execute([':id' => $id]); $d['persons'] = $q->fetchAll();

        $q = $pdo->prepare("SELECT id FROM incident_photos WHERE incident_id = :id ORDER BY id");
        $q->execute([':id' => $id]);
        $d['photos'] = array_map(fn($r) => ['id' => (int)$r['id'], 'url' => 'api/photo.php?id=' . $r['id']], $q->fetchAll());

        $q = $pdo->prepare("SELECT * FROM incident_investigations WHERE incident_id = :id");
        $q->execute([':id' => $id]);
        $inv = $q->fetch() ?: null;
        if ($inv) $inv['porques'] = json_decode($inv['porques'] ?: '[]', true);
        $d['investigation'] = $inv;

        $q = $pdo->prepare("SELECT * FROM incident_actions WHERE incident_id = :id ORDER BY id");
        $q->execute([':id' => $id]); $d['actions'] = $q->fetchAll();

        $d['riesgo_potencial'] = riesgo_nivel($d['pot_probabilidad'] ? (int)$d['pot_probabilidad'] : null, $d['pot_consecuencia'] ? (int)$d['pot_consecuencia'] : null);
        json_ok(['data' => $d]);
    }

    /* ======================= GET listado ======================= */
    if ($method === 'GET') {
        require_login_api();
        $w = ['TRUE']; $p = [];
        if (!empty($_GET['desde'])) { $w[] = 'i.event_datetime >= :desde'; $p[':desde'] = $_GET['desde']; }
        if (!empty($_GET['hasta'])) { $w[] = 'i.event_datetime <= :hasta'; $p[':hasta'] = $_GET['hasta']; }
        if (!empty($_GET['company_id'])) { $w[] = 'i.company_id = :cid'; $p[':cid'] = (int)$_GET['company_id']; }
        if (!empty($_GET['tipo'])) { $w[] = 'i.tipo_contingencia = :tipo'; $p[':tipo'] = $_GET['tipo']; }
        if (!empty($_GET['estado'])) { $w[] = 'i.estado = :estado'; $p[':estado'] = $_GET['estado']; }
        $limit = min(5000, max(1, (int)($_GET['limit'] ?? 2000)));

        $st = $pdo->prepare("
            SELECT i.id, i.title, i.description, i.event_datetime, i.lat, i.lng,
                   i.faena, i.sector, i.turno, i.tipo_contingencia, i.consecuencia, i.dias_perdidos,
                   i.estado, i.riesgo_critico_code, i.pot_probabilidad, i.pot_consecuencia,
                   i.art_denunciado, i.art_fecha_denuncia,
                   c.id AS category_id, c.code AS category_code, c.label AS category_label,
                   s.id AS severity_id, s.code AS severity_code, s.label AS severity_label,
                   co.nombre AS company,
                   (SELECT count(*) FROM incident_actions a WHERE a.incident_id = i.id
                     AND a.estado IN ('PENDIENTE','EN_CURSO')) AS acciones_abiertas,
                   (SELECT string_agg(co2.nombre, ', ') FROM incident_companies ic JOIN companies co2 ON co2.id = ic.company_id
                     WHERE ic.incident_id = i.id) AS companies_all
            FROM incidents i
            JOIN categories c ON c.id = i.category_id
            JOIN severities s ON s.id = i.severity_id
            LEFT JOIN companies co ON co.id = i.company_id
            WHERE " . implode(' AND ', $w) . "
            ORDER BY i.event_datetime DESC, i.id DESC
            LIMIT $limit");
        $st->execute($p);
        $rows = $st->fetchAll();

        if ($rows) {
            $ids = implode(',', array_map('intval', array_column($rows, 'id')));
            $ph = [];
            foreach ($pdo->query("SELECT incident_id, id FROM incident_photos WHERE incident_id IN ($ids) ORDER BY id") as $r) {
                $ph[$r['incident_id']][] = 'api/photo.php?id=' . $r['id'];
            }
            foreach ($rows as &$r) {
                $r['photos'] = $ph[$r['id']] ?? [];
                $r['riesgo_potencial'] = riesgo_nivel($r['pot_probabilidad'] ? (int)$r['pot_probabilidad'] : null, $r['pot_consecuencia'] ? (int)$r['pot_consecuencia'] : null);
            }
            unset($r);
        }
        json_ok(['data' => $rows]);
    }

    /* ======================= DELETE ======================= */
    if ($method === 'DELETE') {
        require_login_api(['ADMIN']);
        $id = (int)($_GET['id'] ?? 0);
        $pdo->prepare('DELETE FROM incidents WHERE id = :id')->execute([':id' => $id]);
        json_ok(['id' => $id]);
    }

    /* ======================= POST alta/edición ======================= */
    if ($method !== 'POST') json_fail('Método no permitido', 405);
    $u = require_login_api();

    $title = trim((string)($_POST['title'] ?? ''));
    if ($title === '') json_fail('Título requerido');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $severity_id = (int)($_POST['severity_id'] ?? 0);
    if (!$category_id || !$severity_id) json_fail('Categoría y severidad son obligatorias');
    $event_dt = dt_or_null($_POST['event_datetime'] ?? '');
    if (!$event_dt) json_fail('Fecha y hora inválidas');
    if (strtotime($event_dt) > time() + 3600) json_fail('La fecha del evento no puede ser futura');
    $lat = (float)($_POST['lat'] ?? 0); $lng = (float)($_POST['lng'] ?? 0);
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 || ($lat == 0.0 && $lng == 0.0)) json_fail('Coordenadas inválidas');

    $tipo   = enum_or((string)($_POST['tipo_contingencia'] ?? ''), TIPO_CONTINGENCIA, 'INCIDENTE');
    $consec = enum_or((string)($_POST['consecuencia'] ?? ''), CONSECUENCIA, 'SIN_LESION');
    $estado = enum_or((string)($_POST['estado'] ?? ''), ESTADO_INCIDENTE, 'ABIERTO');

    $f_baja = date_or_null($_POST['fecha_inicio_baja'] ?? '');
    $f_alta = date_or_null($_POST['fecha_alta'] ?? '');
    $dias = max(0, (int)($_POST['dias_perdidos'] ?? 0));
    // Criterio SRT: días corridos de baja, excluido el día del accidente y el de reintegro.
    if ($f_baja && $f_alta && $dias === 0) {
        $dias = max(0, (int)((strtotime($f_alta) - strtotime($f_baja)) / 86400));
    }
    // Coherencia normativa
    if (in_array($consec, CONSEC_LTI, true) && in_array($tipo, ['INCIDENTE', 'DANO_MATERIAL', 'AMBIENTAL', 'OTRO'], true)) {
        json_fail('Una consecuencia con lesión requiere tipo de contingencia: accidente de trabajo, in itinere o enfermedad profesional');
    }
    if (!in_array($consec, CONSEC_LTI, true)) $dias = 0;

    $companies = json_decode($_POST['companies_json'] ?? '[]', true) ?: [];
    $persons   = json_decode($_POST['persons_json'] ?? '[]', true) ?: [];
    $inv       = json_decode($_POST['investigation_json'] ?? 'null', true);
    $actions   = json_decode($_POST['actions_json'] ?? '[]', true) ?: [];

    // Empresa principal: la elegida explícitamente, o la "PROPIETARIA"/primera de la lista
    $mainCompany = (int)($_POST['company_id'] ?? 0) ?: null;
    if (!$mainCompany) {
        foreach ($companies as $c) if (($c['role'] ?? '') === 'PROPIETARIA' && !empty($c['id'])) { $mainCompany = (int)$c['id']; break; }
        if (!$mainCompany && !empty($companies[0]['id'])) $mainCompany = (int)$companies[0]['id'];
    }

    $vals = [
        ':t' => $title, ':d' => nn($_POST['description'] ?? null), ':cid' => $category_id, ':sid' => $severity_id,
        ':coid' => $mainCompany, ':dt' => $event_dt, ':lat' => $lat, ':lng' => $lng,
        ':faena' => nn($_POST['faena'] ?? null), ':sector' => nn($_POST['sector'] ?? null), ':turno' => nn($_POST['turno'] ?? null),
        ':tipo' => $tipo, ':consec' => $consec, ':dias' => $dias, ':fb' => $f_baja, ':fa' => $f_alta,
        ':forma' => nn($_POST['forma_code'] ?? null), ':agente' => nn($_POST['agente_code'] ?? null),
        ':nat' => nn($_POST['naturaleza_code'] ?? null), ':zona' => nn($_POST['zona_code'] ?? null),
        ':rc' => nn($_POST['riesgo_critico_code'] ?? null),
        ':pp' => int_or_null($_POST['pot_probabilidad'] ?? null, 1, 5), ':pc' => int_or_null($_POST['pot_consecuencia'] ?? null, 1, 5),
        ':artd' => !empty($_POST['art_denunciado']) ? 'true' : 'false', ':artn' => nn($_POST['art_nombre'] ?? null),
        ':arts' => nn($_POST['art_nro_siniestro'] ?? null), ':artf' => dt_or_null($_POST['art_fecha_denuncia'] ?? ''),
        ':autn' => !empty($_POST['autoridad_notificada']) ? 'true' : 'false', ':autf' => dt_or_null($_POST['autoridad_fecha'] ?? ''),
        ':estado' => $estado,
    ];
    $cols = "title=:t, description=:d, category_id=:cid, severity_id=:sid, company_id=:coid, event_datetime=:dt,
             lat=:lat, lng=:lng, faena=:faena, sector=:sector, turno=:turno, tipo_contingencia=:tipo,
             consecuencia=:consec, dias_perdidos=:dias, fecha_inicio_baja=:fb, fecha_alta=:fa,
             forma_code=:forma, agente_code=:agente, naturaleza_code=:nat, zona_code=:zona, riesgo_critico_code=:rc,
             pot_probabilidad=:pp, pot_consecuencia=:pc, art_denunciado=:artd, art_nombre=:artn,
             art_nro_siniestro=:arts, art_fecha_denuncia=:artf, autoridad_notificada=:autn, autoridad_fecha=:autf,
             estado=:estado";

    $pdo->beginTransaction();
    $id = ctype_digit((string)($_POST['id'] ?? '')) ? (int)$_POST['id'] : 0;
    if ($id > 0) {
        $vals[':id'] = $id;
        $st = $pdo->prepare("UPDATE incidents SET $cols, updated_at=now() WHERE id=:id");
        $st->execute($vals);
        if ($st->rowCount() === 0) json_fail('No existe el incidente', 404);
        $pdo->prepare('DELETE FROM incident_companies WHERE incident_id=:id')->execute([':id' => $id]);
        $pdo->prepare('DELETE FROM incident_persons WHERE incident_id=:id')->execute([':id' => $id]);
    } else {
        $vals[':uid'] = $u['id'];
        $set = [];
        foreach (explode(',', $cols) as $pair) { [$c, $v] = array_map('trim', explode('=', $pair)); $set[$c] = $v; }
        $st = $pdo->prepare('INSERT INTO incidents (' . implode(',', array_keys($set)) . ', created_by) VALUES (' . implode(',', $set) . ', :uid) RETURNING id');
        $st->execute($vals);
        $id = (int)$st->fetchColumn();
    }

    // Empresas N:M
    $insIC = $pdo->prepare('INSERT INTO incident_companies (incident_id, company_id, role) VALUES (:i,:c,:r)');
    foreach ($companies as $c) {
        $cid = (int)($c['id'] ?? 0);
        if ($cid > 0) $insIC->execute([':i' => $id, ':c' => $cid, ':r' => substr((string)($c['role'] ?? 'RESPONSABLE'), 0, 40)]);
    }

    // Personas (crea empresa/persona si no existen)
    $selCo  = $pdo->prepare('SELECT id FROM companies WHERE lower(nombre)=lower(:n) LIMIT 1');
    $insCo  = $pdo->prepare("INSERT INTO companies (nombre, tipo) VALUES (:n, 'CONTRATISTA') RETURNING id");
    $selP   = $pdo->prepare('SELECT id FROM persons WHERE lower(full_name)=lower(:f) AND COALESCE(company_id,0)=COALESCE(CAST(:c AS INT),0) LIMIT 1');
    $insP   = $pdo->prepare('INSERT INTO persons (full_name, company_id, puesto) VALUES (:f, :c, :pu) RETURNING id');
    $updP   = $pdo->prepare('UPDATE persons SET puesto = COALESCE(:pu, puesto) WHERE id = :id');
    $insIP  = $pdo->prepare('INSERT INTO incident_persons (incident_id, person_id, role) VALUES (:i,:p,:r)');
    foreach ($persons as $p) {
        $name = trim((string)($p['full_name'] ?? '')); if ($name === '') continue;
        $compName = trim((string)($p['company'] ?? ''));
        $puesto = nn($p['puesto'] ?? null);
        $compId = null;
        if ($compName !== '') {
            $selCo->execute([':n' => $compName]); $compId = $selCo->fetchColumn() ?: null;
            if (!$compId) { $insCo->execute([':n' => $compName]); $compId = (int)$insCo->fetchColumn(); }
        }
        $selP->execute([':f' => $name, ':c' => $compId]); $pid = $selP->fetchColumn();
        if (!$pid) { $insP->execute([':f' => $name, ':c' => $compId, ':pu' => $puesto]); $pid = (int)$insP->fetchColumn(); }
        else $updP->execute([':pu' => $puesto, ':id' => $pid]);
        $insIP->execute([':i' => $id, ':p' => $pid, ':r' => nn($p['role'] ?? null)]);
    }

    // Investigación (1:1)
    if (is_array($inv)) {
        $porques = array_values(array_filter(array_map(fn($x) => trim((string)$x), (array)($inv['porques'] ?? [])), fn($x) => $x !== ''));
        $pdo->prepare("
            INSERT INTO incident_investigations (incident_id, metodo, equipo, hechos, actos_subestandar, condiciones_subestandar,
                factores_personales, factores_trabajo, falta_control, porques, conclusiones, fecha_inicio, fecha_cierre, updated_at)
            VALUES (:i,:m,:eq,:h,:as,:cs,:fp,:ft,:fc,CAST(:pq AS JSONB),:co,:fi,:fcie, now())
            ON CONFLICT (incident_id) DO UPDATE SET metodo=EXCLUDED.metodo, equipo=EXCLUDED.equipo, hechos=EXCLUDED.hechos,
                actos_subestandar=EXCLUDED.actos_subestandar, condiciones_subestandar=EXCLUDED.condiciones_subestandar,
                factores_personales=EXCLUDED.factores_personales, factores_trabajo=EXCLUDED.factores_trabajo,
                falta_control=EXCLUDED.falta_control, porques=EXCLUDED.porques, conclusiones=EXCLUDED.conclusiones,
                fecha_inicio=EXCLUDED.fecha_inicio, fecha_cierre=EXCLUDED.fecha_cierre, updated_at=now()")
        ->execute([
            ':i' => $id, ':m' => enum_or((string)($inv['metodo'] ?? ''), METODO_INVESTIGACION, 'ARBOL_CAUSAS'),
            ':eq' => nn($inv['equipo'] ?? null), ':h' => nn($inv['hechos'] ?? null),
            ':as' => nn($inv['actos_subestandar'] ?? null), ':cs' => nn($inv['condiciones_subestandar'] ?? null),
            ':fp' => nn($inv['factores_personales'] ?? null), ':ft' => nn($inv['factores_trabajo'] ?? null),
            ':fc' => nn($inv['falta_control'] ?? null), ':pq' => json_encode($porques, JSON_UNESCAPED_UNICODE),
            ':co' => nn($inv['conclusiones'] ?? null), ':fi' => date_or_null($inv['fecha_inicio'] ?? ''),
            ':fcie' => date_or_null($inv['fecha_cierre'] ?? ''),
        ]);
    }

    // Acciones: sincroniza (actualiza las que traen id, crea nuevas, borra las quitadas)
    $keep = [];
    $updA = $pdo->prepare("UPDATE incident_actions SET descripcion=:d, tipo=:t, jerarquia=:j, responsable=:r, fecha_compromiso=:fc,
                           estado=:e, fecha_cumplimiento=:fcu, verificacion=:v WHERE id=:id AND incident_id=:i");
    $insA = $pdo->prepare("INSERT INTO incident_actions (incident_id, descripcion, tipo, jerarquia, responsable, fecha_compromiso, estado, fecha_cumplimiento, verificacion)
                           VALUES (:i,:d,:t,:j,:r,:fc,:e,:fcu,:v) RETURNING id");
    foreach ($actions as $a) {
        $desc = trim((string)($a['descripcion'] ?? '')); if ($desc === '') continue;
        $e = enum_or((string)($a['estado'] ?? ''), ESTADO_ACCION, 'PENDIENTE');
        $v = [':i' => $id, ':d' => $desc, ':t' => enum_or((string)($a['tipo'] ?? ''), TIPO_ACCION, 'CORRECTIVA'),
              ':j' => enum_or((string)($a['jerarquia'] ?? ''), JERARQUIA, 'ADMINISTRATIVO'), ':r' => nn($a['responsable'] ?? null),
              ':fc' => date_or_null($a['fecha_compromiso'] ?? ''), ':e' => $e,
              ':fcu' => date_or_null($a['fecha_cumplimiento'] ?? '') ?? (in_array($e, ['CUMPLIDA', 'VERIFICADA'], true) ? date('Y-m-d') : null),
              ':v' => nn($a['verificacion'] ?? null)];
        if (!empty($a['id'])) { $v[':id'] = (int)$a['id']; $updA->execute($v); $keep[] = (int)$a['id']; }
        else { $insA->execute($v); $keep[] = (int)$insA->fetchColumn(); }
    }
    if ($keep) {
        $in = implode(',', array_map('intval', $keep));
        $pdo->prepare("DELETE FROM incident_actions WHERE incident_id=:i AND id NOT IN ($in)")->execute([':i' => $id]);
    } else {
        $pdo->prepare('DELETE FROM incident_actions WHERE incident_id=:i')->execute([':i' => $id]);
    }

    // Fotos nuevas
    if (!empty($_FILES['photos']) && is_array($_FILES['photos']['name'])) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $insPh = $pdo->prepare('INSERT INTO incident_photos (incident_id, filename, mime, data) VALUES (:i,:f,:m,:d)');
        $n = count($_FILES['photos']['name']);
        for ($k = 0; $k < $n; $k++) {
            if ($_FILES['photos']['error'][$k] !== UPLOAD_ERR_OK) continue;
            if ($_FILES['photos']['size'][$k] > MAX_PHOTO_BYTES) json_fail('Foto demasiado grande (máx. 10 MB)');
            $tmp = $_FILES['photos']['tmp_name'][$k];
            $mime = $finfo->file($tmp) ?: '';
            if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) json_fail('Formato de foto no permitido (JPG, PNG o WEBP)');
            [$bytes, $mime] = prepare_photo($tmp, $mime);
            $insPh->bindValue(':i', $id, PDO::PARAM_INT);
            $insPh->bindValue(':f', substr(basename((string)$_FILES['photos']['name'][$k]), 0, 200));
            $insPh->bindValue(':m', $mime);
            $insPh->bindValue(':d', $bytes, PDO::PARAM_LOB);
            $insPh->execute();
        }
    }

    // Fotos a eliminar
    $del = json_decode($_POST['delete_photos_json'] ?? '[]', true) ?: [];
    if ($del) {
        $in = implode(',', array_map('intval', $del));
        $pdo->prepare("DELETE FROM incident_photos WHERE incident_id=:i AND id IN ($in)")->execute([':i' => $id]);
    }

    $pdo->commit();
    json_ok(['id' => $id]);
});
