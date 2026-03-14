<?php
/**
 * GB Launcher - Painel Admin Principal
 */
require_once __DIR__ . '/auth.php';
requireLogin();

if (isset($_GET['logout'])) logout();

$db = getDB();

// ============================================================
// Ações POST (AJAX)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {

            // --- Salvar configurações gerais ---
            case 'save_settings':
                $fields = ['launcher_title', 'primary_color', 'accent_color'];
                $stmt   = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
                foreach ($fields as $key) {
                    if (isset($_POST[$key])) {
                        $stmt->execute([trim($_POST[$key]), $key]);
                    }
                }
                echo json_encode(['success' => true, 'msg' => 'Configurações salvas!']);
                break;

            // --- Adicionar app ---
            case 'add_app':
                $stmt = $db->prepare(
                    "INSERT INTO apps (name, package_name, icon_url, category, description, position, is_visible, is_pinned)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $maxPos = $db->query("SELECT COALESCE(MAX(position),0)+1 FROM apps")->fetchColumn();
                $stmt->execute([
                    trim($_POST['name'] ?? ''),
                    trim($_POST['package_name'] ?? ''),
                    trim($_POST['icon_url'] ?? ''),
                    trim($_POST['category'] ?? 'geral'),
                    trim($_POST['description'] ?? ''),
                    (int)$maxPos,
                    isset($_POST['is_visible']) ? 1 : 0,
                    isset($_POST['is_pinned'])  ? 1 : 0,
                ]);
                echo json_encode(['success' => true, 'id' => $db->lastInsertId(), 'msg' => 'App adicionado!']);
                break;

            // --- Editar app ---
            case 'edit_app':
                $stmt = $db->prepare(
                    "UPDATE apps SET name=?, package_name=?, icon_url=?, category=?, description=?, is_visible=?, is_pinned=?
                     WHERE id=?"
                );
                $stmt->execute([
                    trim($_POST['name'] ?? ''),
                    trim($_POST['package_name'] ?? ''),
                    trim($_POST['icon_url'] ?? ''),
                    trim($_POST['category'] ?? 'geral'),
                    trim($_POST['description'] ?? ''),
                    isset($_POST['is_visible']) ? 1 : 0,
                    isset($_POST['is_pinned'])  ? 1 : 0,
                    (int)($_POST['id'] ?? 0),
                ]);
                echo json_encode(['success' => true, 'msg' => 'App atualizado!']);
                break;

            // --- Remover app ---
            case 'delete_app':
                $stmt = $db->prepare("DELETE FROM apps WHERE id = ?");
                $stmt->execute([(int)($_POST['id'] ?? 0)]);
                echo json_encode(['success' => true, 'msg' => 'App removido!']);
                break;

            // --- Reordenar apps (drag & drop) ---
            case 'reorder_apps':
                $order = json_decode($_POST['order'] ?? '[]', true);
                $stmt  = $db->prepare("UPDATE apps SET position = ? WHERE id = ?");
                foreach ($order as $pos => $id) {
                    $stmt->execute([$pos + 1, (int)$id]);
                }
                echo json_encode(['success' => true]);
                break;

            default:
                echo json_encode(['success' => false, 'error' => 'Ação inválida.']);
        }
    } catch (Throwable $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ============================================================
// Dados para a view
// ============================================================
$apps = $db->query(
    "SELECT * FROM apps ORDER BY is_pinned DESC, position ASC, name ASC"
)->fetchAll();

$settings = [];
foreach ($db->query("SELECT setting_key, setting_value FROM settings")->fetchAll() as $r) {
    $settings[$r['setting_key']] = $r['setting_value'];
}

$cats = $db->query("SELECT * FROM categories ORDER BY position ASC")->fetchAll();

$totalApps    = count($apps);
$pinnedApps   = count(array_filter($apps, fn($a) => $a['is_pinned']));
$visibleApps  = count(array_filter($apps, fn($a) => $a['is_visible']));

// URL base da API
$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
$dir     = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$apiUrl  = $scheme . '://' . $host . $dir . '/api.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>GB Launcher – Painel Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
<style>
/* ============================================================
   RESET & TOKENS
============================================================ */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --bg:       #080d18;
  --surface0: #0f1623;
  --surface1: #14202f;
  --surface2: #1a2840;
  --border:   rgba(255,255,255,.07);
  --primary:  #3b82f6;
  --primary-d:#2563eb;
  --accent:   #f59e0b;
  --success:  #22c55e;
  --danger:   #ef4444;
  --warn:     #f97316;
  --text:     #e2e8f0;
  --muted:    #64748b;
  --radius:   14px;
  --sidebar:  260px;
  --transition: .2s ease;
}

body {
  font-family: 'Inter', sans-serif;
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
  display: flex;
}

/* ============================================================
   SIDEBAR
============================================================ */
.sidebar {
  width: var(--sidebar);
  min-height: 100vh;
  background: var(--surface0);
  border-right: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  position: fixed;
  left: 0; top: 0; bottom: 0;
  z-index: 100;
  transition: transform var(--transition);
}

.sidebar-brand {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 24px 20px 20px;
  border-bottom: 1px solid var(--border);
}

.brand-icon {
  width: 42px; height: 42px;
  background: linear-gradient(135deg,#3b82f6,#7c3aed);
  border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  box-shadow: 0 4px 16px rgba(59,130,246,.35);
}
.brand-icon .material-icons-round { font-size: 22px; color: #fff; }
.brand-name { font-size: 1.05rem; font-weight: 800; letter-spacing: -.3px; }
.brand-name span { color: var(--primary); }

nav { flex: 1; padding: 16px 12px; overflow-y: auto; }
.nav-section { font-size: .7rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .08em; padding: 12px 8px 6px; }

.nav-item {
  display: flex; align-items: center; gap: 12px;
  padding: 11px 12px;
  border-radius: 10px;
  cursor: pointer;
  color: var(--muted);
  font-size: .875rem;
  font-weight: 500;
  transition: background var(--transition), color var(--transition);
  margin-bottom: 2px;
  border: none; background: none; width: 100%; text-align: left;
  text-decoration: none;
}
.nav-item .material-icons-round { font-size: 20px; }
.nav-item:hover  { background: var(--surface2); color: var(--text); }
.nav-item.active { background: rgba(59,130,246,.15); color: var(--primary); }

.sidebar-footer { padding: 16px; border-top: 1px solid var(--border); }
.user-chip {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 12px;
  background: var(--surface1);
  border-radius: 12px;
}
.user-avatar {
  width: 32px; height: 32px;
  background: linear-gradient(135deg,#3b82f6,#7c3aed);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-weight: 700; font-size: .85rem; color: #fff;
}
.user-info { flex: 1; }
.user-name { font-size: .85rem; font-weight: 600; }
.user-role { font-size: .72rem; color: var(--muted); }
.logout-btn {
  background: none; border: none; cursor: pointer;
  color: var(--muted); padding: 4px;
  transition: color var(--transition);
}
.logout-btn:hover { color: var(--danger); }

/* ============================================================
   MAIN CONTENT
============================================================ */
.main {
  margin-left: var(--sidebar);
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 100vh;
}

.topbar {
  display: flex; align-items: center; justify-content: space-between;
  padding: 20px 32px;
  border-bottom: 1px solid var(--border);
  background: rgba(8,13,24,.9);
  backdrop-filter: blur(12px);
  position: sticky; top: 0; z-index: 50;
}

.page-title { font-size: 1.4rem; font-weight: 800; letter-spacing: -.4px; }
.page-subtitle { font-size: .8rem; color: var(--muted); margin-top: 2px; }

.api-badge {
  display: flex; align-items: center; gap: 8px;
  background: var(--surface1);
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: 8px 14px;
  font-size: .8rem; color: var(--muted);
  cursor: pointer;
  transition: border-color var(--transition);
}
.api-badge:hover { border-color: var(--primary); }
.api-badge code { color: var(--primary); font-size: .78rem; max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.api-dot { width: 8px; height: 8px; background: var(--success); border-radius: 50%; animation: pulse 2s infinite; }
@keyframes pulse { 0%,100%{opacity:1;} 50%{opacity:.4;} }

.content { padding: 32px; flex: 1; }

/* ============================================================
   PANEL SECTIONS (tab content)
============================================================ */
.panel { display: none; animation: fadeIn .3s ease; }
.panel.active { display: block; }
@keyframes fadeIn { from{opacity:0;transform:translateY(8px);} to{opacity:1;transform:translateY(0);} }

/* ============================================================
   STAT CARDS
============================================================ */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 18px;
  margin-bottom: 32px;
}

.stat-card {
  background: var(--surface1);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 22px;
  position: relative;
  overflow: hidden;
}
.stat-card::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 3px;
}
.stat-card.blue::before  { background: var(--primary); }
.stat-card.amber::before { background: var(--accent); }
.stat-card.green::before { background: var(--success); }
.stat-card.purple::before{ background: #7c3aed; }

.stat-icon {
  width: 42px; height: 42px;
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  margin-bottom: 14px;
}
.stat-icon.blue   { background: rgba(59,130,246,.15); color: var(--primary); }
.stat-icon.amber  { background: rgba(245,158,11,.15); color: var(--accent); }
.stat-icon.green  { background: rgba(34,197,94,.15);  color: var(--success); }
.stat-icon.purple { background: rgba(124,58,237,.15); color: #7c3aed; }
.stat-icon .material-icons-round { font-size: 22px; }

.stat-value { font-size: 2rem; font-weight: 800; line-height: 1; }
.stat-label { font-size: .8rem; color: var(--muted); margin-top: 4px; }

/* ============================================================
   CARDS / SECTIONS
============================================================ */
.card {
  background: var(--surface1);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  overflow: hidden;
  margin-bottom: 24px;
}

.card-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 20px 24px;
  border-bottom: 1px solid var(--border);
}
.card-title { font-size: 1rem; font-weight: 700; display: flex; align-items: center; gap: 8px; }
.card-title .material-icons-round { font-size: 20px; color: var(--primary); }
.card-body { padding: 24px; }

/* ============================================================
   FORMS
============================================================ */
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
.form-grid.cols-3 { grid-template-columns: 1fr 1fr 1fr; }
.form-full { grid-column: 1 / -1; }

.field-group { display: flex; flex-direction: column; gap: 7px; }
label.field-label { font-size: .78rem; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: .06em; }

input[type="text"],
input[type="url"],
input[type="color"],
select, textarea {
  background: rgba(255,255,255,.05);
  border: 1px solid var(--border);
  border-radius: 10px;
  color: var(--text);
  font-family: 'Inter', sans-serif;
  font-size: .9rem;
  padding: 10px 14px;
  outline: none;
  width: 100%;
  transition: border-color var(--transition), box-shadow var(--transition);
}
input:focus, select:focus, textarea:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(59,130,246,.15);
}
textarea { resize: vertical; min-height: 80px; }
input[type="color"] { padding: 6px; height: 44px; cursor: pointer; }
select { cursor: pointer; }
select option { background: var(--surface0); }

.check-wrap {
  display: flex; align-items: center; gap: 10px;
  cursor: pointer; font-size: .875rem;
}
.check-wrap input[type="checkbox"] {
  width: 18px; height: 18px;
  accent-color: var(--primary);
  cursor: pointer;
}

/* ============================================================
   BUTTONS
============================================================ */
.btn {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 10px 20px;
  border-radius: 10px;
  font-family: 'Inter', sans-serif;
  font-size: .875rem;
  font-weight: 600;
  border: none; cursor: pointer;
  transition: opacity var(--transition), transform var(--transition);
  text-decoration: none;
}
.btn:hover  { opacity: .88; transform: translateY(-1px); }
.btn:active { transform: translateY(0); }
.btn .material-icons-round { font-size: 18px; }

.btn-primary  { background: var(--primary); color: #fff; }
.btn-success  { background: var(--success); color: #fff; }
.btn-danger   { background: var(--danger);  color: #fff; }
.btn-ghost    { background: transparent; color: var(--muted); border: 1px solid var(--border); }
.btn-ghost:hover { color: var(--text); border-color: var(--primary); background: rgba(59,130,246,.08); }
.btn-sm { padding: 7px 14px; font-size: .8rem; }
.btn-icon { padding: 8px; border-radius: 8px; }

/* ============================================================
   APPS TABLE
============================================================ */
.apps-toolbar {
  display: flex; align-items: center; gap: 12px;
  padding: 16px 24px;
  border-bottom: 1px solid var(--border);
  flex-wrap: wrap;
}

.search-wrap {
  position: relative; flex: 1; min-width: 200px;
}
.search-wrap .material-icons-round {
  position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
  color: var(--muted); font-size: 18px; pointer-events: none;
}
.search-input {
  padding-left: 40px !important;
}

.apps-table { width: 100%; border-collapse: collapse; }
.apps-table th {
  text-align: left;
  font-size: .75rem;
  font-weight: 700;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: .06em;
  padding: 12px 16px;
  border-bottom: 1px solid var(--border);
}
.apps-table td {
  padding: 12px 16px;
  border-bottom: 1px solid rgba(255,255,255,.04);
  vertical-align: middle;
}
.apps-table tr:last-child td { border-bottom: none; }
.apps-table tr:hover td { background: rgba(255,255,255,.02); }

.app-icon-cell {
  display: flex; align-items: center; gap: 12px;
}
.app-icon-thumb {
  width: 44px; height: 44px;
  border-radius: 12px;
  object-fit: cover;
  background: var(--surface2);
  flex-shrink: 0;
}
.app-icon-fallback {
  width: 44px; height: 44px;
  border-radius: 12px;
  background: linear-gradient(135deg,#3b82f6,#7c3aed);
  display: flex; align-items: center; justify-content: center;
  color: #fff; font-weight: 700; font-size: 1.1rem;
  flex-shrink: 0;
}
.app-name { font-weight: 600; font-size: .9rem; }
.app-pkg  { font-size: .75rem; color: var(--muted); font-family: 'Courier New', monospace; margin-top: 2px; }

.badge {
  display: inline-flex; align-items: center; gap: 4px;
  padding: 3px 10px; border-radius: 20px;
  font-size: .72rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em;
}
.badge-visible  { background: rgba(34,197,94,.12);  color: var(--success); }
.badge-hidden   { background: rgba(100,116,139,.12); color: var(--muted); }
.badge-pinned   { background: rgba(245,158,11,.12); color: var(--accent); }
.badge-category { background: rgba(59,130,246,.12);  color: var(--primary); }

.actions-cell { display: flex; gap: 6px; }

/* ============================================================
   UPLOAD ZONES
============================================================ */
.upload-zone {
  border: 2px dashed var(--border);
  border-radius: var(--radius);
  padding: 32px;
  text-align: center;
  cursor: pointer;
  transition: border-color var(--transition), background var(--transition);
  position: relative;
}
.upload-zone:hover { border-color: var(--primary); background: rgba(59,130,246,.04); }
.upload-zone input[type="file"] { display: none; }
.upload-icon { font-size: 48px; color: var(--muted); margin-bottom: 10px; }
.upload-label { font-weight: 600; font-size: .9rem; }
.upload-hint  { font-size: .8rem; color: var(--muted); margin-top: 4px; }
.upload-preview {
  max-width: 100%;
  max-height: 160px;
  border-radius: 10px;
  object-fit: contain;
  margin-top: 12px;
}

/* ============================================================
   TOAST NOTIFICATIONS
============================================================ */
#toast-container {
  position: fixed; bottom: 24px; right: 24px; z-index: 1000;
  display: flex; flex-direction: column; gap: 10px;
}
.toast {
  display: flex; align-items: center; gap: 12px;
  background: var(--surface1);
  border: 1px solid var(--border);
  border-radius: 12px;
  padding: 14px 18px;
  font-size: .875rem;
  box-shadow: 0 8px 32px rgba(0,0,0,.4);
  animation: toastIn .3s ease;
  min-width: 260px;
}
.toast.success { border-left: 3px solid var(--success); }
.toast.error   { border-left: 3px solid var(--danger); }
@keyframes toastIn { from{opacity:0;transform:translateX(20px);} to{opacity:1;transform:translateX(0);} }

/* ============================================================
   MODAL
============================================================ */
.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(0,0,0,.7);
  backdrop-filter: blur(4px);
  z-index: 200;
  display: flex; align-items: center; justify-content: center;
  opacity: 0; pointer-events: none;
  transition: opacity var(--transition);
}
.modal-overlay.open { opacity: 1; pointer-events: all; }

.modal {
  background: var(--surface1);
  border: 1px solid var(--border);
  border-radius: 20px;
  width: 560px; max-width: 95vw;
  max-height: 90vh;
  overflow-y: auto;
  transform: scale(.95);
  transition: transform var(--transition);
  box-shadow: 0 32px 80px rgba(0,0,0,.6);
}
.modal-overlay.open .modal { transform: scale(1); }

.modal-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 20px 24px;
  border-bottom: 1px solid var(--border);
}
.modal-title { font-size: 1.1rem; font-weight: 700; }
.modal-close {
  background: none; border: none; cursor: pointer;
  color: var(--muted); padding: 4px;
  transition: color var(--transition);
  line-height: 1;
}
.modal-close:hover { color: var(--danger); }
.modal-body { padding: 24px; }
.modal-footer { padding: 16px 24px; border-top: 1px solid var(--border); display: flex; gap: 10px; justify-content: flex-end; }

/* ============================================================
   RESPONSIVE
============================================================ */
@media (max-width: 1024px) {
  .sidebar { transform: translateX(-100%); }
  .sidebar.open { transform: translateX(0); }
  .main { margin-left: 0; }
}
@media (max-width: 640px) {
  .form-grid { grid-template-columns: 1fr; }
  .stats-grid { grid-template-columns: 1fr 1fr; }
  .content { padding: 16px; }
}
</style>
</head>
<body>

<!-- ============================================================
     SIDEBAR
============================================================ -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon"><span class="material-icons-round">rocket_launch</span></div>
    <div>
      <div class="brand-name">GB <span>Launcher</span></div>
      <div style="font-size:.72rem;color:var(--muted);">Admin Panel</div>
    </div>
  </div>

  <nav>
    <div class="nav-section">Principal</div>
    <button class="nav-item active" data-panel="dashboard" onclick="switchPanel('dashboard',this)">
      <span class="material-icons-round">dashboard</span> Dashboard
    </button>
    <button class="nav-item" data-panel="apps" onclick="switchPanel('apps',this)">
      <span class="material-icons-round">apps</span> Aplicativos
    </button>

    <div class="nav-section">Aparência</div>
    <button class="nav-item" data-panel="appearance" onclick="switchPanel('appearance',this)">
      <span class="material-icons-round">palette</span> Logo & Wallpaper
    </button>
    <button class="nav-item" data-panel="settings" onclick="switchPanel('settings',this)">
      <span class="material-icons-round">tune</span> Configurações
    </button>

    <div class="nav-section">Desenvolvedor</div>
    <button class="nav-item" data-panel="api" onclick="switchPanel('api',this)">
      <span class="material-icons-round">code</span> API
    </button>
  </nav>

  <div class="sidebar-footer">
    <div class="user-chip">
      <div class="user-avatar"><?= strtoupper(substr($_SESSION[ADMIN_USER_KEY] ?? 'A', 0, 1)) ?></div>
      <div class="user-info">
        <div class="user-name"><?= htmlspecialchars($_SESSION[ADMIN_USER_KEY] ?? 'Admin') ?></div>
        <div class="user-role">Administrador</div>
      </div>
      <a href="?logout=1" class="logout-btn" title="Sair"><span class="material-icons-round">logout</span></a>
    </div>
  </div>
</aside>

<!-- ============================================================
     MAIN
============================================================ -->
<div class="main">
  <!-- Topbar -->
  <header class="topbar">
    <div>
      <div class="page-title" id="page-title">Dashboard</div>
      <div class="page-subtitle" id="page-subtitle">Visão geral do sistema</div>
    </div>
    <div class="api-badge" onclick="copyApiUrl()" title="Clique para copiar a URL da API">
      <div class="api-dot"></div>
      <span>API:</span>
      <code id="api-url-display"><?= htmlspecialchars($apiUrl) ?></code>
      <span class="material-icons-round" style="font-size:16px">content_copy</span>
    </div>
  </header>

  <div class="content">

    <!-- ====================================================
         PAINEL: DASHBOARD
    ==================================================== -->
    <div class="panel active" id="panel-dashboard">
      <div class="stats-grid">
        <div class="stat-card blue">
          <div class="stat-icon blue"><span class="material-icons-round">apps</span></div>
          <div class="stat-value"><?= $totalApps ?></div>
          <div class="stat-label">Total de Apps</div>
        </div>
        <div class="stat-card amber">
          <div class="stat-icon amber"><span class="material-icons-round">push_pin</span></div>
          <div class="stat-value"><?= $pinnedApps ?></div>
          <div class="stat-label">Apps Fixados</div>
        </div>
        <div class="stat-card green">
          <div class="stat-icon green"><span class="material-icons-round">visibility</span></div>
          <div class="stat-value"><?= $visibleApps ?></div>
          <div class="stat-label">Apps Visíveis</div>
        </div>
        <div class="stat-card purple">
          <div class="stat-icon purple"><span class="material-icons-round">category</span></div>
          <div class="stat-value"><?= count($cats) ?></div>
          <div class="stat-label">Categorias</div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <div class="card-title"><span class="material-icons-round">info</span> Informações da API</div>
          <button class="btn btn-ghost btn-sm" onclick="copyApiUrl()">
            <span class="material-icons-round">content_copy</span> Copiar URL
          </button>
        </div>
        <div class="card-body">
          <p style="margin-bottom:16px;color:var(--muted);font-size:.875rem;">
            Configure a URL abaixo na Launcher Android para que ela carregue os apps e configurações dinamicamente.
          </p>
          <div style="background:var(--surface0);border:1px solid var(--border);border-radius:10px;padding:14px 16px;font-family:monospace;font-size:.875rem;color:var(--primary);word-break:break-all;">
            <?= htmlspecialchars($apiUrl) ?>
          </div>

          <div style="margin-top:24px;">
            <div style="font-weight:700;margin-bottom:12px;">Endpoints disponíveis:</div>
            <div style="display:flex;flex-direction:column;gap:8px;font-size:.85rem;">
              <?php foreach (['all' => 'Tudo (apps + settings + categorias)', 'apps' => 'Apenas apps', 'settings' => 'Apenas configurações', 'categories' => 'Apenas categorias'] as $s => $label): ?>
              <div style="display:flex;gap:12px;align-items:center;padding:10px 14px;background:var(--surface0);border:1px solid var(--border);border-radius:8px;">
                <span style="color:var(--success);font-weight:700;font-family:monospace;min-width:28px;">GET</span>
                <code style="color:var(--primary);">/api.php<?= $s !== 'all' ? '?section='.$s : '' ?></code>
                <span style="color:var(--muted);">— <?= $label ?></span>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <div class="card-title"><span class="material-icons-round">apps</span> Apps Recentes</div>
          <button class="btn btn-primary btn-sm" onclick="switchPanel('apps', document.querySelector('[data-panel=apps]'))">
            Ver todos
          </button>
        </div>
        <div style="overflow-x:auto;">
          <table class="apps-table">
            <thead><tr>
              <th>App</th><th>Pacote</th><th>Categoria</th><th>Status</th>
            </tr></thead>
            <tbody>
              <?php foreach (array_slice($apps, 0, 5) as $app): ?>
              <tr>
                <td>
                  <div class="app-icon-cell">
                    <?php if ($app['icon_url']): ?>
                    <img src="<?= htmlspecialchars($app['icon_url']) ?>" class="app-icon-thumb" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                    <div class="app-icon-fallback" style="display:none"><?= strtoupper(substr($app['name'],0,1)) ?></div>
                    <?php else: ?>
                    <div class="app-icon-fallback"><?= strtoupper(substr($app['name'],0,1)) ?></div>
                    <?php endif; ?>
                    <div>
                      <div class="app-name"><?= htmlspecialchars($app['name']) ?></div>
                    </div>
                  </div>
                </td>
                <td><span class="app-pkg"><?= htmlspecialchars($app['package_name']) ?></span></td>
                <td><span class="badge badge-category"><?= htmlspecialchars($app['category']) ?></span></td>
                <td>
                  <?php if ($app['is_pinned']): ?><span class="badge badge-pinned">Fixado</span><?php endif; ?>
                  <span class="badge <?= $app['is_visible'] ? 'badge-visible' : 'badge-hidden' ?>">
                    <?= $app['is_visible'] ? 'Visível' : 'Oculto' ?>
                  </span>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div><!-- /dashboard -->

    <!-- ====================================================
         PAINEL: APPS
    ==================================================== -->
    <div class="panel" id="panel-apps">
      <div class="card">
        <div class="apps-toolbar">
          <div class="search-wrap">
            <span class="material-icons-round">search</span>
            <input type="text" class="search-input" id="app-search" placeholder="Buscar app..." oninput="filterApps()">
          </div>
          <button class="btn btn-primary" onclick="openAddModal()">
            <span class="material-icons-round">add</span> Adicionar App
          </button>
        </div>
        <div style="overflow-x:auto;">
          <table class="apps-table" id="apps-table">
            <thead><tr>
              <th>App</th><th>Pacote Android</th><th>Categoria</th><th>Status</th><th>Ações</th>
            </tr></thead>
            <tbody id="apps-tbody">
              <?php foreach ($apps as $app): ?>
              <tr data-id="<?= $app['id'] ?>" data-name="<?= strtolower($app['name']) ?>" data-pkg="<?= strtolower($app['package_name']) ?>">
                <td>
                  <div class="app-icon-cell">
                    <?php if ($app['icon_url']): ?>
                    <img src="<?= htmlspecialchars($app['icon_url']) ?>" class="app-icon-thumb" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
                    <div class="app-icon-fallback" style="display:none"><?= strtoupper(substr($app['name'],0,1)) ?></div>
                    <?php else: ?>
                    <div class="app-icon-fallback"><?= strtoupper(substr($app['name'],0,1)) ?></div>
                    <?php endif; ?>
                    <div>
                      <div class="app-name"><?= htmlspecialchars($app['name']) ?></div>
                      <?php if ($app['description']): ?>
                      <div class="app-pkg"><?= htmlspecialchars(substr($app['description'],0,40)) ?>...</div>
                      <?php endif; ?>
                    </div>
                  </div>
                </td>
                <td><span class="app-pkg"><?= htmlspecialchars($app['package_name']) ?></span></td>
                <td><span class="badge badge-category"><?= htmlspecialchars($app['category']) ?></span></td>
                <td>
                  <?php if ($app['is_pinned']): ?><span class="badge badge-pinned">Fixado</span><?php endif; ?>
                  <span class="badge <?= $app['is_visible'] ? 'badge-visible' : 'badge-hidden' ?>">
                    <?= $app['is_visible'] ? 'Visível' : 'Oculto' ?>
                  </span>
                </td>
                <td>
                  <div class="actions-cell">
                    <button class="btn btn-ghost btn-sm btn-icon" title="Editar"
                      onclick="openEditModal(<?= htmlspecialchars(json_encode($app), ENT_QUOTES) ?>)">
                      <span class="material-icons-round">edit</span>
                    </button>
                    <button class="btn btn-danger btn-sm btn-icon" title="Remover"
                      onclick="deleteApp(<?= $app['id'] ?>, '<?= htmlspecialchars($app['name'], ENT_QUOTES) ?>')">
                      <span class="material-icons-round">delete</span>
                    </button>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div><!-- /apps -->

    <!-- ====================================================
         PAINEL: APARÊNCIA
    ==================================================== -->
    <div class="panel" id="panel-appearance">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">

        <!-- Logo -->
        <div class="card">
          <div class="card-header">
            <div class="card-title"><span class="material-icons-round">image</span> Logo da Launcher</div>
          </div>
          <div class="card-body">
            <div class="upload-zone" id="logo-zone" onclick="document.getElementById('logo-file').click()">
              <input type="file" id="logo-file" accept="image/*" onchange="handleUpload(this,'logo','logo-preview','logo-zone')">
              <?php if (!empty($settings['logo_url'])): ?>
              <img id="logo-preview" src="<?= htmlspecialchars($settings['logo_url']) ?>" class="upload-preview">
              <?php else: ?>
              <div class="upload-icon material-icons-round">add_photo_alternate</div>
              <div class="upload-label">Clique para enviar o logo</div>
              <div class="upload-hint">PNG, SVG recomendados • Máx 5MB</div>
              <img id="logo-preview" class="upload-preview" style="display:none">
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Wallpaper -->
        <div class="card">
          <div class="card-header">
            <div class="card-title"><span class="material-icons-round">wallpaper</span> Wallpaper / Fundo</div>
          </div>
          <div class="card-body">
            <div class="upload-zone" id="wp-zone" onclick="document.getElementById('wp-file').click()">
              <input type="file" id="wp-file" accept="image/*" onchange="handleUpload(this,'wallpaper','wp-preview','wp-zone')">
              <?php if (!empty($settings['wallpaper_url'])): ?>
              <img id="wp-preview" src="<?= htmlspecialchars($settings['wallpaper_url']) ?>"
                   class="upload-preview" style="max-height:180px;width:100%;object-fit:cover;border-radius:10px;">
              <?php else: ?>
              <div class="upload-icon material-icons-round">image</div>
              <div class="upload-label">Clique para enviar o wallpaper</div>
              <div class="upload-hint">JPG, PNG, WEBP • Máx 5MB • 1920×1080 recomendado</div>
              <img id="wp-preview" class="upload-preview" style="display:none">
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div><!-- /appearance -->

    <!-- ====================================================
         PAINEL: CONFIGURAÇÕES
    ==================================================== -->
    <div class="panel" id="panel-settings">
      <div class="card">
        <div class="card-header">
          <div class="card-title"><span class="material-icons-round">tune</span> Configurações Gerais</div>
        </div>
        <div class="card-body">
          <form id="settings-form" onsubmit="saveSettings(event)">
            <div class="form-grid" style="max-width:600px;">
              <div class="field-group form-full">
                <label class="field-label">Nome da Launcher</label>
                <input type="text" name="launcher_title" value="<?= htmlspecialchars($settings['launcher_title'] ?? 'GB Launcher') ?>">
              </div>
              <div class="field-group">
                <label class="field-label">Cor Primária</label>
                <input type="color" name="primary_color" value="<?= htmlspecialchars($settings['primary_color'] ?? '#3b82f6') ?>">
              </div>
              <div class="field-group">
                <label class="field-label">Cor de Destaque</label>
                <input type="color" name="accent_color" value="<?= htmlspecialchars($settings['accent_color'] ?? '#f59e0b') ?>">
              </div>
            </div>
            <div style="margin-top:24px;">
              <button type="submit" class="btn btn-primary">
                <span class="material-icons-round">save</span> Salvar Configurações
              </button>
            </div>
          </form>
        </div>
      </div>
    </div><!-- /settings -->

    <!-- ====================================================
         PAINEL: API
    ==================================================== -->
    <div class="panel" id="panel-api">
      <div class="card">
        <div class="card-header">
          <div class="card-title"><span class="material-icons-round">code</span> Documentação da API</div>
        </div>
        <div class="card-body">
          <p style="color:var(--muted);margin-bottom:20px;font-size:.9rem;">
            A API retorna JSON consumido pela Launcher Android. Configure a URL abaixo nas configurações da Launcher.
          </p>

          <div style="background:var(--surface0);padding:16px;border-radius:12px;margin-bottom:24px;font-family:monospace;font-size:.85rem;">
            <span style="color:var(--success)">Base URL: </span>
            <span style="color:var(--primary)"><?= htmlspecialchars($apiUrl) ?></span>
          </div>

          <div style="display:flex;flex-direction:column;gap:16px;">
            <?php
            $endpoints = [
              ['GET', '', 'all', 'Retorna tudo: apps, settings, categorias e banners'],
              ['GET', '?section=apps', 'apps', 'Retorna apenas a lista de apps visíveis'],
              ['GET', '?section=settings', 'settings', 'Retorna as configurações visuais'],
              ['GET', '?section=categories', 'categories', 'Retorna as categorias disponíveis'],
              ['GET', '?section=banners', 'banners', 'Retorna os banners ativos'],
            ];
            foreach ($endpoints as [$method, $path, $section, $desc]):
            ?>
            <div style="border:1px solid var(--border);border-radius:12px;overflow:hidden;">
              <div style="padding:12px 16px;background:var(--surface0);display:flex;align-items:center;gap:12px;">
                <span style="background:rgba(34,197,94,.15);color:var(--success);padding:3px 10px;border-radius:6px;font-size:.78rem;font-weight:700;font-family:monospace;"><?= $method ?></span>
                <code style="color:var(--primary);font-size:.85rem;">/api.php<?= $path ?></code>
                <span style="color:var(--muted);font-size:.82rem;margin-left:auto;"><?= $desc ?></span>
              </div>
            </div>
            <?php endforeach; ?>
          </div>

          <div style="margin-top:32px;">
            <div style="font-weight:700;margin-bottom:12px;">Exemplo de resposta JSON:</div>
            <pre style="background:var(--surface0);border:1px solid var(--border);border-radius:12px;padding:20px;font-size:.8rem;overflow-x:auto;color:var(--text);line-height:1.6;"><?= htmlspecialchars(json_encode([
  'success'   => true,
  'timestamp' => time(),
  'settings'  => ['launcher_title' => 'GB Launcher', 'logo_url' => 'https://seuservidor.com/uploads/logo.png', 'wallpaper_url' => 'https://seuservidor.com/uploads/wallpaper.jpg'],
  'apps'      => [['id' => 1, 'name' => 'YouTube', 'package_name' => 'com.google.android.youtube', 'icon_url' => 'https://...', 'category' => 'entretenimento', 'is_pinned' => true]],
  'categories'=> [['slug' => 'entretenimento', 'name' => 'Entretenimento', 'icon' => 'movie']],
  'total_apps'=> 6,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
          </div>
        </div>
      </div>
    </div><!-- /api -->

  </div><!-- /content -->
</div><!-- /main -->

<!-- ============================================================
     MODAL: Adicionar / Editar App
============================================================ -->
<div class="modal-overlay" id="app-modal" onclick="closeModal(event)">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="modal-title">Adicionar App</div>
      <button class="modal-close" onclick="closeModalById('app-modal')">
        <span class="material-icons-round">close</span>
      </button>
    </div>
    <div class="modal-body">
      <form id="app-form" onsubmit="submitApp(event)">
        <input type="hidden" id="app-id" name="id">
        <input type="hidden" id="app-action" name="action" value="add_app">

        <div class="form-grid">
          <div class="field-group form-full">
            <label class="field-label">Nome do App *</label>
            <input type="text" id="app-name" name="name" placeholder="Ex: YouTube" required>
          </div>
          <div class="field-group form-full">
            <label class="field-label">Pacote Android *</label>
            <input type="text" id="app-pkg" name="package_name" placeholder="Ex: com.google.android.youtube" required>
          </div>
          <div class="field-group form-full">
            <label class="field-label">URL do Ícone</label>
            <input type="url" id="app-icon" name="icon_url" placeholder="https://exemplo.com/icone.png" oninput="previewModalIcon()">
          </div>

          <div style="grid-column:1/-1;display:flex;align-items:center;gap:16px;">
            <img id="modal-icon-preview" src="" style="width:56px;height:56px;border-radius:14px;object-fit:contain;display:none;background:var(--surface2);padding:4px;">
            <div class="field-group" style="flex:1;">
              <label class="field-label">Categoria</label>
              <select id="app-cat" name="category">
                <?php foreach ($cats as $c): ?>
                <option value="<?= htmlspecialchars($c['slug']) ?>"><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="field-group form-full">
            <label class="field-label">Descrição</label>
            <textarea id="app-desc" name="description" placeholder="Descrição opcional do app..."></textarea>
          </div>

          <div style="display:flex;gap:24px;grid-column:1/-1;">
            <label class="check-wrap">
              <input type="checkbox" id="app-visible" name="is_visible" checked>
              Visível na Launcher
            </label>
            <label class="check-wrap">
              <input type="checkbox" id="app-pinned" name="is_pinned">
              Fixar no topo
            </label>
          </div>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModalById('app-modal')">Cancelar</button>
      <button class="btn btn-primary" onclick="document.getElementById('app-form').dispatchEvent(new Event('submit',{cancelable:true,bubbles:true}))">
        <span class="material-icons-round">save</span>
        <span id="modal-submit-label">Adicionar</span>
      </button>
    </div>
  </div>
</div>

<!-- Toast Container -->
<div id="toast-container"></div>

<!-- ============================================================
     JAVASCRIPT
============================================================ -->
<script>
const apiUrl = <?= json_encode($apiUrl) ?>;

// --------------------------------------------------
// Navigation
// --------------------------------------------------
const panels = {
  dashboard:  { title: 'Dashboard',       subtitle: 'Visão geral do sistema' },
  apps:       { title: 'Aplicativos',     subtitle: 'Gerenciar apps da Launcher' },
  appearance: { title: 'Logo & Wallpaper', subtitle: 'Personalizar aparência visual' },
  settings:   { title: 'Configurações',   subtitle: 'Configurações gerais da Launcher' },
  api:        { title: 'API',             subtitle: 'Documentação e URL da API JSON' },
};

function switchPanel(name, btn) {
  document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.nav-item').forEach(b => b.classList.remove('active'));
  document.getElementById('panel-' + name).classList.add('active');
  btn.classList.add('active');
  document.getElementById('page-title').textContent    = panels[name].title;
  document.getElementById('page-subtitle').textContent = panels[name].subtitle;
}

// --------------------------------------------------
// Copy API URL
// --------------------------------------------------
function copyApiUrl() {
  navigator.clipboard.writeText(apiUrl).then(() => toast('URL da API copiada!', 'success'));
}

// --------------------------------------------------
// Toast
// --------------------------------------------------
function toast(msg, type = 'success') {
  const el = document.createElement('div');
  el.className = `toast ${type}`;
  el.innerHTML = `
    <span class="material-icons-round" style="color:${type==='success'?'var(--success)':'var(--danger)'}">
      ${type==='success' ? 'check_circle' : 'error'}
    </span>
    <span>${msg}</span>`;
  document.getElementById('toast-container').appendChild(el);
  setTimeout(() => el.remove(), 3500);
}

// --------------------------------------------------
// POST helper
// --------------------------------------------------
async function post(data) {
  const fd = new FormData();
  for (const [k, v] of Object.entries(data)) fd.append(k, v);
  const res = await fetch('', { method: 'POST', body: fd });
  return res.json();
}

// --------------------------------------------------
// App Modal
// --------------------------------------------------
function openAddModal() {
  document.getElementById('modal-title').textContent      = 'Adicionar App';
  document.getElementById('modal-submit-label').textContent = 'Adicionar';
  document.getElementById('app-action').value             = 'add_app';
  document.getElementById('app-id').value                 = '';
  document.getElementById('app-form').reset();
  document.getElementById('modal-icon-preview').style.display = 'none';
  document.getElementById('app-modal').classList.add('open');
}

function openEditModal(app) {
  document.getElementById('modal-title').textContent       = 'Editar App';
  document.getElementById('modal-submit-label').textContent = 'Salvar';
  document.getElementById('app-action').value              = 'edit_app';
  document.getElementById('app-id').value                  = app.id;
  document.getElementById('app-name').value                = app.name;
  document.getElementById('app-pkg').value                 = app.package_name;
  document.getElementById('app-icon').value                = app.icon_url || '';
  document.getElementById('app-cat').value                 = app.category;
  document.getElementById('app-desc').value                = app.description || '';
  document.getElementById('app-visible').checked           = !!parseInt(app.is_visible);
  document.getElementById('app-pinned').checked            = !!parseInt(app.is_pinned);
  previewModalIcon();
  document.getElementById('app-modal').classList.add('open');
}

function closeModal(e) {
  if (e.target === document.getElementById('app-modal')) closeModalById('app-modal');
}
function closeModalById(id) { document.getElementById(id).classList.remove('open'); }

function previewModalIcon() {
  const url = document.getElementById('app-icon').value;
  const img  = document.getElementById('modal-icon-preview');
  if (url) { img.src = url; img.style.display = 'block'; }
  else { img.style.display = 'none'; }
}

async function submitApp(e) {
  e.preventDefault();
  const form = document.getElementById('app-form');
  const data = Object.fromEntries(new FormData(form));
  if (!data.name || !data.package_name) return toast('Nome e pacote são obrigatórios.', 'error');

  const json = await post({
    ...data,
    is_visible: form.querySelector('#app-visible').checked ? 'on' : '',
    is_pinned:  form.querySelector('#app-pinned').checked  ? 'on' : '',
  });

  if (json.success) {
    toast(json.msg, 'success');
    closeModalById('app-modal');
    setTimeout(() => location.reload(), 800);
  } else {
    toast(json.error || 'Erro ao salvar.', 'error');
  }
}

async function deleteApp(id, name) {
  if (!confirm(`Remover "${name}"?`)) return;
  const json = await post({ action: 'delete_app', id });
  if (json.success) {
    toast(json.msg, 'success');
    document.querySelector(`[data-id="${id}"]`)?.remove();
  } else {
    toast(json.error || 'Erro.', 'error');
  }
}

// --------------------------------------------------
// Search
// --------------------------------------------------
function filterApps() {
  const q = document.getElementById('app-search').value.toLowerCase();
  document.querySelectorAll('#apps-tbody tr').forEach(row => {
    const match = row.dataset.name?.includes(q) || row.dataset.pkg?.includes(q);
    row.style.display = match ? '' : 'none';
  });
}

// --------------------------------------------------
// Settings
// --------------------------------------------------
async function saveSettings(e) {
  e.preventDefault();
  const form = e.target;
  const data = { action: 'save_settings', ...Object.fromEntries(new FormData(form)) };
  const json = await post(data);
  toast(json.success ? json.msg : (json.error || 'Erro.'), json.success ? 'success' : 'error');
}

// --------------------------------------------------
// Upload
// --------------------------------------------------
async function handleUpload(input, type, previewId, zoneId) {
  const file = input.files[0];
  if (!file) return;

  const fd = new FormData();
  fd.append('file', file);
  fd.append('type', type);

  try {
    const res  = await fetch('upload.php', { method: 'POST', body: fd });
    const json = await res.json();
    if (json.success) {
      const img = document.getElementById(previewId);
      img.src   = json.url + '?t=' + Date.now();
      img.style.display = 'block';
      toast('Imagem enviada com sucesso!', 'success');
    } else {
      toast(json.error || 'Erro no upload.', 'error');
    }
  } catch {
    toast('Falha na conexão.', 'error');
  }
}
</script>
</body>
</html>
