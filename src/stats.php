<?php
declare(strict_types=1);
/*
 * stats.php — índices de siniestralidad y control estadístico.
 *
 * Índices SRT / OIT (constantes 1.000 y 1.000.000):
 *   IF  Índice de frecuencia   = casos con baja × 1.000.000 / horas-hombre trabajadas
 *   IG  Índice de gravedad     = jornadas perdidas × 1.000 / horas-hombre trabajadas
 *   II  Índice de incidencia   = trabajadores siniestrados × 1.000 / trabajadores expuestos (promedio)
 *   IP  Índice de pérdida      = jornadas perdidas × 1.000 / trabajadores expuestos
 *   DMB Duración media de bajas = jornadas perdidas / casos con baja
 *   IM  Incidencia de fallecidos = fallecidos × 1.000.000 / trabajadores expuestos
 *   TRIFR = lesiones registrables × 1.000.000 / HHT
 *
 * Los accidentes in itinere se informan aparte (como hace la SRT), porque no
 * dependen de las condiciones de la faena.
 *
 * Gráfico de control u (Poisson) sobre la tasa mensual por millón de HHT:
 *   n_i = HHT_i / 1e6 ; u_i = c_i / n_i ; ū = Σc / Σn
 *   LCS_i = ū + 3·√(ū / n_i) ; LCI_i = máx(0, ū − 3·√(ū / n_i))
 *   Señales: punto fuera de límites, o 8 puntos seguidos del mismo lado de ū.
 */

function month_list(string $desde, string $hasta): array {
    $out = [];
    $d = new DateTimeImmutable($desde . '-01');
    $h = new DateTimeImmutable($hasta . '-01');
    if ($d > $h) [$d, $h] = [$h, $d];
    while ($d <= $h) { $out[] = $d->format('Y-m'); $d = $d->modify('+1 month'); }
    return $out;
}

function safe_div(float $a, float $b, float $k = 1.0): ?float {
    return $b > 0 ? round($a * $k / $b, 2) : null;
}

/** Pareto: ordena desc. y agrega % y % acumulado. */
function pareto(array $rows): array {
    usort($rows, fn($a, $b) => $b['n'] <=> $a['n']);
    $total = array_sum(array_column($rows, 'n'));
    $acc = 0;
    foreach ($rows as &$r) {
        $acc += $r['n'];
        $r['pct'] = $total ? round($r['n'] * 100 / $total, 1) : 0;
        $r['pct_acum'] = $total ? round($acc * 100 / $total, 1) : 0;
        $r['vital'] = ($r['pct_acum'] - $r['pct']) < 80; // pertenece al 80 % (pocos vitales)
    }
    return $rows;
}

function compute_stats(PDO $pdo, string $desde, string $hasta, ?int $companyId, string $base = 'LTI'): array {
    $months = month_list($desde, $hasta);
    $from = $months[0] . '-01';
    $to   = (new DateTimeImmutable(end($months) . '-01'))->modify('+1 month')->format('Y-m-d');

    $where = 'i.event_datetime >= :from AND i.event_datetime < :to';
    $params = [':from' => $from, ':to' => $to];
    if ($companyId) { $where .= ' AND i.company_id = :cid'; $params[':cid'] = $companyId; }

    $st = $pdo->prepare("
        SELECT i.id, i.event_datetime, i.tipo_contingencia, i.consecuencia, i.dias_perdidos,
               i.forma_code, i.agente_code, i.naturaleza_code, i.zona_code, i.riesgo_critico_code,
               i.pot_probabilidad, i.pot_consecuencia, i.company_id, i.turno,
               i.art_denunciado, i.art_fecha_denuncia,
               c.code AS category_code, c.label AS category_label, co.nombre AS company
        FROM incidents i
        JOIN categories c ON c.id = i.category_id
        LEFT JOIN companies co ON co.id = i.company_id
        WHERE $where");
    $st->execute($params);
    $inc = $st->fetchAll();

    // Exposición
    $wh = 'periodo >= :from AND periodo < :to';
    $wp = [':from' => $from, ':to' => $to];
    if ($companyId) { $wh .= ' AND company_id = :cid'; $wp[':cid'] = $companyId; }
    $st = $pdo->prepare("SELECT to_char(periodo,'YYYY-MM') AS m, company_id, hht::float AS hht, dotacion::float AS dot FROM work_hours WHERE $wh");
    $st->execute($wp);
    $hours = $st->fetchAll();

    $isAT  = fn($r) => $r['tipo_contingencia'] === 'ACCIDENTE_TRABAJO';
    $isLTI = fn($r) => $isAT($r) && in_array($r['consecuencia'], CONSEC_LTI, true);
    $isTRI = fn($r) => $isAT($r) && in_array($r['consecuencia'], CONSEC_TRI, true);

    // ---------- Serie mensual ----------
    $series = [];
    foreach ($months as $m) $series[$m] = ['mes' => $m, 'eventos' => 0, 'lti' => 0, 'tri' => 0, 'dias' => 0, 'hht' => 0.0, 'dotacion' => 0.0];
    foreach ($inc as $r) {
        $m = substr($r['event_datetime'], 0, 7);
        if (!isset($series[$m])) continue;
        $series[$m]['eventos']++;
        if ($isLTI($r)) { $series[$m]['lti']++; $series[$m]['dias'] += (int)$r['dias_perdidos']; }
        if ($isTRI($r)) $series[$m]['tri']++;
    }
    foreach ($hours as $h) {
        if (!isset($series[$h['m']])) continue;
        $series[$h['m']]['hht'] += $h['hht'];
        $series[$h['m']]['dotacion'] += $h['dot'];
    }

    // ---------- Totales ----------
    $lti = $tri = $dias = $fatales = 0; $itinere = 0; $ep = 0; $hipo = 0;
    foreach ($inc as $r) {
        if ($isLTI($r)) { $lti++; $dias += (int)$r['dias_perdidos']; }
        if ($isTRI($r)) $tri++;
        if ($isAT($r) && $r['consecuencia'] === 'FATAL') $fatales++;
        if ($r['tipo_contingencia'] === 'IN_ITINERE' && $r['consecuencia'] !== 'SIN_LESION') $itinere++;
        if ($r['tipo_contingencia'] === 'ENFERMEDAD_PROFESIONAL') $ep++;
        $rn = riesgo_nivel($r['pot_probabilidad'] ? (int)$r['pot_probabilidad'] : null, $r['pot_consecuencia'] ? (int)$r['pot_consecuencia'] : null);
        if ($rn && $rn['valor'] >= 15) $hipo++;
    }
    $hht = array_sum(array_column($series, 'hht'));
    $mesesConDatos = array_values(array_filter($series, fn($s) => $s['hht'] > 0));
    $dotProm = $mesesConDatos ? array_sum(array_column($mesesConDatos, 'dotacion')) / count($mesesConDatos) : 0.0;
    $nMeses = count($mesesConDatos);

    $indices = [
        'IF'    => safe_div($lti, $hht, 1e6),
        'IG'    => safe_div($dias, $hht, 1e3),
        'II'    => safe_div($lti, $dotProm, 1e3),
        'II_anualizado' => ($nMeses && $dotProm > 0) ? round($lti * 1e3 / $dotProm * 12 / $nMeses, 2) : null,
        'IP'    => safe_div($dias, $dotProm, 1e3),
        'DMB'   => safe_div($dias, $lti),
        'IM'    => safe_div($fatales, $dotProm, 1e6),
        'TRIFR' => safe_div($tri, $hht, 1e6),
    ];

    // ---------- Gráfico de control u ----------
    $field = $base === 'TRI' ? 'tri' : 'lti';
    $sumC = 0; $sumN = 0.0;
    foreach ($mesesConDatos as $s) { $sumC += $s[$field]; $sumN += $s['hht'] / 1e6; }
    $ubar = $sumN > 0 ? $sumC / $sumN : null;
    $control = [];
    $side = 0; $run = 0;
    foreach ($series as $s) {
        $row = ['mes' => $s['mes'], 'c' => $s[$field], 'hht' => $s['hht'], 'u' => null, 'lc' => $ubar !== null ? round($ubar, 2) : null, 'lcs' => null, 'lci' => null, 'senal' => null];
        if ($ubar !== null && $s['hht'] > 0) {
            $n = $s['hht'] / 1e6;
            $u = $s[$field] / $n;
            $sigma = sqrt($ubar / $n);
            $row['u']   = round($u, 2);
            $row['lcs'] = round($ubar + 3 * $sigma, 2);
            $row['lci'] = round(max(0, $ubar - 3 * $sigma), 2);
            if ($u > $row['lcs']) $row['senal'] = 'Sobre el límite superior (causa asignable)';
            elseif ($u < $row['lci']) $row['senal'] = 'Bajo el límite inferior (mejora real o subregistro)';
            $cur = $u > $ubar ? 1 : ($u < $ubar ? -1 : 0);
            if ($cur !== 0 && $cur === $side) $run++; else { $side = $cur; $run = $cur !== 0 ? 1 : 0; }
            if ($run >= 8 && !$row['senal']) $row['senal'] = $side > 0 ? '8+ meses sobre la media (tendencia adversa)' : '8+ meses bajo la media (mejora sostenida)';
        }
        $control[] = $row;
    }

    // ---------- Pirámide de Bird (1 : 10 : 30 : 600) ----------
    $bird = ['grave' => 0, 'leve' => 0, 'danio' => 0, 'incidente' => 0];
    foreach ($inc as $r) {
        $c = $r['consecuencia'];
        if (in_array($c, CONSEC_LTI, true)) $bird['grave']++;
        elseif (in_array($c, ['PRIMEROS_AUXILIOS', 'TRATAMIENTO_MEDICO', 'TRABAJO_RESTRINGIDO'], true)) $bird['leve']++;
        elseif ($r['tipo_contingencia'] === 'DANO_MATERIAL') $bird['danio']++;
        elseif ($r['tipo_contingencia'] === 'INCIDENTE' || $r['category_code'] === 'CUASI_INCIDENTE') $bird['incidente']++;
    }
    $g = max(1, $bird['grave']);
    $birdOut = [
        ['nivel' => 'Lesión grave / con baja', 'n' => $bird['grave'], 'ratio' => round($bird['grave'] / $g, 1), 'teorico' => 1],
        ['nivel' => 'Lesión leve',              'n' => $bird['leve'],  'ratio' => round($bird['leve'] / $g, 1),  'teorico' => 10],
        ['nivel' => 'Daño a la propiedad',      'n' => $bird['danio'], 'ratio' => round($bird['danio'] / $g, 1), 'teorico' => 30],
        ['nivel' => 'Incidente sin lesión/daño','n' => $bird['incidente'], 'ratio' => round($bird['incidente'] / $g, 1), 'teorico' => 600],
    ];

    // ---------- Pareto ----------
    $cat = [];
    foreach ($pdo->query('SELECT kind, code, label FROM catalogs') as $c) $cat[$c['kind']][$c['code']] = $c['label'];
    $group = function (array $rows, callable $key, callable $label) {
        $acc = [];
        foreach ($rows as $r) {
            $k = $key($r); if ($k === null || $k === '') $k = '—';
            $acc[$k] ??= ['code' => $k, 'label' => $k === '—' ? 'Sin tipificar' : $label($k, $r), 'n' => 0];
            $acc[$k]['n']++;
        }
        return pareto(array_values($acc));
    };
    $lesiones = array_values(array_filter($inc, fn($r) => $r['consecuencia'] !== 'SIN_LESION'));
    $paretos = [
        'forma'      => $group($lesiones, fn($r) => $r['forma_code'],      fn($k) => $cat['FORMA'][$k] ?? $k),
        'agente'     => $group($lesiones, fn($r) => $r['agente_code'],     fn($k) => $cat['AGENTE'][$k] ?? $k),
        'naturaleza' => $group($lesiones, fn($r) => $r['naturaleza_code'], fn($k) => $cat['NATURALEZA'][$k] ?? $k),
        'zona'       => $group($lesiones, fn($r) => $r['zona_code'],       fn($k) => $cat['ZONA'][$k] ?? $k),
        'riesgo'     => $group($inc, fn($r) => $r['riesgo_critico_code'],  fn($k) => $cat['RIESGO_CRITICO'][$k] ?? $k),
        'categoria'  => $group($inc, fn($r) => $r['category_code'],        fn($k, $r) => $r['category_label']),
    ];

    // ---------- Distribución temporal ----------
    $hora = array_fill(0, 24, 0); $dow = array_fill(0, 7, 0); $turno = [];
    foreach ($inc as $r) {
        $t = strtotime($r['event_datetime']);
        $hora[(int)date('G', $t)]++;
        $dow[(int)date('N', $t) - 1]++;
        $k = $r['turno'] ?: 'Sin dato';
        $turno[$k] = ($turno[$k] ?? 0) + 1;
    }

    // ---------- Por empresa ----------
    $byCo = [];
    foreach ($inc as $r) {
        $k = $r['company_id'] ?? 0;
        $byCo[$k] ??= ['company_id' => $k, 'empresa' => $r['company'] ?? 'Sin empresa', 'eventos' => 0, 'lti' => 0, 'tri' => 0, 'dias' => 0, 'hht' => 0.0];
        $byCo[$k]['eventos']++;
        if ($isLTI($r)) { $byCo[$k]['lti']++; $byCo[$k]['dias'] += (int)$r['dias_perdidos']; }
        if ($isTRI($r)) $byCo[$k]['tri']++;
    }
    $names = [];
    foreach ($pdo->query('SELECT id, nombre FROM companies') as $c) $names[$c['id']] = $c['nombre'];
    foreach ($hours as $h) {
        $k = $h['company_id'];
        $byCo[$k] ??= ['company_id' => $k, 'empresa' => $names[$k] ?? ('#' . $k), 'eventos' => 0, 'lti' => 0, 'tri' => 0, 'dias' => 0, 'hht' => 0.0];
        $byCo[$k]['hht'] += $h['hht'];
    }
    foreach ($byCo as &$c) {
        $c['IF'] = safe_div($c['lti'], $c['hht'], 1e6);
        $c['IG'] = safe_div($c['dias'], $c['hht'], 1e3);
        $c['TRIFR'] = safe_div($c['tri'], $c['hht'], 1e6);
    }
    unset($c);
    $byCo = array_values($byCo);
    usort($byCo, fn($a, $b) => ($b['IF'] ?? -1) <=> ($a['IF'] ?? -1));

    // ---------- Cumplimiento de denuncia a la ART (48 h, Res. SRT 525/15) ----------
    $den = ['a_denunciar' => 0, 'en_plazo' => 0, 'fuera_plazo' => 0, 'pendientes' => 0];
    foreach ($inc as $r) {
        if (!in_array($r['tipo_contingencia'], ['ACCIDENTE_TRABAJO', 'IN_ITINERE', 'ENFERMEDAD_PROFESIONAL'], true)) continue;
        if ($r['consecuencia'] === 'SIN_LESION') continue;
        $den['a_denunciar']++;
        if (!$r['art_denunciado'] || !$r['art_fecha_denuncia']) { $den['pendientes']++; continue; }
        $hrs = (strtotime($r['art_fecha_denuncia']) - strtotime($r['event_datetime'])) / 3600;
        $hrs <= 48 ? $den['en_plazo']++ : $den['fuera_plazo']++;
    }

    // ---------- Acciones correctivas ----------
    $aw = 'i.event_datetime >= :from AND i.event_datetime < :to';
    if ($companyId) $aw .= ' AND i.company_id = :cid';
    $st = $pdo->prepare("
        SELECT a.estado, a.jerarquia,
               (a.estado IN ('PENDIENTE','EN_CURSO') AND a.fecha_compromiso < CURRENT_DATE) AS vencida
        FROM incident_actions a JOIN incidents i ON i.id = a.incident_id WHERE $aw");
    $st->execute($params);
    $acc = ['total' => 0, 'vencidas' => 0, 'por_estado' => [], 'por_jerarquia' => []];
    foreach ($st as $a) {
        if ($a['estado'] === 'ANULADA') continue;
        $acc['total']++;
        if ($a['vencida']) $acc['vencidas']++;
        $acc['por_estado'][$a['estado']] = ($acc['por_estado'][$a['estado']] ?? 0) + 1;
        $acc['por_jerarquia'][$a['jerarquia']] = ($acc['por_jerarquia'][$a['jerarquia']] ?? 0) + 1;
    }
    $cerradas = ($acc['por_estado']['CUMPLIDA'] ?? 0) + ($acc['por_estado']['VERIFICADA'] ?? 0);
    $acc['pct_cumplimiento'] = $acc['total'] ? round($cerradas * 100 / $acc['total'], 1) : null;

    return [
        'periodo'  => ['desde' => $months[0], 'hasta' => end($months), 'meses' => count($months), 'meses_con_hht' => $nMeses],
        'base_control' => $field === 'tri' ? 'TRI' : 'LTI',
        'totales'  => [
            'eventos' => count($inc), 'lti' => $lti, 'tri' => $tri, 'dias_perdidos' => $dias, 'fatales' => $fatales,
            'in_itinere' => $itinere, 'enfermedades_prof' => $ep, 'alto_potencial' => $hipo,
            'hht' => round($hht, 2), 'dotacion_promedio' => round($dotProm, 1),
        ],
        'indices'  => $indices,
        'serie'    => array_values($series),
        'control'  => $control,
        'bird'     => $birdOut,
        'pareto'   => $paretos,
        'hora'     => $hora,
        'dia_semana' => $dow,
        'turno'    => $turno,
        'empresas' => $byCo,
        'denuncia_art' => $den,
        'acciones' => $acc,
    ];
}
