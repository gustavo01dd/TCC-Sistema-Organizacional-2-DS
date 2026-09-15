<?php
// Popula o banco com dados iniciais: o gestor de teste e o
// primeiro formulário (com as 10 perguntas originais do projeto).
// Rodar com: php database/seed.php  (ou "docker compose exec php php ../database/seed.php")

require_once __DIR__ . '/../public/config.php';

echo "== Seed: Pesquisa de Clima Organizacional ==\n";

// ---- Gestor de teste ----
$nome = 'Gestor Teste';
$email = 'gestor@escola.com';
$senha = '123456';
$hash = password_hash($senha, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("
    INSERT INTO gestores (nome, email, senha_hash)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE senha_hash = VALUES(senha_hash)
");
$stmt->execute([$nome, $email, $hash]);
echo "Gestor de teste pronto -> $email / $senha\n";

// ---- Formulário inicial ----
$tituloInicial = 'Pesquisa de Clima Organizacional 2026';

$stmt = $pdo->prepare("SELECT id FROM formularios WHERE titulo = ?");
$stmt->execute([$tituloInicial]);
$existente = $stmt->fetch();

if ($existente) {
    echo "Formulário inicial já existe (id {$existente['id']}), nada a fazer.\n";
} else {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO formularios (titulo, descricao, status, respondentes_esperados, data_abertura)
        VALUES (?, ?, 'ativo', ?, NOW())
    ");
    $stmt->execute([
        $tituloInicial,
        'Sua opinião é fundamental para melhorarmos juntos',
        151,
    ]);
    $formularioId = $pdo->lastInsertId();

    $perguntas = [
        'O ambiente de trabalho é respeitoso e colaborativo',
        'A comunicação interna é clara e eficiente',
        'Tenho os recursos necessários para realizar meu trabalho',
        'Sinto que meu trabalho é reconhecido',
        'Tenho boas oportunidades de desenvolvimento',
        'A liderança está aberta para ouvir os funcionários',
        'Existe equilíbrio entre trabalho e vida pessoal',
        'Sinto-me seguro para dar minha opinião',
        'As decisões da empresa são comunicadas de forma clara',
        'Eu recomendaria esta empresa como um bom lugar para trabalhar',
    ];

    $stmtP = $pdo->prepare("INSERT INTO perguntas (formulario_id, texto, ordem) VALUES (?, ?, ?)");
    foreach ($perguntas as $i => $texto) {
        $stmtP->execute([$formularioId, $texto, $i + 1]);
    }

    $pdo->commit();
    echo "Formulário inicial criado (id $formularioId) com " . count($perguntas) . " perguntas.\n";
}

echo "== Seed concluído ==\n";
