<?php
declare(strict_types=1);
/* horas.php — carga de horas-hombre trabajadas (HHT) y dotación por empresa y mes */
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/layout.php';
$user = require_login_page();
page_head('Horas-hombre');
?>
<body>
<?php page_nav($user, 'horas.php'); ?>
<div class="container-fluid py-3">
  <div class="cardish d-flex flex-wrap gap-2 align-items-end">
    <div><label class="form-label">Año</label><input id="anio" type="number" class="form-control form-control-sm" style="width:100px"></div>
    <div class="small-muted" style="max-width:720px">
      Las <b>HHT</b> son las horas efectivamente trabajadas por todo el personal de la empresa en el mes, incluidas las horas extra y sin contar licencias ni vacaciones. La <b>dotación</b> es el promedio de trabajadores del mes. Son el denominador de todos los índices.
    </div>
    <?php if ($user['role'] !== 'LECTOR'): ?><button id="btnSave" class="btn btn-primary btn-sm ms-auto"><i class="bi bi-save"></i> Guardar</button><?php endif; ?>
  </div>
  <div class="cardish">
    <div class="table-responsive"><table class="table table-sm mb-0 align-middle tabular" id="grid"></table></div>
    <div class="small-muted mt-2">En cada celda: HHT arriba, dotación abajo. Dejar las HHT vacías borra el mes.</div>
  </div>
</div>
<?php page_scripts(); ?>
<script>
const $ = id => document.getElementById(id);
const M = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
let COS = [];
// Acepta "12345.5", "12.345,5" o "12345,5"
const num = s => { s = s.trim(); return s.includes(',') ? s.replace(/\./g, '').replace(',', '.') : s; };
async function load() {
  const anio = $('anio').value;
  const [{ data: cos }, { data }] = await Promise.all([api('companies.php'), api('hours.php?anio=' + anio)]);
  COS = cos;
  const map = {}; data.forEach(r => map[r.company_id + '|' + r.periodo] = r);
  let html = '<thead class="table-light"><tr><th>Empresa</th>' + M.map(m => `<th class="text-center">${m}</th>`).join('') + '<th class="text-end">Total HHT</th></tr></thead><tbody>';
  cos.forEach(c => {
    let tot = 0;
    html += `<tr><td class="text-nowrap"><b>${esc(c.nombre)}</b><div class="small-muted">${esc(c.tipo)}</div></td>`;
    M.forEach((_, k) => {
      const per = `${anio}-${String(k + 1).padStart(2, '0')}`, r = map[c.id + '|' + per];
      if (r) tot += r.hht;
      html += `<td style="min-width:92px"><input class="form-control form-control-sm mb-1 text-end" data-c="${c.id}" data-p="${per}" data-f="hht" placeholder="HHT" value="${r ? r.hht : ''}">
        <input class="form-control form-control-sm text-end" data-c="${c.id}" data-p="${per}" data-f="dot" placeholder="Dot." value="${r ? r.dotacion : ''}"></td>`;
    });
    html += `<td class="text-end fw-semibold">${fmtN(tot, 0)}</td></tr>`;
  });
  $('grid').innerHTML = html + '</tbody>';
  if (!cos.length) $('grid').innerHTML = '<tr><td class="small-muted">No hay empresas cargadas. Agregalas en Administración.</td></tr>';
}
$('btnSave')?.addEventListener('click', async () => {
  const rows = {};
  document.querySelectorAll('#grid input').forEach(i => {
    const k = i.dataset.c + '|' + i.dataset.p;
    rows[k] ??= { company_id: +i.dataset.c, periodo: i.dataset.p };
    rows[k][i.dataset.f === 'hht' ? 'hht' : 'dotacion'] = num(i.value);
  });
  try { const j = await api('hours.php', { json: { rows: Object.values(rows) } }); toast(`${j.guardados} meses guardados`); load(); }
  catch (e) { toast(e.message, 'danger'); }
});
$('anio').value = new Date().getFullYear();
$('anio').addEventListener('change', load);
load().catch(e => toast(e.message, 'danger'));
</script>
</body></html>
