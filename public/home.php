<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/layout.php';
$user = require_login_page();
page_head('Inicio');
?>
<body>
<?php page_nav($user, 'home.php'); ?>
<main class="container-xl py-3 py-md-4">

  <div class="row g-3 mb-3">
    <!-- Cartel de faena -->
    <div class="col-12 col-lg-7">
      <section class="cartel h-100" aria-label="Días sin accidentes con baja">
        <div class="cartel-body">
          <div class="numero" id="c_dias">—</div>
          <div>
            <div class="leyenda" id="c_ley">días sin accidentes con baja</div>
            <div class="detalle" id="c_det">Calculando…</div>
          </div>
        </div>
        <div class="cartel-foot">
          <span>Récord <b id="c_rec">—</b> días</span>
          <span>Índice de frecuencia <b id="c_if">—</b></span>
          <span>Últimos 12 meses <b id="c_lti">—</b> con baja</span>
        </div>
      </section>
    </div>

    <!-- Qué requiere atención -->
    <div class="col-12 col-lg-5">
      <section class="cardish h-100 mb-0">
        <h2 class="section-title mb-2">Requiere atención</h2>
        <div id="atencion" class="d-grid gap-2"><div class="small-muted">Cargando…</div></div>
      </section>
    </div>
  </div>

  <div class="row g-2 g-md-3 mb-3">
    <div class="col-6 col-md-3"><div class="kpi k-info"><div class="lbl">Eventos registrados</div><div class="val" id="k_ev">—</div><div class="sub">últimos 12 meses</div></div></div>
    <div class="col-6 col-md-3"><div class="kpi k-acento"><div class="lbl">Índice de gravedad</div><div class="val" id="k_ig">—</div><div class="sub">días perdidos por mil HHT</div></div></div>
    <div class="col-6 col-md-3"><div class="kpi k-acento"><div class="lbl">TRIFR</div><div class="val" id="k_trifr">—</div><div class="sub">lesiones registrables por millón de HHT</div></div></div>
    <div class="col-6 col-md-3"><div class="kpi k-ok"><div class="lbl">Acciones cumplidas</div><div class="val" id="k_acc">—</div><div class="sub" id="k_acc_s">&nbsp;</div></div></div>
  </div>

  <div class="row g-2 g-md-3">
    <?php
    $cards = [
      ['index.php','bi-plus-lg','Registrar un evento','Ubicación en el mapa, lesión, denuncia ART, investigación y fotos.', true],
      ['dashboard.php','bi-map','Mapa y registros','Filtrá, abrí cada caso o exportá a CSV y Google Earth.', false],
      ['estadisticas.php','bi-graph-up','Estadísticas','Índices SRT, gráfico de control, Bird y Pareto.', false],
      ['acciones.php','bi-list-check','Acciones correctivas','Vencimientos por responsable y verificación de eficacia.', false],
      ['horas.php','bi-clock-history','Horas-hombre','Exposición mensual por empresa, base de los índices.', false],
    ];
    foreach ($cards as [$href,$icon,$t,$d,$main]): ?>
      <div class="col-12 col-sm-6 col-xl">
        <a href="<?= $href ?>" class="acceso<?= $main ? ' principal' : '' ?>"><i class="bi <?= $icon ?>"></i><div><div class="t"><?= $t ?></div><div class="d"><?= $d ?></div></div></a>
      </div>
    <?php endforeach; ?>
  </div>
</main>
<?php page_scripts(); ?>
<script>
const $ = id => document.getElementById(id);
const item = (icon, color, text, href) =>
  `<a href="${href}" class="d-flex align-items-start gap-2 text-decoration-none text-reset p-2 rounded" style="background:#F6F8F7">
     <i class="bi ${icon} fs-5" style="color:${color}"></i><span>${text}</span></a>`;

(async () => {
  try {
    const [{ data: s }, { data: inc }] = await Promise.all([api('stats.php'), api('incidents.php?limit=500')]);
    const c = s.contador;
    $('c_dias').textContent = c.dias ?? '—';
    if (c.dias === 1) $('c_ley').textContent = 'día sin accidentes con baja';
    $('c_det').innerHTML = c.ultimo
      ? `Último accidente con baja: <b>${esc(fmtD(c.ultimo))}</b>.`
      : (c.desde_inicio ? `Sin accidentes con baja desde el primer registro (${esc(fmtD(c.desde_inicio))}).` : 'Todavía no hay registros.');
    $('c_rec').textContent = c.record ?? '—';
    $('c_if').textContent = fmtN(s.indices.IF);
    $('c_lti').textContent = s.totales.lti;
    $('k_ev').textContent = s.totales.eventos;
    $('k_ig').textContent = fmtN(s.indices.IG);
    $('k_trifr').textContent = fmtN(s.indices.TRIFR);
    $('k_acc').textContent = s.acciones.pct_cumplimiento !== null ? fmtN(s.acciones.pct_cumplimiento, 1) + ' %' : '—';
    $('k_acc_s').textContent = s.acciones.total ? `de ${s.acciones.total} acciones` : 'sin acciones cargadas';

    // Lista de pendientes accionables
    const needDen = i => ['ACCIDENTE_TRABAJO', 'IN_ITINERE', 'ENFERMEDAD_PROFESIONAL'].includes(i.tipo_contingencia) && i.consecuencia !== 'SIN_LESION' && !i.art_denunciado;
    const den = inc.filter(needDen);
    const hipo = inc.filter(i => i.estado !== 'CERRADO' && i.riesgo_potencial && i.riesgo_potencial.valor >= 15);
    const out = [];
    den.slice(0, 3).forEach(i => {
      const h = Math.round((Date.now() - new Date(i.event_datetime.replace(' ', 'T'))) / 3600000);
      out.push(item('bi-exclamation-octagon-fill', 'var(--oxido)', `<b>Denuncia ART pendiente</b> · ${esc(i.title)} <span class="small-muted">(hace ${h} h, plazo 48 h)</span>`, `index.php#edit=${i.id}`));
    });
    if (s.acciones.vencidas) out.push(item('bi-calendar-x-fill', 'var(--oxido)', `<b>${s.acciones.vencidas} ${s.acciones.vencidas === 1 ? 'acción vencida' : 'acciones vencidas'}</b> sin cumplir`, 'acciones.php?vencidas=1'));
    if (hipo.length) out.push(item('bi-lightning-charge-fill', 'var(--seguridad-osc)', `<b>${hipo.length} ${hipo.length === 1 ? 'evento de alto potencial sigue abierto' : 'eventos de alto potencial siguen abiertos'}</b>`, 'dashboard.php'));
    const sig = s.control.filter(r => r.senal).slice(-1)[0];
    if (sig) out.push(item('bi-graph-up-arrow', 'var(--azul)', `<b>Señal estadística</b> en ${esc(new Date(sig.mes + '-15').toLocaleDateString('es-AR', { month: 'long', year: 'numeric' }))}: ${esc(sig.senal.toLowerCase())}`, 'estadisticas.php'));
    if (!s.totales.hht) out.push(item('bi-clock-history', 'var(--azul)', '<b>Cargá las horas-hombre</b> para calcular los índices', 'horas.php'));
    $('atencion').innerHTML = out.length ? out.join('')
      : '<div class="d-flex gap-2 align-items-center p-2"><i class="bi bi-check-circle-fill fs-5" style="color:var(--verde)"></i> Nada pendiente: denuncias al día y acciones en plazo.</div>';
  } catch (e) { toast(e.message, 'danger'); }
})();
</script>
</body></html>
