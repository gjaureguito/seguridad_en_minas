<?php
declare(strict_types=1);
/* informe.php — informe imprimible de investigación de accidente/incidente */
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/layout.php';
$user = require_login_page();
$pdo = db();
$id = (int)($_GET['id'] ?? 0);

$st = $pdo->prepare("SELECT i.*, c.label AS categoria, s.label AS severidad, co.nombre AS empresa, co.cuit, co.art AS empresa_art, u.full_name AS autor
                     FROM incidents i JOIN categories c ON c.id=i.category_id JOIN severities s ON s.id=i.severity_id
                     LEFT JOIN companies co ON co.id=i.company_id LEFT JOIN users u ON u.id=i.created_by WHERE i.id=:id");
$st->execute([':id' => $id]);
$d = $st->fetch();
if (!$d) { http_response_code(404); echo 'No existe el registro'; exit; }

$cat = [];
foreach ($pdo->query('SELECT kind, code, label FROM catalogs') as $r) $cat[$r['kind']][$r['code']] = $r['label'];
$lbl = fn(string $k, ?string $c) => $c ? ($c . ' · ' . ($cat[$k][$c] ?? '')) : '—';

$q = $pdo->prepare('SELECT co.nombre, co.tipo, ic.role FROM incident_companies ic JOIN companies co ON co.id=ic.company_id WHERE ic.incident_id=:id ORDER BY ic.id');
$q->execute([':id' => $id]); $companies = $q->fetchAll();
$q = $pdo->prepare('SELECT p.full_name, p.puesto, co.nombre AS empresa, ip.role FROM incident_persons ip JOIN persons p ON p.id=ip.person_id LEFT JOIN companies co ON co.id=p.company_id WHERE ip.incident_id=:id ORDER BY ip.id');
$q->execute([':id' => $id]); $persons = $q->fetchAll();
$q = $pdo->prepare('SELECT * FROM incident_investigations WHERE incident_id=:id');
$q->execute([':id' => $id]); $inv = $q->fetch() ?: [];
$porques = $inv ? (json_decode($inv['porques'] ?? '[]', true) ?: []) : [];
$q = $pdo->prepare('SELECT * FROM incident_actions WHERE incident_id=:id ORDER BY id');
$q->execute([':id' => $id]); $actions = $q->fetchAll();
$q = $pdo->prepare('SELECT id FROM incident_photos WHERE incident_id=:id ORDER BY id');
$q->execute([':id' => $id]); $photos = $q->fetchAll(PDO::FETCH_COLUMN);
$rp = riesgo_nivel($d['pot_probabilidad'] ? (int)$d['pot_probabilidad'] : null, $d['pot_consecuencia'] ? (int)$d['pot_consecuencia'] : null);
$fdt = fn(?string $s) => $s ? date('d/m/Y H:i', strtotime($s)) : '—';
$fd  = fn(?string $s) => $s ? date('d/m/Y', strtotime($s)) : '—';
$txt = fn(?string $s) => $s ? nl2br(h($s)) : '<span class="text-muted">—</span>';

page_head('Informe #' . $id);
?>
<body style="background:#fff">
<div class="container py-4" style="max-width:900px">
  <div class="d-flex justify-content-between align-items-start mb-3">
    <div>
      <div class="small-muted">Informe de investigación de accidente / incidente</div>
      <h1 class="h4 fw-bold mb-0">#<?= $id ?> — <?= h($d['title']) ?></h1>
      <div class="small-muted">Dec. 249/07 art. 13 o · Ley 19.587 · Ley 24.557</div>
    </div>
    <button class="btn btn-outline-secondary btn-sm no-print" onclick="print()"><i class="bi bi-printer"></i> Imprimir / PDF</button>
  </div>

  <div class="cardish"><div class="section-title">1. Datos del evento</div>
    <table class="table table-sm mb-0"><tbody>
      <tr><th style="width:28%">Fecha y hora</th><td><?= $fdt($d['event_datetime']) ?> · turno <?= h($d['turno'] ?: '—') ?></td></tr>
      <tr><th>Lugar</th><td><?= h($d['faena'] ?: '—') ?> / <?= h($d['sector'] ?: '—') ?> · <?= number_format((float)$d['lat'], 6) ?>, <?= number_format((float)$d['lng'], 6) ?>
        <a class="no-print ms-2" target="_blank" rel="noopener" href="https://earth.google.com/web/@<?= number_format((float)$d['lat'], 6, '.', '') ?>,<?= number_format((float)$d['lng'], 6, '.', '') ?>,3000a,1200d,35y,0h,60t,0r">Ver en Google Earth</a></td></tr>
      <tr><th>Tipo de contingencia</th><td><?= h(TIPO_CONTINGENCIA[$d['tipo_contingencia']] ?? $d['tipo_contingencia']) ?></td></tr>
      <tr><th>Categoría / severidad real</th><td><?= h($d['categoria']) ?> · <?= h($d['severidad']) ?></td></tr>
      <tr><th>Severidad potencial</th><td><?= $rp ? h($rp['nivel']) . ' (P' . $d['pot_probabilidad'] . ' × C' . $d['pot_consecuencia'] . ' = ' . $rp['valor'] . ')' : '—' ?></td></tr>
      <tr><th>Empresa</th><td><?= h($d['empresa'] ?: '—') ?><?= $d['cuit'] ? ' · CUIT ' . h($d['cuit']) : '' ?></td></tr>
      <tr><th>Estado</th><td><?= h(ESTADO_INCIDENTE[$d['estado']] ?? $d['estado']) ?></td></tr>
      <tr><th>Descripción</th><td><?= $txt($d['description']) ?></td></tr>
    </tbody></table>
  </div>

  <div class="cardish"><div class="section-title">2. Lesión y tipificación (tablas OIT / RENAL)</div>
    <table class="table table-sm mb-0"><tbody>
      <tr><th style="width:28%">Consecuencia</th><td><?= h(CONSECUENCIA[$d['consecuencia']] ?? $d['consecuencia']) ?></td></tr>
      <?php if (in_array($d['consecuencia'], CONSEC_LTI, true)): ?>
      <tr><th>Baja</th><td>desde <?= $fd($d['fecha_inicio_baja']) ?> hasta <?= $fd($d['fecha_alta']) ?> · <b><?= (int)$d['dias_perdidos'] ?> días perdidos</b></td></tr>
      <?php endif; ?>
      <tr><th>Forma del accidente</th><td><?= h($lbl('FORMA', $d['forma_code'])) ?></td></tr>
      <tr><th>Agente material</th><td><?= h($lbl('AGENTE', $d['agente_code'])) ?></td></tr>
      <tr><th>Naturaleza de la lesión</th><td><?= h($lbl('NATURALEZA', $d['naturaleza_code'])) ?></td></tr>
      <tr><th>Zona del cuerpo</th><td><?= h($lbl('ZONA', $d['zona_code'])) ?></td></tr>
      <tr><th>Riesgo crítico</th><td><?= h($d['riesgo_critico_code'] ? ($cat['RIESGO_CRITICO'][$d['riesgo_critico_code']] ?? $d['riesgo_critico_code']) : '—') ?></td></tr>
      <tr><th>Denuncia ART</th><td><?= $d['art_denunciado'] ? 'Sí · ' . h($d['art_nombre'] ?: '') . ' · siniestro ' . h($d['art_nro_siniestro'] ?: '—') . ' · ' . $fdt($d['art_fecha_denuncia']) : 'No' ?></td></tr>
      <tr><th>Comunicación a autoridad</th><td><?= $d['autoridad_notificada'] ? 'Sí · ' . $fdt($d['autoridad_fecha']) : 'No' ?></td></tr>
    </tbody></table>
  </div>

  <div class="cardish"><div class="section-title">3. Empresas y personas involucradas</div>
    <div class="row"><div class="col-md-5"><ul class="small mb-0"><?php foreach ($companies as $c): ?><li><?= h($c['nombre']) ?> (<?= h($c['tipo']) ?>) — <?= h($c['role']) ?></li><?php endforeach; if (!$companies) echo '<li class="text-muted">—</li>'; ?></ul></div>
    <div class="col-md-7"><ul class="small mb-0"><?php foreach ($persons as $p): ?><li><b><?= h($p['full_name']) ?></b> · <?= h($p['puesto'] ?: '') ?> · <?= h($p['empresa'] ?: '') ?> — <?= h($p['role'] ?: '') ?></li><?php endforeach; if (!$persons) echo '<li class="text-muted">—</li>'; ?></ul></div></div>
  </div>

  <div class="cardish"><div class="section-title">4. Investigación — <?= h(METODO_INVESTIGACION[$inv['metodo'] ?? ''] ?? '—') ?></div>
    <div class="small mb-2">Equipo: <?= h($inv['equipo'] ?? '—') ?> · Inicio <?= $fd($inv['fecha_inicio'] ?? null) ?> · Cierre <?= $fd($inv['fecha_cierre'] ?? null) ?></div>
    <table class="table table-sm mb-2"><tbody>
      <tr><th style="width:28%">Hechos</th><td><?= $txt($inv['hechos'] ?? null) ?></td></tr>
      <tr><th>Causas inmediatas: actos</th><td><?= $txt($inv['actos_subestandar'] ?? null) ?></td></tr>
      <tr><th>Causas inmediatas: condiciones</th><td><?= $txt($inv['condiciones_subestandar'] ?? null) ?></td></tr>
      <tr><th>Causas básicas: factores personales</th><td><?= $txt($inv['factores_personales'] ?? null) ?></td></tr>
      <tr><th>Causas básicas: factores del trabajo</th><td><?= $txt($inv['factores_trabajo'] ?? null) ?></td></tr>
      <tr><th>Falta de control</th><td><?= $txt($inv['falta_control'] ?? null) ?></td></tr>
      <tr><th>Cadena de porqués</th><td><?php if ($porques): ?><ol class="mb-0 ps-3"><?php foreach ($porques as $p): ?><li><?= h($p) ?></li><?php endforeach; ?></ol><?php else: ?>—<?php endif; ?></td></tr>
      <tr><th>Conclusiones</th><td><?= $txt($inv['conclusiones'] ?? null) ?></td></tr>
    </tbody></table>
  </div>

  <div class="cardish"><div class="section-title">5. Plan de acción</div>
    <table class="table table-sm mb-0"><thead><tr><th>Acción</th><th>Tipo / jerarquía</th><th>Responsable</th><th>Compromiso</th><th>Estado</th><th>Verificación</th></tr></thead><tbody>
      <?php foreach ($actions as $a): ?>
      <tr><td><?= h($a['descripcion']) ?></td><td class="small"><?= h(TIPO_ACCION[$a['tipo']]) ?> · <?= h(JERARQUIA[$a['jerarquia']]) ?></td><td><?= h($a['responsable'] ?: '') ?></td>
        <td><?= $fd($a['fecha_compromiso']) ?></td><td><?= h(ESTADO_ACCION[$a['estado']]) ?><?= $a['fecha_cumplimiento'] ? '<div class="small">' . $fd($a['fecha_cumplimiento']) . '</div>' : '' ?></td><td class="small"><?= h($a['verificacion'] ?: '') ?></td></tr>
      <?php endforeach; if (!$actions) echo '<tr><td colspan="6" class="text-muted">Sin acciones</td></tr>'; ?>
    </tbody></table>
  </div>

  <?php if ($photos): ?>
  <div class="cardish"><div class="section-title">6. Registro fotográfico</div>
    <div class="d-flex flex-wrap gap-2"><?php foreach ($photos as $pid): ?><img src="api/photo.php?id=<?= (int)$pid ?>" style="width:200px;height:150px;object-fit:cover;border-radius:8px;border:1px solid #e5e7eb"><?php endforeach; ?></div>
  </div>
  <?php endif; ?>

  <div class="row mt-5 text-center small">
    <div class="col">____________________<br>Responsable de Higiene y Seguridad</div>
    <div class="col">____________________<br>Supervisor del área</div>
    <div class="col">____________________<br>Representante del Comité</div>
  </div>
  <div class="small-muted mt-4">Registrado por <?= h($d['autor'] ?: '—') ?> el <?= $fdt($d['created_at']) ?> · emitido el <?= date('d/m/Y H:i') ?></div>
</div>
<?php page_scripts(); ?>
</body></html>
