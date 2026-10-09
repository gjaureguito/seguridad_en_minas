<?php
declare(strict_types=1);
/* estadisticas.php — índices SRT, control estadístico, Bird, Pareto, distribución temporal */
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/layout.php';
$user = require_login_page();
page_head('Estadísticas');
?>
<body>
<?php page_nav($user, 'estadisticas.php'); ?>
<style>
  .viz{--s1:#2a78d6;--s2:#eb6834;--grid:#e9e8e4;--ink2:#52514e;--crit:#c0262d}
  .chart-box{position:relative;height:280px}
  .formula{font-family:ui-monospace,Menlo,Consolas,monospace;font-size:.72rem;color:var(--ui-muted)}
  .bird-bar{height:10px;border-radius:0 4px 4px 0;background:#2a78d6;display:inline-block;vertical-align:middle}
  .bird-bar.teo{background:#c4c3bd}
</style>
<div class="container-fluid py-3 viz">
  <div class="cardish d-flex flex-wrap gap-2 align-items-end no-print">
    <div><label class="form-label">Desde</label><input id="desde" type="month" class="form-control form-control-sm"></div>
    <div><label class="form-label">Hasta</label><input id="hasta" type="month" class="form-control form-control-sm"></div>
    <div><label class="form-label">Empresa</label><select id="company" class="form-select form-select-sm"><option value="">Todas</option></select></div>
    <div><label class="form-label">Gráfico de control sobre</label><select id="base" class="form-select form-select-sm">
      <option value="LTI">Accidentes con baja (LTI)</option><option value="TRI">Lesiones registrables (TRI)</option></select></div>
    <button id="btnGo" class="btn btn-primary btn-sm">Calcular</button>
    <button onclick="print()" class="btn btn-outline-secondary btn-sm ms-auto"><i class="bi bi-printer"></i> Imprimir</button>
  </div>
  <div id="warn"></div>

  <!-- Índices -->
  <div class="row g-2 mb-3" id="idx"></div>

  <div class="row g-3">
    <div class="col-12 col-xl-7">
      <div class="cardish">
        <div class="section-title">Gráfico de control u: tasa mensual por millón de HHT</div>
        <div class="small-muted mb-2">Media ū y límites de 3σ con distribución de Poisson. Los límites cambian cada mes según las horas trabajadas: con menos exposición, más amplios.</div>
        <div class="chart-box"><canvas id="chControl" role="img" aria-label="Gráfico de control u"></canvas></div>
        <div id="signals" class="small mt-2"></div>
      </div>
    </div>
    <div class="col-12 col-xl-5">
      <div class="cardish">
        <div class="section-title">Pirámide de Bird: proporción observada vs. teórica (1 : 10 : 30 : 600)</div>
        <div class="small-muted mb-2">Si la base de la pirámide está muy por debajo de la teórica, suele haber subregistro de incidentes, no una operación más segura.</div>
        <table class="table table-sm mb-1"><thead><tr><th>Nivel</th><th class="text-end">N</th><th class="text-end">Observado</th><th class="text-end">Teórico</th><th style="width:38%">Escala log.</th></tr></thead><tbody id="bird"></tbody></table>
      </div>
      <div class="cardish">
        <div class="section-title">Denuncia a la ART y acciones</div>
        <div class="row g-2" id="cumpl"></div>
      </div>
    </div>

    <div class="col-12 col-xl-6">
      <div class="cardish">
        <div class="section-title">Accidentes con baja por mes</div>
        <div class="chart-box"><canvas id="chMes" role="img" aria-label="Accidentes con baja por mes"></canvas></div>
      </div>
    </div>
    <div class="col-12 col-xl-6">
      <div class="cardish">
        <div class="section-title">Horas-hombre trabajadas por mes</div>
        <div class="chart-box"><canvas id="chHht" role="img" aria-label="Horas-hombre por mes"></canvas></div>
      </div>
    </div>

    <div class="col-12 col-xl-6">
      <div class="cardish">
        <div class="d-flex align-items-center gap-2 mb-1">
          <div class="section-title m-0">Pareto</div>
          <select id="paretoDim" class="form-select form-select-sm w-auto ms-auto">
            <option value="forma">Forma del accidente (lesiones)</option><option value="agente">Agente material (lesiones)</option>
            <option value="naturaleza">Naturaleza de la lesión</option><option value="zona">Zona del cuerpo</option>
            <option value="riesgo">Riesgo crítico (todos los eventos)</option><option value="categoria">Categoría (todos los eventos)</option>
          </select>
        </div>
        <div class="small-muted mb-2">Barras: % de casos. Línea: % acumulado. Las barras oscuras son el ~80 % que conviene atacar primero.</div>
        <div class="chart-box" style="height:320px"><canvas id="chPareto" role="img" aria-label="Pareto"></canvas></div>
      </div>
    </div>
    <div class="col-12 col-xl-6">
      <div class="cardish">
        <div class="section-title">Distribución por hora del día</div>
        <div class="chart-box" style="height:150px"><canvas id="chHora" role="img" aria-label="Eventos por hora"></canvas></div>
        <div class="section-title mt-3">Por día de la semana</div>
        <div class="chart-box" style="height:130px"><canvas id="chDow" role="img" aria-label="Eventos por día de la semana"></canvas></div>
      </div>
    </div>

    <div class="col-12">
      <div class="cardish">
        <div class="section-title">Índices por empresa (titular y contratistas)</div>
        <div class="table-responsive"><table class="table table-sm table-hover mb-0 tabular">
          <thead class="table-light"><tr><th>Empresa</th><th class="text-end">Eventos</th><th class="text-end">LTI</th><th class="text-end">TRI</th><th class="text-end">Días perdidos</th><th class="text-end">HHT</th><th class="text-end">IF</th><th class="text-end">IG</th><th class="text-end">TRIFR</th></tr></thead>
          <tbody id="emp"></tbody></table></div>
      </div>
    </div>

    <div class="col-12">
      <div class="cardish">
        <div class="section-title">Serie mensual (tabla)</div>
        <div class="table-responsive"><table class="table table-sm mb-0 tabular">
          <thead class="table-light"><tr><th>Mes</th><th class="text-end">Eventos</th><th class="text-end">LTI</th><th class="text-end">TRI</th><th class="text-end">Días</th><th class="text-end">HHT</th><th class="text-end">Dotación</th><th class="text-end">u</th><th class="text-end">LCS</th><th class="text-end">LCI</th><th>Señal</th></tr></thead>
          <tbody id="serie"></tbody></table></div>
      </div>
    </div>
  </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<?php page_scripts(); ?>
<script>
const $ = id => document.getElementById(id);
const C = { s1: '#2a78d6', s2: '#eb6834', grid: '#e9e8e4', ink2: '#52514e', ink3: '#8a8984', crit: '#c0262d', limit: '#6b6a65' };
Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
Chart.defaults.font.size = 11;
Chart.defaults.color = C.ink2;
Chart.defaults.plugins.legend.labels.boxWidth = 10;
Chart.defaults.plugins.tooltip.mode = 'index';
Chart.defaults.plugins.tooltip.intersect = false;
const gridOpts = { color: C.grid, drawTicks: false };
const charts = {};
let S = null;
const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
const mesLbl = m => MESES[+m.slice(5, 7) - 1] + ' ' + m.slice(2, 4);

function chart(id, cfg) { charts[id]?.destroy(); charts[id] = new Chart($(id), cfg); }

const IDX = [
  ['IF', 'Índice de frecuencia', 'LTI × 10⁶ / HHT'],
  ['IG', 'Índice de gravedad', 'días × 10³ / HHT'],
  ['II', 'Índice de incidencia', 'LTI × 10³ / dotación'],
  ['IP', 'Índice de pérdida', 'días × 10³ / dotación'],
  ['DMB', 'Duración media de bajas', 'días / LTI'],
  ['IM', 'Incidencia de fallecidos', 'fatales × 10⁶ / dotación'],
  ['TRIFR', 'TRIFR', 'TRI × 10⁶ / HHT'],
];

function renderIdx() {
  const t = S.totales, i = S.indices;
  let html = IDX.map(([k, l, f]) => `<div class="col-6 col-md-3 col-xxl"><div class="kpi"><div class="lbl">${l}</div><div class="val">${fmtN(i[k])}</div><div class="formula">${f}</div></div></div>`).join('');
  html += `<div class="col-12"><div class="small-muted">
    ${t.eventos} eventos · <b>${t.lti}</b> accidentes de trabajo con baja · ${t.tri} lesiones registrables · ${t.dias_perdidos} días perdidos · ${t.fatales} fatales ·
    ${t.in_itinere} in itinere con lesión (se informan aparte) · ${t.enfermedades_prof} enf. profesionales · <b>${t.alto_potencial}</b> de alto potencial ·
    HHT ${fmtN(t.hht, 0)} · dotación promedio ${fmtN(t.dotacion_promedio, 1)} · II anualizado ${fmtN(i.II_anualizado)} (${S.periodo.meses_con_hht} meses con HHT)</div></div>`;
  $('idx').innerHTML = html;
  $('warn').innerHTML = !t.hht ? `<div class="alert alert-warning small"><i class="bi bi-exclamation-triangle"></i> No hay horas-hombre cargadas para el período: los índices quedan en “—”. Cargalas en <a href="horas.php">Horas-hombre</a>.</div>`
    : (S.periodo.meses_con_hht < S.periodo.meses ? `<div class="alert alert-light border small">Faltan horas-hombre en ${S.periodo.meses - S.periodo.meses_con_hht} de ${S.periodo.meses} meses: esos meses quedan fuera del gráfico de control.</div>` : '');
}

function renderControl() {
  const rows = S.control;
  const labels = rows.map(r => mesLbl(r.mes));
  chart('chControl', {
    type: 'line',
    data: { labels, datasets: [
      { label: `u (${S.base_control} / 10⁶ HHT)`, data: rows.map(r => r.u), borderColor: C.s1, backgroundColor: C.s1, borderWidth: 2, tension: 0,
        pointRadius: rows.map(r => r.senal ? 6 : 4), pointBackgroundColor: rows.map(r => r.senal ? C.crit : C.s1), pointBorderColor: '#fff', pointBorderWidth: 2, spanGaps: false },
      { label: 'ū (línea central)', data: rows.map(r => r.u === null ? null : r.lc), borderColor: C.ink2, borderWidth: 1.5, pointRadius: 0, stepped: 'middle' },
      { label: 'LCS / LCI (3σ)', data: rows.map(r => r.lcs), borderColor: C.limit, borderDash: [5, 4], borderWidth: 1.5, pointRadius: 0, stepped: 'middle' },
      { label: 'LCI', data: rows.map(r => r.lci), borderColor: C.limit, borderDash: [5, 4], borderWidth: 1.5, pointRadius: 0, stepped: 'middle' },
    ] },
    options: { maintainAspectRatio: false, scales: { y: { beginAtZero: true, grid: gridOpts, border: { display: false } }, x: { grid: { display: false } } },
      plugins: { legend: { labels: { filter: it => it.text !== 'LCI' } },
        tooltip: { callbacks: { afterBody: items => { const r = rows[items[0].dataIndex]; return r.senal ? '⚠ ' + r.senal : ''; } } } } },
  });
  const sig = rows.filter(r => r.senal);
  $('signals').innerHTML = sig.length ? sig.map(r => `<div><i class="bi bi-exclamation-triangle-fill" style="color:${C.crit}"></i> <b>${mesLbl(r.mes)}</b>: ${esc(r.senal)}</div>`).join('')
    : (rows.some(r => r.u !== null) ? '<div class="small-muted"><i class="bi bi-check-circle"></i> Proceso bajo control estadístico: ningún mes supera los límites ni hay rachas de 8 meses.</div>' : '');
}

function renderBird() {
  const max = Math.log10(601);
  $('bird').innerHTML = S.bird.map(b => {
    const w = r => r > 0 ? Math.max(2, Math.log10(r + 1) / max * 100) : 0;
    return `<tr><td>${esc(b.nivel)}</td><td class="text-end tabular">${b.n}</td><td class="text-end tabular">${fmtN(b.ratio, 1)}</td><td class="text-end tabular">${b.teorico}</td>
      <td><div><span class="bird-bar" style="width:${w(b.ratio)}%" title="Observado ${b.ratio}"></span></div><div><span class="bird-bar teo" style="width:${w(b.teorico)}%" title="Teórico ${b.teorico}"></span></div></td></tr>`;
  }).join('') + `<tr><td colspan="5" class="small-muted border-0"><span class="bird-bar" style="width:14px"></span> observado <span class="bird-bar teo ms-2" style="width:14px"></span> teórico. Cada nivel se expresa por cada lesión grave.</td></tr>`;
}

function renderCumpl() {
  const d = S.denuncia_art, a = S.acciones;
  const k = (l, v, s) => `<div class="col-6"><div class="kpi"><div class="lbl">${l}</div><div class="val">${v}</div><div class="sub">${s}</div></div></div>`;
  $('cumpl').innerHTML =
    k('Denuncias en plazo', d.a_denunciar ? Math.round(d.en_plazo * 100 / d.a_denunciar) + ' %' : '—', `${d.en_plazo} de ${d.a_denunciar} (≤ 48 h, Res. SRT 525/15)`) +
    k('Denuncias pendientes', d.pendientes, `${d.fuera_plazo} hechas fuera de plazo`) +
    k('Acciones cumplidas', a.pct_cumplimiento !== null ? a.pct_cumplimiento + ' %' : '—', `${a.total} acciones en el período`) +
    k('Acciones vencidas', a.vencidas, `Verificadas: ${a.por_estado.VERIFICADA || 0}`);
}

function renderMes() {
  const labels = S.serie.map(s => mesLbl(s.mes));
  chart('chMes', {
    type: 'bar',
    data: { labels, datasets: [
      { label: 'Con baja (LTI)', data: S.serie.map(s => s.lti), backgroundColor: C.s1, borderRadius: { topLeft: 4, topRight: 4 }, borderSkipped: 'bottom', maxBarThickness: 28 },
      { label: 'Otras registrables', data: S.serie.map(s => s.tri - s.lti), backgroundColor: C.s2, borderRadius: { topLeft: 4, topRight: 4 }, borderSkipped: 'bottom', maxBarThickness: 28 },
    ] },
    options: { maintainAspectRatio: false, datasets: { bar: { borderColor: '#fff', borderWidth: { top: 2 } } },
      scales: { x: { stacked: true, grid: { display: false } }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0 }, grid: gridOpts, border: { display: false } } } },
  });
  chart('chHht', {
    type: 'bar',
    data: { labels, datasets: [{ label: 'HHT', data: S.serie.map(s => s.hht), backgroundColor: C.s1, borderRadius: { topLeft: 4, topRight: 4 }, borderSkipped: 'bottom', maxBarThickness: 28 }] },
    options: { maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => 'HHT: ' + fmtN(c.raw, 0) + ' · dotación ' + fmtN(S.serie[c.dataIndex].dotacion, 1) } } },
      scales: { x: { grid: { display: false } }, y: { beginAtZero: true, grid: gridOpts, border: { display: false }, ticks: { callback: v => fmtN(v, 0) } } } },
  });
}

function renderPareto() {
  const rows = S.pareto[$('paretoDim').value].slice(0, 12);
  chart('chPareto', {
    data: { labels: rows.map(r => r.label.length > 28 ? r.label.slice(0, 27) + '…' : r.label), datasets: [
      { type: 'bar', label: '% de casos', data: rows.map(r => r.pct), backgroundColor: rows.map(r => r.vital ? C.s1 : '#9ec5f4'), borderRadius: { topLeft: 4, topRight: 4 }, borderSkipped: 'bottom', maxBarThickness: 36, order: 2 },
      { type: 'line', label: '% acumulado', data: rows.map(r => r.pct_acum), borderColor: C.s2, backgroundColor: C.s2, borderWidth: 2, pointRadius: 4, pointBorderColor: '#fff', pointBorderWidth: 2, order: 1 },
    ] },
    options: { maintainAspectRatio: false,
      plugins: { tooltip: { callbacks: { title: it => rows[it[0].dataIndex].label, afterTitle: it => `${rows[it[0].dataIndex].n} casos` } } },
      scales: { y: { min: 0, max: 100, ticks: { callback: v => v + ' %' }, grid: gridOpts, border: { display: false } }, x: { grid: { display: false }, ticks: { maxRotation: 50, minRotation: 30 } } } },
  });
}

function renderTiempo() {
  const bar = (id, labels, data) => chart(id, {
    type: 'bar', data: { labels, datasets: [{ label: 'Eventos', data, backgroundColor: C.s1, borderRadius: { topLeft: 4, topRight: 4 }, borderSkipped: 'bottom' }] },
    options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { grid: { display: false } }, y: { beginAtZero: true, ticks: { precision: 0 }, grid: gridOpts, border: { display: false } } } },
  });
  bar('chHora', [...Array(24).keys()].map(h => h + 'h'), S.hora);
  bar('chDow', ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'], S.dia_semana);
}

function renderTablas() {
  $('emp').innerHTML = S.empresas.map(e => `<tr><td>${esc(e.empresa)}</td><td class="text-end">${e.eventos}</td><td class="text-end">${e.lti}</td><td class="text-end">${e.tri}</td>
    <td class="text-end">${e.dias}</td><td class="text-end">${fmtN(e.hht, 0)}</td><td class="text-end fw-semibold">${fmtN(e.IF)}</td><td class="text-end">${fmtN(e.IG)}</td><td class="text-end">${fmtN(e.TRIFR)}</td></tr>`).join('')
    || '<tr><td colspan="9" class="small-muted">Sin datos</td></tr>';
  $('serie').innerHTML = S.serie.map((s, k) => { const c = S.control[k]; return `<tr><td>${mesLbl(s.mes)}</td><td class="text-end">${s.eventos}</td><td class="text-end">${s.lti}</td><td class="text-end">${s.tri}</td>
    <td class="text-end">${s.dias}</td><td class="text-end">${fmtN(s.hht, 0)}</td><td class="text-end">${fmtN(s.dotacion, 1)}</td><td class="text-end">${fmtN(c.u)}</td><td class="text-end">${fmtN(c.lcs)}</td><td class="text-end">${fmtN(c.lci)}</td>
    <td class="small">${c.senal ? '<i class="bi bi-exclamation-triangle-fill" style="color:' + C.crit + '"></i> ' + esc(c.senal) : ''}</td></tr>`; }).join('');
}

async function load() {
  const q = new URLSearchParams({ desde: $('desde').value, hasta: $('hasta').value, company_id: $('company').value, base: $('base').value });
  try {
    S = (await api('stats.php?' + q)).data;
    renderIdx(); renderControl(); renderBird(); renderCumpl(); renderMes(); renderPareto(); renderTiempo(); renderTablas();
  } catch (e) { toast(e.message, 'danger'); }
}

$('btnGo').onclick = load;
$('paretoDim').onchange = () => S && renderPareto();
(async () => {
  const now = new Date(); const h = now.toISOString().slice(0, 7);
  const d = new Date(now.getFullYear(), now.getMonth() - 11, 1);
  $('hasta').value = h; $('desde').value = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
  try { const { data } = await api('companies.php'); $('company').innerHTML += data.map(c => `<option value="${c.id}">${esc(c.nombre)}</option>`).join(''); } catch (_) {}
  load();
})();
</script>
</body></html>
