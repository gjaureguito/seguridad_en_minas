<?php
declare(strict_types=1);
/* dashboard.php — mapa, filtros, tabla y exportación CSV */
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/layout.php';
$user = require_login_page();
page_head('Dashboard', ['<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">']);
?>
<body>
<?php page_nav($user, 'dashboard.php'); ?>
<div class="container-fluid py-3">
  <div class="row g-3">
    <div class="col-12 col-lg-3">
      <div class="cardish">
        <div class="section-title">Filtros</div>
        <div class="row g-2">
          <div class="col-6"><label class="form-label">Desde</label><input id="f_ini" type="date" class="form-control form-control-sm"></div>
          <div class="col-6"><label class="form-label">Hasta</label><input id="f_fin" type="date" class="form-control form-control-sm"></div>
          <div class="col-12"><label class="form-label">Tipo de contingencia</label><select id="f_tipo" class="form-select form-select-sm"></select></div>
          <div class="col-12"><label class="form-label">Consecuencia</label><select id="f_consec" class="form-select form-select-sm"></select></div>
          <div class="col-6"><label class="form-label">Categoría</label><select id="f_cat" class="form-select form-select-sm"></select></div>
          <div class="col-6"><label class="form-label">Severidad</label><select id="f_sev" class="form-select form-select-sm"></select></div>
          <div class="col-6"><label class="form-label">Estado</label><select id="f_estado" class="form-select form-select-sm"></select></div>
          <div class="col-6"><label class="form-label">Riesgo potencial</label><select id="f_pot" class="form-select form-select-sm">
            <option value="">Todos</option><option value="HIPO">Alto potencial (≥15)</option></select></div>
          <div class="col-12"><label class="form-label">Empresa / faena (contiene)</label><input id="f_emp" class="form-control form-control-sm"></div>
          <div class="col-12"><label class="form-label">Texto libre</label><input id="f_q" class="form-control form-control-sm" placeholder="Título, descripción…"></div>
          <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="f_bbox"><label class="form-check-label small" for="f_bbox">Solo lo visible en el mapa</label></div></div>
        </div>
        <div class="d-flex gap-2 mt-3">
          <button id="btnClear" class="btn btn-outline-secondary btn-sm">Limpiar</button>
          <button id="btnCSV" class="btn btn-outline-success btn-sm ms-auto"><i class="bi bi-filetype-csv"></i> Exportar CSV</button>
        </div>
      </div>
    </div>

    <div class="col-12 col-lg-9">
      <div class="row g-2 mb-2">
        <?php foreach ([['k_total','Registros'],['k_at','Accidentes de trabajo'],['k_lti','Con baja'],['k_dias','Días perdidos'],['k_hipo','Alto potencial'],['k_den','Sin denunciar (ART)']] as [$id,$l]): ?>
        <div class="col-6 col-md-4 col-xl-2"><div class="kpi"><div class="lbl"><?= $l ?></div><div class="val" id="<?= $id ?>">—</div></div></div>
        <?php endforeach; ?>
      </div>
      <div id="map" class="cardish p-0 mb-2" style="height:44vh"></div>
      <div class="cardish">
        <div class="d-flex justify-content-between mb-2"><div class="section-title m-0">Registros filtrados</div><div class="small-muted"><span id="cnt">0</span> filas</div></div>
        <div class="table-responsive" style="max-height:40vh">
          <table class="table table-sm table-hover mb-0">
            <thead class="table-light position-sticky top-0"><tr>
              <th>#</th><th>Fecha</th><th>Título</th><th>Tipo</th><th>Consecuencia</th><th class="text-end">Días</th><th>Sev.</th><th>Potencial</th><th>Empresa</th><th>Estado</th><th></th></tr></thead>
            <tbody id="tbody"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<?php page_scripts(); ?>
<script>
const $ = id => document.getElementById(id);
let META, RAW = [], FILTERED = [];
const SJ = L.latLngBounds([[-32.3, -69.9], [-29.0, -66.5]]);
const map = L.map('map'); map.fitBounds(SJ);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);
const layer = L.layerGroup().addTo(map);
const LTI = ['CON_BAJA', 'INCAPACIDAD_PERMANENTE', 'FATAL'];

function opts(id, entries) { $(id).innerHTML = '<option value="">Todos</option>' + entries.map(([k, v]) => `<option value="${esc(k)}">${esc(v)}</option>`).join(''); }

function needsDenuncia(it) { return ['ACCIDENTE_TRABAJO', 'IN_ITINERE', 'ENFERMEDAD_PROFESIONAL'].includes(it.tipo_contingencia) && it.consecuencia !== 'SIN_LESION'; }

function apply() {
  const ini = $('f_ini').value, fin = $('f_fin').value, emp = $('f_emp').value.trim().toLowerCase(), q = $('f_q').value.trim().toLowerCase();
  const b = $('f_bbox').checked ? map.getBounds() : null;
  FILTERED = RAW.filter(it => {
    const d = it.event_datetime.slice(0, 10);
    if (ini && d < ini) return false;
    if (fin && d > fin) return false;
    if ($('f_tipo').value && it.tipo_contingencia !== $('f_tipo').value) return false;
    if ($('f_consec').value && it.consecuencia !== $('f_consec').value) return false;
    if ($('f_cat').value && it.category_code !== $('f_cat').value) return false;
    if ($('f_sev').value && it.severity_code !== $('f_sev').value) return false;
    if ($('f_estado').value && it.estado !== $('f_estado').value) return false;
    if ($('f_pot').value === 'HIPO' && !(it.riesgo_potencial && it.riesgo_potencial.valor >= 15)) return false;
    if (emp && !`${it.company || ''} ${it.companies_all || ''} ${it.faena || ''} ${it.sector || ''}`.toLowerCase().includes(emp)) return false;
    if (q && !`${it.title} ${it.description || ''}`.toLowerCase().includes(q)) return false;
    if (b && !b.contains([+it.lat, +it.lng])) return false;
    return true;
  });
  render();
}

function render() {
  const at = FILTERED.filter(i => i.tipo_contingencia === 'ACCIDENTE_TRABAJO');
  const lti = at.filter(i => LTI.includes(i.consecuencia));
  $('k_total').textContent = FILTERED.length;
  $('k_at').textContent = at.length;
  $('k_lti').textContent = lti.length;
  $('k_dias').textContent = lti.reduce((s, i) => s + (+i.dias_perdidos || 0), 0);
  $('k_hipo').textContent = FILTERED.filter(i => i.riesgo_potencial && i.riesgo_potencial.valor >= 15).length;
  $('k_den').textContent = FILTERED.filter(i => needsDenuncia(i) && !i.art_denunciado).length;

  layer.clearLayers();
  FILTERED.forEach(it => {
    L.circleMarker([+it.lat, +it.lng], { radius: LTI.includes(it.consecuencia) ? 9 : 6, color: '#111827', weight: 1, fillColor: SEV_COLOR[it.severity_code] || '#ccc', fillOpacity: .9 })
      .bindPopup(`<b>${esc(it.title)}</b><br><small>${esc(fmtDT(it.event_datetime))}</small><br>${esc(META.enums.consecuencia[it.consecuencia])}<br><a href="index.php#edit=${it.id}">Abrir</a> · <a target="_blank" href="informe.php?id=${it.id}">Informe</a>`)
      .addTo(layer);
  });

  $('cnt').textContent = FILTERED.length;
  $('tbody').innerHTML = FILTERED.map(it => `<tr>
    <td>${it.id}</td><td class="text-nowrap">${esc(fmtDT(it.event_datetime))}</td><td>${esc(it.title)}</td>
    <td><span class="pill">${esc((META.enums.tipo_contingencia[it.tipo_contingencia] || '').split(' (')[0])}</span></td>
    <td>${esc(META.enums.consecuencia[it.consecuencia])}</td><td class="text-end tabular">${LTI.includes(it.consecuencia) ? it.dias_perdidos : ''}</td>
    <td><span class="status-dot" style="background:${SEV_COLOR[it.severity_code]};border:1px solid #111"></span>${esc(it.severity_label)}</td>
    <td>${riesgoBadge(it.riesgo_potencial)}</td><td>${esc(it.company || '')}</td>
    <td>${esc(META.enums.estado[it.estado])}${needsDenuncia(it) && !it.art_denunciado ? ' <span class="badge text-bg-danger" title="Sin denuncia ART">ART</span>' : ''}</td>
    <td class="text-nowrap"><a class="btn btn-sm btn-outline-primary py-0" href="index.php#edit=${it.id}">Abrir</a></td></tr>`).join('');
}

function csv() {
  const cell = v => { const s = String(v ?? '').replace(/"/g, '""').replace(/\r?\n/g, ' '); return /[";\n]/.test(s) ? `"${s}"` : s; };
  const head = ['id', 'fecha_hora', 'titulo', 'tipo_contingencia', 'consecuencia', 'dias_perdidos', 'categoria', 'severidad', 'riesgo_potencial',
    'empresa', 'empresas_involucradas', 'faena', 'sector', 'turno', 'estado', 'denunciado_art', 'fecha_denuncia_art', 'lat', 'lng', 'descripcion'];
  const rows = FILTERED.map(it => [it.id, it.event_datetime, it.title, it.tipo_contingencia, it.consecuencia, it.dias_perdidos, it.category_label,
    it.severity_label, it.riesgo_potencial ? it.riesgo_potencial.valor : '', it.company, it.companies_all, it.faena, it.sector, it.turno, it.estado,
    it.art_denunciado ? 'SI' : 'NO', it.art_fecha_denuncia, it.lat, it.lng, it.description]);
  // Separador ; y BOM para que Excel en español lo abra bien
  const txt = '﻿' + [head, ...rows].map(r => r.map(cell).join(';')).join('\n');
  const a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([txt], { type: 'text/csv;charset=utf-8' }));
  a.download = `incidentes_${new Date().toISOString().slice(0, 10)}.csv`; a.click();
}

['f_ini', 'f_fin', 'f_tipo', 'f_consec', 'f_cat', 'f_sev', 'f_estado', 'f_pot', 'f_bbox'].forEach(id => $(id).addEventListener('change', apply));
['f_emp', 'f_q'].forEach(id => $(id).addEventListener('input', apply));
map.on('moveend', () => { if ($('f_bbox').checked) apply(); });
$('btnClear').onclick = () => { document.querySelectorAll('input,select').forEach(el => el.type === 'checkbox' ? el.checked = false : el.value = ''); apply(); };
$('btnCSV').onclick = csv;

(async () => {
  try {
    META = await api('meta.php');
    opts('f_tipo', Object.entries(META.enums.tipo_contingencia));
    opts('f_consec', Object.entries(META.enums.consecuencia));
    opts('f_estado', Object.entries(META.enums.estado));
    opts('f_cat', META.categories.map(c => [c.code, c.label]));
    opts('f_sev', META.severities.map(s => [s.code, s.label]));
    const d = new Date(); d.setFullYear(d.getFullYear() - 1);
    $('f_ini').value = d.toISOString().slice(0, 10);
    RAW = (await api('incidents.php')).data;
    apply();
    if (FILTERED.length) map.fitBounds(L.latLngBounds(FILTERED.map(i => [+i.lat, +i.lng])).extend(SJ), { padding: [20, 20] });
  } catch (e) { toast(e.message, 'danger'); }
})();
</script>
</body></html>
