<?php
// Conexão com o banco de dados.
// Os valores padrão batem com o docker-compose.yml; em produção,
// troque via variáveis de ambiente (DB_HOST, DB_NAME, DB_USER, DB_PASS).

$host = getenv('DB_HOST') ?: 'mariadb';
$db   = getenv('DB_NAME') ?: 'clima_tcc';
$user = getenv('DB_USER') ?: 'clima_user';
$pass = getenv('DB_PASS') ?: 'clima_pass';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    die(json_encode(['erro' => 'Falha na conexão com o banco de dados']));
}