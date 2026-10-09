<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/layout.php';

start_session();
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    // Freno simple contra fuerza bruta: 5 intentos fallidos → espera 60 s
    $_SESSION['fails'] = $_SESSION['fails'] ?? 0;
    if ($_SESSION['fails'] >= 5 && time() - ($_SESSION['last_fail'] ?? 0) < 60) {
        $error = 'Demasiados intentos. Esperá un minuto.';
    } elseif (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        $error = 'La sesión expiró, volvé a intentar.';
    } else {
        $st = db()->prepare('SELECT * FROM users WHERE email = lower(:e) AND active');
        $st->execute([':e' => trim((string)($_POST['email'] ?? ''))]);
        $u = $st->fetch();
        if ($u && password_verify((string)($_POST['password'] ?? ''), $u['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user'] = ['id' => (int)$u['id'], 'email' => $u['email'], 'full_name' => $u['full_name'], 'role' => $u['role']];
            $_SESSION['fails'] = 0;
            unset($_SESSION['csrf']);
            header('Location: home.php'); exit;
        }
        $_SESSION['fails']++; $_SESSION['last_fail'] = time();
        $error = 'Email o contraseña incorrectos.';
    }
}
page_head('Ingresar');
?>
<body>
<div class="container" style="max-width:420px; padding-top:12vh">
  <div class="text-center mb-4">
    <div class="fs-2 text-primary"><i class="bi bi-shield-check"></i></div>
    <h1 class="h4 fw-bold mb-1">Seguridad Minera</h1>
    <div class="small-muted">Registro y análisis de incidentes en faena</div>
  </div>
  <form method="post" class="cardish p-4">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <?php if ($error): ?><div class="alert alert-danger py-2 small"><?= h($error) ?></div><?php endif; ?>
    <div class="mb-3"><label class="form-label">Email</label><input name="email" type="email" class="form-control" required autofocus autocomplete="username"></div>
    <div class="mb-3"><label class="form-label">Contraseña</label><input name="password" type="password" class="form-control" required autocomplete="current-password"></div>
    <button class="btn btn-primary w-100">Ingresar</button>
  </form>
  <?php if (getenv('DEMO_DATA') === '1'): ?><div class="text-center small-muted">Entorno de demostración con datos ficticios.</div><?php endif; ?>
</div>
</body></html>
