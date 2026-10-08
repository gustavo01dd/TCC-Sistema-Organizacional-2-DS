<?php
// ============================================================
// Prepara o banco: cria as tabelas que faltarem, atualiza bancos de versões
// anteriores (sem apagar nada) e cadastra os usuários e a pesquisa de exemplo.
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

// 3) atualiza bancos criados por versões anteriores do sistema
$migracoes = [
    "ALTER TABLE funcionarios ADD COLUMN IF NOT EXISTS termo_versao INT UNSIGNED NULL AFTER ativo",
    "ALTER TABLE funcionarios ADD COLUMN IF NOT EXISTS termo_aceito_em DATETIME NULL AFTER termo_versao",
    "ALTER TABLE perguntas ADD COLUMN IF NOT EXISTS tipo ENUM('nota', 'sim_nao', 'multipla') NOT NULL DEFAULT 'nota' AFTER texto",
    "ALTER TABLE perguntas ADD COLUMN IF NOT EXISTS opcoes TEXT NULL AFTER tipo",
    "ALTER TABLE perguntas ADD COLUMN IF NOT EXISTS categoria VARCHAR(60) NULL AFTER opcoes",
    "ALTER TABLE resposta_itens MODIFY nota TINYINT UNSIGNED NULL",
    "ALTER TABLE resposta_itens ADD COLUMN IF NOT EXISTS opcao TINYINT UNSIGNED NULL AFTER nota",
    "ALTER TABLE funcionarios ADD COLUMN IF NOT EXISTS senha_alterada_em DATETIME NULL AFTER termo_aceito_em",
    "ALTER TABLE funcionarios ADD COLUMN IF NOT EXISTS trocar_senha TINYINT(1) NOT NULL DEFAULT 0 AFTER senha_alterada_em",
];
foreach ($migracoes as $comando) {
    $pdo->exec($comando);
}
echo "Estrutura atualizada para a versão atual.\n";

if ($pdo->query("SHOW TABLES LIKE 'gestores'")->fetch()) {
    echo "Aviso: a tabela antiga 'gestores' não é mais usada (os gestores agora ficam em 'funcionarios').\n";
}

// 4) chave de criptografia e criptografia de comentários antigos (RNF05)
chaveCriptografia();
if (!getenv('APP_KEY')) {
    echo "Chave de criptografia: " . caminhoChave() . " (guarde junto com os backups).\n";
}
$antigos = $pdo->query("SELECT id, comentario FROM respostas WHERE comentario IS NOT NULL AND comentario <> '' AND comentario NOT LIKE 'enc1:%'")->fetchAll();
$stmt = $pdo->prepare("UPDATE respostas SET comentario = ? WHERE id = ?");
foreach ($antigos as $r) {
    $stmt->execute([criptografar($r['comentario']), $r['id']]);
}
$relatoriosAntigos = $pdo->query("SELECT id, dados_consolidados FROM relatorios WHERE dados_consolidados NOT LIKE 'enc1:%'")->fetchAll();
$stmt = $pdo->prepare("UPDATE relatorios SET dados_consolidados = ? WHERE id = ?");
foreach ($relatoriosAntigos as $r) {
    $stmt->execute([criptografar($r['dados_consolidados']), $r['id']]);
}
if (count($antigos) + count($relatoriosAntigos) > 0) {
    echo 'Criptografados: ' . count($antigos) . ' comentário(s) e ' . count($relatoriosAntigos) . " relatório(s) antigos.\n";
}

// 5) na primeira atualização, as pesquisas que já existiam são marcadas como notificadas,
//    para não dispararem e-mails atrasados de abertura/encerramento
if ((int)$pdo->query("SELECT COUNT(*) FROM notificacoes")->fetchColumn() === 0) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO notificacoes (formulario_id, tipo, chave, enviada_em) VALUES (?, ?, ?, NOW())");
    foreach ($pdo->query("SELECT id, status, data_abertura FROM formularios WHERE data_abertura IS NOT NULL")->fetchAll() as $f) {
        $ciclo = chaveCiclo($f);
        $stmt->execute([$f['id'], 'abertura', 'abertura-' . $ciclo]);
        if ($f['status'] === 'encerrado') {
            $stmt->execute([$f['id'], 'encerramento', 'encerramento-' . $ciclo]);
        }
    }
}

// 5b) bancos criados quando o exemplo era de uma escola: os 4 usuários de teste passam
//     de @escola.com para @empresa.com (o histórico de quem respondeu é mantido) e a
//     pesquisa de exemplo ganha os textos de empresa. Só mexe no que o próprio seed criou.
$renomear = [
    ['gestor@escola.com', 'gestor@empresa.com', 'Coordenação', 'Gerência'],
    ['funcionario1@escola.com', 'funcionario1@empresa.com', 'Professor', 'Analista'],
    ['funcionario2@escola.com', 'funcionario2@empresa.com', 'Professor', 'Analista'],
    ['funcionario3@escola.com', 'funcionario3@empresa.com', 'Secretaria', 'Administrativo'],
];
$existeEmail = $pdo->prepare("SELECT COUNT(*) FROM funcionarios WHERE email = ?");
$trocaEmail = $pdo->prepare("UPDATE funcionarios SET email = ?, cargo = IF(cargo = ?, ?, cargo) WHERE email = ?");
foreach ($renomear as [$antigo, $novo, $cargoAntigo, $cargoNovo]) {
    $existeEmail->execute([$antigo]);
    $temAntigo = (int)$existeEmail->fetchColumn() > 0;
    $existeEmail->execute([$novo]);
    $temNovo = (int)$existeEmail->fetchColumn() > 0;
    if ($temAntigo && !$temNovo) {
        $trocaEmail->execute([$novo, $cargoAntigo, $cargoNovo, $antigo]);
        echo "Usuário de teste renomeado: $antigo -> $novo\n";
    }
}
$idExemplo = $pdo->prepare("SELECT id FROM formularios WHERE titulo = 'Pesquisa de Clima Organizacional 2026'");
$idExemplo->execute();
$idExemplo = $idExemplo->fetchColumn();
if ($idExemplo) {
    $textos = [
        'Você recomendaria a instituição como um bom lugar para trabalhar?' => 'Você recomendaria a empresa como um bom lugar para trabalhar?',
        'Você pretende continuar trabalhando na instituição no próximo ano?' => 'Você pretende continuar trabalhando na empresa no próximo ano?',
    ];
    $trocaTexto = $pdo->prepare("UPDATE perguntas SET texto = ? WHERE formulario_id = ? AND texto = ?");
    foreach ($textos as $antigo => $novo) {
        $trocaTexto->execute([$novo, $idExemplo, $antigo]);
    }
    $pdo->prepare("UPDATE perguntas SET opcoes = REPLACE(opcoes, 'Mural da escola', 'Intranet') WHERE formulario_id = ? AND opcoes LIKE '%Mural da escola%'")
        ->execute([$idExemplo]);
}

// 5c) o gestor e os funcionários 1 e 2 de teste passaram a usar contas Gmail de verdade
//     (para testar os e-mails). O cadastro antigo vira o novo, mantendo o histórico.
//     Se os dois já existirem, o antigo era uma cópia criada por um seed anterior:
//     é apagado se nunca respondeu nada, senão fica desativado.
$contasReais = [
    ['gestor@empresa.com', 'gestorclimatize@gmail.com', 'Gestor Teste', 'Gestor Climatize'],
    ['funcionario1@empresa.com', 'funcionario01.empresa@gmail.com', 'Funcionário 1', 'Matheus Cunha'],
    ['funcionario2@empresa.com', 'funcionario02.empresa@gmail.com', 'Funcionário 2', 'Felipe Alves'],
];
$trocaConta = $pdo->prepare("UPDATE funcionarios SET email = ?, nome = IF(nome = ?, ?, nome) WHERE email = ?");
$participou = $pdo->prepare("SELECT COUNT(*) FROM controle_acesso ca JOIN funcionarios f ON f.id = ca.funcionario_id WHERE f.email = ?");
foreach ($contasReais as [$antigo, $novo, $nomeAntigo, $nomeNovo]) {
    $existeEmail->execute([$antigo]);
    $temAntigo = (int)$existeEmail->fetchColumn() > 0;
    $existeEmail->execute([$novo]);
    $temNovo = (int)$existeEmail->fetchColumn() > 0;
    if ($temAntigo && !$temNovo) {
        $trocaConta->execute([$novo, $nomeAntigo, $nomeNovo, $antigo]);
        echo "Usuário de teste agora usa o Gmail: $antigo -> $novo\n";
    } elseif ($temAntigo && $temNovo) {
        $participou->execute([$antigo]);
        if ((int)$participou->fetchColumn() === 0) {
            $pdo->prepare("DELETE FROM funcionarios WHERE email = ?")->execute([$antigo]);
            echo "Cadastro repetido removido: $antigo (a conta certa é $novo)\n";
        } else {
            $pdo->prepare("UPDATE funcionarios SET ativo = 0 WHERE email = ?")->execute([$antigo]);
            echo "Cadastro repetido desativado: $antigo (a conta certa é $novo)\n";
        }
    }
}

// 6) usuários de teste (todos com a senha 123456, sem troca obrigatória no primeiro acesso)
$senhaPadrao = '123456';
$usuarios = [
    ['Gestor Climatize', 'gestorclimatize@gmail.com', 'gestor', 'Gerência'],
    ['Matheus Cunha', 'funcionario01.empresa@gmail.com', 'funcionario', 'Analista'],
    ['Felipe Alves', 'funcionario02.empresa@gmail.com', 'funcionario', 'Analista'],
    ['Funcionário 3', 'funcionario3@empresa.com', 'funcionario', 'Administrativo'],
];
$stmt = $pdo->prepare("INSERT INTO funcionarios (nome, email, senha_hash, cargo, tipo_perfil, trocar_senha) VALUES (?, ?, ?, ?, ?, 0) ON DUPLICATE KEY UPDATE senha_hash = VALUES(senha_hash), tipo_perfil = VALUES(tipo_perfil), ativo = 1, trocar_senha = 0");
foreach ($usuarios as [$nome, $email, $perfil, $cargo]) {
    $stmt->execute([$nome, $email, password_hash($senhaPadrao, PASSWORD_DEFAULT), $cargo, $perfil]);
    echo "Usuário pronto: $email / $senhaPadrao ($perfil)\n";
}

// 7) pesquisa de exemplo com as 10 perguntas originais (com categorias)
//    e mais uma de sim/não e uma de múltipla escolha
$titulo = 'Pesquisa de Clima Organizacional 2026';
$perguntasExemplo = [
    ['Como você avalia o ambiente de trabalho?', 'Ambiente'],
    ['Você se sente valorizado pela gestão?', 'Liderança'],
    ['Como avalia a comunicação entre as equipes?', 'Comunicação'],
    ['Você tem os recursos necessários para realizar seu trabalho?', 'Recursos e infraestrutura'],
    ['Como avalia as oportunidades de crescimento profissional?', 'Desenvolvimento'],
    ['Você recomendaria a empresa como um bom lugar para trabalhar?', 'Engajamento'],
    ['Como avalia o equilíbrio entre vida pessoal e trabalho?', 'Qualidade de vida'],
    ['Você recebe feedback construtivo sobre seu desempenho?', 'Liderança'],
    ['Como avalia a infraestrutura física do local de trabalho?', 'Recursos e infraestrutura'],
    ['Você se sente parte de uma equipe unida?', 'Engajamento'],
];

$stmt = $pdo->prepare("SELECT id FROM formularios WHERE titulo = ?");
$stmt->execute([$titulo]);
$existente = $stmt->fetchColumn();

if ($existente) {
    // bancos antigos: completa as categorias das perguntas de exemplo, se ainda estiverem vazias
    $stmtCat = $pdo->prepare("UPDATE perguntas SET categoria = ? WHERE formulario_id = ? AND texto = ? AND (categoria IS NULL OR categoria = '')");
    foreach ($perguntasExemplo as [$texto, $categoria]) {
        $stmtCat->execute([$categoria, $existente, $texto]);
    }
    echo "A pesquisa de exemplo já existe, nada a fazer.\n";
} else {
    $perguntas = [];
    foreach ($perguntasExemplo as [$texto, $categoria]) {
        $perguntas[] = ['texto' => $texto, 'tipo' => 'nota', 'categoria' => $categoria];
    }
    $perguntas[] = ['texto' => 'Você pretende continuar trabalhando na empresa no próximo ano?', 'tipo' => 'sim_nao', 'categoria' => 'Engajamento'];
    $perguntas[] = ['texto' => 'Qual canal de comunicação interna você prefere?', 'tipo' => 'multipla', 'categoria' => 'Comunicação',
        'opcoes' => ['E-mail', 'WhatsApp', 'Reuniões presenciais', 'Intranet']];
    [$perguntas, $erro] = validarPerguntas($perguntas);

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
    salvarPerguntas($pdo, $formularioId, $perguntas);
    $pdo->commit();

    echo "Pesquisa de exemplo criada e ativa até " . $fechamento->format('d/m/Y H:i') . " (" . DIAS_PESQUISA_ABERTA . " dias).\n";
}

echo "\nPronto! Abra http://localhost:8080";
if (emailAtivo()) {
    echo "  (e-mails de teste em http://localhost:8025)";
}
echo "\n";