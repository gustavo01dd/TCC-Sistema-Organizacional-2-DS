<?php
// ============================================================
// API da Pesquisa de Clima Organizacional
// Todas as rotas passam por aqui: api.php?action=<nome>
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funcoes.php';
require_once __DIR__ . '/excel.php';

iniciarSessao();

$action = $_GET['action'] ?? '';
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';


// ---------------- utilidades da API ----------------

function responderJson($dados, $codigo = 200) {
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

function corpoJson() {
    $dados = json_decode(file_get_contents('php://input'), true);
    return is_array($dados) ? $dados : [];
}

function exigirMetodo($esperado) {
    global $metodo;
    if ($metodo !== $esperado) {
        responderJson(['erro' => 'Método inválido'], 405);
    }
}

// RN06 / RNF04: só gestores autenticados
function exigirGestor($pdo) {
    $usuario = usuarioLogado($pdo);
    if (!$usuario) {
        responderJson(['erro' => 'Faça login para continuar'], 401);
    }
    if ($usuario['tipo_perfil'] !== 'gestor') {
        responderJson(['erro' => 'Acesso restrito aos gestores'], 403);
    }
    return $usuario;
}

function exigirFuncionario($pdo) {
    $usuario = usuarioLogado($pdo);
    if (!$usuario) {
        responderJson(['erro' => 'Faça login para acessar a pesquisa'], 401);
    }
    if ($usuario['tipo_perfil'] !== 'funcionario') {
        responderJson(['erro' => 'A pesquisa é respondida pelos funcionários. Gestores acompanham os resultados pelo painel.'], 403);
    }
    return $usuario;
}

function validarDadosFuncionario($dados, $senhaObrigatoria) {
    $nome = trim((string)($dados['nome'] ?? ''));
    $email = trim((string)($dados['email'] ?? ''));
    $senha = (string)($dados['senha'] ?? '');
    $cargo = trim((string)($dados['cargo'] ?? ''));
    $dataAdmissao = trim((string)($dados['data_admissao'] ?? ''));
    $perfil = (string)($dados['tipo_perfil'] ?? 'funcionario');

    if ($nome === '' || $email === '') {
        responderJson(['erro' => 'Informe o nome e o email'], 400);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        responderJson(['erro' => 'Email inválido'], 400);
    }
    if (tamanhoTexto($nome) > 100 || tamanhoTexto($email) > 100 || tamanhoTexto($cargo) > 50) {
        responderJson(['erro' => 'Texto muito longo (nome e email até 100 caracteres, cargo até 50)'], 400);
    }
    if (($senhaObrigatoria || $senha !== '') && strlen($senha) < 6) {
        responderJson(['erro' => 'A senha precisa ter pelo menos 6 caracteres'], 400);
    }
    if (!in_array($perfil, ['funcionario', 'gestor'], true)) {
        responderJson(['erro' => 'Perfil inválido'], 400);
    }
    if (validarData($dataAdmissao) === false) {
        responderJson(['erro' => 'Data de admissão inválida'], 400);
    }

    return [
        'nome' => $nome,
        'email' => $email,
        'senha' => $senha,
        'cargo' => $cargo !== '' ? $cargo : null,
        'data_admissao' => $dataAdmissao !== '' ? $dataAdmissao : null,
        'perfil' => $perfil,
    ];
}


// ---------------- conexão ----------------

try {
    $pdo = conectarBanco();
} catch (PDOException $e) {
    responderJson(['erro' => 'Falha na conexão com o banco de dados'], 500);
}

try {
    processarEncerramentos($pdo);
} catch (PDOException $e) {
    error_log('Erro ao processar encerramentos: ' . $e->getMessage());
}


// ---------------- rotas ----------------

try {
    switch ($action) {

        // =======================================================
        // PÚBLICO
        // =======================================================

        // RF08: aviso de abertura/encerramento mostrado na tela inicial
        case 'status_pesquisa':
            $aberta = buscarFormularioAberto($pdo);
            if ($aberta) {
                responderJson(['situacao' => 'aberta', 'titulo' => $aberta['titulo'], 'data_fechamento' => $aberta['data_fechamento']]);
            }
            $stmt = $pdo->query("SELECT titulo, data_fechamento FROM formularios WHERE status = 'encerrado' AND data_fechamento >= DATE_SUB(NOW(), INTERVAL 7 DAY) ORDER BY data_fechamento DESC LIMIT 1");
            $encerrada = $stmt->fetch();
            if ($encerrada) {
                responderJson(['situacao' => 'encerrada', 'titulo' => $encerrada['titulo'], 'data_fechamento' => $encerrada['data_fechamento']]);
            }
            responderJson(['situacao' => 'nenhuma']);
            break;

        // Login único: o perfil decide se vai para a pesquisa ou para o painel
        case 'login':
            exigirMetodo('POST');
            $dados = corpoJson();
            $email = trim((string)($dados['email'] ?? ''));
            $senha = (string)($dados['senha'] ?? '');

            $stmt = $pdo->prepare("SELECT id, nome, email, senha_hash, tipo_perfil, ativo FROM funcionarios WHERE email = ?");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();

            if ($usuario && $usuario['ativo'] && password_verify($senha, $usuario['senha_hash'])) {
                session_regenerate_id(true);
                $_SESSION = ['usuario_id' => (int)$usuario['id']];
                if ($usuario['tipo_perfil'] === 'gestor') {
                    registrarLog($pdo, (int)$usuario['id'], $usuario['email'], 'login');
                }
                responderJson(['sucesso' => true, 'nome' => $usuario['nome'], 'perfil' => $usuario['tipo_perfil']]);
            }

            if ($usuario && $usuario['tipo_perfil'] === 'gestor') {
                registrarLog($pdo, (int)$usuario['id'], $usuario['email'], 'falha_login');
            }
            responderJson(['erro' => 'Email ou senha incorretos'], 401);
            break;

        case 'logout':
            $usuario = usuarioLogado($pdo);
            if ($usuario && $usuario['tipo_perfil'] === 'gestor') {
                registrarLog($pdo, (int)$usuario['id'], $usuario['email'], 'logout');
            }
            $_SESSION = [];
            session_destroy();
            responderJson(['sucesso' => true]);
            break;

        // =======================================================
        // FUNCIONÁRIO: responder a pesquisa
        // =======================================================

        case 'formulario_ativo':
            $usuario = exigirFuncionario($pdo);

            $formulario = buscarFormularioAberto($pdo);
            if (!$formulario) {
                responderJson(['erro' => 'Nenhuma pesquisa aberta no momento. Fique de olho nos avisos da tela inicial.'], 404);
            }

            $bloqueio = verificarBloqueioParticipacao($pdo, (int)$usuario['id'], (int)$formulario['id']);
            if ($bloqueio) {
                responderJson(['erro' => $bloqueio], 403);
            }

            $stmt = $pdo->prepare("SELECT id, texto FROM perguntas WHERE formulario_id = ? ORDER BY ordem, id");
            $stmt->execute([$formulario['id']]);
            $formulario['perguntas'] = $stmt->fetchAll();

            responderJson($formulario);
            break;

        case 'enviar_resposta':
            exigirMetodo('POST');
            $usuario = exigirFuncionario($pdo);

            $dados = corpoJson();
            $formularioId = (int)($dados['formulario_id'] ?? 0);
            $itens = $dados['respostas'] ?? [];
            $comentario = trim((string)($dados['comentario'] ?? ''));

            if (strlen($comentario) > 8000) {
                responderJson(['erro' => 'Comentário muito longo'], 400);
            }

            $formulario = buscarFormularioAberto($pdo);
            if (!$formulario || (int)$formulario['id'] !== $formularioId) {
                responderJson(['erro' => 'Esta pesquisa não está mais aberta.'], 409);
            }

            $bloqueio = verificarBloqueioParticipacao($pdo, (int)$usuario['id'], $formularioId);
            if ($bloqueio) {
                responderJson(['erro' => $bloqueio], 403);
            }

            // todas as perguntas do formulário, cada uma com uma nota de 0 a 10
            $stmt = $pdo->prepare("SELECT id FROM perguntas WHERE formulario_id = ?");
            $stmt->execute([$formularioId]);
            $idsValidos = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

            if (!is_array($itens)) {
                responderJson(['erro' => 'Resposta inválida'], 400);
            }
            $notasPorPergunta = [];
            foreach ($itens as $item) {
                $perguntaId = (int)($item['pergunta_id'] ?? 0);
                $nota = $item['nota'] ?? null;
                if (!in_array($perguntaId, $idsValidos, true) || !is_numeric($nota) || (int)$nota < 0 || (int)$nota > 10) {
                    responderJson(['erro' => 'Resposta inválida'], 400);
                }
                $notasPorPergunta[$perguntaId] = (int)$nota;
            }
            if (count($notasPorPergunta) !== count($idsValidos)) {
                responderJson(['erro' => 'Responda todas as perguntas antes de enviar.'], 400);
            }

            try {
                $pdo->beginTransaction();

                // 1) carimbo de participação. A chave primária (funcionário + formulário)
                //    impede resposta dupla até com dois envios ao mesmo tempo (RN02).
                $stmt = $pdo->prepare("INSERT INTO controle_acesso (funcionario_id, formulario_id, respondeu, data_resposta) VALUES (?, ?, 1, CURDATE())");
                $stmt->execute([(int)$usuario['id'], $formularioId]);

                // 2) conteúdo anônimo: nada aqui aponta para o funcionário (RN05)
                $stmt = $pdo->prepare("INSERT INTO respostas (formulario_id, comentario) VALUES (?, ?)");
                $stmt->execute([$formularioId, $comentario !== '' ? $comentario : null]);
                $respostaId = $pdo->lastInsertId();

                $stmtItem = $pdo->prepare("INSERT INTO resposta_itens (resposta_id, pergunta_id, nota) VALUES (?, ?, ?)");
                foreach ($notasPorPergunta as $perguntaId => $nota) {
                    $stmtItem->execute([$respostaId, $perguntaId, $nota]);
                }

                $pdo->commit();
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($e->getCode() === '23000') {
                    responderJson(['erro' => 'Você já respondeu esta pesquisa.'], 403);
                }
                throw $e;
            }

            responderJson(['sucesso' => true]);
            break;

        // =======================================================
        // GESTOR: cadastro de funcionários e gestores (RF01)
        // =======================================================

        case 'funcionarios':
            $gestor = exigirGestor($pdo);

            $stmt = $pdo->query("SELECT id, titulo FROM formularios WHERE status = 'ativo' ORDER BY data_abertura DESC LIMIT 1");
            $formularioAtual = $stmt->fetch() ?: null;

            // "já respondeu" = existe carimbo em controle_acesso para a pesquisa atual.
            // Só isso: o conteúdo das respostas continua sem ligação com a pessoa.
            $stmt = $pdo->prepare("
                SELECT f.id, f.nome, f.email, f.cargo, f.data_admissao, f.tipo_perfil, f.ativo,
                       (ca.funcionario_id IS NOT NULL) AS respondeu_atual
                FROM funcionarios f
                LEFT JOIN controle_acesso ca ON ca.funcionario_id = f.id AND ca.formulario_id = ?
                ORDER BY f.tipo_perfil DESC, f.nome
            ");
            $stmt->execute([$formularioAtual ? $formularioAtual['id'] : 0]);

            responderJson([
                'meu_id' => (int)$gestor['id'],
                'formulario_atual' => $formularioAtual,
                'funcionarios' => $stmt->fetchAll(),
            ]);
            break;

        case 'criar_funcionario':
            exigirGestor($pdo);
            exigirMetodo('POST');
            $d = validarDadosFuncionario(corpoJson(), true);

            $stmt = $pdo->prepare("SELECT id FROM funcionarios WHERE email = ?");
            $stmt->execute([$d['email']]);
            if ($stmt->fetch()) {
                responderJson(['erro' => 'Já existe um cadastro com este email'], 409);
            }

            $stmt = $pdo->prepare("INSERT INTO funcionarios (nome, email, senha_hash, cargo, data_admissao, tipo_perfil) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$d['nome'], $d['email'], password_hash($d['senha'], PASSWORD_DEFAULT), $d['cargo'], $d['data_admissao'], $d['perfil']]);

            responderJson(['sucesso' => true, 'funcionario_id' => (int)$pdo->lastInsertId()]);
            break;

        case 'editar_funcionario':
            $gestor = exigirGestor($pdo);
            exigirMetodo('POST');
            $dados = corpoJson();
            $funcionarioId = (int)($dados['funcionario_id'] ?? 0);
            $d = validarDadosFuncionario($dados, false);

            if ($funcionarioId === (int)$gestor['id'] && $d['perfil'] !== 'gestor') {
                responderJson(['erro' => 'Você não pode tirar o seu próprio acesso de gestor.'], 400);
            }

            $stmt = $pdo->prepare("SELECT id FROM funcionarios WHERE id = ?");
            $stmt->execute([$funcionarioId]);
            if (!$stmt->fetch()) {
                responderJson(['erro' => 'Cadastro não encontrado'], 404);
            }

            $stmt = $pdo->prepare("SELECT id FROM funcionarios WHERE email = ? AND id <> ?");
            $stmt->execute([$d['email'], $funcionarioId]);
            if ($stmt->fetch()) {
                responderJson(['erro' => 'Já existe outro cadastro com este email'], 409);
            }

            if ($d['senha'] !== '') {
                $stmt = $pdo->prepare("UPDATE funcionarios SET nome = ?, email = ?, cargo = ?, data_admissao = ?, tipo_perfil = ?, senha_hash = ? WHERE id = ?");
                $stmt->execute([$d['nome'], $d['email'], $d['cargo'], $d['data_admissao'], $d['perfil'], password_hash($d['senha'], PASSWORD_DEFAULT), $funcionarioId]);
            } else {
                $stmt = $pdo->prepare("UPDATE funcionarios SET nome = ?, email = ?, cargo = ?, data_admissao = ?, tipo_perfil = ? WHERE id = ?");
                $stmt->execute([$d['nome'], $d['email'], $d['cargo'], $d['data_admissao'], $d['perfil'], $funcionarioId]);
            }

            responderJson(['sucesso' => true]);
            break;

        case 'alterar_status_funcionario':
            $gestor = exigirGestor($pdo);
            exigirMetodo('POST');
            $dados = corpoJson();
            $funcionarioId = (int)($dados['funcionario_id'] ?? 0);
            $ativo = !empty($dados['ativo']) ? 1 : 0;

            if (!$funcionarioId) {
                responderJson(['erro' => 'Dados inválidos'], 400);
            }
            if ($funcionarioId === (int)$gestor['id']) {
                responderJson(['erro' => 'Você não pode desativar a sua própria conta.'], 400);
            }

            $stmt = $pdo->prepare("UPDATE funcionarios SET ativo = ? WHERE id = ?");
            $stmt->execute([$ativo, $funcionarioId]);
            responderJson(['sucesso' => true]);
            break;

        case 'excluir_funcionario':
            $gestor = exigirGestor($pdo);
            exigirMetodo('POST');
            $dados = corpoJson();
            $funcionarioId = (int)($dados['funcionario_id'] ?? 0);

            if (!$funcionarioId) {
                responderJson(['erro' => 'Dados inválidos'], 400);
            }
            if ($funcionarioId === (int)$gestor['id']) {
                responderJson(['erro' => 'Você não pode excluir a sua própria conta.'], 400);
            }

            // Apaga o cadastro (e o carimbo de participação). As respostas já enviadas
            // nunca tiveram ligação com a pessoa e continuam intactas (RN04 / RN05).
            $stmt = $pdo->prepare("DELETE FROM funcionarios WHERE id = ?");
            $stmt->execute([$funcionarioId]);
            responderJson(['sucesso' => true]);
            break;

        // =======================================================
        // GESTOR: formulários (RF02)
        // =======================================================

        case 'formularios':
            exigirGestor($pdo);
            $stmt = $pdo->query("
                SELECT f.id, f.titulo, f.status, f.respondentes_esperados, f.data_abertura, f.data_fechamento,
                       (SELECT COUNT(*) FROM respostas r WHERE r.formulario_id = f.id) AS total_respostas,
                       rel.data_geracao AS relatorio_gerado_em
                FROM formularios f
                LEFT JOIN relatorios rel ON rel.formulario_id = f.id
                ORDER BY f.criado_em DESC, f.id DESC
            ");
            responderJson($stmt->fetchAll());
            break;

        case 'criar_formulario':
            exigirGestor($pdo);
            exigirMetodo('POST');
            $dados = corpoJson();
            $titulo = trim((string)($dados['titulo'] ?? ''));
            $perguntasTexto = is_array($dados['perguntas'] ?? null) ? $dados['perguntas'] : [];
            $esperados = !empty($dados['respondentes_esperados']) ? (int)$dados['respondentes_esperados'] : null;
            $abertura = validarDataHora($dados['data_abertura'] ?? null);
            $fechamento = validarDataHora($dados['data_fechamento'] ?? null);

            $perguntasValidas = array_values(array_filter(array_map(fn($t) => trim((string)$t), $perguntasTexto), fn($t) => $t !== ''));

            if ($titulo === '' || count($perguntasValidas) < 1) {
                responderJson(['erro' => 'Informe um título e ao menos uma pergunta'], 400);
            }
            if (tamanhoTexto($titulo) > 150) {
                responderJson(['erro' => 'O título pode ter no máximo 150 caracteres'], 400);
            }
            foreach ($perguntasValidas as $texto) {
                if (tamanhoTexto($texto) > 255) {
                    responderJson(['erro' => 'Cada pergunta pode ter no máximo 255 caracteres'], 400);
                }
            }
            if (count($perguntasValidas) > 50) {
                responderJson(['erro' => 'Máximo de 50 perguntas por formulário'], 400);
            }
            if ($abertura === false || $fechamento === false) {
                responderJson(['erro' => 'Data inválida'], 400);
            }
            if ($abertura && $fechamento && strtotime($fechamento) <= strtotime($abertura)) {
                responderJson(['erro' => 'A data de término deve ser depois da data de início'], 400);
            }
            if ($fechamento && strtotime($fechamento) <= time()) {
                responderJson(['erro' => 'A data de término já passou'], 400);
            }
            if ($esperados !== null && $esperados < 1) {
                $esperados = null;
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO formularios (titulo, respondentes_esperados, status, data_abertura, data_fechamento) VALUES (?, ?, 'rascunho', ?, ?)");
            $stmt->execute([$titulo, $esperados, $abertura, $fechamento]);
            $formularioId = (int)$pdo->lastInsertId();

            $stmtP = $pdo->prepare("INSERT INTO perguntas (formulario_id, texto, ordem) VALUES (?, ?, ?)");
            foreach ($perguntasValidas as $i => $texto) {
                $stmtP->execute([$formularioId, $texto, $i + 1]);
            }
            $pdo->commit();

            responderJson(['sucesso' => true, 'formulario_id' => $formularioId]);
            break;

        case 'alterar_status_formulario':
            exigirGestor($pdo);
            exigirMetodo('POST');
            $dados = corpoJson();
            $formularioId = (int)($dados['formulario_id'] ?? 0);
            $novoStatus = (string)($dados['status'] ?? '');

            if (!$formularioId || !in_array($novoStatus, ['ativo', 'encerrado'], true)) {
                responderJson(['erro' => 'Dados inválidos'], 400);
            }

            $stmt = $pdo->prepare("SELECT id, data_abertura, data_fechamento FROM formularios WHERE id = ?");
            $stmt->execute([$formularioId]);
            $formulario = $stmt->fetch();
            if (!$formulario) {
                responderJson(['erro' => 'Formulário não encontrado'], 404);
            }

            $pdo->beginTransaction();

            if ($novoStatus === 'ativo') {
                // só uma pesquisa ativa por vez: a atual é encerrada e ganha seu relatório (RN08)
                $stmt = $pdo->prepare("SELECT id FROM formularios WHERE status = 'ativo' AND id <> ?");
                $stmt->execute([$formularioId]);
                foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $outraId) {
                    encerrarFormulario($pdo, (int)$outraId);
                }

                // RN01: sem data de término, fica aberta por 7 dias a partir da publicação.
                // Reabrir uma pesquisa já vencida recomeça o prazo.
                $agora = new DateTime();
                $abertura = $formulario['data_abertura'] ? new DateTime($formulario['data_abertura']) : null;
                $fechamento = $formulario['data_fechamento'] ? new DateTime($formulario['data_fechamento']) : null;
                $prazoVencido = $fechamento !== null && $fechamento <= $agora;

                if ($abertura === null || $prazoVencido) {
                    $abertura = clone $agora;
                }
                if ($fechamento === null || $prazoVencido) {
                    $fechamento = (clone $abertura)->modify('+' . DIAS_PESQUISA_ABERTA . ' days');
                    if ($fechamento <= $agora) {
                        $abertura = clone $agora;
                        $fechamento = (clone $agora)->modify('+' . DIAS_PESQUISA_ABERTA . ' days');
                    }
                }

                $stmt = $pdo->prepare("UPDATE formularios SET status = 'ativo', data_abertura = ?, data_fechamento = ? WHERE id = ?");
                $stmt->execute([$abertura->format('Y-m-d H:i:s'), $fechamento->format('Y-m-d H:i:s'), $formularioId]);

                // reaberta: o relatório antigo sai; um novo será gerado no próximo encerramento
                $stmt = $pdo->prepare("DELETE FROM relatorios WHERE formulario_id = ?");
                $stmt->execute([$formularioId]);
            } else {
                encerrarFormulario($pdo, $formularioId);
            }

            $pdo->commit();
            responderJson(['sucesso' => true]);
            break;

        // =======================================================
        // GESTOR: indicadores (RF05, RF07)
        // =======================================================

        case 'dashboard':
            exigirGestor($pdo);
            $formularioId = resolverFormularioId($pdo, $_GET['formulario_id'] ?? null);
            if (!$formularioId) {
                responderJson(['erro' => 'Nenhum formulário cadastrado ainda'], 404);
            }

            $stmt = $pdo->prepare("SELECT id, titulo, status, respondentes_esperados, data_abertura, data_fechamento FROM formularios WHERE id = ?");
            $stmt->execute([$formularioId]);
            $formulario = $stmt->fetch();
            if (!$formulario) {
                responderJson(['erro' => 'Formulário não encontrado'], 404);
            }

            $total = contarRespostas($pdo, $formularioId);
            $ocultos = dadosOcultos($total);

            // do pior para o melhor; perguntas sem média vão para o fim
            $porPergunta = buscarResultadosPorPergunta($pdo, $formularioId, 'completo');
            usort($porPergunta, fn($a, $b) => [$a['media'] === null, $a['media']] <=> [$b['media'] === null, $b['media']]);

            $stmt = $pdo->prepare("SELECT DATE(criada_em) AS dia, COUNT(*) AS total FROM respostas WHERE formulario_id = ? GROUP BY DATE(criada_em) ORDER BY dia");
            $stmt->execute([$formularioId]);
            $evolucao = $stmt->fetchAll();

            // participação: nº informado no formulário ou, se vazio, funcionários cadastrados
            $esperados = $formulario['respondentes_esperados'] ? (int)$formulario['respondentes_esperados'] : contarFuncionariosAtivos($pdo);
            $taxa = $esperados > 0 ? (int)round($total / $esperados * 100) : null;

            responderJson([
                'formulario' => $formulario,
                'total_respostas' => $total,
                'dados_ocultos' => $ocultos,
                'minimo_anonimato' => MINIMO_RESPOSTAS_ANONIMATO,
                'media_geral' => $ocultos ? null : calcularMediaGeral($pdo, $formularioId),
                'respondentes_esperados' => $esperados,
                'taxa_participacao' => $taxa,
                'por_pergunta' => $porPergunta,
                'distribuicao' => $ocultos ? ['insatisfeito' => 0, 'neutro' => 0, 'satisfeito' => 0] : calcularDistribuicao($pdo, $formularioId),
                'evolucao' => $evolucao,
            ]);
            break;

        case 'resultados':
            exigirGestor($pdo);
            $formularioId = resolverFormularioId($pdo, $_GET['formulario_id'] ?? null);
            if (!$formularioId) {
                responderJson(['erro' => 'Nenhum formulário cadastrado ainda'], 404);
            }
            $periodo = in_array($_GET['periodo'] ?? '', ['7', '30', 'completo'], true) ? $_GET['periodo'] : 'completo';

            $stmt = $pdo->prepare("SELECT id, titulo FROM formularios WHERE id = ?");
            $stmt->execute([$formularioId]);
            $formulario = $stmt->fetch();
            if (!$formulario) {
                responderJson(['erro' => 'Formulário não encontrado'], 404);
            }

            responderJson([
                'formulario' => $formulario,
                'periodo' => $periodo,
                'minimo_anonimato' => MINIMO_RESPOSTAS_ANONIMATO,
                'perguntas' => buscarResultadosPorPergunta($pdo, $formularioId, $periodo),
            ]);
            break;

        case 'comentarios':
            exigirGestor($pdo);
            $formularioId = resolverFormularioId($pdo, $_GET['formulario_id'] ?? null);
            if (!$formularioId) {
                responderJson(['erro' => 'Nenhum formulário cadastrado ainda'], 404);
            }

            $stmt = $pdo->prepare("SELECT id, titulo FROM formularios WHERE id = ?");
            $stmt->execute([$formularioId]);
            $formulario = $stmt->fetch();
            if (!$formulario) {
                responderJson(['erro' => 'Formulário não encontrado'], 404);
            }

            $total = contarRespostas($pdo, $formularioId);
            $ocultos = dadosOcultos($total);

            responderJson([
                'formulario' => $formulario,
                'oculto' => $ocultos,
                'minimo_anonimato' => MINIMO_RESPOSTAS_ANONIMATO,
                'total_respostas' => $total,
                'comentarios' => $ocultos ? [] : buscarComentarios($pdo, $formularioId),
            ]);
            break;

        // =======================================================
        // GESTOR: exportações (Relatórios) e logs
        // =======================================================

        // Planilha do Excel (.xlsx) já formatada, com todas as respostas anônimas
        case 'exportar_excel':
            exigirGestor($pdo);
            $formularioId = resolverFormularioId($pdo, $_GET['formulario_id'] ?? null);
            if (!$formularioId) {
                responderJson(['erro' => 'Nenhum formulário cadastrado ainda'], 404);
            }

            $total = contarRespostas($pdo, $formularioId);
            if (dadosOcultos($total)) {
                responderJson(['erro' => "Exportação indisponível: este formulário tem $total resposta(s). Para proteger o anonimato, é preciso ter pelo menos " . MINIMO_RESPOSTAS_ANONIMATO . '.'], 403);
            }

            $stmt = $pdo->prepare("SELECT titulo, data_abertura, data_fechamento FROM formularios WHERE id = ?");
            $stmt->execute([$formularioId]);
            $formulario = $stmt->fetch();
            if (!$formulario) {
                responderJson(['erro' => 'Formulário não encontrado'], 404);
            }

            $stmt = $pdo->prepare("SELECT id, texto FROM perguntas WHERE formulario_id = ? ORDER BY ordem, id");
            $stmt->execute([$formularioId]);
            $perguntas = $stmt->fetchAll();

            // sem id e sem hora, em ordem aleatória dentro do dia (RN07)
            $stmt = $pdo->prepare("SELECT id, comentario, DATE(criada_em) AS data_envio FROM respostas WHERE formulario_id = ? ORDER BY DATE(criada_em), RAND()");
            $stmt->execute([$formularioId]);
            $respostas = $stmt->fetchAll();

            $stmtItens = $pdo->prepare("SELECT pergunta_id, nota FROM resposta_itens WHERE resposta_id = ?");
            $linhas = [];
            foreach ($respostas as $resposta) {
                $stmtItens->execute([$resposta['id']]);
                $notas = [];
                foreach ($stmtItens->fetchAll() as $item) {
                    $notas[(int)$item['pergunta_id']] = (int)$item['nota'];
                }
                $linhas[] = [
                    'data' => $resposta['data_envio'],
                    'notas' => array_map(fn($p) => $notas[(int)$p['id']] ?? null, $perguntas),
                    'comentario' => (string)($resposta['comentario'] ?? ''),
                ];
            }

            $subtitulo = 'Gerado em ' . date('d/m/Y') . ' às ' . date('H:i') . '  ·  ' . count($linhas) . ' resposta(s), todas anônimas';
            if ($formulario['data_abertura']) {
                $subtitulo .= '  ·  Período: ' . date('d/m/Y', strtotime($formulario['data_abertura'])) . ' a '
                    . ($formulario['data_fechamento'] ? date('d/m/Y', strtotime($formulario['data_fechamento'])) : 'em aberto');
            }

            $arquivo = gerarPlanilhaRespostas($formulario['titulo'], $subtitulo, array_column($perguntas, 'texto'), $linhas);

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="respostas_pesquisa_' . $formularioId . '.xlsx"');
            header('Content-Length: ' . strlen($arquivo));
            echo $arquivo;
            exit;

        case 'exportar_comentarios':
            exigirGestor($pdo);
            $formularioId = resolverFormularioId($pdo, $_GET['formulario_id'] ?? null);
            if (!$formularioId) {
                responderJson(['erro' => 'Nenhum formulário cadastrado ainda'], 404);
            }

            $total = contarRespostas($pdo, $formularioId);
            if (dadosOcultos($total)) {
                responderJson(['erro' => "Exportação indisponível: este formulário tem $total resposta(s). Para proteger o anonimato, é preciso ter pelo menos " . MINIMO_RESPOSTAS_ANONIMATO . '.'], 403);
            }

            $stmt = $pdo->prepare("SELECT titulo FROM formularios WHERE id = ?");
            $stmt->execute([$formularioId]);
            $titulo = (string)$stmt->fetchColumn();
            $comentarios = buscarComentarios($pdo, $formularioId);

            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Disposition: attachment; filename="comentarios_pesquisa_' . $formularioId . '.txt"');

            echo "Comentários e sugestões — $titulo\n";
            echo 'Gerado em ' . date('d/m/Y H:i') . ' — ' . count($comentarios) . " comentário(s), todos anônimos\n\n";
            if (count($comentarios) === 0) {
                echo "Nenhum comentário registrado.\n";
            }
            foreach ($comentarios as $c) {
                echo '[' . date('d/m/Y', strtotime($c['data_envio'])) . "]\n" . $c['comentario'] . "\n\n";
            }
            exit;

        case 'logs_acesso':
            exigirGestor($pdo);
            $stmt = $pdo->query("SELECT email, acao, data_hora FROM logs_acesso ORDER BY data_hora DESC, id DESC LIMIT 30");
            responderJson($stmt->fetchAll());
            break;

        default:
            responderJson(['erro' => 'Ação não encontrada'], 404);
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Erro de banco na ação '$action': " . $e->getMessage());
    if ($e->getCode() === '42S02') {
        responderJson(['erro' => 'Falta criar tabelas no banco. Rode o seed: docker compose exec php php /var/www/database/seed.php'], 500);
    }
    responderJson(['erro' => 'Erro interno no banco de dados. Tente novamente.'], 500);
}