<?php
declare(strict_types=1);
/*
 * bin/demo_seed.php — carga DATOS FICTICIOS de demostración.
 *
 * Todo es inventado: empresas, faenas, personas, ART y hechos. No corresponde
 * a ninguna mina, empresa ni trabajador real. Se puede publicar sin problema.
 *
 * Uso:
 *   - Automático: variable DEMO_DATA=1 → migrate.php lo ejecuta si no hay incidentes.
 *   - Manual:     php bin/demo_seed.php          (solo si la base no tiene incidentes)
 *                 php bin/demo_seed.php --force  (agrega aunque haya datos)
 */

if (PHP_SAPI === 'cli' && realpath($argv[0] ?? '') === realpath(__FILE__)) {
    require __DIR__ . '/../src/bootstrap.php';
    $pdo = db();
    $n = (int)$pdo->query('SELECT count(*) FROM incidents')->fetchColumn();
    if ($n > 0 && !in_array('--force', $argv, true)) { echo "La base ya tiene {$n} incidentes. Usá --force para agregar igual.\n"; exit(0); }
    demo_seed($pdo);
}

function demo_seed(PDO $pdo): void {
    mt_srand(20261009); // determinístico: siempre genera el mismo set
    $pick = fn(array $a) => $a[mt_rand(0, count($a) - 1)];
    $chance = fn(float $p) => mt_rand() / mt_getrandmax() < $p;

    $pdo->beginTransaction();

    /* ---------------- Empresas ficticias ---------------- */
    $companies = [
        ['Minera Cerro Aurora S.A. (ficticia)', 'TITULAR', '30-00000001-0', 'ART Andina Demo'],
        ['Transportes Quebrada Seca S.R.L. (ficticia)', 'CONTRATISTA', '30-00000002-0', 'ART Andina Demo'],
        ['Perforaciones del Valle S.A. (ficticia)', 'CONTRATISTA', '30-00000003-0', 'Cuyo Riesgos ART Demo'],
        ['Servicios Mineros El Mirador S.A. (ficticia)', 'CONTRATISTA', '30-00000004-0', 'Cuyo Riesgos ART Demo'],
        ['Montajes Industriales Los Algarrobos (ficticia)', 'SUBCONTRATISTA', '30-00000005-0', 'Precordillera ART Demo'],
    ];
    $insCo = $pdo->prepare("INSERT INTO companies (nombre, tipo, cuit, art, activo) VALUES (:n,:t,:c,:a,TRUE)
                            ON CONFLICT (nombre) DO UPDATE SET tipo=EXCLUDED.tipo RETURNING id");
    $coIds = []; $coArt = [];
    foreach ($companies as [$n, $t, $c, $a]) { $insCo->execute([':n' => $n, ':t' => $t, ':c' => $c, ':a' => $a]); $id = (int)$insCo->fetchColumn(); $coIds[] = $id; $coArt[$id] = $a; }

    /* ---------------- Exposición: últimos 12 meses ---------------- */
    // [HHT base, dotación base] por empresa
    $expo = [[52000, 270], [21000, 110], [14500, 72], [11000, 58], [5200, 28]];
    $start = (new DateTimeImmutable('first day of this month'))->modify('-11 months');
    $months = [];
    for ($i = 0; $i < 12; $i++) $months[] = $start->modify("+{$i} months");
    $insH = $pdo->prepare("INSERT INTO work_hours (company_id, periodo, hht, dotacion) VALUES (:c,:p,:h,:d)
                           ON CONFLICT (company_id, periodo) DO UPDATE SET hht=EXCLUDED.hht, dotacion=EXCLUDED.dotacion");
    foreach ($months as $k => $m) {
        foreach ($coIds as $j => $cid) {
            // variación estacional suave + parada de planta en el mes 6
            $f = 1 + 0.06 * sin($k / 2) + (mt_rand(-40, 40) / 1000) - ($k === 6 ? 0.18 : 0);
            $insH->execute([':c' => $cid, ':p' => $m->format('Y-m-01'), ':h' => round($expo[$j][0] * $f), ':d' => round($expo[$j][1] * $f)]);
        }
    }

    /* ---------------- Catálogos ---------------- */
    $cat = []; foreach ($pdo->query('SELECT id, code FROM categories') as $r) $cat[$r['code']] = (int)$r['id'];
    $sev = []; foreach ($pdo->query('SELECT id, code FROM severities') as $r) $sev[$r['code']] = (int)$r['id'];
    $admin = (int)($pdo->query("SELECT id FROM users WHERE role='ADMIN' ORDER BY id LIMIT 1")->fetchColumn() ?: 0) ?: null;

    /* ---------------- Faenas ficticias (zona precordillerana genérica) ---------------- */
    $faenas = [
        ['Faena Cerro Aurora (ficticia)', -30.512, -69.318, ['Rajo Norte', 'Rajo Sur', 'Botadero Este', 'Taller de camiones', 'Polvorín']],
        ['Planta Quebrada Seca (ficticia)', -30.548, -69.262, ['Chancado primario', 'Molienda', 'Lixiviación', 'Laboratorio', 'Bodega de reactivos']],
        ['Campamento y accesos (ficticio)', -30.585, -69.205, ['Campamento', 'Camino de acceso km 12', 'Comedor', 'Garita']],
    ];

    /* ---------------- Personas ficticias ---------------- */
    $nombres = ['Martín', 'Lucía', 'Diego', 'Carla', 'Javier', 'Sofía', 'Pablo', 'Valeria', 'Nicolás', 'Florencia', 'Matías', 'Romina', 'Gustavo', 'Paula', 'Hernán', 'Natalia', 'Ezequiel', 'Daniela'];
    $apellidos = ['Ficticio', 'Ejemplar', 'Demostración', 'Modelo', 'Prueba', 'Muestra', 'Simulado', 'Ensayo'];
    $puestos = ['Operador de camión', 'Perforista', 'Mecánico', 'Electricista', 'Ayudante de planta', 'Operador de cargador', 'Soldador', 'Supervisor de turno', 'Técnico de laboratorio', 'Chofer de camioneta'];
    $persona = fn() => $pick($nombres) . ' ' . $pick($apellidos) . ' ' . chr(65 + mt_rand(0, 25)) . '.';

    /* ---------------- Plantillas de eventos ficticios ---------------- */
    // tipo: [titulo, descripcion, categoria, riesgo, forma, agente, naturaleza, zona, faena idx]
    $lesiones = [
        ['Golpe en mano al cambiar dientes de pala', 'Durante el cambio de dientes del balde, el pasador se liberó y golpeó la mano izquierda del mecánico.', 'INCIDENTE_CON_LESION', 'ENERGIA_PELIGROSA', '3', '2.4', '60', '4.1', 0],
        ['Esguince de tobillo al descender de camión', 'El operador descendió de la cabina sin mantener tres puntos de apoyo y apoyó mal el pie en el último peldaño.', 'INCIDENTE_CON_LESION', 'INTERACCION_EQUIPOS', '1.1', '2.1', '25', '5.1', 0],
        ['Corte en antebrazo con plancha metálica', 'Al manipular una plancha de desgaste sin guantes anticorte, el borde produjo un corte en el antebrazo derecho.', 'INCIDENTE_CON_LESION', 'GUARDAS', '3', '4', '41', '4', 1],
        ['Proyección de partícula en ojo en molienda', 'Al soplar con aire comprimido un filtro, una partícula ingresó al ojo derecho por no usar antiparras.', 'INCIDENTE_CON_LESION', 'SUSTANCIAS_PELIGROSAS', '8', '3.2', '50', '1.1', 1],
        ['Lumbalgia al levantar bolsa de reactivo', 'El ayudante levantó una bolsa de 40 kg desde el piso girando el tronco, sin ayuda mecánica.', 'INCIDENTE_CON_LESION', 'OTRO', '5', '4.2', '25', '3', 1],
        ['Caída a nivel en pasarela con derrame', 'Resbalón en pasarela de chancado por derrame de lodo no señalizado. Contusión en rodilla.', 'INCIDENTE_CON_LESION', 'TRABAJO_ALTURA', '1.2', '5', '60', '5', 1],
        ['Atrapamiento de dedo en polea de cinta', 'Al intentar retirar material adherido con la cinta en movimiento, el dedo quedó atrapado entre polea y banda.', 'INCIDENTE_CON_LESION', 'GUARDAS', '4', '1.2', '10', '4.2', 1],
        ['Quemadura con escoria en soldadura', 'Escoria caliente ingresó por la manga del soldador durante soldadura sobre cabeza.', 'INCIDENTE_CON_LESION', 'INCENDIO', '6', '3', '70', '4', 0],
    ];
    $cuasis = [
        ['Camión liviano ingresa a zona de carga sin autorización', 'Camioneta ingresó al área de carguío con pala operando, sin comunicación radial con el operador.', 'CUASI_INCIDENTE', 'INTERACCION_EQUIPOS', 0],
        ['Desprendimiento de roca desde banco superior', 'Caída de bloques de 0,5 m³ desde banco 3 a 15 m de un equipo de perforación. Sin personas expuestas.', 'EVENTO_GEOTECNICO', 'CAIDA_ROCAS', 0],
        ['Carga suspendida pasa sobre personal', 'Durante izaje de un motor, la carga pasó sobre dos trabajadores que estaban fuera del área delimitada.', 'CUASI_INCIDENTE', 'IZAJE', 1],
        ['Tiro quedado detectado en frente de voladura', 'Al revisar el frente tras la voladura se detectó un tiro sin detonar. Se aisló el área y se aplicó el procedimiento.', 'CUASI_INCIDENTE', 'EXPLOSIVOS', 0],
        ['Bloqueo incompleto en tablero de bomba', 'Se encontró un candado personal faltante en un tablero intervenido por dos técnicos.', 'ACTO_INSEGURO', 'ENERGIA_PELIGROSA', 1],
        ['Ingreso a estanque sin medición de gases', 'Un ayudante comenzó a ingresar a un estanque de solución sin permiso ni medición previa. Lo detuvo el supervisor.', 'ACTO_INSEGURO', 'ESPACIO_CONFINADO', 1],
        ['Baranda floja en pasarela de molienda', 'Inspección detectó baranda con anclajes sueltos en pasarela a 4 m de altura.', 'CONDICION_INSEGURA', 'TRABAJO_ALTURA', 1],
        ['Exceso de velocidad en camino de acceso', 'Control GPS registró camioneta a 78 km/h en tramo de 40 km/h.', 'ACTO_INSEGURO', 'VEHICULOS_LIVIANOS', 2],
        ['Alarma de retroceso inoperativa en cargador', 'Inspección pre-uso detectó alarma de retroceso sin funcionar. Equipo detenido.', 'FALLA_EQUIPO', 'INTERACCION_EQUIPOS', 0],
        ['Polvo en suspensión sobre límite en chancado', 'Monitoreo indicó concentración de polvo superior al valor de referencia interno por falla del aspersor.', 'PELIGRO_AMBIENTAL', 'ATMOSFERA', 1],
        ['Extintor vencido en camioneta', 'Inspección vehicular detectó extintor con carga vencida.', 'CONDICION_INSEGURA', 'INCENDIO', 2],
        ['Grieta de tracción en coronamiento de talud', 'Topografía detectó grieta de 8 m en coronamiento del rajo sur. Se restringió el tránsito.', 'EVENTO_GEOTECNICO', 'CAIDA_ROCAS', 0],
    ];
    $danios = [
        ['Camión golpea berma y daña neumático', 'Camión de extracción golpeó berma de seguridad en curva, con daño en neumático delantero.', 'FALLA_EQUIPO', 'INTERACCION_EQUIPOS', 0],
        ['Retroceso de camioneta contra poste de iluminación', 'Al maniobrar en estacionamiento, una camioneta retrocedió contra un poste. Daño en paragolpes.', 'INCIDENTE_SIN_LESION', 'VEHICULOS_LIVIANOS', 2],
        ['Rotura de manguera hidráulica en perforadora', 'Rotura de manguera con derrame de 20 L de aceite contenido en bandeja.', 'FALLA_EQUIPO', 'SUSTANCIAS_PELIGROSAS', 0],
    ];

    /* ---------------- Inserción ---------------- */
    $cols = 'title, description, category_id, severity_id, company_id, event_datetime, lat, lng, faena, sector, turno,
             tipo_contingencia, consecuencia, dias_perdidos, fecha_inicio_baja, fecha_alta, forma_code, agente_code,
             naturaleza_code, zona_code, riesgo_critico_code, pot_probabilidad, pot_consecuencia, art_denunciado,
             art_nombre, art_nro_siniestro, art_fecha_denuncia, autoridad_notificada, autoridad_fecha, estado, created_by';
    $insI = $pdo->prepare("INSERT INTO incidents ($cols) VALUES (:t,:d,:cat,:sev,:co,:dt,:lat,:lng,:fa,:se,:tu,:tc,:con,:dias,:fb,:fal,
                            :fo,:ag,:na,:zo,:rc,:pp,:pc,:artd,:artn,:arts,:artf,:autn,:autf,:est,:uid) RETURNING id");
    $insIC = $pdo->prepare('INSERT INTO incident_companies (incident_id, company_id, role) VALUES (:i,:c,:r)');
    $insP  = $pdo->prepare('INSERT INTO persons (full_name, company_id, puesto) VALUES (:f,:c,:p)
                            ON CONFLICT ((lower(full_name)), (COALESCE(company_id, 0))) DO UPDATE SET puesto = EXCLUDED.puesto RETURNING id');
    $insIP = $pdo->prepare('INSERT INTO incident_persons (incident_id, person_id, role) VALUES (:i,:p,:r)');
    $insInv = $pdo->prepare("INSERT INTO incident_investigations (incident_id, metodo, equipo, hechos, actos_subestandar, condiciones_subestandar,
                             factores_personales, factores_trabajo, falta_control, porques, conclusiones, fecha_inicio, fecha_cierre)
                             VALUES (:i,:m,:eq,:h,:a,:c,:fp,:ft,:fc,CAST(:pq AS JSONB),:co,:fi,:fci)");
    $insA = $pdo->prepare("INSERT INTO incident_actions (incident_id, descripcion, tipo, jerarquia, responsable, fecha_compromiso, estado, fecha_cumplimiento, verificacion)
                           VALUES (:i,:d,:t,:j,:r,:fc,:e,:fcu,:v)");

    $now = new DateTimeImmutable();
    $siniestro = 1;
    $mkDate = function (DateTimeImmutable $m) use ($now) {
        $last = (int)$m->format('t');
        $maxDay = ($m->format('Y-m') === $now->format('Y-m')) ? max(1, (int)$now->format('j') - 1) : $last;
        // horas con más eventos al inicio del turno y de madrugada (fatiga)
        $h = [6, 7, 8, 9, 10, 10, 11, 13, 14, 15, 16, 18, 20, 22, 2, 3, 4][mt_rand(0, 16)];
        return $m->setDate((int)$m->format('Y'), (int)$m->format('n'), mt_rand(1, $maxDay))->setTime($h, mt_rand(0, 59));
    };
    $place = function (int $fi) use ($faenas, $pick) {
        [$name, $lat, $lng, $sectors] = $faenas[$fi];
        return [$name, $pick($sectors), $lat + mt_rand(-90, 90) / 10000, $lng + mt_rand(-90, 90) / 10000];
    };
    $turnoDe = fn(DateTimeImmutable $d) => ((int)$d->format('G') >= 7 && (int)$d->format('G') < 19) ? 'DIA' : 'NOCHE';

    $created = 0;
    $photoTargets = [];

    // Accidentes con baja por mes: el mes 8 tiene un pico para que el gráfico de control muestre una señal
    $ltiPorMes = [0, 1, 0, 1, 0, 1, 0, 0, 3, 0, 1, 0];
    $triPorMes = [1, 0, 1, 1, 0, 1, 1, 0, 1, 1, 0, 1];

    foreach ($months as $k => $m) {
        $nEventos = [];
        for ($x = 0; $x < $ltiPorMes[$k]; $x++) $nEventos[] = 'LTI';
        for ($x = 0; $x < $triPorMes[$k]; $x++) $nEventos[] = 'TRI';
        for ($x = 0, $c = mt_rand(1, 2); $x < $c; $x++) $nEventos[] = 'LEVE';
        for ($x = 0, $c = mt_rand(5, 9); $x < $c; $x++) $nEventos[] = 'CUASI';
        if ($chance(0.5)) $nEventos[] = 'DANIO';
        if ($k === 3 || $k === 9) $nEventos[] = 'ITINERE';

        foreach ($nEventos as $kind) {
            $dt = $mkDate($m);
            if ($dt > $now) $dt = $now->modify('-1 day');
            $co = $pick($coIds);
            $art = $coArt[$co];
            $v = [':artd' => 'false', ':artn' => null, ':arts' => null, ':artf' => null, ':autn' => 'false', ':autf' => null,
                  ':dias' => 0, ':fb' => null, ':fal' => null, ':fo' => null, ':ag' => null, ':na' => null, ':zo' => null, ':uid' => $admin];
            $old = $dt < $now->modify('-45 days');

            if (in_array($kind, ['LTI', 'TRI', 'LEVE'], true)) {
                $tpl = $pick($lesiones);
                [$fa, $se, $lat, $lng] = $place($tpl[8]);
                $consec = ['LTI' => 'CON_BAJA', 'TRI' => $pick(['TRATAMIENTO_MEDICO', 'TRABAJO_RESTRINGIDO']), 'LEVE' => 'PRIMEROS_AUXILIOS'][$kind];
                $v += [':t' => $tpl[0], ':d' => $tpl[1] . ' (Caso ficticio de demostración.)', ':cat' => $cat[$tpl[2]],
                       ':sev' => $sev[$kind === 'LTI' ? 'ALTA' : ($kind === 'TRI' ? 'MEDIA' : 'BAJA')], ':rc' => $tpl[3],
                       ':tc' => 'ACCIDENTE_TRABAJO', ':con' => $consec];
                $v[':fo'] = $tpl[4]; $v[':ag'] = $tpl[5]; $v[':na'] = $tpl[6]; $v[':zo'] = $tpl[7];
                $v[':pp'] = mt_rand(2, 4); $v[':pc'] = $kind === 'LTI' ? mt_rand(3, 5) : mt_rand(2, 3);
                if ($kind === 'LTI') {
                    $dias = $pick([3, 5, 7, 9, 12, 15, 21, 30, 45]);
                    $fb = $dt->modify('+1 day');
                    $v[':dias'] = $dias; $v[':fb'] = $fb->format('Y-m-d'); $v[':fal'] = $fb->modify("+{$dias} days")->format('Y-m-d');
                }
                // denuncia ART: casi siempre en plazo; algunos fuera de plazo o pendientes (para ver los indicadores)
                $r = mt_rand(1, 10);
                if ($r <= 7 || $old) {
                    $hrs = $r === 7 ? 70 : mt_rand(2, 30);
                    $v[':artd'] = 'true'; $v[':artn'] = $art; $v[':arts'] = sprintf('DEMO-%s-%05d', $dt->format('Y'), $siniestro++);
                    $v[':artf'] = $dt->modify("+{$hrs} hours")->format('Y-m-d H:i:s');
                }
                if ($kind === 'LTI') { $v[':autn'] = 'true'; $v[':autf'] = $dt->modify('+20 hours')->format('Y-m-d H:i:s'); }
                $estado = $old ? 'CERRADO' : $pick(['EN_INVESTIGACION', 'ACCIONES_PENDIENTES']);
            } elseif ($kind === 'ITINERE') {
                [$fa, $se, $lat, $lng] = $place(2);
                $dias = 10; $fb = $dt->modify('+1 day');
                $v += [':t' => 'Choque en ruta al regresar del turno (in itinere)', ':d' => 'El trabajador sufrió un choque por alcance en la ruta provincial camino a su domicilio. (Caso ficticio de demostración.)',
                       ':cat' => $cat['INCIDENTE_CON_LESION'], ':sev' => $sev['MEDIA'], ':rc' => 'VEHICULOS_LIVIANOS', ':tc' => 'IN_ITINERE', ':con' => 'CON_BAJA',
                       ':pp' => 3, ':pc' => 3];
                $v[':fo'] = '9.1'; $v[':ag'] = '2.2'; $v[':na'] = '30'; $v[':zo'] = '2';
                $v[':dias'] = $dias; $v[':fb'] = $fb->format('Y-m-d'); $v[':fal'] = $fb->modify("+{$dias} days")->format('Y-m-d');
                $v[':artd'] = 'true'; $v[':artn'] = $art; $v[':arts'] = sprintf('DEMO-%s-%05d', $dt->format('Y'), $siniestro++);
                $v[':artf'] = $dt->modify('+12 hours')->format('Y-m-d H:i:s');
                $dt = $dt->setTime(19, mt_rand(20, 59));
                $estado = 'CERRADO';
            } elseif ($kind === 'DANIO') {
                $tpl = $pick($danios);
                [$fa, $se, $lat, $lng] = $place($tpl[4]);
                $v += [':t' => $tpl[0], ':d' => $tpl[1] . ' (Caso ficticio de demostración.)', ':cat' => $cat[$tpl[2]], ':sev' => $sev['MEDIA'],
                       ':rc' => $tpl[3], ':tc' => 'DANO_MATERIAL', ':con' => 'SIN_LESION', ':pp' => mt_rand(2, 4), ':pc' => mt_rand(2, 4)];
                $estado = $old ? 'CERRADO' : 'ACCIONES_PENDIENTES';
            } else {
                $tpl = $pick($cuasis);
                [$fa, $se, $lat, $lng] = $place($tpl[4]);
                $hipo = in_array($tpl[3], ['CAIDA_ROCAS', 'EXPLOSIVOS', 'IZAJE', 'ESPACIO_CONFINADO', 'ENERGIA_PELIGROSA', 'INTERACCION_EQUIPOS'], true);
                $v += [':t' => $tpl[0], ':d' => $tpl[1] . ' (Caso ficticio de demostración.)', ':cat' => $cat[$tpl[2]],
                       ':sev' => $sev[$hipo ? 'POTENCIAL' : $pick(['BAJA', 'MEDIA'])], ':rc' => $tpl[3],
                       ':tc' => in_array($tpl[2], ['PELIGRO_AMBIENTAL'], true) ? 'AMBIENTAL' : 'INCIDENTE', ':con' => 'SIN_LESION',
                       ':pp' => $hipo ? mt_rand(3, 4) : mt_rand(1, 3), ':pc' => $hipo ? mt_rand(4, 5) : mt_rand(1, 3)];
                $estado = $old ? 'CERRADO' : $pick(['ABIERTO', 'ACCIONES_PENDIENTES', 'CERRADO']);
            }

            $insI->execute($v + [':co' => $co, ':dt' => $dt->format('Y-m-d H:i:s'), ':lat' => round($lat, 6), ':lng' => round($lng, 6),
                                 ':fa' => $fa, ':se' => $se, ':tu' => $turnoDe($dt), ':est' => $estado]);
            $iid = (int)$insI->fetchColumn();
            $created++;

            // Empresas involucradas
            $insIC->execute([':i' => $iid, ':c' => $co, ':r' => 'RESPONSABLE']);
            if ($co !== $coIds[0]) $insIC->execute([':i' => $iid, ':c' => $coIds[0], ':r' => 'PROPIETARIA']);

            // Personas (ficticias)
            $conLesion = $v[':con'] !== 'SIN_LESION';
            $insP->execute([':f' => $persona(), ':c' => $co, ':p' => $pick($puestos)]);
            $insIP->execute([':i' => $iid, ':p' => (int)$insP->fetchColumn(), ':r' => $conLesion ? 'lesionado' : $pick(['operador', 'conductor'])]);
            if ($chance(0.6)) { $insP->execute([':f' => $persona(), ':c' => $co, ':p' => 'Supervisor de turno']); $insIP->execute([':i' => $iid, ':p' => (int)$insP->fetchColumn(), ':r' => 'supervisor']); }
            if ($chance(0.4)) { $insP->execute([':f' => $persona(), ':c' => $pick($coIds), ':p' => $pick($puestos)]); $insIP->execute([':i' => $iid, ':p' => (int)$insP->fetchColumn(), ':r' => 'testigo']); }

            // Investigación para lesiones y eventos de alto potencial
            $alto = ($v[':pp'] ?? 0) * ($v[':pc'] ?? 0) >= 15;
            if (in_array($kind, ['LTI', 'TRI', 'ITINERE'], true) || $alto) {
                $inv = demo_investigacion($v[':rc'], $pick);
                $insInv->execute([':i' => $iid, ':m' => $inv['metodo'], ':eq' => 'Supervisor del área, técnico de HyS, representante del Comité (personas ficticias)',
                    ':h' => $inv['hechos'], ':a' => $inv['actos'], ':c' => $inv['cond'], ':fp' => $inv['fp'], ':ft' => $inv['ft'], ':fc' => $inv['fc'],
                    ':pq' => json_encode($inv['porques'], JSON_UNESCAPED_UNICODE), ':co' => $inv['concl'],
                    ':fi' => $dt->modify('+1 day')->format('Y-m-d'), ':fci' => $estado === 'CERRADO' ? $dt->modify('+9 days')->format('Y-m-d') : null]);
                // Acciones
                foreach ($inv['acciones'] as $ai => [$desc, $jer]) {
                    $comp = $dt->modify('+' . (15 + 15 * $ai) . ' days');
                    if ($estado === 'CERRADO') { $e = $chance(0.6) ? 'VERIFICADA' : 'CUMPLIDA'; }
                    else { $e = $comp < $now ? $pick(['PENDIENTE', 'EN_CURSO', 'CUMPLIDA']) : $pick(['PENDIENTE', 'EN_CURSO']); }
                    $fcu = in_array($e, ['CUMPLIDA', 'VERIFICADA'], true) ? min($comp, $now)->format('Y-m-d') : null;
                    $insA->execute([':i' => $iid, ':d' => $desc, ':t' => $ai === 0 ? 'CORRECTIVA' : 'PREVENTIVA', ':j' => $jer,
                        ':r' => $pick(['Jefe de mantenimiento', 'Supervisor de mina', 'Jefe de planta', 'Responsable de HyS', 'Jefe de transporte']),
                        ':fc' => $comp->format('Y-m-d'), ':e' => $e, ':fcu' => $fcu,
                        ':v' => $e === 'VERIFICADA' ? 'Inspección de seguimiento a 30 días: el control está implementado y en uso. (demo)' : null]);
                }
                if ($kind === 'LTI' && count($photoTargets) < 6) $photoTargets[] = [$iid, $v[':t']];
            }
        }
    }

    // Fotos ilustrativas generadas (no son fotos reales)
    if (function_exists('imagecreatetruecolor')) {
        $insPh = $pdo->prepare('INSERT INTO incident_photos (incident_id, filename, mime, data) VALUES (:i,:f,:m,:d)');
        foreach ($photoTargets as $n => [$iid, $title]) {
            $bytes = demo_foto($title, $n);
            $insPh->bindValue(':i', $iid, PDO::PARAM_INT);
            $insPh->bindValue(':f', "demo_{$iid}.jpg");
            $insPh->bindValue(':m', 'image/jpeg');
            $insPh->bindValue(':d', $bytes, PDO::PARAM_LOB);
            $insPh->execute();
        }
    }

    $pdo->commit();
    echo "Datos de demostración cargados: {$created} eventos, " . count($coIds) . " empresas, 12 meses de horas-hombre.\n";
}

/** Investigación ficticia coherente con el riesgo crítico. */
function demo_investigacion(string $riesgo, callable $pick): array {
    $base = [
        'INTERACCION_EQUIPOS' => ['No se respetó la distancia de seguridad con el equipo en operación.', 'Punto ciego del equipo sin espejo ni cámara.', 'Comunicación radial no establecida antes de ingresar.',
            [['Instalar cámaras y sensores de proximidad en equipos de carguío', 'INGENIERIA'], ['Reforzar el procedimiento de contacto radial en zona de carga', 'ADMINISTRATIVO']]],
        'CAIDA_ROCAS' => ['Tránsito bajo banco sin evaluación geotécnica del turno.', 'Banco con bloques sueltos sin acuñadura.', 'Monitoreo de taludes con frecuencia insuficiente.',
            [['Instalar radar de monitoreo de taludes en rajo sur', 'INGENIERIA'], ['Inspección geotécnica obligatoria al inicio de cada turno', 'ADMINISTRATIVO']]],
        'ENERGIA_PELIGROSA' => ['Intervención sin bloqueo y etiquetado completo.', 'Punto de bloqueo de difícil acceso.', 'Supervisión no verificó la energía cero.',
            [['Instalar puntos de bloqueo accesibles y señalizados en tableros', 'INGENIERIA'], ['Verificación de energía cero con firma del supervisor', 'ADMINISTRATIVO']]],
        'GUARDAS' => ['Intervención con equipo en movimiento.', 'Guarda de protección retirada y no repuesta.', 'Sin enclavamiento eléctrico en la guarda.',
            [['Instalar guardas con enclavamiento en poleas de cola', 'INGENIERIA'], ['Capacitar en riesgos de partes móviles', 'ADMINISTRATIVO']]],
        'IZAJE' => ['Personal dentro del radio de izaje.', 'Área de izaje sin delimitar.', 'Plan de izaje no difundido al equipo.',
            [['Delimitar físicamente el radio de izaje con barreras', 'INGENIERIA'], ['Charla previa obligatoria con plan de izaje firmado', 'ADMINISTRATIVO']]],
        'ESPACIO_CONFINADO' => ['Ingreso sin permiso de trabajo ni medición de gases.', 'Acceso al estanque sin bloqueo físico.', 'Procedimiento de espacios confinados desactualizado.',
            [['Instalar tapas con candado en accesos a estanques', 'INGENIERIA'], ['Actualizar y difundir el procedimiento de espacios confinados', 'ADMINISTRATIVO']]],
        'EXPLOSIVOS' => ['Revisión del frente antes del tiempo de espera.', 'Iniciadores con defecto de fabricación del lote.', 'Sin registro del conteo de tiros detonados.',
            [['Implementar conteo electrónico de tiros detonados', 'INGENIERIA'], ['Reforzar los tiempos de espera tras voladura (Dec. 249/07 art. 95)', 'ADMINISTRATIVO']]],
        'TRABAJO_ALTURA' => ['Tránsito por pasarela sin atención a la superficie.', 'Derrame sin limpiar ni señalizar.', 'Programa de orden y limpieza no aplicado.',
            [['Instalar piso antideslizante y bandejas colectoras de derrame', 'INGENIERIA'], ['Inspección de orden y limpieza por turno', 'ADMINISTRATIVO']]],
        'VEHICULOS_LIVIANOS' => ['Conducción con fatiga al final del turno.', 'Ruta sin iluminación ni banquina.', 'Gestión de fatiga no contemplada en el traslado.',
            [['Implementar traslado en bus al cierre de turno noche', 'SUSTITUCION'], ['Programa de gestión de fatiga y descanso', 'ADMINISTRATIVO']]],
        'SUSTANCIAS_PELIGROSAS' => ['No se usó protección ocular.', 'Uso de aire comprimido para limpieza.', 'Matriz de EPP sin actualizar para la tarea.',
            [['Reemplazar limpieza con aire comprimido por aspiración', 'SUSTITUCION'], ['Actualizar la matriz de EPP por tarea', 'EPP']]],
        'INCENDIO' => ['EPP incompleto para soldadura sobre cabeza.', 'Ausencia de pantalla protectora.', 'Permiso de trabajo en caliente sin revisión de EPP.',
            [['Proveer equipo ignífugo completo para soldadura sobre cabeza', 'EPP'], ['Incluir verificación de EPP en el permiso de trabajo en caliente', 'ADMINISTRATIVO']]],
    ];
    $b = $base[$riesgo] ?? ['Manipulación manual sin ayuda mecánica.', 'Carga pesada a nivel de piso.', 'Sin evaluación ergonómica del puesto.',
        [['Instalar mesa elevadora para bolsas de reactivo', 'INGENIERIA'], ['Capacitación en levantamiento seguro de cargas', 'ADMINISTRATIVO']]];
    return [
        'metodo' => $pick(['ARBOL_CAUSAS', 'CINCO_PORQUES', 'ICAM']),
        'hechos' => "1. El trabajador realizaba la tarea habitual del turno.\n2. " . $b[1] . "\n3. " . $b[0] . "\n4. Se produjo el evento y se dio aviso al supervisor.\n(Hechos ficticios de demostración.)",
        'actos' => $b[0], 'cond' => $b[1],
        'fp' => $pick(['Exceso de confianza por experiencia en la tarea.', 'Fatiga al final del turno.', 'Capacitación insuficiente en el procedimiento.']),
        'ft' => $pick(['Presión por cumplir la producción del turno.', 'Herramienta o equipo inadecuado para la tarea.', 'Mantenimiento preventivo atrasado.']),
        'fc' => $b[2],
        'porques' => ['¿Por qué ocurrió? ' . $b[0], '¿Por qué? ' . $b[1], '¿Por qué? ' . $b[2], '¿Por qué? No estaba identificado en la matriz de riesgos del área.'],
        'concl' => 'La causa raíz es una falla del control crítico "' . $riesgo . '". Se priorizan controles de ingeniería sobre los administrativos. (Conclusión ficticia.)',
        'acciones' => $b[3],
    ];
}

/** Imagen ilustrativa generada por código (no es una fotografía real). */
function demo_foto(string $title, int $n): string {
    $w = 960; $h = 640;
    $im = imagecreatetruecolor($w, $h);
    $sky = [[176, 196, 222], [196, 186, 170], [170, 190, 200]][$n % 3];
    imagefill($im, 0, 0, imagecolorallocate($im, ...$sky));
    // montañas y terreno
    $mtn = imagecolorallocate($im, 139, 115, 85);
    imagefilledpolygon($im, [0, 420, 220, 180, 420, 380, 640, 150, 960, 400, 960, 640, 0, 640], $mtn);
    imagefilledrectangle($im, 0, 470, $w, $h, imagecolorallocate($im, 181, 154, 118));
    // "equipo" esquemático
    $yel = imagecolorallocate($im, 230, 170, 30);
    imagefilledrectangle($im, 360 + $n * 30, 420, 600 + $n * 30, 500, $yel);
    imagefilledrectangle($im, 520 + $n * 30, 370, 600 + $n * 30, 420, $yel);
    $blk = imagecolorallocate($im, 30, 30, 30);
    imagefilledellipse($im, 400 + $n * 30, 510, 60, 60, $blk);
    imagefilledellipse($im, 560 + $n * 30, 510, 60, 60, $blk);
    // cartel
    $white = imagecolorallocate($im, 255, 255, 255);
    imagefilledrectangle($im, 0, 0, $w, 60, imagecolorallocatealpha($im, 0, 0, 0, 40));
    imagestring($im, 5, 16, 12, 'IMAGEN ILUSTRATIVA - DATOS FICTICIOS DE DEMOSTRACION', $white);
    $t = iconv('UTF-8', 'ASCII//TRANSLIT', $title) ?: $title;
    imagestring($im, 4, 16, 36, substr($t, 0, 100), $white);
    ob_start(); imagejpeg($im, null, 80); $out = ob_get_clean();
    imagedestroy($im);
    return $out;
}
