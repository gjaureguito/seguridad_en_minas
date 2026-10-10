<?php
declare(strict_types=1);
/* Encabezado y navegación compartidos por las páginas HTML. */

function page_head(string $title, array $extraHead = []): void {
    $csrf = csrf_token();
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<meta name="csrf-token" content="' . h($csrf) . '">';
    echo '<title>' . h($title) . ' — Seguridad Minera</title>';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">';
    echo '<link href="assets/app.css?v=2" rel="stylesheet">';
    foreach ($extraHead as $tag) echo $tag;
    echo '</head>';
}

function page_nav(array $user, string $active): void {
    $items = [
        'home.php'         => ['bi-house', 'Inicio'],
        'index.php'        => ['bi-geo-alt', 'Cargar'],
        'dashboard.php'    => ['bi-map', 'Dashboard'],
        'estadisticas.php' => ['bi-graph-up', 'Estadísticas'],
        'acciones.php'     => ['bi-list-check', 'Acciones'],
        'horas.php'        => ['bi-clock-history', 'Horas-hombre'],
    ];
    if ($user['role'] === 'ADMIN') $items['admin.php'] = ['bi-gear', 'Administración'];
    echo '<nav class="navbar navbar-expand-lg bg-white border-bottom sm-nav"><div class="container-fluid">';
    echo '<a class="navbar-brand fw-bold" href="home.php"><i class="bi bi-shield-check text-primary"></i> Seguridad Minera</a>';
    echo '<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>';
    echo '<div class="collapse navbar-collapse" id="nav"><ul class="navbar-nav me-auto">';
    foreach ($items as $href => [$icon, $label]) {
        $cls = $href === $active ? ' active fw-semibold' : '';
        echo '<li class="nav-item"><a class="nav-link' . $cls . '" href="' . $href . '"><i class="bi ' . $icon . '"></i> ' . $label . '</a></li>';
    }
    echo '</ul><span class="navbar-text small me-3">' . h($user['full_name']) . ' · <span class="badge text-bg-light border">' . h($user['role']) . '</span></span>';
    echo '<a class="btn btn-outline-secondary btn-sm" href="logout.php"><i class="bi bi-box-arrow-right"></i> Salir</a>';
    echo '</div></div></nav>';
    if (getenv('DEMO_DATA') === '1') {
        echo '<div class="text-center small py-1" style="background:#fef3c7;color:#78350f;border-bottom:1px solid #fde68a">'
           . '<i class="bi bi-info-circle"></i> Entorno de demostración: todos los datos (empresas, faenas, personas y hechos) son <b>ficticios</b>.</div>';
    }
}

function page_scripts(): void {
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>';
    echo '<script src="assets/common.js?v=3"></script>';
}
