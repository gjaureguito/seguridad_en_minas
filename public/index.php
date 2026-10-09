<?php
declare(strict_types=1);
/* index.php — Carga y edición de incidentes (mapa Leaflet + formulario normativo por pestañas) */
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/layout.php';
$user = require_login_page();
$readonly = $user['role'] === 'LECTOR';
page_head('Cargar incidente', [
  '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">',
  '<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">',
  '<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">',
]);
?>
<body>
<?php page_nav($user, 'index.php'); ?>
<style>
  .work{display:flex;height:calc(100vh - 57px)}
  .panel{width:540px;max-width:100%;overflow:auto;padding:14px;border-right:1px solid var(--ui-border);background:var(--ui-bg)}
  #map{flex:1;min-height:320px}
  @media (max-width: 992px){.work{flex-direction:column;height:auto}.panel{width:100%;border-right:0}#map{height:55vh;flex:none}}
  .nav-tabs .nav-link{font-size:.8rem;padding:.4rem .6rem}
  .photo-tip img{display:block;width:160px;height:110px;object-fit:cover;border-radius:8px}
  .leaflet-tooltip.photo-tip{background:#fff;border:1px solid var(--ui-border);padding:4px;border-radius:10px}
  .list-editor li{border:1px solid var(--ui-border);border-radius:10px;padding:6px 8px;margin-bottom:6px;background:#fff;list-style:none}
  .list-editor{padding-left:0}
  #legendBox{position:absolute;right:12px;bottom:56px;z-index:1000;background:#fff;border:1px solid var(--ui-border);border-radius:12px;padding:8px 10px;width:250px;max-height:50vh;overflow:auto;display:none;font-size:.78rem}
  #legendBtn{position:absolute;right:12px;bottom:12px;z-index:1001;width:40px;height:40px;border-radius:12px;background:#fff;border:1px solid var(--ui-border)}
  .mapwrap{position:relative;flex:1;display:flex}
</style>

<div class="work">
  <div class="panel">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <div>
        <div class="fw-bold" id="formTitle">Nuevo registro</div>
        <div class="small-muted">Clic en el mapa para fijar la ubicación. Clic en un pin para editarlo.</div>
      </div>
      <a id="lnkInforme" class="btn btn-outline-secondary btn-sm d-none" target="_blank"><i class="bi bi-printer"></i> Informe</a>
    </div>

    <div class="cardish py-2">
      <div class="d-flex align-items-center gap-2 small">
        <span class="small-muted">Lat</span><span id="lat" class="tabular">—</span>
        <span class="small-muted ms-2">Lng</span><span id="lng" class="tabular">—</span>
        <button id="btnUseCenter" class="btn btn-outline-secondary btn-sm ms-auto" type="button"><i class="bi bi-crosshair"></i> Centro</button>
      </div>
      <div class="input-group input-group-sm mt-2">
        <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
        <input id="coordsPaste" class="form-control" placeholder="-31.544893, -68.567611">
        <button id="btnSetCoords" class="btn btn-outline-primary" type="button">Aplicar</button>
      </div>
    </div>

    <form id="frm" autocomplete="off">
      <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#t-gen" type="button">1. General</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t-les" type="button">2. Lesión</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t-den" type="button">3. Denuncia</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t-inv" type="button">4. Investigación</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#t-acc" type="button">5. Acciones <span id="accCount" class="badge text-bg-light border">0</span></button></li>
      </ul>

      <div class="tab-content cardish" style="border-top-left-radius:0">
        <!-- ============ 1. GENERAL ============ -->
        <div class="tab-pane fade show active" id="t-gen">
          <div class="mb-2"><label class="form-label">Título *</label><input id="title" class="form-control" required maxlength="200"></div>
          <div class="mb-2"><label class="form-label">Descripción (qué, cómo, dónde)</label><textarea id="description" class="form-control" rows="3"></textarea></div>
          <div class="row g-2">
            <div class="col-6"><label class="form-label">Fecha y hora *</label><input id="event_datetime" type="datetime-local" class="form-control" required></div>
            <div class="col-6"><label class="form-label">Turno</label>
              <select id="turno" class="form-select"><option value="">—</option><option>DIA</option><option>TARDE</option><option>NOCHE</option><option>ROTATIVO</option></select></div>
            <div class="col-6"><label class="form-label">Faena / proyecto</label><input id="faena" class="form-control" list="dlFaena"></div>
            <div class="col-6"><label class="form-label">Sector / labor</label><input id="sector" class="form-control" placeholder="Rajo, planta, taller…"></div>
            <div class="col-12"><label class="form-label">Tipo de contingencia (Ley 24.557) *</label><select id="tipo_contingencia" class="form-select"></select></div>
            <div class="col-6"><label class="form-label">Categoría *</label><select id="category" class="form-select" required></select></div>
            <div class="col-6"><label class="form-label">Severidad real *</label><select id="severity" class="form-select" required></select></div>
            <div class="col-6"><label class="form-label">Empresa empleadora / principal</label><select id="company_id" class="form-select"></select></div>
            <div class="col-6"><label class="form-label">Estado</label><select id="estado" class="form-select"></select></div>
          </div>
          <div class="mt-2 d-flex align-items-center gap-2"><span class="form-label m-0">Ícono:</span><div id="iconPreview"></div></div>

          <hr>
          <div class="section-title">Empresas involucradas</div>
          <div class="row g-2">
            <div class="col-7"><select id="companies" class="form-select form-select-sm"></select></div>
            <div class="col-3"><select id="company_role" class="form-select form-select-sm">
              <option value="RESPONSABLE">Responsable</option><option value="PROPIETARIA">Titular</option><option value="AFECTADA">Afectada</option>
              <option value="REPORTANTE">Reportante</option><option value="SUPERVISORA">Supervisora</option></select></div>
            <div class="col-2 d-grid"><button type="button" id="btnAddCompany" class="btn btn-outline-primary btn-sm">+</button></div>
          </div>
          <ul id="companiesUI" class="list-editor small mt-2"></ul>

          <div class="section-title mt-3">Personas involucradas</div>
          <div class="row g-2">
            <div class="col-6"><input id="p_name" class="form-control form-control-sm" placeholder="Nombre y apellido" list="dlPersons"></div>
            <div class="col-6"><input id="p_company" class="form-control form-control-sm" placeholder="Empresa" list="dlCompanies"></div>
            <div class="col-5"><input id="p_puesto" class="form-control form-control-sm" placeholder="Puesto (ej. operador de camión)"></div>
            <div class="col-5"><select id="p_role" class="form-select form-select-sm">
              <option value="lesionado">Lesionado / afectado</option><option value="testigo">Testigo</option><option value="supervisor">Supervisor</option>
              <option value="operador">Operador</option><option value="conductor">Conductor</option><option value="investigador">Investigador</option></select></div>
            <div class="col-2 d-grid"><button type="button" id="btnAddPerson" class="btn btn-outline-primary btn-sm">+</button></div>
          </div>
          <ul id="personsUI" class="list-editor small mt-2"></ul>

          <div class="section-title mt-3">Fotos</div>
          <input id="photos" type="file" accept="image/jpeg,image/png,image/webp" class="form-control form-control-sm" multiple>
          <div id="photosExisting" class="d-flex gap-2 mt-2 flex-wrap"></div>
          <div id="photosNew" class="d-flex gap-2 mt-2 flex-wrap"></div>
        </div>

        <!-- ============ 2. LESIÓN Y TIPIFICACIÓN ============ -->
        <div class="tab-pane fade" id="t-les">
          <div class="alert alert-light border alert-normativa">
            Tipificación con los grupos de la OIT, que coinciden con los títulos de las tablas del RENAL (Res. SRT 3326/14). Los días perdidos se cuentan como días corridos de baja, sin contar el día del accidente ni el día de reintegro.
          </div>
          <div class="row g-2">
            <div class="col-12"><label class="form-label">Consecuencia real</label><select id="consecuencia" class="form-select"></select></div>
            <div class="col-4 lti-only"><label class="form-label">Inicio de baja</label><input id="fecha_inicio_baja" type="date" class="form-control"></div>
            <div class="col-4 lti-only"><label class="form-label">Alta / reintegro</label><input id="fecha_alta" type="date" class="form-control"></div>
            <div class="col-4 lti-only"><label class="form-label">Días perdidos</label><input id="dias_perdidos" type="number" min="0" class="form-control"></div>
            <div class="col-12"><label class="form-label">Forma del accidente</label><select id="forma_code" class="form-select"></select></div>
            <div class="col-12"><label class="form-label">Agente material</label><select id="agente_code" class="form-select"></select></div>
            <div class="col-6"><label class="form-label">Naturaleza de la lesión</label><select id="naturaleza_code" class="form-select"></select></div>
            <div class="col-6"><label class="form-label">Zona del cuerpo</label><select id="zona_code" class="form-select"></select></div>
            <div class="col-12"><label class="form-label">Riesgo crítico asociado</label><select id="riesgo_critico_code" class="form-select"></select></div>
          </div>
          <div class="section-title mt-3">Severidad potencial (peor resultado creíble)</div>
          <div class="small-muted mb-2">Marcá probabilidad × consecuencia. Un valor de 15 o más es un evento de <b>alto potencial</b> y conviene investigarlo a fondo aunque no haya habido lesión.</div>
          <div class="d-flex gap-3 align-items-start">
            <table class="matrix" id="matrix"></table>
            <div class="small">
              <div>Resultado: <span id="riesgoOut">—</span></div>
              <button type="button" class="btn btn-link btn-sm p-0" id="btnClearMatrix">limpiar</button>
            </div>
          </div>
        </div>

        <!-- ============ 3. DENUNCIA ============ -->
        <div class="tab-pane fade" id="t-den">
          <div class="alert alert-light border alert-normativa">
            <b>Res. SRT 525/15:</b> el empleador denuncia a la ART los accidentes de trabajo, in itinere y enfermedades profesionales, con o sin baja, <b>dentro de las 48 h</b>, y entrega una copia al trabajador. La ART lo informa al RENAL (Res. SRT 3326/14).
          </div>
          <div id="plazoBox" class="mb-2"></div>
          <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="art_denunciado"><label class="form-check-label" for="art_denunciado">Denunciado a la ART</label></div>
          <div class="row g-2">
            <div class="col-6"><label class="form-label">ART</label><input id="art_nombre" class="form-control" list="dlArt"></div>
            <div class="col-6"><label class="form-label">N.º de siniestro</label><input id="art_nro_siniestro" class="form-control"></div>
            <div class="col-6"><label class="form-label">Fecha y hora de denuncia</label><input id="art_fecha_denuncia" type="datetime-local" class="form-control"></div>
          </div>
          <hr>
          <div class="form-check mb-2"><input class="form-check-input" type="checkbox" id="autoridad_notificada"><label class="form-check-label" for="autoridad_notificada">Comunicado a la autoridad minera / de trabajo provincial</label></div>
          <div class="row g-2"><div class="col-6"><label class="form-label">Fecha de comunicación</label><input id="autoridad_fecha" type="datetime-local" class="form-control"></div></div>
          <div class="small-muted mt-2">El Dec. 249/07 no fija un plazo para comunicar accidentes a la autoridad; seguí el procedimiento de la autoridad provincial y de tu ART.</div>
        </div>

        <!-- ============ 4. INVESTIGACIÓN ============ -->
        <div class="tab-pane fade" id="t-inv">
          <div class="alert alert-light border alert-normativa">
            Según el Dec. 249/07 (art. 13 o), el Servicio de Higiene y Seguridad investiga los accidentes y el Comité participa (art. 26 g). Se usa el modelo de causalidad de Bird/ILCI: <i>causas inmediatas</i> (actos y condiciones) → <i>causas básicas</i> (factores personales y del trabajo) → <i>falta de control</i> del sistema de gestión.
          </div>
          <div class="row g-2">
            <div class="col-6"><label class="form-label">Método</label><select id="inv_metodo" class="form-select"></select></div>
            <div class="col-3"><label class="form-label">Inicio</label><input id="inv_fecha_inicio" type="date" class="form-control"></div>
            <div class="col-3"><label class="form-label">Cierre</label><input id="inv_fecha_cierre" type="date" class="form-control"></div>
            <div class="col-12"><label class="form-label">Equipo investigador</label><input id="inv_equipo" class="form-control" placeholder="Supervisor, técnico HyS, representante del Comité…"></div>
            <div class="col-12"><label class="form-label">Hechos (lista objetiva, sin juicios: base del árbol de causas)</label><textarea id="inv_hechos" class="form-control" rows="3"></textarea></div>
            <div class="col-6"><label class="form-label">Causas inmediatas: actos subestándar</label><textarea id="inv_actos" class="form-control" rows="2"></textarea></div>
            <div class="col-6"><label class="form-label">Causas inmediatas: condiciones subestándar</label><textarea id="inv_condiciones" class="form-control" rows="2"></textarea></div>
            <div class="col-6"><label class="form-label">Causas básicas: factores personales</label><textarea id="inv_fpers" class="form-control" rows="2"></textarea></div>
            <div class="col-6"><label class="form-label">Causas básicas: factores del trabajo</label><textarea id="inv_ftrab" class="form-control" rows="2"></textarea></div>
            <div class="col-12"><label class="form-label">Falta de control (programa, estándares, cumplimiento)</label><textarea id="inv_fcontrol" class="form-control" rows="2"></textarea></div>
          </div>
          <div class="section-title mt-3">Cadena de “¿por qué?”</div>
          <ol id="porquesUI" class="small ps-3 mb-1"></ol>
          <div class="input-group input-group-sm"><input id="porqueTxt" class="form-control" placeholder="¿Por qué ocurrió…?"><button type="button" id="btnAddPorque" class="btn btn-outline-primary">Agregar</button></div>
          <div class="mt-2"><label class="form-label">Conclusiones</label><textarea id="inv_conclusiones" class="form-control" rows="2"></textarea></div>
        </div>

        <!-- ============ 5. ACCIONES ============ -->
        <div class="tab-pane fade" id="t-acc">
          <div class="small-muted mb-2">Priorizá controles altos en la jerarquía (eliminación → EPP). Cada acción necesita un responsable y una fecha. Se cierra con la verificación de eficacia.</div>
          <div class="row g-2">
            <div class="col-12"><textarea id="a_desc" class="form-control form-control-sm" rows="2" placeholder="Descripción de la acción"></textarea></div>
            <div class="col-4"><select id="a_tipo" class="form-select form-select-sm"></select></div>
            <div class="col-8"><select id="a_jer" class="form-select form-select-sm"></select></div>
            <div class="col-6"><input id="a_resp" class="form-control form-control-sm" placeholder="Responsable"></div>
            <div class="col-4"><input id="a_fecha" type="date" class="form-control form-control-sm"></div>
            <div class="col-2 d-grid"><button type="button" id="btnAddAction" class="btn btn-outline-primary btn-sm">+</button></div>
          </div>
          <ul id="actionsUI" class="list-editor small mt-2"></ul>
        </div>
      </div>

      <?php if (!$readonly): ?>
      <div class="d-grid gap-2">
        <button class="btn btn-primary" type="submit" id="btnSave"><i class="bi bi-save"></i> Guardar</button>
        <button id="btnCancel" class="btn btn-outline-secondary" type="button">Nuevo / cancelar edición</button>
      </div>
      <?php endif; ?>
    </form>
  </div>

  <div class="mapwrap">
    <div id="map"></div>
    <button id="legendBtn" title="Leyenda"><i class="bi bi-layers"></i></button>
    <div id="legendBox"><div class="fw-bold mb-1">Severidad</div><div id="legSev"></div><div class="fw-bold mt-2 mb-1">Categorías</div><div id="legCat"></div></div>
  </div>
</div>

<datalist id="dlPersons"></datalist><datalist id="dlCompanies"></datalist><datalist id="dlFaena"></datalist>
<datalist id="dlArt"><option>Prevención ART</option><option>Galeno ART</option><option>Provincia ART</option><option>Experta ART</option><option>Federación Patronal</option><option>La Segunda ART</option><option>Swiss Medical ART</option><option>Asociart</option></datalist>

<div class="modal fade" id="photoModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content border-0">
  <div class="modal-body p-0 text-center"><img id="photoBig" style="max-width:100%;max-height:80vh"></div></div></div></div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<?php page_scripts(); ?>
<script>
const READONLY = <?= $readonly ? 'true' : 'false' ?>;
const $ = id => document.getElementById(id);
let META = null, editingId = null, pickMarker = null;
const companiesChosen = [], personsChosen = [], porques = [], actions = [], photosToDelete = new Set();
let matrixSel = { p: null, c: null };

/* ---------------- Mapa ---------------- */
const SJ_BOUNDS = L.latLngBounds([[-32.3, -69.9], [-29.0, -66.5]]);
const map = L.map('map');
L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);
map.fitBounds(SJ_BOUNDS, { padding: [20, 20] });
const cluster = L.markerClusterGroup({ maxClusterRadius: 50, showCoverageOnHover: false, disableClusteringAtZoom: 17 }).addTo(map);

function pinSize() { const z = map.getZoom(); return z <= 9 ? 11 : z >= 16 ? 18 : Math.round(11 + (z - 9) * 1); }
function iconFor(catCode, sevCode) {
  const div = document.createElement('div');
  div.className = 'sev-pin'; div.style.background = SEV_COLOR[sevCode] || '#e5e7eb';
  div.textContent = (catCode || '•').charAt(0);
  const s = pinSize();
  return L.divIcon({ html: div, className: '', iconSize: [s, s], iconAnchor: [s / 2, s / 2] });
}
function codeOf(sel) { return sel.selectedOptions[0]?.dataset.code || ''; }
function renderIconPreview() {
  const cat = codeOf($('category')), sev = codeOf($('severity'));
  $('iconPreview').innerHTML = '';
  $('iconPreview').appendChild(iconFor(cat, sev).options.html.cloneNode(true));
  if (pickMarker) pickMarker.setIcon(iconFor(cat, sev));
}
function setPicked(ll) {
  $('lat').textContent = ll.lat.toFixed(6); $('lng').textContent = ll.lng.toFixed(6);
  const ic = iconFor(codeOf($('category')), codeOf($('severity')));
  if (!pickMarker) { pickMarker = L.marker(ll, { draggable: true, icon: ic, zIndexOffset: 1000 }).addTo(map); pickMarker.on('dragend', e => setPicked(e.target.getLatLng())); }
  else { pickMarker.setLatLng(ll); pickMarker.setIcon(ic); }
}
map.on('click', e => { if (!READONLY) setPicked(e.latlng); });
$('btnUseCenter').onclick = () => setPicked(map.getCenter());
$('btnSetCoords').onclick = () => {
  const m = $('coordsPaste').value.trim().match(/^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/);
  if (!m) return toast('Formato inválido. Ej: -31.544893, -68.567611', 'danger');
  setPicked({ lat: +m[1], lng: +m[2] }); map.setView([+m[1], +m[2]], 14);
};
$('legendBtn').onclick = () => { const b = $('legendBox'); b.style.display = b.style.display === 'block' ? 'none' : 'block'; };

/* ---------------- Combos ---------------- */
function fillSelect(id, entries, { blank = null } = {}) {
  const el = $(id);
  el.innerHTML = (blank !== null ? `<option value="">${esc(blank)}</option>` : '') + entries.map(([v, l, code]) => `<option value="${esc(v)}"${code ? ` data-code="${esc(code)}"` : ''}>${esc(l)}</option>`).join('');
}
const catEntries = kind => (META.catalogs[kind] || []).map(c => [c.code, (c.code.includes('.') ? ' ' : '') + c.code + ' · ' + c.label]);

async function loadMeta() {
  META = await api('meta.php');
  fillSelect('category', META.categories.map(c => [c.id, c.label, c.code]));
  fillSelect('severity', META.severities.map(s => [s.id, s.label, s.code]));
  fillSelect('tipo_contingencia', Object.entries(META.enums.tipo_contingencia));
  fillSelect('consecuencia', Object.entries(META.enums.consecuencia));
  fillSelect('estado', Object.entries(META.enums.estado));
  fillSelect('inv_metodo', Object.entries(META.enums.metodo));
  fillSelect('a_tipo', Object.entries(META.enums.tipo_accion));
  fillSelect('a_jer', Object.entries(META.enums.jerarquia));
  $('a_jer').value = 'INGENIERIA';
  fillSelect('forma_code', catEntries('FORMA'), { blank: '—' });
  fillSelect('agente_code', catEntries('AGENTE'), { blank: '—' });
  fillSelect('naturaleza_code', catEntries('NATURALEZA'), { blank: '—' });
  fillSelect('zona_code', catEntries('ZONA'), { blank: '—' });
  fillSelect('riesgo_critico_code', (META.catalogs.RIESGO_CRITICO || []).map(c => [c.code, c.label]), { blank: '—' });
  $('legSev').innerHTML = META.severities.map(s => `<div><span class="status-dot" style="background:${SEV_COLOR[s.code] || '#ccc'};border:1px solid #111"></span>${esc(s.label)}</div>`).join('');
  $('legCat').innerHTML = META.categories.map(c => `<div><b>${esc(c.code.charAt(0))}</b> · ${esc(c.label)}</div>`).join('');
  ['category', 'severity'].forEach(id => $(id).addEventListener('change', renderIconPreview));
  $('tipo_contingencia').addEventListener('change', syncLesionUI);
  $('consecuencia').addEventListener('change', syncLesionUI);
  renderIconPreview(); syncLesionUI();
}
async function loadCompanies() {
  const { data } = await api('companies.php');
  fillSelect('company_id', data.map(c => [c.id, `${c.nombre} (${c.tipo})`]), { blank: '— sin especificar —' });
  fillSelect('companies', data.map(c => [c.id, `${c.nombre} (${c.tipo})`]));
  $('dlCompanies').innerHTML = data.map(c => `<option value="${esc(c.nombre)}">`).join('');
}
$('p_name').addEventListener('input', async e => {
  const q = e.target.value.trim(); if (q.length < 2) return;
  try { const { data } = await api('persons.php?q=' + encodeURIComponent(q)); $('dlPersons').innerHTML = data.map(p => `<option value="${esc(p.full_name)}">${esc(p.company)}</option>`).join(''); } catch (_) {}
});

/* ---------------- Lesión: visibilidad y cálculo de días ---------------- */
const LTI = ['CON_BAJA', 'INCAPACIDAD_PERMANENTE', 'FATAL'];
function syncLesionUI() {
  const lti = LTI.includes($('consecuencia').value);
  document.querySelectorAll('.lti-only').forEach(el => el.style.display = lti ? '' : 'none');
  updatePlazo();
}
function calcDias() {
  const a = $('fecha_inicio_baja').value, b = $('fecha_alta').value;
  if (a && b) { const d = Math.round((new Date(b) - new Date(a)) / 86400000); if (d >= 0) $('dias_perdidos').value = d; }
}
$('fecha_inicio_baja').addEventListener('change', calcDias);
$('fecha_alta').addEventListener('change', calcDias);

function updatePlazo() {
  const tipo = $('tipo_contingencia').value, consec = $('consecuencia').value;
  const box = $('plazoBox');
  const req = ['ACCIDENTE_TRABAJO', 'IN_ITINERE', 'ENFERMEDAD_PROFESIONAL'].includes(tipo) && consec !== 'SIN_LESION';
  if (!req) { box.innerHTML = '<span class="badge text-bg-light border">Este registro no requiere denuncia a la ART</span>'; return; }
  const ev = new Date($('event_datetime').value);
  const lim = new Date(ev.getTime() + 48 * 3600000);
  if ($('art_denunciado').checked && $('art_fecha_denuncia').value) {
    const d = new Date($('art_fecha_denuncia').value);
    box.innerHTML = d <= lim ? '<span class="badge text-bg-success">Denunciado en plazo (≤ 48 h)</span>' : '<span class="badge text-bg-warning">Denunciado fuera de plazo (> 48 h)</span>';
  } else {
    const hrs = Math.round((lim - new Date()) / 3600000);
    box.innerHTML = hrs >= 0 ? `<span class="badge text-bg-warning">Requiere denuncia: vence ${esc(lim.toLocaleString('es-AR'))} (${hrs} h)</span>`
                             : `<span class="badge text-bg-danger">Requiere denuncia: plazo vencido hace ${-hrs} h</span>`;
  }
}
['event_datetime', 'art_fecha_denuncia', 'art_denunciado'].forEach(id => $(id).addEventListener('change', updatePlazo));

/* ---------------- Matriz 5x5 ---------------- */
const PROB = ['Raro', 'Improbable', 'Posible', 'Probable', 'Casi seguro'];
const CONS = ['Insignif.', 'Menor', 'Moderada', 'Mayor', 'Catastróf.'];
function nivel(v) { return v >= 15 ? 'intolerable' : v >= 10 ? 'alto' : v >= 5 ? 'moderado' : 'bajo'; }
function renderMatrix() {
  let html = '<tr><td></td>' + CONS.map(c => `<td style="font-size:.6rem;font-weight:600;cursor:default">${c}</td>`).join('') + '</tr>';
  for (let p = 5; p >= 1; p--) {
    html += `<tr><td style="font-size:.6rem;font-weight:600;cursor:default;width:62px;text-align:right;padding-right:4px">${PROB[p - 1]}</td>`;
    for (let c = 1; c <= 5; c++) {
      const v = p * c, sel = matrixSel.p === p && matrixSel.c === c;
      html += `<td class="m-${nivel(v)}${sel ? ' sel' : ''}" data-p="${p}" data-c="${c}">${v}</td>`;
    }
    html += '</tr>';
  }
  $('matrix').innerHTML = html;
  $('riesgoOut').innerHTML = matrixSel.p ? riesgoBadge({ valor: matrixSel.p * matrixSel.c, nivel: nivel(matrixSel.p * matrixSel.c).toUpperCase() }) : '—';
}
$('matrix').addEventListener('click', e => { const td = e.target.closest('td[data-p]'); if (!td) return; matrixSel = { p: +td.dataset.p, c: +td.dataset.c }; renderMatrix(); });
$('btnClearMatrix').onclick = () => { matrixSel = { p: null, c: null }; renderMatrix(); };

/* ---------------- Listas: empresas, personas, porqués, acciones ---------------- */
function rmBtn(fn) { return READONLY ? '' : `<button type="button" class="btn btn-link btn-sm text-danger p-0 ms-2" onclick="${fn}"><i class="bi bi-x-circle"></i></button>`; }
function renderCompanies() {
  $('companiesUI').innerHTML = companiesChosen.map((c, i) => `<li>${esc(c.name)} — <b>${esc(c.role)}</b>${rmBtn(`companiesChosen.splice(${i},1);renderCompanies()`)}</li>`).join('');
}
$('btnAddCompany').onclick = () => {
  const sel = $('companies'), opt = sel.selectedOptions[0]; if (!opt) return;
  const id = +opt.value, role = $('company_role').value;
  if (!companiesChosen.some(x => x.id === id && x.role === role)) companiesChosen.push({ id, name: opt.textContent, role });
  renderCompanies();
};
function renderPersons() {
  $('personsUI').innerHTML = personsChosen.map((p, i) => `<li><b>${esc(p.full_name)}</b>${p.company ? ' · ' + esc(p.company) : ''}${p.puesto ? ' · ' + esc(p.puesto) : ''} — <span class="pill">${esc(p.role || '—')}</span>${rmBtn(`personsChosen.splice(${i},1);renderPersons()`)}</li>`).join('');
}
$('btnAddPerson').onclick = () => {
  const name = $('p_name').value.trim(); if (!name) return toast('Nombre requerido', 'danger');
  personsChosen.push({ full_name: name, company: $('p_company').value.trim() || null, puesto: $('p_puesto').value.trim() || null, role: $('p_role').value });
  ['p_name', 'p_company', 'p_puesto'].forEach(id => $(id).value = '');
  renderPersons();
};
function renderPorques() {
  $('porquesUI').innerHTML = porques.map((t, i) => `<li>${esc(t)}${rmBtn(`porques.splice(${i},1);renderPorques()`)}</li>`).join('');
}
$('btnAddPorque').onclick = () => { const t = $('porqueTxt').value.trim(); if (!t) return; porques.push(t); $('porqueTxt').value = ''; renderPorques(); };

function renderActions() {
  $('accCount').textContent = actions.length;
  const today = new Date().toISOString().slice(0, 10);
  $('actionsUI').innerHTML = actions.map((a, i) => {
    const venc = ['PENDIENTE', 'EN_CURSO'].includes(a.estado) && a.fecha_compromiso && a.fecha_compromiso < today;
    const estados = Object.entries(META.enums.estado_accion).map(([k, v]) => `<option value="${k}"${k === a.estado ? ' selected' : ''}>${esc(v)}</option>`).join('');
    return `<li>
      <div class="d-flex"><div class="flex-grow-1"><b>${esc(a.descripcion)}</b></div>${rmBtn(`actions.splice(${i},1);renderActions()`)}</div>
      <div class="mt-1"><span class="pill">${esc(META.enums.tipo_accion[a.tipo])}</span><span class="pill">${esc(META.enums.jerarquia[a.jerarquia])}</span>
        ${a.responsable ? `<span class="pill"><i class="bi bi-person"></i> ${esc(a.responsable)}</span>` : ''}
        ${a.fecha_compromiso ? `<span class="pill ${venc ? 'text-danger fw-bold' : ''}"><i class="bi bi-calendar"></i> ${esc(fmtD(a.fecha_compromiso))}${venc ? ' · vencida' : ''}</span>` : ''}</div>
      <div class="row g-1 mt-1"><div class="col-5"><select class="form-select form-select-sm" onchange="actions[${i}].estado=this.value;renderActions()">${estados}</select></div>
        <div class="col-7"><input class="form-control form-control-sm" placeholder="Verificación de eficacia" value="${esc(a.verificacion || '')}" onchange="actions[${i}].verificacion=this.value"></div></div>
    </li>`;
  }).join('');
}
$('btnAddAction').onclick = () => {
  const d = $('a_desc').value.trim(); if (!d) return toast('Describí la acción', 'danger');
  if (!$('a_resp').value.trim() || !$('a_fecha').value) return toast('Indicá responsable y fecha de compromiso', 'danger');
  actions.push({ descripcion: d, tipo: $('a_tipo').value, jerarquia: $('a_jer').value, responsable: $('a_resp').value.trim(), fecha_compromiso: $('a_fecha').value, estado: 'PENDIENTE' });
  ['a_desc', 'a_resp', 'a_fecha'].forEach(id => $(id).value = '');
  renderActions();
};

/* ---------------- Fotos ---------------- */
function showPhoto(url) { $('photoBig').src = url; new bootstrap.Modal($('photoModal')).show(); }
$('photos').addEventListener('change', e => {
  $('photosNew').innerHTML = '';
  [...e.target.files].forEach(f => { const u = URL.createObjectURL(f); const img = document.createElement('img'); img.src = u; img.className = 'thumb'; img.onclick = () => showPhoto(u); $('photosNew').appendChild(img); });
});
function renderExistingPhotos(list) {
  $('photosExisting').innerHTML = list.map(p => `<div class="position-relative">
    <img src="${esc(p.url)}" class="thumb ${photosToDelete.has(p.id) ? 'opacity-25' : ''}" style="cursor:pointer" onclick="showPhoto('${esc(p.url)}')">
    ${READONLY ? '' : `<button type="button" class="btn btn-sm btn-light position-absolute top-0 end-0 p-0 px-1" title="Quitar" onclick="togglePhoto(${p.id})"><i class="bi bi-trash"></i></button>`}</div>`).join('');
  window._photos = list;
}
function togglePhoto(id) { photosToDelete.has(id) ? photosToDelete.delete(id) : photosToDelete.add(id); renderExistingPhotos(window._photos || []); }

/* ---------------- Listado en mapa ---------------- */
async function reloadIncidents(fit = true) {
  cluster.clearLayers();
  const { data } = await api('incidents.php');
  const faenas = new Set();
  data.forEach(it => {
    if (it.faena) faenas.add(it.faena);
    const m = L.marker([+it.lat, +it.lng], { icon: iconFor(it.category_code, it.severity_code), title: it.title, meta: it });
    if (it.photos.length) m.bindTooltip(`<img src="${esc(it.photos[0])}">`, { direction: 'top', className: 'photo-tip' });
    m.bindPopup(`<div style="min-width:240px">
      <b>${esc(it.title)}</b><br><small class="text-muted">${esc(fmtDT(it.event_datetime))}</small><br>
      <span class="pill">${esc(META.enums.tipo_contingencia[it.tipo_contingencia] || '')}</span>
      <span class="pill">${esc(META.enums.consecuencia[it.consecuencia] || '')}</span><br>
      <span class="pill">${esc(it.category_label)}</span><span class="pill">${esc(it.severity_label)}</span>
      ${it.company ? '<div class="small mt-1"><b>Empresa:</b> ' + esc(it.company) + '</div>' : ''}
      <div class="small mt-1">Estado: <b>${esc(META.enums.estado[it.estado])}</b> · Acciones abiertas: ${it.acciones_abiertas}</div>
      <div class="mt-2 d-flex gap-2"><button class="btn btn-sm btn-primary" data-edit="${it.id}">${READONLY ? 'Ver' : 'Editar'}</button>
      <a class="btn btn-sm btn-outline-secondary" target="_blank" href="informe.php?id=${it.id}">Informe</a></div></div>`);
    m.on('popupopen', ev => { ev.popup.getElement().querySelector('[data-edit]').onclick = () => { loadForEdit(it.id); ev.popup.remove(); }; });
    m.addTo(cluster);
  });
  $('dlFaena').innerHTML = [...faenas].map(f => `<option value="${esc(f)}">`).join('');
  if (fit && cluster.getLayers().length) map.fitBounds(cluster.getBounds().extend(SJ_BOUNDS), { padding: [20, 20] });
}
map.on('zoomend', () => cluster.eachLayer(l => l.options.meta && l.setIcon(iconFor(l.options.meta.category_code, l.options.meta.severity_code))));

/* ---------------- Reset / edición ---------------- */
function resetForm() {
  editingId = null;
  $('frm').reset();
  $('event_datetime').value = localInputNow();
  $('tipo_contingencia').value = 'INCIDENTE';
  $('formTitle').textContent = 'Nuevo registro';
  $('lnkInforme').classList.add('d-none');
  companiesChosen.length = 0; personsChosen.length = 0; porques.length = 0; actions.length = 0; photosToDelete.clear();
  matrixSel = { p: null, c: null };
  renderCompanies(); renderPersons(); renderPorques(); renderActions(); renderMatrix(); renderExistingPhotos([]);
  $('photosNew').innerHTML = '';
  $('a_jer').value = 'INGENIERIA';
  if (pickMarker) { map.removeLayer(pickMarker); pickMarker = null; $('lat').textContent = '—'; $('lng').textContent = '—'; }
  renderIconPreview(); syncLesionUI();
  if (location.hash) history.replaceState(null, '', location.pathname);
}
const v = x => x ?? '';
async function loadForEdit(id) {
  try {
    const { data: d } = await api('incidents.php?id=' + id);
    resetForm();
    editingId = d.id;
    $('formTitle').textContent = `Editando #${d.id}`;
    $('lnkInforme').href = 'informe.php?id=' + d.id; $('lnkInforme').classList.remove('d-none');
    setPicked({ lat: +d.lat, lng: +d.lng });
    const simple = ['title', 'description', 'faena', 'sector', 'turno', 'tipo_contingencia', 'estado', 'consecuencia', 'dias_perdidos',
      'forma_code', 'agente_code', 'naturaleza_code', 'zona_code', 'riesgo_critico_code', 'art_nombre', 'art_nro_siniestro'];
    simple.forEach(k => $(k).value = v(d[k]));
    $('event_datetime').value = d.event_datetime_local;
    $('category').value = d.category_id; $('severity').value = d.severity_id; $('company_id').value = v(d.company_id);
    $('fecha_inicio_baja').value = v(d.fecha_inicio_baja); $('fecha_alta').value = v(d.fecha_alta);
    $('art_denunciado').checked = !!d.art_denunciado; $('autoridad_notificada').checked = !!d.autoridad_notificada;
    $('art_fecha_denuncia').value = d.art_fecha_denuncia ? d.art_fecha_denuncia.replace(' ', 'T').slice(0, 16) : '';
    $('autoridad_fecha').value = d.autoridad_fecha ? d.autoridad_fecha.replace(' ', 'T').slice(0, 16) : '';
    matrixSel = { p: d.pot_probabilidad, c: d.pot_consecuencia }; renderMatrix();
    d.companies.forEach(c => companiesChosen.push({ id: c.id, name: `${c.name} (${c.tipo})`, role: c.role })); renderCompanies();
    d.persons.forEach(p => personsChosen.push({ full_name: p.full_name, company: p.company, puesto: p.puesto, role: p.role })); renderPersons();
    const inv = d.investigation || {};
    $('inv_metodo').value = inv.metodo || 'ARBOL_CAUSAS';
    const invMap = { inv_equipo: 'equipo', inv_hechos: 'hechos', inv_actos: 'actos_subestandar', inv_condiciones: 'condiciones_subestandar',
      inv_fpers: 'factores_personales', inv_ftrab: 'factores_trabajo', inv_fcontrol: 'falta_control', inv_conclusiones: 'conclusiones',
      inv_fecha_inicio: 'fecha_inicio', inv_fecha_cierre: 'fecha_cierre' };
    Object.entries(invMap).forEach(([el, k]) => $(el).value = v(inv[k]));
    (inv.porques || []).forEach(p => porques.push(p)); renderPorques();
    d.actions.forEach(a => actions.push(a)); renderActions();
    renderExistingPhotos(d.photos);
    renderIconPreview(); syncLesionUI();
    map.setView([+d.lat, +d.lng], Math.max(map.getZoom(), 13));
    document.querySelector('.panel').scrollTo({ top: 0, behavior: 'smooth' });
    bootstrap.Tab.getOrCreateInstance(document.querySelector('[data-bs-target="#t-gen"]')).show();
  } catch (e) { toast('No se pudo cargar: ' + e.message, 'danger'); }
}

/* ---------------- Guardar ---------------- */
$('frm').addEventListener('submit', async e => {
  e.preventDefault();
  if (READONLY) return;
  if ($('lat').textContent === '—') return toast('Seleccioná la ubicación en el mapa', 'danger');
  const fd = new FormData();
  if (editingId) fd.append('id', editingId);
  ['title', 'description', 'event_datetime', 'faena', 'sector', 'turno', 'tipo_contingencia', 'estado', 'company_id',
   'consecuencia', 'fecha_inicio_baja', 'fecha_alta', 'dias_perdidos', 'forma_code', 'agente_code', 'naturaleza_code', 'zona_code',
   'riesgo_critico_code', 'art_nombre', 'art_nro_siniestro', 'art_fecha_denuncia', 'autoridad_fecha'].forEach(k => fd.append(k, $(k).value));
  fd.append('category_id', $('category').value); fd.append('severity_id', $('severity').value);
  if ($('art_denunciado').checked) fd.append('art_denunciado', '1');
  if ($('autoridad_notificada').checked) fd.append('autoridad_notificada', '1');
  fd.append('lat', $('lat').textContent); fd.append('lng', $('lng').textContent);
  if (matrixSel.p) { fd.append('pot_probabilidad', matrixSel.p); fd.append('pot_consecuencia', matrixSel.c); }
  fd.append('companies_json', JSON.stringify(companiesChosen.map(c => ({ id: c.id, role: c.role }))));
  fd.append('persons_json', JSON.stringify(personsChosen));
  fd.append('actions_json', JSON.stringify(actions));
  fd.append('delete_photos_json', JSON.stringify([...photosToDelete]));
  fd.append('investigation_json', JSON.stringify({
    metodo: $('inv_metodo').value, equipo: $('inv_equipo').value, hechos: $('inv_hechos').value,
    actos_subestandar: $('inv_actos').value, condiciones_subestandar: $('inv_condiciones').value,
    factores_personales: $('inv_fpers').value, factores_trabajo: $('inv_ftrab').value, falta_control: $('inv_fcontrol').value,
    porques, conclusiones: $('inv_conclusiones').value, fecha_inicio: $('inv_fecha_inicio').value, fecha_cierre: $('inv_fecha_cierre').value,
  }));
  [...$('photos').files].forEach(f => fd.append('photos[]', f, f.name));

  const btn = $('btnSave'); btn.disabled = true;
  try {
    const j = await api('incidents.php', { method: 'POST', body: fd });
    toast(editingId ? `Registro #${j.id} actualizado` : `Registro #${j.id} guardado`);
    await reloadIncidents(false);
    await loadForEdit(j.id);
  } catch (err) { toast('Error al guardar: ' + err.message, 'danger'); }
  finally { btn.disabled = false; }
});
$('btnCancel')?.addEventListener('click', resetForm);

if (READONLY) document.querySelectorAll('#frm input, #frm select, #frm textarea, #frm button').forEach(el => { if (!el.matches('[data-bs-toggle]')) el.disabled = true; });

/* ---------------- Inicio ---------------- */
(async () => {
  try {
    const m = location.hash.match(/edit=(\d+)/);
    await loadMeta(); await loadCompanies();
    resetForm();
    await reloadIncidents();
    if (m) loadForEdit(+m[1]);
  } catch (e) { toast('Error inicial: ' + e.message, 'danger'); }
})();
</script>
</body></html>
