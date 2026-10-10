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
<body class="login">
<style>
  body.login{min-height:100vh;background:var(--pizarra);display:flex;align-items:center;justify-content:center;padding:24px 16px;position:relative}
  body.login::before{content:"";position:fixed;inset:0 0 auto 0;height:12px;background:repeating-linear-gradient(-45deg,var(--seguridad) 0 18px,#17212B 18px 36px)}
  .login-box{width:100%;max-width:400px}
  .login-head{color:#fff;margin-bottom:18px;display:flex;gap:14px;align-items:center}
  .login-head .brand-mark{width:52px;height:52px;border-radius:10px;background:var(--seguridad);color:var(--pizarra);display:grid;place-items:center;font-size:1.7rem;flex:none}
  .login-head h1{margin:0;font-size:2rem}
  .login-head p{margin:0;color:#BFCBCF}
  .login-box .cardish{padding:22px;border:0}
  .login-foot{color:#93A3A8;font-size:.82rem;text-align:center;margin-top:14px}
</style>
<main class="login-box">
  <div class="login-head">
    <span class="brand-mark"><i class="bi bi-shield-fill-check"></i></span>
    <div><h1>Seguridad Minera</h1><p>Registro e investigación de incidentes en faena</p></div>
  </div>
  <form method="post" class="cardish">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <?php if ($error): ?><div class="alert alert-danger py-2 small"><?= h($error) ?></div><?php endif; ?>
    <div class="mb-3"><label class="form-label" for="em">Email</label><input id="em" name="email" type="email" class="form-control" required autofocus autocomplete="username"></div>
    <div class="mb-3"><label class="form-label" for="pw">Contraseña</label><input id="pw" name="password" type="password" class="form-control" required autocomplete="current-password"></div>
    <button class="btn btn-primary w-100 py-2">Ingresar</button>
  </form>
  <?php if (getenv('DEMO_DATA') === '1'): ?><div class="login-foot">Entorno de demostración con datos ficticios.</div><?php endif; ?>
  <div class="login-foot">Ley 19.587 · Ley 24.557 · Dec. 249/07</div>
</main>
</body></html>
