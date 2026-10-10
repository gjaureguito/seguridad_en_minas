/* common.js — helpers compartidos: API con CSRF, escape HTML, formatos */
const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

async function api(path, opts = {}) {
  const o = { cache: 'no-store', credentials: 'same-origin', ...opts };
  o.headers = { 'X-CSRF-Token': CSRF, ...(opts.headers || {}) };
  if (o.json !== undefined) {
    o.method = o.method || 'POST';
    o.headers['Content-Type'] = 'application/json';
    o.body = JSON.stringify(o.json);
    delete o.json;
  }
  const res = await fetch('api/' + path, o);
  if (res.status === 401) { location.href = 'login.php'; throw new Error('Sesión vencida'); }
  const txt = await res.text();
  let j;
  try { j = JSON.parse(txt); } catch (e) { throw new Error(`Respuesta no JSON (${res.status}): ${txt.slice(0, 200)}`); }
  if (!j.ok) throw new Error(j.error || 'Error');
  return j;
}

function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}
function fmtDT(s) { if (!s) return ''; const d = new Date(String(s).replace(' ', 'T')); return isNaN(d) ? s : d.toLocaleString('es-AR', { dateStyle: 'short', timeStyle: 'short' }); }
function fmtD(s) { if (!s) return ''; const d = new Date(String(s).slice(0, 10) + 'T00:00'); return isNaN(d) ? s : d.toLocaleDateString('es-AR'); }
function fmtN(v, dec = 2) { return (v === null || v === undefined || v === '') ? '—' : Number(v).toLocaleString('es-AR', { maximumFractionDigits: dec, minimumFractionDigits: 0 }); }
function localInputNow() { const n = new Date(); n.setSeconds(0, 0); return new Date(n.getTime() - n.getTimezoneOffset() * 60000).toISOString().slice(0, 16); }

function toast(msg, kind = 'success') {
  let box = document.getElementById('toastBox');
  if (!box) { box = document.createElement('div'); box.id = 'toastBox'; box.className = 'toast-container position-fixed bottom-0 end-0 p-3'; box.style.zIndex = 3000; document.body.appendChild(box); }
  const el = document.createElement('div');
  el.className = `toast align-items-center text-bg-${kind} border-0`;
  el.innerHTML = `<div class="d-flex"><div class="toast-body">${esc(msg)}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
  box.appendChild(el);
  const t = new bootstrap.Toast(el, { delay: kind === 'danger' ? 7000 : 3500 }); t.show();
  el.addEventListener('hidden.bs.toast', () => el.remove());
}

/* Colores accesibles (Okabe–Ito) por severidad, igual que la versión anterior */
const SEV_COLOR = { BAJA: '#3DB7E9', MEDIA: '#F0E442', ALTA: '#E69F00', CRITICA: '#D55E00', POTENCIAL: '#CC79A7' };
const RIESGO_COLOR = { BAJO: 'success', MODERADO: 'warning', ALTO: 'orange', INTOLERABLE: 'danger' };
function riesgoBadge(r) {
  if (!r) return '<span class="text-muted">—</span>';
  const cls = { BAJO: 'text-bg-success', MODERADO: 'text-bg-warning', ALTO: 'badge-orange', INTOLERABLE: 'text-bg-danger' }[r.nivel] || 'text-bg-secondary';
  return `<span class="badge ${cls}">${esc(r.nivel)} (${r.valor})</span>`;
}

/* ---------------- Capas base: mapa, satélite (tipo Google Earth) y relieve ---------------- */
function addBaseLayers(map) {
  const esri = 'https://server.arcgisonline.com/ArcGIS/rest/services/';
  const layers = {
    'Satélite': L.layerGroup([
      L.tileLayer(esri + 'World_Imagery/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19, attribution: 'Imágenes &copy; Esri, Maxar, Earthstar Geographics' }),
      L.tileLayer(esri + 'Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19, opacity: 0.85 }),
    ]),
    'Relieve': L.tileLayer(esri + 'World_Topo_Map/MapServer/tile/{z}/{y}/{x}', { maxZoom: 19, attribution: '&copy; Esri' }),
    'Mapa': L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }),
  };
  let pref = 'Satélite';
  try { pref = localStorage.getItem('sm_base') || pref; } catch (_) {}
  (layers[pref] || layers['Satélite']).addTo(map);
  L.control.layers(layers, null, { position: 'topright', collapsed: false }).addTo(map);
  map.on('baselayerchange', e => { try { localStorage.setItem('sm_base', e.name); } catch (_) {} });
}

/* Link para abrir un punto en Google Earth (web) */
function earthUrl(lat, lng) {
  return `https://earth.google.com/web/@${(+lat).toFixed(6)},${(+lng).toFixed(6)},3000a,1200d,35y,0h,60t,0r`;
}

/* ---------------- Exportación KML para Google Earth ---------------- */
function kmlColor(hex, alpha = 'ff') { const h = hex.replace('#', ''); return alpha + h.slice(4, 6) + h.slice(2, 4) + h.slice(0, 2); } // KML usa aabbggrr
function toKML(items, meta, title = 'Incidentes — Seguridad Minera') {
  const x = s => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  const base = location.origin + location.pathname.replace(/[^/]*$/, '');
  const LTI = ['CON_BAJA', 'INCAPACIDAD_PERMANENTE', 'FATAL'];
  const styles = Object.entries(SEV_COLOR).map(([k, c]) => `
  <Style id="sev-${k}"><IconStyle><color>${kmlColor(c)}</color><scale>1.1</scale>
    <Icon><href>http://maps.google.com/mapfiles/kml/shapes/placemark_circle.png</href></Icon></IconStyle>
    <LabelStyle><scale>0</scale></LabelStyle></Style>
  <Style id="sev-${k}-lti"><IconStyle><color>${kmlColor(c)}</color><scale>1.6</scale>
    <Icon><href>http://maps.google.com/mapfiles/kml/shapes/caution.png</href></Icon></IconStyle>
    <LabelStyle><scale>0.8</scale></LabelStyle></Style>`).join('');
  // Una carpeta por tipo de contingencia (se pueden prender/apagar en Google Earth)
  const groups = {};
  items.forEach(it => (groups[it.tipo_contingencia] ??= []).push(it));
  const folders = Object.entries(groups).map(([tipo, list]) => `
  <Folder><name>${x(meta.enums.tipo_contingencia[tipo] || tipo)} (${list.length})</name>${list.map(it => {
    const lti = LTI.includes(it.consecuencia);
    const r = it.riesgo_potencial;
    return `
    <Placemark>
      <name>#${it.id} ${x(it.title)}</name>
      <TimeStamp><when>${x(String(it.event_datetime).replace(' ', 'T'))}</when></TimeStamp>
      <styleUrl>#sev-${x(it.severity_code)}${lti ? '-lti' : ''}</styleUrl>
      <description><![CDATA[
        <b>${x(fmtDT(it.event_datetime))}</b> · turno ${x(it.turno || '—')}<br>
        ${x(it.faena || '')} ${it.sector ? '/ ' + x(it.sector) : ''}<br>
        <b>Consecuencia:</b> ${x(meta.enums.consecuencia[it.consecuencia])}${lti ? ' · ' + it.dias_perdidos + ' días perdidos' : ''}<br>
        <b>Categoría:</b> ${x(it.category_label)} · <b>Severidad:</b> ${x(it.severity_label)}<br>
        ${r ? `<b>Riesgo potencial:</b> ${x(r.nivel)} (${r.valor})<br>` : ''}
        <b>Empresa:</b> ${x(it.company || '—')}<br>
        <b>Estado:</b> ${x(meta.enums.estado[it.estado])}<br><br>
        ${x(it.description || '')}<br><br>
        <a href="${base}informe.php?id=${it.id}">Ver informe completo</a>
      ]]></description>
      <ExtendedData>
        <Data name="tipo"><value>${x(it.tipo_contingencia)}</value></Data>
        <Data name="consecuencia"><value>${x(it.consecuencia)}</value></Data>
        <Data name="dias_perdidos"><value>${it.dias_perdidos ?? 0}</value></Data>
        <Data name="empresa"><value>${x(it.company || '')}</value></Data>
      </ExtendedData>
      <Point><coordinates>${(+it.lng).toFixed(6)},${(+it.lat).toFixed(6)},0</coordinates></Point>
    </Placemark>`; }).join('')}
  </Folder>`).join('');
  return `<?xml version="1.0" encoding="UTF-8"?>
<kml xmlns="http://www.opengis.net/kml/2.2">
<Document>
  <name>${x(title)}</name>
  <description>Exportado el ${x(new Date().toLocaleString('es-AR'))} · ${items.length} registros. Íconos de advertencia = accidentes con baja. Color = severidad.</description>${styles}${folders}
</Document>
</kml>`;
}
