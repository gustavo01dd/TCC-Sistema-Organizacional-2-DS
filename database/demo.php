<?php
// ============================================================
// Dados de demonstração para a apresentação do TCC.
//
// Cria 24 funcionários fictícios e duas pesquisas com respostas e comentários:
//   - uma encerrada há uns 5 meses (com relatório consolidado)
//   - uma aberta agora (começou há 5 dias e fecha daqui a 7)
// Assim o painel aparece completo: médias, categorias, pontos fortes e críticos,
// evolução por dia, comentários e a comparação entre os dois ciclos.
//
// As contas fictícias usam o domínio @demo.climatize e NUNCA recebem e-mail.
// Os usuários de teste (Matheus, Felipe e Funcionário 3) ficam sem responder a
// pesquisa aberta, para responderem ao vivo na apresentação.
//
// Uso:
//   docker compose exec php php /var/www/database/demo.php            cria (ou recria do zero)
//   docker compose exec php php /var/www/database/demo.php --limpar   apaga os dados de demonstração
//
// Use só no banco da apresentação: a pesquisa que estiver aberta é encerrada.
// ============================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../public/config.php';
require_once __DIR__ . '/../public/funcoes.php';

$limpar = in_array('--limpar', $argv, true);

// ---------- conexão (o banco pode levar alguns segundos para subir) ----------
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
if (!$pdo->query("SHOW COLUMNS FROM funcionarios LIKE 'trocar_senha'")->fetch()) {
    echo "ERRO: o banco está desatualizado. Rode antes o seed:\n  docker compose exec php php /var/www/database/seed.php\n";
    exit(1);
}

// tabela que guarda quais pesquisas foram criadas por este script (para poder apagar depois)
$pdo->exec("CREATE TABLE IF NOT EXISTS demonstracao (
    formulario_id INT UNSIGNED PRIMARY KEY,
    criado_em DATETIME NOT NULL,
    CONSTRAINT fk_demonstracao_formulario FOREIGN KEY (formulario_id) REFERENCES formularios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

function limparDemonstracao($pdo) {
    $ids = $pdo->query("SELECT formulario_id FROM demonstracao")->fetchAll(PDO::FETCH_COLUMN);
    if ($ids) {
        // apagar a pesquisa apaga junto perguntas, respostas, carimbos, relatório e avisos (ON DELETE CASCADE)
        $pdo->exec("DELETE FROM formularios WHERE id IN (" . implode(',', array_map('intval', $ids)) . ")");
    }
    $stmt = $pdo->prepare("DELETE FROM funcionarios WHERE email LIKE ?");
    $stmt->execute(['%@' . DOMINIO_DEMONSTRACAO]);
    return [count($ids), $stmt->rowCount()];
}

if ($limpar) {
    [$pesquisas, $pessoas] = limparDemonstracao($pdo);
    $pdo->exec("DROP TABLE IF EXISTS demonstracao");
    echo "Dados de demonstração apagados: $pesquisas pesquisa(s) e $pessoas funcionário(s) fictício(s).\n";
    echo "Os usuários de teste e as outras pesquisas continuam no banco.\n";
    exit(0);
}

echo "== Preparando os dados de demonstração ==\n";
[$pesquisasAntigas, $pessoasAntigas] = limparDemonstracao($pdo);
if ($pesquisasAntigas || $pessoasAntigas) {
    echo "Dados de demonstração anteriores apagados (recriando do zero).\n";
}

// sorteios sempre iguais: a demonstração sai idêntica toda vez
mt_srand(2026);

function sortearNormal($media, $desvio) {
    $u1 = max(mt_rand() / mt_getrandmax(), 1e-9);
    $u2 = mt_rand() / mt_getrandmax();
    return $media + $desvio * sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2);
}

function sortearPeso(array $pesos) {
    $alvo = mt_rand() / mt_getrandmax() * array_sum($pesos);
    foreach ($pesos as $indice => $peso) {
        $alvo -= $peso;
        if ($alvo <= 0) {
            return $indice;
        }
    }
    return array_key_last($pesos);
}

function emailDemonstracao($nome) {
    $semAcento = strtr($nome, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c',
                                 'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ç' => 'c']);
    return preg_replace('/[^a-z]+/', '.', strtolower($semAcento)) . '@' . DOMINIO_DEMONSTRACAO;
}

function mesAno($data) {
    $meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
    return $meses[(int)date('n', strtotime($data)) - 1] . '/' . date('Y', strtotime($data));
}

// ---------- equipe fictícia ----------
$equipe = [
    ['Ana Paula Ribeiro', 'Analista de RH', '2021-03-15'], ['Bruno Carvalho', 'Desenvolvedor', '2022-07-04'],
    ['Camila Ferreira', 'Analista Financeira', '2019-11-11'], ['Diego Martins', 'Suporte Técnico', '2023-02-01'],
    ['Eduarda Lima', 'Designer', '2022-09-19'], ['Fernando Rocha', 'Vendedor', '2020-05-25'],
    ['Gabriela Souza', 'Atendimento', '2023-06-12'], ['Henrique Alves', 'Logística', '2018-08-06'],
    ['Isabela Gomes', 'Marketing', '2021-10-18'], ['João Pedro Santos', 'Desenvolvedor', '2024-01-08'],
    ['Juliana Costa', 'Analista de Dados', '2022-04-11'], ['Lucas Oliveira', 'Vendedor', '2023-09-04'],
    ['Mariana Araújo', 'Administrativo', '2017-02-20'], ['Nicolas Pereira', 'Suporte Técnico', '2024-03-18'],
    ['Patrícia Mendes', 'Coordenadora de Vendas', '2016-06-13'], ['Rafael Barbosa', 'Logística', '2021-01-25'],
    ['Renata Dias', 'Atendimento', '2022-11-07'], ['Rodrigo Teixeira', 'Financeiro', '2020-09-14'],
    ['Sabrina Nunes', 'Marketing', '2023-04-24'], ['Thiago Moreira', 'Desenvolvedor', '2019-07-29'],
    ['Vanessa Castro', 'Administrativo', '2021-12-06'], ['Vinícius Freitas', 'Comercial', '2022-02-14'],
    ['Larissa Monteiro', 'Analista de RH', '2024-05-06'], ['Gustavo Pinto', 'Infraestrutura', '2018-10-01'],
];

// ---------- perguntas (as mesmas da pesquisa de exemplo do seed) ----------
$perguntasBase = [
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
$perguntas = [];
foreach ($perguntasBase as [$texto, $categoria]) {
    $perguntas[] = ['texto' => $texto, 'tipo' => 'nota', 'categoria' => $categoria];
}
$perguntas[] = ['texto' => 'Você pretende continuar trabalhando na empresa no próximo ano?', 'tipo' => 'sim_nao', 'categoria' => 'Engajamento'];
$perguntas[] = ['texto' => 'Qual canal de comunicação interna você prefere?', 'tipo' => 'multipla', 'categoria' => 'Comunicação',
    'opcoes' => ['E-mail', 'WhatsApp', 'Reuniões presenciais', 'Intranet']];
[$perguntas, $erro] = validarPerguntas($perguntas);
if ($erro) {
    echo "ERRO nas perguntas: $erro\n";
    exit(1);
}

// ---------- como cada ciclo "se sente" (médias de 0 a 10 por categoria) ----------
// do 1º para o 2º ciclo: comunicação e liderança melhoram; recursos e qualidade de vida pioram
$ciclos = [
    [
        'abertura' => date('Y-m-d 09:00:00', strtotime('-150 days')),
        'fechamento' => date('Y-m-d 18:00:00', strtotime('-136 days')),
        'status' => 'encerrado',
        'participantes' => 19,
        'medias' => ['Ambiente' => 7.2, 'Liderança' => 5.7, 'Comunicação' => 4.9, 'Recursos e infraestrutura' => 6.4,
                     'Desenvolvimento' => 5.2, 'Engajamento' => 7.3, 'Qualidade de vida' => 6.8],
        'chance_sim' => 0.70,
        'canais' => [0.27, 0.41, 0.24, 0.08],
        'comentarios' => [
            'A comunicação entre as áreas precisa melhorar. Muitas vezes ficamos sabendo das mudanças pelos corredores.',
            'Gosto muito da minha equipe, o ambiente é leve e todo mundo se ajuda.',
            'Sinto falta de feedback mais frequente sobre o meu trabalho.',
            'As reuniões poderiam ser mais objetivas e com pauta definida.',
            'Seria bom ter um plano de carreira mais claro. Hoje não sei como posso crescer aqui.',
            'O horário flexível faz muita diferença na minha qualidade de vida.',
            'A liderança é acessível, mas as decisões nem sempre são explicadas para a equipe.',
            'Poderia haver mais treinamentos e cursos para a equipe.',
            'Me sinto valorizado pelos colegas, mas pouco reconhecido pela gestão.',
            'Os avisos importantes chegam por canais diferentes e às vezes se perdem.',
        ],
    ],
    [
        'abertura' => date('Y-m-d 09:00:00', strtotime('-5 days')),
        'fechamento' => date('Y-m-d 18:00:00', strtotime('+7 days')),
        'status' => 'ativo',
        'participantes' => 22,
        'medias' => ['Ambiente' => 7.9, 'Liderança' => 7.3, 'Comunicação' => 7.1, 'Recursos e infraestrutura' => 5.4,
                     'Desenvolvimento' => 6.5, 'Engajamento' => 8.2, 'Qualidade de vida' => 6.0],
        'chance_sim' => 0.82,
        'canais' => [0.20, 0.36, 0.22, 0.22],
        'comentarios' => [
            'A comunicação melhorou bastante depois das reuniões mensais com a gestão. Parabéns!',
            'O novo mural na intranet ajudou muito a saber o que está acontecendo na empresa.',
            'A carga de trabalho aumentou nos últimos meses e está difícil equilibrar com a vida pessoal.',
            'O ar-condicionado da sala principal continua com problema.',
            'Percebi mais abertura da liderança para ouvir sugestões.',
            'Faltam equipamentos: em alguns dias dividimos o mesmo notebook entre duas pessoas.',
            'Gostei do programa de treinamentos, espero que continue.',
            'Ambiente de trabalho muito bom, me sinto parte do time.',
            'As metas poderiam ser definidas junto com a equipe.',
            'O feedback ficou mais frequente e isso motiva bastante.',
            'Um dia de home office por semana ajudaria muito na qualidade de vida.',
        ],
    ],
];

$pdo->beginTransaction();
try {
    // 1) a pesquisa que estiver aberta é encerrada, sem disparar o e-mail de encerramento
    $abertas = $pdo->query("SELECT id, titulo, data_abertura FROM formularios WHERE status = 'ativo'")->fetchAll();
    foreach ($abertas as $f) {
        reservarNotificacao($pdo, (int)$f['id'], 'encerramento', 'encerramento-' . chaveCiclo($f));
        encerrarFormulario($pdo, (int)$f['id']);
        echo "Pesquisa \"{$f['titulo']}\" encerrada para dar lugar à demonstração.\n";
    }

    // 2) funcionários fictícios (ninguém entra com eles: a senha é aleatória)
    $stmt = $pdo->prepare("INSERT INTO funcionarios (nome, email, senha_hash, cargo, data_admissao, tipo_perfil, trocar_senha, termo_versao, termo_aceito_em)
                           VALUES (?, ?, ?, ?, ?, 'funcionario', 0, ?, ?)");
    $idsEquipe = [];
    foreach ($equipe as [$nome, $cargo, $admissao]) {
        $stmt->execute([$nome, emailDemonstracao($nome), password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT, ['cost' => 4]), $cargo, $admissao,
                        VERSAO_TERMO, date('Y-m-d H:i:s', strtotime($ciclos[0]['abertura']) + mt_rand(1, 3) * 3600)]);
        $idsEquipe[] = (int)$pdo->lastInsertId();
    }

    // 3) as duas pesquisas, com respostas anônimas no mesmo formato do sistema
    $stmtForm = $pdo->prepare("INSERT INTO formularios (titulo, status, data_abertura, data_fechamento) VALUES (?, ?, ?, ?)");
    $stmtDemo = $pdo->prepare("INSERT INTO demonstracao (formulario_id, criado_em) VALUES (?, NOW())");
    $stmtCarimbo = $pdo->prepare("INSERT INTO controle_acesso (funcionario_id, formulario_id, respondeu, data_resposta) VALUES (?, ?, 1, ?)");
    $stmtResposta = $pdo->prepare("INSERT INTO respostas (formulario_id, comentario, criada_em) VALUES (?, ?, ?)");
    $stmtItem = $pdo->prepare("INSERT INTO resposta_itens (resposta_id, pergunta_id, nota, opcao) VALUES (?, ?, ?, ?)");
    $resumo = [];

    foreach ($ciclos as $ciclo) {
        $titulo = 'Pesquisa de Clima · ' . mesAno($ciclo['abertura']);
        $stmtForm->execute([$titulo, $ciclo['status'], $ciclo['abertura'], $ciclo['fechamento']]);
        $formularioId = (int)$pdo->lastInsertId();
        $stmtDemo->execute([$formularioId]);
        salvarPerguntas($pdo, $formularioId, $perguntas);
        $doBanco = buscarPerguntas($pdo, $formularioId);

        // sem e-mails de abertura e encerramento para estas pesquisas
        $formulario = ['id' => $formularioId, 'data_abertura' => $ciclo['abertura']];
        reservarNotificacao($pdo, $formularioId, 'abertura', 'abertura-' . chaveCiclo($formulario));
        if ($ciclo['status'] === 'encerrado') {
            reservarNotificacao($pdo, $formularioId, 'encerramento', 'encerramento-' . chaveCiclo($formulario));
        }

        // quem responde e quando (mais respostas nos primeiros dias, como na vida real)
        $inicio = strtotime($ciclo['abertura']);
        $fim = min(strtotime($ciclo['fechamento']), time() - 1800);
        $participantes = $idsEquipe;
        shuffle($participantes);
        $participantes = array_slice($participantes, 0, $ciclo['participantes']);
        $comentarios = $ciclo['comentarios'];
        shuffle($comentarios);

        foreach ($participantes as $funcionarioId) {
            $dia = $inicio + (int)(pow(mt_rand() / mt_getrandmax(), 1.7) * max(1, $fim - $inicio));
            $quando = strtotime(date('Y-m-d', $dia) . ' ' . sprintf('%02d:%02d:00', mt_rand(8, 18), mt_rand(0, 59)));
            $quando = min(max($quando, $inicio), $fim);

            $stmtCarimbo->execute([$funcionarioId, $formularioId, date('Y-m-d', $quando)]);
            $comentario = (mt_rand(1, 100) <= 48 && $comentarios) ? array_pop($comentarios) : null;
            $stmtResposta->execute([$formularioId, $comentario !== null ? criptografar($comentario) : null, date('Y-m-d H:i:s', $quando)]);
            $respostaId = (int)$pdo->lastInsertId();

            $humor = sortearNormal(0, 0.9);   // umas pessoas mais satisfeitas que outras
            foreach ($doBanco as $p) {
                if ($p['tipo'] === 'nota') {
                    $nota = (int)round(sortearNormal($ciclo['medias'][$p['categoria']] + $humor, 1.3));
                    $stmtItem->execute([$respostaId, $p['id'], max(0, min(10, $nota)), null]);
                } elseif ($p['tipo'] === 'sim_nao') {
                    $sim = (mt_rand() / mt_getrandmax()) < $ciclo['chance_sim'] + $humor * 0.08;
                    $stmtItem->execute([$respostaId, $p['id'], null, $sim ? 1 : 0]);   // 0 = Não, 1 = Sim
                } else {
                    $stmtItem->execute([$respostaId, $p['id'], null, sortearPeso($ciclo['canais'])]);
                }
            }
        }

        if ($ciclo['status'] === 'encerrado') {
            gerarRelatorioConsolidado($pdo, $formularioId);
        }
        $resumo[] = [$titulo, $ciclo, count($participantes), $formularioId];
    }

    // 4) os usuários de teste ficam livres para responder a pesquisa aberta ao vivo
    //    (se responderam outra pesquisa nos últimos 21 dias, a regra RN03 os bloquearia)
    $idAberta = $resumo[1][3];
    $liberados = [];
    $pessoas = $pdo->prepare("SELECT id, nome, email FROM funcionarios WHERE ativo = 1 AND tipo_perfil = 'funcionario' AND email NOT LIKE ? ORDER BY nome");
    $pessoas->execute(['%@' . DOMINIO_DEMONSTRACAO]);
    $apagaRecentes = $pdo->prepare("DELETE FROM controle_acesso WHERE funcionario_id = ? AND data_resposta > DATE_SUB(CURDATE(), INTERVAL " . DIAS_INTERVALO_PARTICIPACAO . " DAY)");
    foreach ($pessoas->fetchAll() as $p) {
        if (verificarBloqueioParticipacao($pdo, (int)$p['id'], $idAberta) !== null) {
            $apagaRecentes->execute([$p['id']]);
        }
        if (verificarBloqueioParticipacao($pdo, (int)$p['id'], $idAberta) === null) {
            $liberados[] = $p['nome'];
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "ERRO ao criar os dados de demonstração: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n== Dados de demonstração prontos ==\n";
foreach ($resumo as [$titulo, $ciclo, $quantos]) {
    if ($ciclo['status'] === 'encerrado') {
        echo "Pesquisa encerrada: $titulo ($quantos respostas, relatório consolidado gerado)\n";
    } else {
        echo "Pesquisa aberta:    $titulo ($quantos respostas, aberta até " . formatarDataHoraBr($ciclo['fechamento']) . ")\n";
    }
}
echo count($equipe) . " funcionários fictícios (@" . DOMINIO_DEMONSTRACAO . ": nunca recebem e-mail)\n";
if ($liberados) {
    echo "Podem responder ao vivo: " . implode(', ', $liberados) . "\n";
}
echo "\nEntre no painel como gestor (gestorclimatize@gmail.com) e mostre o Dashboard, Resultados, Comparar e Comentários.\n";
echo "Para apagar depois: docker compose exec php php /var/www/database/demo.php --limpar\n";