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
