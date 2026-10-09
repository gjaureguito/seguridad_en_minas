<?php
declare(strict_types=1);
/* acciones.php — tablero de acciones correctivas / preventivas */
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/layout.php';
$user = require_login_page();
page_head('Acciones correctivas');
?>
<body>
<?php page_nav($user, 'acciones.php'); ?>
<div class="container-fluid py-3">
  <div class="cardish d-flex flex-wrap gap-2 align-items-end">
    <div><label class="form-label">Estado</label><select id="f_estado" class="form-select form-select-sm"><option value="">Todos (sin anuladas)</option></select></div>
    <div><label class="form-label">Responsable</label><input id="f_resp" class="form-control form-control-sm"></div>
    <div class="form-check ms-2 mb-1"><input class="form-check-input" type="checkbox" id="f_venc"><label class="form-check-label small" for="f_venc">Solo vencidas</label></div>
    <div class="ms-auto small-muted" id="resumen"></div>
  </div>
  <div class="cardish">
    <div class="table-responsive"><table class="table table-sm table-hover mb-0">
      <thead class="table-light"><tr><th>Acción</th><th>Incidente</th><th>Jerarquía</th><th>Responsable</th><th>Compromiso</th><th>Estado</th><th>Verificación de eficacia</th><th></th></tr></thead>
      <tbody id="tbody"></tbody></table></div>
  </div>
</div>
<?php page_scripts(); ?>
<script>
const $ = id => document.getElementById(id);
const RO = <?= $user['role'] === 'LECTOR' ? 'true' : 'false' ?>;
let META, ROWS = [];
async function load() {
  const q = new URLSearchParams({ estado: $('f_estado').value, responsable: $('f_resp').value, vencidas: $('f_venc').checked ? 1 : '' });
  ROWS = (await api('actions.php?' + q)).data;
  const venc = ROWS.filter(r => r.vencida).length;
  $('resumen').innerHTML = `${ROWS.length} acciones · <span class="${venc ? 'text-danger fw-bold' : ''}">${venc} vencidas</span>`;
  $('tbody').innerHTML = ROWS.map((a, i) => {
    const est = Object.entries(META.enums.estado_accion).map(([k, v]) => `<option value="${k}"${k === a.estado ? ' selected' : ''}>${esc(v)}</option>`).join('');
    const plazo = a.fecha_compromiso ? `${esc(fmtD(a.fecha_compromiso))}<div class="small ${a.vencida ? 'text-danger fw-bold' : 'small-muted'}">${a.vencida ? 'vencida hace ' + (-a.dias_restantes) + ' d' : (['PENDIENTE','EN_CURSO'].includes(a.estado) && a.dias_restantes !== null ? 'faltan ' + a.dias_restantes + ' d' : '')}</div>` : '—';
    return `<tr class="${a.vencida ? 'table-danger' : ''}">
      <td style="max-width:320px">${esc(a.descripcion)}<div><span class="pill">${esc(META.enums.tipo_accion[a.tipo])}</span></div></td>
      <td><a href="index.php#edit=${a.incident_id}">#${a.incident_id}</a> ${esc(a.incident_title)}<div class="small-muted">${esc(fmtD(a.event_datetime))} · ${esc(a.company || '')}</div></td>
      <td class="small">${esc(META.enums.jerarquia[a.jerarquia])}</td><td>${esc(a.responsable || '')}</td><td class="text-nowrap">${plazo}</td>
      <td><select class="form-select form-select-sm" id="e${i}" ${RO ? 'disabled' : ''}>${est}</select></td>
      <td><input class="form-control form-control-sm" id="v${i}" value="${esc(a.verificacion || '')}" ${RO ? 'disabled' : ''}></td>
      <td>${RO ? '' : `<button class="btn btn-sm btn-outline-primary" onclick="save(${i})"><i class="bi bi-check2"></i></button>`}</td></tr>`;
  }).join('') || '<tr><td colspan="8" class="small-muted">Sin acciones</td></tr>';
}
async function save(i) {
  try { await api('actions.php', { json: { id: ROWS[i].id, estado: $('e' + i).value, verificacion: $('v' + i).value } }); toast('Acción actualizada'); load(); }
  catch (e) { toast(e.message, 'danger'); }
}
['f_estado', 'f_venc'].forEach(id => $(id).addEventListener('change', load));
$('f_resp').addEventListener('input', () => { clearTimeout(window._t); window._t = setTimeout(load, 300); });
(async () => {
  META = await api('meta.php');
  $('f_estado').innerHTML += Object.entries(META.enums.estado_accion).map(([k, v]) => `<option value="${k}">${esc(v)}</option>`).join('');
  load();
})();
</script>
</body></html>
