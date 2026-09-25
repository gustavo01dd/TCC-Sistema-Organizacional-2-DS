<?php
// ============================================================
// Configuração central: fuso horário, conexão com o banco e sessão
// ============================================================

// Mesmo fuso das datas digitadas no painel (São Paulo)
date_default_timezone_set('America/Sao_Paulo');

function conectarBanco() {
    // Os valores padrão batem com o docker-compose.yml.
    // Em outro servidor, troque aqui ou pelas variáveis de ambiente.
    $host = getenv('DB_HOST') ?: 'mariadb';
    $banco = getenv('DB_NAME') ?: 'clima_tcc';
    $usuario = getenv('DB_USER') ?: 'clima_user';
    $senha = getenv('DB_PASS') ?: 'clima_pass';
    $fuso = getenv('DB_TIMEZONE') ?: '-03:00';

    $pdo = new PDO("mysql:host=$host;dbname=$banco;charset=utf8mb4", $usuario, $senha, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Faz o NOW() do banco usar o mesmo fuso do PHP e do navegador.
    // Sem isso o banco usaria UTC, e uma pesquisa marcada para abrir
    // às 19:45 abriria 3 horas antes.
    $stmt = $pdo->prepare("SET time_zone = ?");
    $stmt->execute([$fuso]);

    return $pdo;
}

function iniciarSessao() {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,   // o JavaScript não consegue ler o cookie de sessão
            'samesite' => 'Lax',  // outros sites não conseguem usar a sessão
        ]);
        session_start();
    }
}