<?php
declare(strict_types=1);
require __DIR__ . '/../../src/bootstrap.php';
require __DIR__ . '/../../src/stats.php';

api_guard(function () {
    require_login_api();
    $re = '/^\d{4}-\d{2}$/';
    $hasta = preg_match($re, $_GET['hasta'] ?? '') ? $_GET['hasta'] : date('Y-m');
    $desde = preg_match($re, $_GET['desde'] ?? '') ? $_GET['desde'] : date('Y-m', strtotime($hasta . '-01 -11 months'));
    $company = !empty($_GET['company_id']) ? (int)$_GET['company_id'] : null;
    $base = ($_GET['base'] ?? 'LTI') === 'TRI' ? 'TRI' : 'LTI';
    json_ok(['data' => compute_stats(db(), $desde, $hasta, $company, $base)]);
});
