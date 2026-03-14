<?php
/**
 * GB Launcher - Upload de Imagens
 */
require_once __DIR__ . '/auth.php';
requireLogin();

header('Content-Type: application/json');

$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$type    = $_POST['type'] ?? ''; // logo | wallpaper | icon
$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'];

if (empty($_FILES['file'])) {
    echo json_encode(['success' => false, 'error' => 'Nenhum arquivo enviado.']);
    exit;
}

$file     = $_FILES['file'];
$mimeType = mime_content_type($file['tmp_name']);

if (!in_array($mimeType, $allowed)) {
    echo json_encode(['success' => false, 'error' => 'Tipo de arquivo não permitido.']);
    exit;
}

if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'error' => 'Arquivo muito grande (máx 5 MB).']);
    exit;
}

$ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
$safeName = ($type === 'logo' || $type === 'wallpaper')
    ? $type . '.' . $ext
    : uniqid('icon_', true) . '.' . $ext;

$dest = $uploadDir . $safeName;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(['success' => false, 'error' => 'Falha ao salvar o arquivo.']);
    exit;
}

$url = '/uploads/' . $safeName;

// Atualiza setting se for logo ou wallpaper
if (in_array($type, ['logo', 'wallpaper'])) {
    $db   = getDB();
    $key  = $type === 'logo' ? 'logo_url' : 'wallpaper_url';
    $stmt = $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
    $stmt->execute([$url, $key]);
}

echo json_encode(['success' => true, 'url' => $url]);
