<?php
require_once __DIR__ . '/config.php';
session_start();

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
$metodo = $_SERVER['REQUEST_METHOD'];

function corpoJson() {
    $dados = json_decode(file_get_contents('php://input'), true);
    return is_array($dados) ? $dados : [];
}

function exigirGestor() {
    if (empty($_SESSION['gestor_id'])) {
        http_response_code(401);
        echo json_encode(['erro' => 'Não autenticado']);
        exit;
    }
}

function exigirMetodo($esperado) {
    global $metodo;
    if ($metodo !== $esperado) {
        http_response_code(405);
        echo json_encode(['erro' => 'Método inválido']);
        exit;
    }
}

switch ($action) {

    // ---------------------------------------------------------
    // PÚBLICO: pesquisa
    // ---------------------------------------------------------

    case 'formulario_ativo':
        $stmt = $pdo->query("SELECT id, titulo, descricao FROM formulariosWHERE status = 'ativo'AND (data_abertura IS NULL OR data_abertura <= NOW())AND (data_fechamento IS NULL OR data_fechamento >= NOW())ORDER BY data_abertura DESC LIMIT 1
");
        $formulario = $stmt->fetch();

        if (!$formulario) {
            http_response_code(404);
            echo json_encode(['erro' => 'Nenhuma pesquisa ativa no momento']);
            break;
        }

        $stmtP = $pdo->prepare("SELECT id, texto FROM perguntas WHERE formulario_id = ? ORDER BY ordem, id");
        $stmtP->execute([$formulario['id']]);
        $formulario['perguntas'] = $stmtP->fetchAll();

        echo json_encode($formulario);
        break;

    case 'enviar_resposta':
        exigirMetodo('POST');
        $dados = corpoJson();
        $formularioId = $dados['formulario_id'] ?? null;
        $itens = $dados['respostas'] ?? [];
        $comentario = trim((string)($dados['comentario'] ?? ''));

        if (!$formularioId || !is_array($itens) || count($itens) === 0) {
            http_response_code(400);
            echo json_encode(['erro' => 'Dados incompletos']);
            break;
        }

        // confere se o formulário ainda está ativo (evita enviar pra pesquisa já encerrada)
        $stmt = $pdo->prepare("SELECT id FROM formularios WHERE id = ? AND status = 'ativo'");
        $stmt->execute([$formularioId]);
        if (!$stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['erro' => 'Esta pesquisa não está mais ativa']);
            break;
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO respostas (formulario_id, comentario) VALUES (?, ?)");
            $stmt->execute([$formularioId, $comentario !== '' ? $comentario : null]);
            $respostaId = $pdo->lastInsertId();

            $stmtItem = $pdo->prepare("INSERT INTO resposta_itens (resposta_id, pergunta_id, nota) VALUES (?, ?, ?)");
            foreach ($itens as $item) {
                $perguntaId = (int)($item['pergunta_id'] ?? 0);
                $nota = max(0, min(10, (int)($item['nota'] ?? 0)));
                if ($perguntaId <= 0) continue;
                $stmtItem->execute([$respostaId, $perguntaId, $nota]);
            }

            $pdo->commit();
            echo json_encode(['sucesso' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['erro' => 'Não foi possível salvar a resposta']);
        }
        break;

    // ---------------------------------------------------------
    // AUTENTICAÇÃO DO GESTOR
    // ---------------------------------------------------------

    case 'login':
        exigirMetodo('POST');
        $dados = corpoJson();
        $email = trim((string)($dados['email'] ?? ''));
        $senha = (string)($dados['senha'] ?? '');

        $stmt = $pdo->prepare("SELECT id, nome, senha_hash FROM gestores WHERE email = ?");
        $stmt->execute([$email]);
        $gestor = $stmt->fetch();

        if ($gestor && password_verify($senha, $gestor['senha_hash'])) {
            session_regenerate_id(true);
            $_SESSION['gestor_id'] = $gestor['id'];
            $_SESSION['gestor_nome'] = $gestor['nome'];
            echo json_encode(['sucesso' => true, 'nome' => $gestor['nome']]);
        } else {
            http_response_code(401);
            echo json_encode(['erro' => 'Email ou senha incorretos']);
        }
        break;

    case 'logout':
        $_SESSION = [];
        session_destroy();
        echo json_encode(['sucesso' => true]);
        break;

    // ---------------------------------------------------------
    // GESTOR: gerenciar formulários
    // ---------------------------------------------------------

    case 'formularios':
        exigirGestor();
        $stmt = $pdo->query("
            SELECT f.id, f.titulo, f.status, f.respondentes_esperados,
                   f.data_abertura, f.data_fechamento,
                   (SELECT COUNT(*) FROM respostas r WHERE r.formulario_id = f.id) AS total_respostas
            FROM formularios f
            ORDER BY f.criado_em DESC
        ");
        echo json_encode($stmt->fetchAll());
        break;

    case 'criar_formulario':
        exigirGestor();
        exigirMetodo('POST');
        $dados = corpoJson();
        $titulo = trim((string)($dados['titulo'] ?? ''));
        $descricao = trim((string)($dados['descricao'] ?? ''));
        $perguntasTexto = $dados['perguntas'] ?? [];
        $respondentesEsperados = !empty($dados['respondentes_esperados']) ? (int)$dados['respondentes_esperados'] : null;
        $dataAbertura = !empty($dados['data_abertura']) ? $dados['data_abertura'] : null;
        $dataFechamento = !empty($dados['data_fechamento']) ? $dados['data_fechamento'] : null;

        $perguntasValidas = array_values(array_filter(array_map('trim', is_array($perguntasTexto) ? $perguntasTexto : []), fn($t) => $t !== ''));

        if ($titulo === '' || count($perguntasValidas) < 1) {
            http_response_code(400);
            echo json_encode(['erro' => 'Informe um título e ao menos uma pergunta']);
            break;
        }

        if ($dataAbertura && $dataFechamento && strtotime($dataFechamento) <= strtotime($dataAbertura)) {
        http_response_code(400);
        echo json_encode(['erro' => 'A data de término deve ser depois da data de início']);
        break;
}

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO formularios (titulo, descricao, respondentes_esperados, status) VALUES (?, ?, ?, 'rascunho')");
            $stmt->execute([$titulo, $descricao !== '' ? $descricao : null, $respondentesEsperados]);
            $formularioId = $pdo->lastInsertId();

            $stmt = $pdo->prepare("INSERT INTO formularios (titulo, descricao, respondentes_esperados, status, data_abertura, data_fechamento)
            VALUES (?, ?, ?, 'rascunho', ?, ?)");
            $stmt->execute([$titulo, $descricao !== '' ? $descricao : null, $respondentesEsperados, $dataAbertura, $dataFechamento]);
            $ordem = 1;
            foreach ($perguntasValidas as $texto) {
                $stmtP->execute([$formularioId, $texto, $ordem]);
                $ordem++;
            }

            $pdo->commit();
            echo json_encode(['sucesso' => true, 'formulario_id' => $formularioId]);
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['erro' => 'Não foi possível criar o formulário']);
        }
        break;

    case 'alterar_status_formulario':
        exigirGestor();
        exigirMetodo('POST');
        $dados = corpoJson();
        $formularioId = (int)($dados['formulario_id'] ?? 0);
        $novoStatus = $dados['status'] ?? '';

        if (!$formularioId || !in_array($novoStatus, ['rascunho', 'ativo', 'encerrado'], true)) {
            http_response_code(400);
            echo json_encode(['erro' => 'Dados inválidos']);
            break;
        }

        try {
            $pdo->beginTransaction();

            if ($novoStatus === 'ativo') {
                // só um formulário ativo por vez
                $pdo->exec("UPDATE formularios SET status = 'encerrado', data_fechamento = NOW() WHERE status = 'ativo'");
                $stmt = $pdo->prepare("UPDATE formularios SET status = 'ativo', data_abertura = COALESCE(data_abertura, NOW()) WHERE id = ?");
            } elseif ($novoStatus === 'encerrado') {
                $stmt = $pdo->prepare("UPDATE formularios SET status = 'encerrado', data_fechamento = NOW() WHERE id = ?");
            } else {
                $stmt = $pdo->prepare("UPDATE formularios SET status = ? WHERE id = ?");
                $stmt->execute([$novoStatus, $formularioId]);
                $pdo->commit();
                echo json_encode(['sucesso' => true]);
                break;
            }

            $stmt->execute([$formularioId]);
            $pdo->commit();
            echo json_encode(['sucesso' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo json_encode(['erro' => 'Não foi possível atualizar o status']);
        }
        break;

    // ---------------------------------------------------------
    // GESTOR: dashboard e comentários
    // ---------------------------------------------------------

    case 'dashboard':
        exigirGestor();
        $formularioId = isset($_GET['formulario_id']) ? (int)$_GET['formulario_id'] : null;

        if (!$formularioId) {
            $stmt = $pdo->query("SELECT id FROM formularios WHERE status = 'ativo' ORDER BY data_abertura DESC LIMIT 1");
            $ativo = $stmt->fetch();
            $formularioId = $ativo['id'] ?? null;
        }

        if (!$formularioId) {
            echo json_encode(['erro' => 'Nenhum formulário encontrado']);
            break;
        }

        $stmt = $pdo->prepare("SELECT * FROM formularios WHERE id = ?");
        $stmt->execute([$formularioId]);
        $formulario = $stmt->fetch();

        if (!$formulario) {
            http_response_code(404);
            echo json_encode(['erro' => 'Formulário não encontrado']);
            break;
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM respostas WHERE formulario_id = ?");
        $stmt->execute([$formularioId]);
        $totalRespostas = (int)$stmt->fetch()['total'];

        $stmt = $pdo->prepare("
            SELECT AVG(ri.nota) AS media
            FROM resposta_itens ri
            JOIN respostas r ON r.id = ri.resposta_id
            WHERE r.formulario_id = ?
        ");
        $stmt->execute([$formularioId]);
        $mediaGeral = round((float)($stmt->fetch()['media'] ?? 0), 1);

        $stmt = $pdo->prepare("
            SELECT p.id, p.texto, AVG(ri.nota) AS media
            FROM perguntas p
            LEFT JOIN resposta_itens ri ON ri.pergunta_id = p.id
            WHERE p.formulario_id = ?
            GROUP BY p.id, p.texto
            ORDER BY media ASC
        ");
        $stmt->execute([$formularioId]);
        $porPergunta = $stmt->fetchAll();
        foreach ($porPergunta as &$p) {
            $p['media'] = $p['media'] !== null ? round((float)$p['media'], 1) : null;
        }
        unset($p);

        $stmt = $pdo->prepare("
            SELECT AVG(ri.nota) AS media
            FROM respostas r
            JOIN resposta_itens ri ON ri.resposta_id = r.id
            WHERE r.formulario_id = ?
            GROUP BY r.id
        ");
        $stmt->execute([$formularioId]);
        $medias = array_column($stmt->fetchAll(), 'media');
        $dist = ['insatisfeito' => 0, 'neutro' => 0, 'satisfeito' => 0];
        foreach ($medias as $m) {
            $m = (float)$m;
            if ($m <= 3) $dist['insatisfeito']++;
            elseif ($m <= 6) $dist['neutro']++;
            else $dist['satisfeito']++;
        }

        $stmt = $pdo->prepare("
            SELECT DATE(criada_em) AS dia, COUNT(*) AS total
            FROM respostas
            WHERE formulario_id = ?
            GROUP BY DATE(criada_em)
            ORDER BY dia
        ");
        $stmt->execute([$formularioId]);
        $evolucao = $stmt->fetchAll();

        $taxaParticipacao = null;
        if (!empty($formulario['respondentes_esperados'])) {
            $taxaParticipacao = round(($totalRespostas / $formulario['respondentes_esperados']) * 100);
        }

        echo json_encode([
            'formulario' => $formulario,
            'total_respostas' => $totalRespostas,
            'media_geral' => $mediaGeral,
            'taxa_participacao' => $taxaParticipacao,
            'por_pergunta' => $porPergunta,
            'distribuicao' => $dist,
            'evolucao' => $evolucao,
        ]);
        break;

    case 'comentarios':
        exigirGestor();
        $formularioId = isset($_GET['formulario_id']) ? (int)$_GET['formulario_id'] : null;

        if (!$formularioId) {
            $stmt = $pdo->query("SELECT id FROM formularios WHERE status = 'ativo' ORDER BY data_abertura DESC LIMIT 1");
            $ativo = $stmt->fetch();
            $formularioId = $ativo['id'] ?? null;
        }

        if (!$formularioId) {
            echo json_encode([]);
            break;
        }

        $stmt = $pdo->prepare("
            SELECT comentario, criada_em
            FROM respostas
            WHERE formulario_id = ? AND comentario IS NOT NULL AND comentario <> ''
            ORDER BY criada_em DESC
        ");
        $stmt->execute([$formularioId]);
        echo json_encode($stmt->fetchAll());
        break;

    default:
        http_response_code(404);
        echo json_encode(['erro' => 'Ação não encontrada']);
}