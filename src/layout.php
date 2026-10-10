<?php
declare(strict_types=1);
/* Encabezado y navegación compartidos por las páginas HTML. */

function page_head(string $title, array $extraHead = []): void {
    $csrf = csrf_token();
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">';
    echo '<meta name="theme-color" content="#26343A">';
    echo '<meta name="csrf-token" content="' . h($csrf) . '">';
    echo '<title>' . h($title) . ' — Seguridad Minera</title>';
    echo '<link rel="icon" href="data:image/svg+xml,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32"><rect width="32" height="32" rx="7" fill="#F5A400"/><path d="M16 5 7 9v7c0 5 4 9.5 9 11 5-1.5 9-6 9-11V9z" fill="#26343A"/></svg>') . '">';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@600;700;800&display=swap" rel="stylesheet">';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">';
    echo '<link href="assets/app.css?v=5" rel="stylesheet">';
    foreach ($extraHead as $tag) echo $tag;
    echo '</head>';
}

function page_nav(array $user, string $active): void {
    $items = [
        'home.php'         => ['bi-house-door', 'Inicio'],
        'index.php'        => ['bi-plus-circle', 'Cargar'],
        'dashboard.php'    => ['bi-map', 'Mapa y registros'],
        'estadisticas.php' => ['bi-graph-up', 'Estadísticas'],
        'acciones.php'     => ['bi-list-check', 'Acciones'],
        'horas.php'        => ['bi-clock-history', 'Horas-hombre'],
    ];
    if ($user['role'] === 'ADMIN') $items['admin.php'] = ['bi-gear', 'Administración'];

    echo '<nav class="sm-nav d-flex align-items-center gap-2">';
    echo '<a class="brand me-2" href="home.php"><span class="brand-mark"><i class="bi bi-shield-fill-check"></i></span>Seguridad Minera</a>';
    // Escritorio: menú completo
    echo '<ul class="nav desk me-auto flex-nowrap">';
    foreach ($items as $href => [$icon, $label]) {
        $cls = $href === $active ? ' active' : '';
        echo '<li class="nav-item"><a class="nav-link' . $cls . '" href="' . $href . '"' . ($cls ? ' aria-current="page"' : '') . '><i class="bi ' . $icon . '"></i> ' . $label . '</a></li>';
    }
    echo '</ul>';
    echo '<span class="user desk text-nowrap">' . h($user['full_name']) . '<span class="role">' . h(ucfirst(strtolower($user['role']))) . '</span></span>';
    echo '<a class="btn btn-sm btn-salir desk" href="logout.php"><i class="bi bi-box-arrow-right"></i> Salir</a>';
    // Celular: menú secundario
    echo '<div class="dropdown d-lg-none ms-auto">';
    echo '<button class="btn btn-sm btn-salir" data-bs-toggle="dropdown" aria-label="Más opciones"><i class="bi bi-three-dots"></i></button>';
    echo '<ul class="dropdown-menu dropdown-menu-end">';
    echo '<li><span class="dropdown-item-text small text-muted">' . h($user['full_name']) . '</span></li>';
    echo '<li><a class="dropdown-item" href="horas.php"><i class="bi bi-clock-history"></i> Horas-hombre</a></li>';
    if ($user['role'] === 'ADMIN') echo '<li><a class="dropdown-item" href="admin.php"><i class="bi bi-gear"></i> Administración</a></li>';
    echo '<li><hr class="dropdown-divider"></li><li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right"></i> Salir</a></li>';
    echo '</ul></div>';
    echo '</nav>';

    if (getenv('DEMO_DATA') === '1') {
        echo '<div class="demo-strip"><i class="bi bi-info-circle"></i> Entorno de demostración: empresas, faenas, personas y hechos son ficticios.</div>';
    }

    // Celular: barra inferior al alcance del pulgar
    $tabs = [
        'home.php' => ['bi-house-door', 'Inicio', ''],
        'dashboard.php' => ['bi-map', 'Mapa', ''],
        'index.php' => ['bi-plus-lg', 'Cargar', ' cta'],
        'estadisticas.php' => ['bi-graph-up', 'Índices', ''],
        'acciones.php' => ['bi-list-check', 'Acciones', ''],
    ];
    echo '<nav class="tabbar" aria-label="Navegación principal">';
    foreach ($tabs as $href => [$icon, $label, $extra]) {
        $cls = ($href === $active ? ' active' : '') . $extra;
        echo '<a class="' . trim($cls) . '" href="' . $href . '"><i class="bi ' . $icon . '"></i><span>' . $label . '</span></a>';
    }
    echo '</nav>';
}

function page_scripts(): void {
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>';
    echo '<script src="assets/common.js?v=5"></script>';
}
