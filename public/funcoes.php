<?php
// ============================================================
// Regras e funções compartilhadas (api.php, relatorio_impressao.php e seed.php)
// ============================================================

// RN07: médias, gráficos, comentários e exportações só aparecem a partir deste
// número de respostas. Com menos, a "média" seria praticamente a resposta de
// uma pessoa, e como o gestor vê quem já respondeu, daria para identificá-la.
const MINIMO_RESPOSTAS_ANONIMATO = 3;

// RN01: prazo padrão de uma pesquisa depois de publicada (ativada)
const DIAS_PESQUISA_ABERTA = 7;

// RN03: intervalo mínimo entre duas participações do mesmo funcionário
const DIAS_INTERVALO_PARTICIPACAO = 21;


// ---------------- usuário logado ----------------

// Relê o usuário no banco a cada requisição: se o gestor desativar ou excluir
// alguém, o acesso dessa pessoa para de funcionar na hora.
function usuarioLogado($pdo) {
    if (empty($_SESSION['usuario_id'])) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT id, nome, email, tipo_perfil, ativo FROM funcionarios WHERE id = ?");
    $stmt->execute([$_SESSION['usuario_id']]);
    $usuario = $stmt->fetch();
    if (!$usuario || !$usuario['ativo']) {
        return null;
    }
    return $usuario;
}

// RF12: log de acesso dos gestores
function registrarLog($pdo, $usuarioId, $email, $acao) {
    $stmt = $pdo->prepare("INSERT INTO logs_acesso (usuario_id, email, acao, data_hora) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$usuarioId, $email, $acao]);
}


// ---------------- validações ----------------

function tamanhoTexto($texto) {
    return function_exists('mb_strlen') ? mb_strlen($texto) : strlen($texto);
}

// data e hora vindas do campo datetime-local (ex.: 2026-09-16 19:45:00)
// retorna null se vazio, false se inválido
function validarDataHora($valor) {
    if ($valor === null || $valor === '') {
        return null;
    }
    $data = DateTime::createFromFormat('Y-m-d H:i:s', (string)$valor);
    if (!$data || $data->format('Y-m-d H:i:s') !== $valor) {
        return false;
    }
    return $valor;
}

// data vinda do campo date (ex.: 2026-02-10)
function validarData($valor) {
    if ($valor === null || $valor === '') {
        return null;
    }
    $data = DateTime::createFromFormat('Y-m-d', (string)$valor);
    if (!$data || $data->format('Y-m-d') !== $valor) {
        return false;
    }
    return $valor;
}


// ---------------- estatística ----------------

function calcularEstatisticas(array $notas) {
    $n = count($notas);
    if ($n === 0) {
        return ['media' => null, 'mediana' => null, 'desvio_padrao' => null, 'total' => 0];
    }

    $media = array_sum($notas) / $n;

    sort($notas);
    $meio = intdiv($n, 2);
    $mediana = ($n % 2 === 0) ? ($notas[$meio - 1] + $notas[$meio]) / 2 : $notas[$meio];

    $somaQuadrados = 0;
    foreach ($notas as $nota) {
        $somaQuadrados += pow($nota - $media, 2);
    }

    return [
        'media' => round($media, 1),
        'mediana' => round($mediana, 1),
        'desvio_padrao' => round(sqrt($somaQuadrados / $n), 1),
        'total' => $n,
    ];
}

function dadosOcultos($totalRespostas) {
    return $totalRespostas > 0 && $totalRespostas < MINIMO_RESPOSTAS_ANONIMATO;
}


// ---------------- formulários ----------------

// pesquisa que os funcionários podem responder agora
function buscarFormularioAberto($pdo) {
    $stmt = $pdo->query("SELECT id, titulo, descricao, data_abertura, data_fechamento FROM formularios WHERE status = 'ativo' AND (data_abertura IS NULL OR data_abertura <= NOW()) AND (data_fechamento IS NULL OR data_fechamento >= NOW()) ORDER BY data_abertura DESC LIMIT 1");
    $formulario = $stmt->fetch();
    return $formulario ?: null;
}

// formulário que o gestor está analisando: o pedido ou, se nenhum,
// o ativo, senão o último encerrado, senão o último criado
function resolverFormularioId($pdo, $formularioId) {
    $formularioId = (int)$formularioId;
    if ($formularioId > 0) {
        return $formularioId;
    }
    $stmt = $pdo->query("SELECT id FROM formularios ORDER BY FIELD(status, 'ativo', 'encerrado', 'rascunho'), COALESCE(data_abertura, criado_em) DESC, id DESC LIMIT 1");
    $formulario = $stmt->fetch();
    return $formulario ? (int)$formulario['id'] : null;
}

function contarRespostas($pdo, $formularioId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM respostas WHERE formulario_id = ?");
    $stmt->execute([$formularioId]);
    return (int)$stmt->fetchColumn();
}

function contarFuncionariosAtivos($pdo) {
    return (int)$pdo->query("SELECT COUNT(*) FROM funcionarios WHERE ativo = 1 AND tipo_perfil = 'funcionario'")->fetchColumn();
}

function calcularMediaGeral($pdo, $formularioId) {
    $stmt = $pdo->prepare("SELECT AVG(ri.nota) FROM resposta_itens ri JOIN respostas r ON r.id = ri.resposta_id WHERE r.formulario_id = ?");
    $stmt->execute([$formularioId]);
    $media = $stmt->fetchColumn();
    return $media === null ? null : round((float)$media, 1);
}

function calcularDistribuicao($pdo, $formularioId) {
    $stmt = $pdo->prepare("SELECT AVG(ri.nota) FROM respostas r JOIN resposta_itens ri ON ri.resposta_id = r.id WHERE r.formulario_id = ? GROUP BY r.id");
    $stmt->execute([$formularioId]);
    $distribuicao = ['insatisfeito' => 0, 'neutro' => 0, 'satisfeito' => 0];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $media) {
        $media = (float)$media;
        if ($media <= 3) {
            $distribuicao['insatisfeito']++;
        } elseif ($media <= 6) {
            $distribuicao['neutro']++;
        } else {
            $distribuicao['satisfeito']++;
        }
    }
    return $distribuicao;
}

function buscarResultadosPorPergunta($pdo, $formularioId, $periodo = 'completo') {
    $filtroData = '';
    if ($periodo === '7') {
        $filtroData = 'AND r.criada_em >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
    } elseif ($periodo === '30') {
        $filtroData = 'AND r.criada_em >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
    }

    $stmtP = $pdo->prepare("SELECT id, texto FROM perguntas WHERE formulario_id = ? ORDER BY ordem, id");
    $stmtP->execute([$formularioId]);
    $perguntas = $stmtP->fetchAll();

    $stmtN = $pdo->prepare("SELECT ri.nota FROM resposta_itens ri JOIN respostas r ON r.id = ri.resposta_id WHERE ri.pergunta_id = ? $filtroData");

    $resultado = [];
    foreach ($perguntas as $pergunta) {
        $stmtN->execute([$pergunta['id']]);
        $notas = array_map('intval', $stmtN->fetchAll(PDO::FETCH_COLUMN));
        $stats = calcularEstatisticas($notas);
        $oculto = dadosOcultos($stats['total']);

        $resultado[] = [
            'id' => (int)$pergunta['id'],
            'texto' => $pergunta['texto'],
            'total' => $stats['total'],
            'oculto' => $oculto,
            'media' => $oculto ? null : $stats['media'],
            'mediana' => $oculto ? null : $stats['mediana'],
            'desvio_padrao' => $oculto ? null : $stats['desvio_padrao'],
        ];
    }
    return $resultado;
}

// Só a data (sem hora) e em ordem aleatória dentro do mesmo dia: dificulta
// ligar um comentário a quem acabou de aparecer como "já respondeu" (RN07)
function buscarComentarios($pdo, $formularioId) {
    $stmt = $pdo->prepare("SELECT comentario, DATE(criada_em) AS data_envio FROM respostas WHERE formulario_id = ? AND comentario IS NOT NULL AND comentario <> '' ORDER BY DATE(criada_em) DESC, RAND()");
    $stmt->execute([$formularioId]);
    return $stmt->fetchAll();
}


// ---------------- participação (RN02 / RN03) ----------------

// retorna a mensagem de bloqueio, ou null se o funcionário pode responder
function verificarBloqueioParticipacao($pdo, $funcionarioId, $formularioId) {
    $stmt = $pdo->prepare("SELECT 1 FROM controle_acesso WHERE funcionario_id = ? AND formulario_id = ?");
    $stmt->execute([$funcionarioId, $formularioId]);
    if ($stmt->fetch()) {
        return 'Você já respondeu esta pesquisa. Obrigado pela participação!';
    }

    $stmt = $pdo->prepare("SELECT MAX(data_resposta) FROM controle_acesso WHERE funcionario_id = ?");
    $stmt->execute([$funcionarioId]);
    $ultimaParticipacao = $stmt->fetchColumn();

    if ($ultimaParticipacao) {
        $liberaEm = (new DateTime($ultimaParticipacao))->modify('+' . DIAS_INTERVALO_PARTICIPACAO . ' days');
        if (new DateTime('today') < $liberaEm) {
            return 'Você participou de uma pesquisa em ' . date('d/m/Y', strtotime($ultimaParticipacao))
                . '. É preciso esperar ' . DIAS_INTERVALO_PARTICIPACAO . ' dias entre uma participação e outra: você poderá responder novamente a partir de '
                . $liberaEm->format('d/m/Y') . '.';
        }
    }
    return null;
}


// ---------------- encerramento e relatório automático (RN01 / RN08) ----------------

function montarDadosConsolidados($pdo, $formularioId) {
    $stmt = $pdo->prepare("SELECT id, titulo, status, data_abertura, data_fechamento FROM formularios WHERE id = ?");
    $stmt->execute([$formularioId]);
    $formulario = $stmt->fetch();
    if (!$formulario) {
        return null;
    }

    $total = contarRespostas($pdo, $formularioId);
    $ocultos = dadosOcultos($total);

    return [
        'formulario' => $formulario,
        'total_respostas' => $total,
        'dados_ocultos' => $ocultos,
        'minimo_anonimato' => MINIMO_RESPOSTAS_ANONIMATO,
        'media_geral' => $ocultos ? null : calcularMediaGeral($pdo, $formularioId),
        'por_pergunta' => buscarResultadosPorPergunta($pdo, $formularioId, 'completo'),
        'comentarios' => $ocultos ? [] : buscarComentarios($pdo, $formularioId),
    ];
}

function gerarRelatorioConsolidado($pdo, $formularioId) {
    $dados = montarDadosConsolidados($pdo, $formularioId);
    if ($dados === null) {
        return;
    }
    $stmt = $pdo->prepare("INSERT INTO relatorios (formulario_id, data_geracao, dados_consolidados) VALUES (?, NOW(), ?) ON DUPLICATE KEY UPDATE data_geracao = NOW(), dados_consolidados = VALUES(dados_consolidados)");
    $stmt->execute([$formularioId, json_encode($dados, JSON_UNESCAPED_UNICODE)]);
}

function encerrarFormulario($pdo, $formularioId) {
    $stmt = $pdo->prepare("UPDATE formularios SET status = 'encerrado', data_fechamento = CASE WHEN data_fechamento IS NULL OR data_fechamento > NOW() THEN NOW() ELSE data_fechamento END WHERE id = ?");
    $stmt->execute([$formularioId]);
    gerarRelatorioConsolidado($pdo, $formularioId);
}

// Roda no início de cada requisição: encerra as pesquisas cujo prazo acabou
// e gera o relatório consolidado de toda pesquisa encerrada que ainda não tem
function processarEncerramentos($pdo) {
    $pdo->exec("UPDATE formularios SET status = 'encerrado' WHERE status = 'ativo' AND data_fechamento IS NOT NULL AND data_fechamento < NOW()");

    $ids = $pdo->query("SELECT f.id FROM formularios f LEFT JOIN relatorios rel ON rel.formulario_id = f.id WHERE f.status = 'encerrado' AND rel.id IS NULL")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $id) {
        gerarRelatorioConsolidado($pdo, (int)$id);
    }
}
