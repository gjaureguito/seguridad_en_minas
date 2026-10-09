<?php
declare(strict_types=1);
/*
 * bin/migrate.php — aplica db/schema.sql y db/seed.sql y crea el admin inicial.
 * Se ejecuta en cada arranque del contenedor (docker/entrypoint.sh) y localmente:
 *   php bin/migrate.php
 * Variables: DATABASE_URL (o PG*), ADMIN_EMAIL, ADMIN_PASSWORD, ADMIN_NAME
 */
require __DIR__ . '/../src/bootstrap.php';

$tries = 0;
while (true) {
    try { $pdo = db(); break; }
    catch (Throwable $e) {
        if (++$tries >= 15) { fwrite(STDERR, "No se pudo conectar a PostgreSQL: {$e->getMessage()}\n"); exit(1); }
        fwrite(STDERR, "Esperando PostgreSQL ({$tries})...\n");
        sleep(2);
    }
}

// Evita que dos réplicas migren a la vez
$pdo->exec('SELECT pg_advisory_lock(424242)');
try {
    foreach (['schema.sql', 'seed.sql'] as $f) {
        $pdo->exec(file_get_contents(__DIR__ . '/../db/' . $f));
        echo "OK {$f}\n";
    }

    $count = (int)$pdo->query('SELECT count(*) FROM users')->fetchColumn();
    if ($count === 0) {
        $email = getenv('ADMIN_EMAIL') ?: 'admin@local';
        $pass  = getenv('ADMIN_PASSWORD') ?: '';
        if ($pass === '') {
            $pass = bin2hex(random_bytes(6));
            echo "ADMIN_PASSWORD no definido: contraseña generada para {$email}: {$pass}\n";
        }
        $st = $pdo->prepare("INSERT INTO users (email, full_name, password_hash, role) VALUES (:e, :n, :h, 'ADMIN')");
        $st->execute([':e' => strtolower($email), ':n' => getenv('ADMIN_NAME') ?: 'Administrador', ':h' => password_hash($pass, PASSWORD_DEFAULT)]);
        echo "Usuario administrador creado: {$email}\n";
    }

    // Recuperación: si ADMIN_RESET_PASSWORD está definida, reemplaza la clave de ADMIN_EMAIL
    // y lo reactiva como ADMIN. Borrar la variable después de ingresar.
    $reset = getenv('ADMIN_RESET_PASSWORD') ?: '';
    $email = strtolower(getenv('ADMIN_EMAIL') ?: '');
    if ($reset !== '' && $email !== '') {
        $st = $pdo->prepare("UPDATE users SET password_hash = :h, active = TRUE, role = 'ADMIN' WHERE email = :e");
        $st->execute([':h' => password_hash($reset, PASSWORD_DEFAULT), ':e' => $email]);
        echo $st->rowCount() ? "Contraseña de {$email} restablecida desde ADMIN_RESET_PASSWORD\n" : "ADMIN_RESET_PASSWORD: no existe {$email}\n";
    }

    // Datos ficticios de demostración (solo si DEMO_DATA=1 y la base no tiene incidentes)
    if (getenv('DEMO_DATA') === '1' && (int)$pdo->query('SELECT count(*) FROM incidents')->fetchColumn() === 0) {
        require __DIR__ . '/demo_seed.php';
        try { demo_seed($pdo); }
        catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            fwrite(STDERR, "No se pudieron cargar los datos de demostración: {$e->getMessage()}\n");
        }
    }
} finally {
    $pdo->exec('SELECT pg_advisory_unlock(424242)');
}
