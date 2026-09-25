<?php
// ============================================================
// Prepara o banco: cria as tabelas que faltarem (a partir do schema.sql)
// e cadastra os usuários e a pesquisa de exemplo.
// Pode rodar quantas vezes quiser, não duplica nada.
//
// Uso: docker compose exec php php /var/www/database/seed.php
// ============================================================

require_once __DIR__ . '/../public/config.php';
require_once __DIR__ . '/../public/funcoes.php';

echo "== Preparando o banco da Pesquisa de Clima ==\n";

// 1) conecta. Na primeira vez, o MariaDB pode levar alguns segundos para subir.
$pdo = null;
for ($tentativa = 1; $tentativa <= 30; $tentativa++) {
    try {
        $pdo = conectarBanco();
        break;
    } catch (PDOException $e) {
        echo "Aguardando o banco de dados ficar pronto ($tentativa/30)...\n";
        sleep(2);
    }
}
if (!$pdo) {
    echo "ERRO: não consegui conectar ao banco. Confira se os containers estão rodando (docker compose ps).\n";
    exit(1);
}

// 2) cria as tabelas que ainda não existem
$sql = file_get_contents(__DIR__ . '/schema.sql');
$linhas = array_filter(explode("\n", $sql), fn($linha) => !str_starts_with(trim($linha), '--'));
$comandos = array_filter(array_map('trim', explode(';', implode("\n", $linhas))));
foreach ($comandos as $comando) {
    if (preg_match('/^(CREATE DATABASE|USE)\b/i', $comando)) {
        continue;
    }
    $pdo->exec($comando);
}
echo "Tabelas conferidas.\n";

if ($pdo->query("SHOW TABLES LIKE 'gestores'")->fetch()) {
    echo "Aviso: a tabela antiga 'gestores' não é mais usada (os gestores agora ficam em 'funcionarios').\n";
}

// 3) usuários de teste (todos com a senha 123456)
$senhaPadrao = '123456';
$usuarios = [
    ['Gestor Teste', 'gestor@escola.com', 'gestor', 'Coordenação'],
    ['Funcionário 1', 'funcionario1@escola.com', 'funcionario', 'Professor'],
    ['Funcionário 2', 'funcionario2@escola.com', 'funcionario', 'Professor'],
    ['Funcionário 3', 'funcionario3@escola.com', 'funcionario', 'Secretaria'],
];
$stmt = $pdo->prepare("INSERT INTO funcionarios (nome, email, senha_hash, cargo, tipo_perfil) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE senha_hash = VALUES(senha_hash), tipo_perfil = VALUES(tipo_perfil), ativo = 1");
foreach ($usuarios as [$nome, $email, $perfil, $cargo]) {
    $stmt->execute([$nome, $email, password_hash($senhaPadrao, PASSWORD_DEFAULT), $cargo, $perfil]);
    echo "Usuário pronto: $email / $senhaPadrao ($perfil)\n";
}

// 4) pesquisa de exemplo com as 10 perguntas originais
$titulo = 'Pesquisa de Clima Organizacional 2026';
$stmt = $pdo->prepare("SELECT id FROM formularios WHERE titulo = ?");
$stmt->execute([$titulo]);

if ($stmt->fetch()) {
    echo "A pesquisa de exemplo já existe, nada a fazer.\n";
} else {
    $perguntas = [
        'Como você avalia o ambiente de trabalho?',
        'Você se sente valorizado pela gestão?',
        'Como avalia a comunicação entre as equipes?',
        'Você tem os recursos necessários para realizar seu trabalho?',
        'Como avalia as oportunidades de crescimento profissional?',
        'Você recomendaria a instituição como um bom lugar para trabalhar?',
        'Como avalia o equilíbrio entre vida pessoal e trabalho?',
        'Você recebe feedback construtivo sobre seu desempenho?',
        'Como avalia a infraestrutura física do local de trabalho?',
        'Você se sente parte de uma equipe unida?',
    ];

    $pdo->beginTransaction();

    // só uma pesquisa ativa por vez
    foreach ($pdo->query("SELECT id FROM formularios WHERE status = 'ativo'")->fetchAll(PDO::FETCH_COLUMN) as $idAtivo) {
        encerrarFormulario($pdo, (int)$idAtivo);
    }

    // RN01: aberta por 7 dias a partir de agora
    $abertura = new DateTime();
    $fechamento = (clone $abertura)->modify('+' . DIAS_PESQUISA_ABERTA . ' days');

    $stmt = $pdo->prepare("INSERT INTO formularios (titulo, descricao, status, data_abertura, data_fechamento) VALUES (?, ?, 'ativo', ?, ?)");
    $stmt->execute([$titulo, 'Pesquisa inicial de clima organizacional', $abertura->format('Y-m-d H:i:s'), $fechamento->format('Y-m-d H:i:s')]);
    $formularioId = (int)$pdo->lastInsertId();

    $stmtP = $pdo->prepare("INSERT INTO perguntas (formulario_id, texto, ordem) VALUES (?, ?, ?)");
    foreach ($perguntas as $i => $texto) {
        $stmtP->execute([$formularioId, $texto, $i + 1]);
    }
    $pdo->commit();

    echo "Pesquisa de exemplo criada e ativa até " . $fechamento->format('d/m/Y H:i') . " (" . DIAS_PESQUISA_ABERTA . " dias).\n";
}

echo "\nPronto! Abra http://localhost:8080\n";