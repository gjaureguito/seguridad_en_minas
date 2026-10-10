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
  <div class="d-flex d-lg-none gap-2 mb-2">
    <button class="btn btn-outline-primary flex-grow-1" data-bs-toggle="offcanvas" data-bs-target="#filtros"><i class="bi bi-funnel"></i> Filtros <span id="fCount" class="badge text-bg-warning ms-1 d-none"></span></button>
    <button class="btn btn-outline-primary" onclick="document.getElementById('btnKML').click()" title="Google Earth"><i class="bi bi-globe-americas"></i></button>
  </div>
  <div class="row g-3">
    <div class="col-12 col-lg-3">
      <div class="offcanvas-lg offcanvas-start" tabindex="-1" id="filtros" aria-labelledby="filtrosTit">
      <div class="offcanvas-header d-lg-none"><h2 class="section-title m-0" id="filtrosTit">Filtros</h2><button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#filtros" aria-label="Cerrar"></button></div>
      <div class="offcanvas-body d-block p-3 p-lg-0">
      <div class="cardish">
        <div class="section-title d-none d-lg-block">Filtros</div>
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
          <button id="btnCSV" class="btn btn-outline-success btn-sm ms-auto"><i class="bi bi-filetype-csv"></i> CSV</button>
          <button id="btnKML" class="btn btn-outline-primary btn-sm" title="Abrir en Google Earth"><i class="bi bi-globe-americas"></i> Google Earth</button>
        </div>
        <button class="btn btn-primary w-100 mt-3 d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#filtros">Ver <span id="fRes">0</span> registros</button>
      </div>
      </div></div>
    </div>

    <div class="col-12 col-lg-9">
      <div class="row g-2 mb-2">
        <?php foreach ([['k_total','Registros','k-info'],['k_at','Accidentes de trabajo','k-acento'],['k_lti','Con baja','k-alerta'],['k_dias','Días perdidos','k-alerta'],['k_hipo','Alto potencial','k-acento'],['k_den','Sin denunciar a la ART','k-alerta']] as [$id,$l,$k]): ?>
        <div class="col-4 col-md-4 col-xl-2"><div class="kpi <?= $k ?>"><div class="lbl"><?= $l ?></div><div class="val" id="<?= $id ?>">—</div></div></div>
        <?php endforeach; ?>
      </div>
      <div id="map" class="cardish p-0 mb-2" style="height:clamp(280px,44vh,520px)"></div>
      <div class="cardish">
        <div class="d-flex justify-content-between mb-2"><div class="section-title m-0">Registros filtrados</div><div class="small-muted"><span id="cnt">0</span> filas</div></div>
        <div id="cards" class="d-md-none"></div>
        <div class="table-responsive d-none d-md-block" style="max-height:48vh">
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
addBaseLayers(map);
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
      .bindPopup(`<b>${esc(it.title)}</b><br><small>${esc(fmtDT(it.event_datetime))}</small><br>${esc(META.enums.consecuencia[it.consecuencia])}<br><a href="index.php#edit=${it.id}">Abrir</a> · <a target="_blank" href="informe.php?id=${it.id}">Informe</a> · <a target="_blank" rel="noopener" href="${earthUrl(it.lat, it.lng)}">Google Earth</a>`)
      .addTo(layer);
  });

  $('cnt').textContent = FILTERED.length;
  $('fRes').textContent = FILTERED.length;
  const nf = ['f_fin','f_tipo','f_consec','f_cat','f_sev','f_estado','f_pot','f_emp','f_q'].filter(id => $(id).value).length + ($('f_bbox').checked ? 1 : 0);
  $('fCount').textContent = nf; $('fCount').classList.toggle('d-none', !nf);
  $('cards').innerHTML = FILTERED.slice(0, 200).map(it => {
    const lti = LTI.includes(it.consecuencia), den = needsDenuncia(it) && !it.art_denunciado;
    return `<a href="index.php#edit=${it.id}" class="reg-card d-block text-reset text-decoration-none ${lti ? 'lti' : ''}">
      <div class="d-flex justify-content-between gap-2"><span class="t">${esc(it.title)}</span><span class="small-muted text-nowrap">#${it.id}</span></div>
      <div class="m">${esc(fmtDT(it.event_datetime))} · ${esc(it.company || 'Sin empresa')}</div>
      <div class="mt-1"><span class="status-dot" style="background:${SEV_COLOR[it.severity_code]};border:1px solid #17212B"></span><span class="pill">${esc(META.enums.consecuencia[it.consecuencia])}${lti ? ' · ' + it.dias_perdidos + ' d' : ''}</span>
        <span class="pill">${esc(META.enums.estado[it.estado])}</span>${den ? '<span class="badge text-bg-danger">Sin denuncia ART</span>' : ''}</div></a>`;
  }).join('') + (FILTERED.length > 200 ? `<div class="small-muted text-center">Mostrando 200 de ${FILTERED.length}. Usá los filtros para acotar.</div>` : '')
  || '<div class="small-muted p-3 text-center">Ningún registro coincide con los filtros.</div>';
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
$('btnKML').onclick = () => {
  if (!FILTERED.length) return toast('No hay registros filtrados para exportar', 'danger');
  const a = document.createElement('a');
  a.href = URL.createObjectURL(new Blob([toKML(FILTERED, META)], { type: 'application/vnd.google-earth.kml+xml' }));
  a.download = `incidentes_${new Date().toISOString().slice(0, 10)}.kml`; a.click();
  toast(`KML con ${FILTERED.length} registros. Abrilo en Google Earth: Proyectos → Abrir → Importar archivo KML.`);
};

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
    if (FILTERED.length) map.fitBounds(L.latLngBounds(FILTERED.map(i => [+i.lat, +i.lng])), { padding: [30, 30], maxZoom: 15 });
  } catch (e) { toast(e.message, 'danger'); }
})();
</script>
</body></html>
