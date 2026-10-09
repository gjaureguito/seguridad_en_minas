<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/layout.php';
$user = require_login_page();
page_head('Inicio');
?>
<body>
<?php page_nav($user, 'home.php'); ?>
<div class="container py-4">
  <div class="mb-4">
    <h1 class="h3 fw-bold mb-1">Sistema de Seguridad en Terreno — San Juan</h1>
    <p class="small-muted mb-0">Registro, investigación y control estadístico de accidentes, incidentes y condiciones en faena, según Ley 19.587, Ley 24.557 y Dec. 249/07.</p>
  </div>

  <div class="row g-3 mb-4" id="kpis">
    <?php foreach ([['k_ev','Eventos (12 meses)'],['k_lti','Accidentes con baja'],['k_if','Índice de frecuencia'],['k_ig','Índice de gravedad'],['k_den','Denuncias ART pendientes'],['k_acc','Acciones vencidas']] as [$id,$lbl]): ?>
      <div class="col-6 col-md-4 col-xl-2"><div class="kpi"><div class="lbl"><?= $lbl ?></div><div class="val" id="<?= $id ?>">—</div><div class="sub" id="<?= $id ?>_s">&nbsp;</div></div></div>
    <?php endforeach; ?>
  </div>

  <div class="row g-3">
    <?php
    $cards = [
      ['index.php','bi-geo-alt','Cargar / editar incidentes','Georreferencia, clasificación normativa, lesión, denuncia ART, investigación, acciones y fotos.'],
      ['dashboard.php','bi-map','Dashboard y filtros','Mapa, tabla filtrable y exportación CSV.'],
      ['estadisticas.php','bi-graph-up','Estadísticas e índices','IF, IG, II, DMB, TRIFR, pirámide de Bird, Pareto y gráfico de control u.'],
      ['acciones.php','bi-list-check','Acciones correctivas','Seguimiento por responsable, vencimientos y verificación de eficacia.'],
      ['horas.php','bi-clock-history','Horas-hombre y dotación','Exposición mensual por empresa: base de todos los índices.'],
    ];
    foreach ($cards as [$href,$icon,$t,$d]): ?>
      <div class="col-12 col-md-6 col-xl-4">
        <a href="<?= $href ?>" class="text-decoration-none text-reset">
          <div class="cardish h-100"><div class="d-flex gap-3">
            <div class="fs-3 text-primary"><i class="bi <?= $icon ?>"></i></div>
            <div><div class="fw-bold"><?= $t ?></div><div class="small-muted"><?= $d ?></div></div>
          </div></div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php page_scripts(); ?>
<script>
(async () => {
  try {
    const { data: s } = await api('stats.php');
    document.getElementById('k_ev').textContent = s.totales.eventos;
    document.getElementById('k_lti').textContent = s.totales.lti;
    document.getElementById('k_lti_s').textContent = s.totales.dias_perdidos + ' días perdidos';
    document.getElementById('k_if').textContent = fmtN(s.indices.IF);
    document.getElementById('k_if_s').textContent = s.totales.hht ? 'por millón de HHT' : 'cargá horas-hombre';
    document.getElementById('k_ig').textContent = fmtN(s.indices.IG);
    document.getElementById('k_ig_s').textContent = 'días por mil HHT';
    document.getElementById('k_den').textContent = s.denuncia_art.pendientes;
    document.getElementById('k_den_s').textContent = s.denuncia_art.fuera_plazo + ' fuera de plazo (48 h)';
    document.getElementById('k_acc').textContent = s.acciones.vencidas;
    document.getElementById('k_acc_s').textContent = s.acciones.pct_cumplimiento !== null ? s.acciones.pct_cumplimiento + ' % cumplidas' : 'sin acciones';
  } catch (e) { toast(e.message, 'danger'); }
})();
</script>
</body></html>
