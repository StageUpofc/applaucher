<?php
/**
 * GB Launcher - API JSON
 * Endpoint consumido pela Launcher Android
 *
 * GET /api.php                → dados completos (apps + settings)
 * GET /api.php?section=apps   → somente apps
 * GET /api.php?section=settings → somente configurações
 *
 * Header de autenticação opcional: X-API-Token: <token>
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Token');
header('Cache-Control: no-store, no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/db.php';

// Autenticação por token (opcional – descomente para ativar)
// $db = getDB();
// $row = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'api_token'")->fetch();
// $expected = $row['setting_value'] ?? '';
// $received  = $_SERVER['HTTP_X_API_TOKEN'] ?? '';
// if ($expected && $received !== $expected) {
//     http_response_code(401);
//     echo json_encode(['error' => 'Unauthorized']);
//     exit;
// }

try {
    $db      = getDB();
    $section = $_GET['section'] ?? 'all';
    $baseUrl = getBaseUrl();

    $response = ['success' => true, 'timestamp' => time()];

    // -------------------------------------------------------
    // Settings
    // -------------------------------------------------------
    if (in_array($section, ['all', 'settings'])) {
        $stmt = $db->query("SELECT setting_key, setting_value FROM settings");
        $raw  = $stmt->fetchAll();

        $settings = [];
        foreach ($raw as $row) {
            $val = $row['setting_value'];
            // Adiciona baseUrl em URLs relativas
            if ($row['setting_key'] !== 'api_token' && $val && str_starts_with($val, '/')) {
                $val = $baseUrl . $val;
            }
            $settings[$row['setting_key']] = $val;
        }
        $response['settings'] = $settings;
    }

    // -------------------------------------------------------
    // Apps
    // -------------------------------------------------------
    if (in_array($section, ['all', 'apps'])) {
        $stmt = $db->query(
            "SELECT id, name, package_name, icon_url, category, description, position, is_pinned
             FROM apps
             WHERE is_visible = 1
             ORDER BY is_pinned DESC, position ASC, name ASC"
        );
        $apps = $stmt->fetchAll();

        // Resolve URLs relativas
        foreach ($apps as &$app) {
            if ($app['icon_url'] && str_starts_with($app['icon_url'], '/')) {
                $app['icon_url'] = $baseUrl . $app['icon_url'];
            }
            $app['is_pinned'] = (bool) $app['is_pinned'];
        }
        unset($app);

        $response['apps'] = $apps;
        $response['total_apps'] = count($apps);
    }

    // -------------------------------------------------------
    // Categories
    // -------------------------------------------------------
    if (in_array($section, ['all', 'categories'])) {
        $stmt = $db->query(
            "SELECT slug, name, icon FROM categories ORDER BY position ASC"
        );
        $response['categories'] = $stmt->fetchAll();
    }

    // -------------------------------------------------------
    // Banners
    // -------------------------------------------------------
    if (in_array($section, ['all', 'banners'])) {
        $stmt = $db->query(
            "SELECT id, title, image_url, action FROM banners
             WHERE is_active = 1
             ORDER BY position ASC"
        );
        $banners = $stmt->fetchAll();
        foreach ($banners as &$b) {
            if ($b['image_url'] && str_starts_with($b['image_url'], '/')) {
                $b['image_url'] = $baseUrl . $b['image_url'];
            }
        }
        unset($b);
        $response['banners'] = $banners;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// -------------------------------------------------------
// Helpers
// -------------------------------------------------------
function getBaseUrl(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir    = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    return $scheme . '://' . $host . $dir;
}
