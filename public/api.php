<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=utf-8');

function resposta(array $dados, int $status = 200): never {
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

function lerJson(): array {
    $dados = json_decode(file_get_contents('php://input'), true);
    return is_array($dados) ? $dados : [];
}

function conectar(): PDO {
    $host = getenv('DB_HOST') ?: 'mariadb';
    $db   = getenv('DB_DATABASE') ?: 'clima_tcc';
    $user = getenv('DB_USER') ?: 'clima_user';
    $pass = getenv('DB_PASSWORD') ?: 'clima_pass';

    return new PDO(
        "mysql:host={$host};dbname={$db};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
}

try {
    $pdo = conectar();
    $action = $_GET['action'] ?? '';

    if ($action === 'salvar_pesquisa') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            resposta(['sucesso' => false, 'mensagem' => 'Método não permitido.'], 405);
        }

        $dados = lerJson();
        $notas = $dados['notas'] ?? [];
        $comentario = trim((string)($dados['comentario'] ?? ''));

        if (!is_array($notas) || count($notas) !== 10) {
            resposta(['sucesso' => false, 'mensagem' => 'A pesquisa precisa ter 10 respostas.'], 422);
        }

        foreach ($notas as $nota) {
            if (!is_numeric($nota) || (int)$nota < 0 || (int)$nota > 10) {
                resposta(['sucesso' => false, 'mensagem' => 'Existe uma nota inválida.'], 422);
            }
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "INSERT INTO respostas (comentario, criada_em) VALUES (:comentario, NOW())"
        );
        $stmt->execute(['comentario' => $comentario !== '' ? $comentario : null]);
        $respostaId = (int)$pdo->lastInsertId();

        $stmtItem = $pdo->prepare(
            "INSERT INTO resposta_itens (resposta_id, pergunta_id, nota)
             VALUES (:resposta_id, :pergunta_id, :nota)"
        );

        foreach ($notas as $indice => $nota) {
            $stmtItem->execute([
                'resposta_id' => $respostaId,
                'pergunta_id' => $indice + 1,
                'nota' => (int)$nota
            ]);
        }

        $pdo->commit();
        resposta(['sucesso' => true]);
    }

    if ($action === 'login') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            resposta(['sucesso' => false, 'mensagem' => 'Método não permitido.'], 405);
        }

        $dados = lerJson();
        $email = trim((string)($dados['email'] ?? ''));
        $senha = (string)($dados['senha'] ?? '');

        $stmt = $pdo->prepare("SELECT id, nome, email, senha_hash FROM gestores WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $gestor = $stmt->fetch();

        if (!$gestor || !password_verify($senha, $gestor['senha_hash'])) {
            resposta(['sucesso' => false, 'mensagem' => 'Email ou senha incorretos.'], 401);
        }

        session_regenerate_id(true);
        $_SESSION['gestor_id'] = (int)$gestor['id'];
        $_SESSION['gestor_nome'] = $gestor['nome'];

        resposta(['sucesso' => true, 'nome' => $gestor['nome']]);
    }

    if ($action === 'logout') {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'], $params['secure'], $params['httponly']
            );
        }
        session_destroy();
        resposta(['sucesso' => true]);
    }

    if ($action === 'dashboard') {
        if (empty($_SESSION['gestor_id'])) {
            resposta(['sucesso' => false, 'mensagem' => 'Acesso não autorizado.'], 401);
        }

        $media = (float)$pdo->query("SELECT COALESCE(AVG(nota), 0) FROM resposta_itens")->fetchColumn();
        $total = (int)$pdo->query("SELECT COUNT(*) FROM respostas")->fetchColumn();

        $ultima = $pdo->query(
            "SELECT DATE_FORMAT(criada_em, '%d/%m/%Y %H:%i') AS data_formatada
             FROM respostas ORDER BY criada_em DESC LIMIT 1"
        )->fetchColumn();

        $perguntas = $pdo->query(
            "SELECT p.id, p.texto, COALESCE(AVG(ri.nota), 0) AS media
             FROM perguntas p
             LEFT JOIN resposta_itens ri ON ri.pergunta_id = p.id
             GROUP BY p.id, p.texto
             ORDER BY media ASC"
        )->fetchAll();

        $pontosCriticos = array_slice($perguntas, 0, 2);
        usort($perguntas, fn($a, $b) => (float)$b['media'] <=> (float)$a['media']);
        $pontosFortes = array_slice($perguntas, 0, 2);

        $formatar = function(array $item): array {
            return [
                'nome' => $item['texto'],
                'media' => (float)$item['media']
            ];
        };

        // Sem uma tabela de funcionários, a taxa é apenas um indicador demonstrativo.
        $taxaParticipacao = $total > 0 ? 100 : 0;

        resposta([
            'sucesso' => true,
            'media_geral' => round($media, 1),
            'total_respostas' => $total,
            'taxa_participacao' => $taxaParticipacao,
            'ultima_atualizacao' => $ultima ? 'Atualizado' : null,
            'ultima_atualizacao_completa' => $ultima ?: null,
            'pontos_criticos' => array_map($formatar, $pontosCriticos),
            'pontos_fortes' => array_map($formatar, $pontosFortes)
        ]);
    }

    resposta(['sucesso' => false, 'mensagem' => 'Ação não encontrada.'], 404);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    resposta(['sucesso' => false, 'mensagem' => 'Erro interno do servidor.'], 500);
}
