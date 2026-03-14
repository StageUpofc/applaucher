<?php
/**
 * GB Launcher - Login do Painel Admin
 */
require_once __DIR__ . '/auth.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    if (attemptLogin($u, $p)) {
        header('Location: index.php');
        exit;
    }
    $error = 'Usuário ou senha inválidos.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>GB Launcher – Admin Login</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --bg:       #0a0e1a;
    --surface:  #111827;
    --border:   #1e2d45;
    --primary:  #3b82f6;
    --accent:   #f59e0b;
    --text:     #f1f5f9;
    --muted:    #64748b;
    --error:    #ef4444;
    --radius:   16px;
  }

  body {
    font-family: 'Inter', sans-serif;
    background: var(--bg);
    color: var(--text);
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
  }

  /* Animated background */
  body::before {
    content: '';
    position: fixed;
    inset: 0;
    background:
      radial-gradient(ellipse 80% 60% at 20% 20%, rgba(59,130,246,.15) 0%, transparent 60%),
      radial-gradient(ellipse 60% 50% at 80% 80%, rgba(245,158,11,.10) 0%, transparent 60%);
    pointer-events: none;
    z-index: 0;
  }

  .card {
    position: relative;
    z-index: 1;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 24px;
    padding: 48px 40px;
    width: 420px;
    max-width: 95vw;
    box-shadow: 0 25px 60px rgba(0,0,0,.5);
    animation: slideUp .5s ease;
  }

  @keyframes slideUp {
    from { opacity: 0; transform: translateY(30px); }
    to   { opacity: 1; transform: translateY(0); }
  }

  .logo-area {
    text-align: center;
    margin-bottom: 36px;
  }

  .logo-icon {
    width: 72px;
    height: 72px;
    background: linear-gradient(135deg, var(--primary), #7c3aed);
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 16px;
    box-shadow: 0 8px 32px rgba(59,130,246,.4);
  }

  .logo-icon .material-icons-round { font-size: 36px; color: #fff; }

  h1 { font-size: 1.6rem; font-weight: 800; letter-spacing: -.5px; }
  h1 span { color: var(--primary); }
  .subtitle { color: var(--muted); font-size: .875rem; margin-top: 4px; }

  .field { margin-bottom: 18px; }
  label { display: block; font-size: .8rem; font-weight: 600; color: var(--muted); margin-bottom: 8px; text-transform: uppercase; letter-spacing: .05em; }

  .input-wrap {
    position: relative;
    display: flex;
    align-items: center;
  }

  .input-wrap .material-icons-round {
    position: absolute;
    left: 14px;
    color: var(--muted);
    font-size: 20px;
    pointer-events: none;
  }

  input[type="text"],
  input[type="password"] {
    width: 100%;
    padding: 13px 14px 13px 44px;
    background: rgba(255,255,255,.04);
    border: 1px solid var(--border);
    border-radius: 12px;
    color: var(--text);
    font-family: 'Inter', sans-serif;
    font-size: .95rem;
    outline: none;
    transition: border-color .2s, box-shadow .2s;
  }

  input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(59,130,246,.15);
  }

  .error-msg {
    background: rgba(239,68,68,.1);
    border: 1px solid rgba(239,68,68,.3);
    border-radius: 10px;
    padding: 10px 14px;
    font-size: .875rem;
    color: var(--error);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
  }

  button[type="submit"] {
    width: 100%;
    padding: 14px;
    background: linear-gradient(135deg, var(--primary), #7c3aed);
    color: #fff;
    font-family: 'Inter', sans-serif;
    font-size: 1rem;
    font-weight: 700;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    transition: opacity .2s, transform .15s;
    margin-top: 8px;
    letter-spacing: .02em;
  }

  button[type="submit"]:hover  { opacity: .9; transform: translateY(-1px); }
  button[type="submit"]:active { transform: translateY(0); }

  .footer { text-align: center; margin-top: 24px; font-size: .8rem; color: var(--muted); }
</style>
</head>
<body>
<div class="card">
  <div class="logo-area">
    <div class="logo-icon">
      <span class="material-icons-round">rocket_launch</span>
    </div>
    <h1>GB <span>Launcher</span></h1>
    <p class="subtitle">Painel de Administração</p>
  </div>

  <?php if ($error): ?>
  <div class="error-msg">
    <span class="material-icons-round" style="font-size:18px">error_outline</span>
    <?= htmlspecialchars($error) ?>
  </div>
  <?php endif; ?>

  <form method="POST" autocomplete="off">
    <div class="field">
      <label for="username">Usuário</label>
      <div class="input-wrap">
        <span class="material-icons-round">person</span>
        <input id="username" type="text" name="username" placeholder="Digite seu usuário" required autofocus>
      </div>
    </div>
    <div class="field">
      <label for="password">Senha</label>
      <div class="input-wrap">
        <span class="material-icons-round">lock</span>
        <input id="password" type="password" name="password" placeholder="Digite sua senha" required>
      </div>
    </div>
    <button type="submit">Entrar no Painel</button>
  </form>

  <p class="footer">GB Launcher Admin &copy; <?= date('Y') ?></p>
</div>
</body>
</html>
