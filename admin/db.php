<?php
/**
 * GB Launcher - Conexão com Banco de Dados
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'gb_launcher');
define('DB_USER', 'root');        // Troque pelo seu usuário MySQL
define('DB_PASS', '');            // Troque pela sua senha MySQL
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['error' => 'Erro de conexão com o banco de dados.']));
        }
    }
    return $pdo;
}
