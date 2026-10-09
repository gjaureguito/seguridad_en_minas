<?php
declare(strict_types=1);
/*
 * bootstrap.php — conexión PostgreSQL, sesión, autenticación y helpers.
 * Todas las páginas y endpoints lo incluyen.
 */

date_default_timezone_set(getenv('APP_TZ') ?: 'America/Argentina/San_Juan');

/* ---------------- Base de datos ---------------- */
function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    // Railway expone DATABASE_URL = postgresql://user:pass@host:port/db
    $url = getenv('DATABASE_URL') ?: '';
    if ($url !== '') {
        $p = parse_url($url);
        $host = $p['host'] ?? 'localhost';
        $port = $p['port'] ?? 5432;
        $name = ltrim($p['path'] ?? '/postgres', '/');
        $user = urldecode($p['user'] ?? 'postgres');
        $pass = urldecode($p['pass'] ?? '');
    } else {
        $host = getenv('PGHOST') ?: '127.0.0.1';
        $port = getenv('PGPORT') ?: '5432';
        $name = getenv('PGDATABASE') ?: 'seguridad_mina';
        $user = getenv('PGUSER') ?: 'postgres';
        $pass = getenv('PGPASSWORD') ?: '';
    }
    $dsn = "pgsql:host={$host};port={$port};dbname={$name}";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $pdo->exec("SET TIME ZONE '" . date_default_timezone_get() . "'");
    return $pdo;
}

/* ---------------- Sesión ---------------- */
function start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => $secure, 'path' => '/']);
    session_name('SMSESS');
    session_start();
}

function current_user(): ?array {
    start_session();
    return $_SESSION['user'] ?? null;
}

function csrf_token(): string {
    start_session();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

/** Para páginas HTML: redirige a login si no hay sesión. */
function require_login_page(array $roles = []): array {
    $u = current_user();
    if (!$u) { header('Location: login.php'); exit; }
    if ($roles && !in_array($u['role'], $roles, true)) { http_response_code(403); echo 'Sin permiso'; exit; }
    return $u;
}

/** Para endpoints JSON. Escrituras requieren rol con permiso + token CSRF. */
function require_login_api(array $roles = []): array {
    $u = current_user();
    if (!$u) json_fail('No autenticado', 401);
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method !== 'GET') {
        $tok = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf'] ?? '');
        if (!hash_equals($_SESSION['csrf'] ?? '', (string)$tok)) json_fail('Token CSRF inválido', 419);
        if ($u['role'] === 'LECTOR') json_fail('Usuario de solo lectura', 403);
    }
    if ($roles && !in_array($u['role'], $roles, true)) json_fail('Sin permiso', 403);
    return $u;
}

/* ---------------- JSON ---------------- */
function json_out(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function json_ok(array $data = []): never { json_out(['ok' => true] + $data); }
function json_fail(string $msg, int $code = 400): never { json_out(['ok' => false, 'error' => $msg], $code); }

/** Manejo uniforme de excepciones en endpoints. */
function api_guard(callable $fn): void {
    try { $fn(); }
    catch (Throwable $e) {
        $pdo = null;
        try { $pdo = db(); if ($pdo->inTransaction()) $pdo->rollBack(); } catch (Throwable $ignored) {}
        error_log('[seguridad-minera] ' . $e->getMessage());
        json_fail(getenv('APP_DEBUG') ? $e->getMessage() : 'Error interno del servidor', 500);
    }
}

function body_json(): array {
    $raw = file_get_contents('php://input') ?: '';
    $j = json_decode($raw, true);
    return is_array($j) ? $j : [];
}

/* ---------------- HTML ---------------- */
function h(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* ---------------- Dominios (etiquetas compartidas con el front) ---------------- */
const TIPO_CONTINGENCIA = [
    'ACCIDENTE_TRABAJO'      => 'Accidente de trabajo (por el hecho o en ocasión)',
    'IN_ITINERE'             => 'Accidente in itinere',
    'ENFERMEDAD_PROFESIONAL' => 'Enfermedad profesional',
    'INCIDENTE'              => 'Incidente / cuasi accidente (sin lesión)',
    'DANO_MATERIAL'          => 'Daño material / a la propiedad',
    'AMBIENTAL'              => 'Evento ambiental',
    'OTRO'                   => 'Otro (inspección, simulacro, mejora)',
];
const CONSECUENCIA = [
    'SIN_LESION'             => 'Sin lesión',
    'PRIMEROS_AUXILIOS'      => 'Primeros auxilios',
    'TRATAMIENTO_MEDICO'     => 'Tratamiento médico (sin baja)',
    'TRABAJO_RESTRINGIDO'    => 'Trabajo restringido / tareas livianas',
    'CON_BAJA'               => 'Con baja (ILT)',
    'INCAPACIDAD_PERMANENTE' => 'Incapacidad permanente (ILP)',
    'FATAL'                  => 'Fatal',
];
const ESTADO_INCIDENTE = [
    'ABIERTO' => 'Abierto', 'EN_INVESTIGACION' => 'En investigación',
    'ACCIONES_PENDIENTES' => 'Acciones pendientes', 'CERRADO' => 'Cerrado',
];
const METODO_INVESTIGACION = [
    'ARBOL_CAUSAS' => 'Árbol de causas', 'CINCO_PORQUES' => '5 porqués',
    'ICAM' => 'ICAM', 'TAPROOT' => 'TapRooT', 'OTRO' => 'Otro',
];
const TIPO_ACCION = ['CORRECTIVA' => 'Correctiva', 'PREVENTIVA' => 'Preventiva', 'MEJORA' => 'Mejora'];
const JERARQUIA = [
    'ELIMINACION' => '1. Eliminación', 'SUSTITUCION' => '2. Sustitución', 'INGENIERIA' => '3. Control de ingeniería',
    'ADMINISTRATIVO' => '4. Control administrativo', 'EPP' => '5. EPP',
];
const ESTADO_ACCION = [
    'PENDIENTE' => 'Pendiente', 'EN_CURSO' => 'En curso', 'CUMPLIDA' => 'Cumplida',
    'VERIFICADA' => 'Verificada (eficaz)', 'ANULADA' => 'Anulada',
];

/** Lesión con tiempo perdido (LTI): computa en Índice de Frecuencia SRT. */
const CONSEC_LTI = ['CON_BAJA', 'INCAPACIDAD_PERMANENTE', 'FATAL'];
/** Lesión registrable (TRI = LTI + tratamiento médico + trabajo restringido). */
const CONSEC_TRI = ['TRATAMIENTO_MEDICO', 'TRABAJO_RESTRINGIDO', 'CON_BAJA', 'INCAPACIDAD_PERMANENTE', 'FATAL'];

/** Clasificación de la matriz de riesgo 5x5. */
function riesgo_nivel(?int $p, ?int $c): ?array {
    if (!$p || !$c) return null;
    $r = $p * $c;
    if ($r >= 15) return ['valor' => $r, 'nivel' => 'INTOLERABLE'];
    if ($r >= 10) return ['valor' => $r, 'nivel' => 'ALTO'];
    if ($r >= 5)  return ['valor' => $r, 'nivel' => 'MODERADO'];
    return ['valor' => $r, 'nivel' => 'BAJO'];
}
