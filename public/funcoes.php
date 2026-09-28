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

// RNF09: versão do termo de consentimento. Se o texto do termo mudar,
// aumente este número e todos os funcionários aceitam de novo.
const VERSAO_TERMO = 1;

// tipos de pergunta
const TIPOS_PERGUNTA = ['nota', 'sim_nao', 'multipla'];
const MAXIMO_OPCOES = 10;


// ---------- usuário logado ----------

// Relê o usuário no banco a cada requisição: se o gestor desativar ou excluir
// alguém, o acesso dessa pessoa para de funcionar na hora.
function usuarioLogado($pdo) {
    if (empty($_SESSION['usuario_id'])) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT id, nome, email, tipo_perfil, ativo, termo_versao FROM funcionarios WHERE id = ?");
    $stmt->execute([$_SESSION['usuario_id']]);
    $usuario = $stmt->fetch();
    if (!$usuario || !$usuario['ativo']) {
        return null;
    }
    return $usuario;
}

// RNF09: o funcionário precisa aceitar o termo antes de responder
function termoAceito($usuario) {
    if ($usuario['tipo_perfil'] === 'gestor') {
        return true;
    }
    return (int)($usuario['termo_versao'] ?? 0) >= VERSAO_TERMO;
}

// RF12: log de acesso dos gestores
function registrarLog($pdo, $usuarioId, $email, $acao) {
    $stmt = $pdo->prepare("INSERT INTO logs_acesso (usuario_id, email, acao, data_hora) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$usuarioId, $email, $acao]);
}


// ---------- validações ----------

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

// Valida os dados de um cadastro (tela Funcionários e importação por planilha).
// Retorna [dados normalizados, null] ou [null, mensagem de erro].
function validarCadastro($dados, $senhaObrigatoria) {
    $nome = trim((string)($dados['nome'] ?? ''));
    $email = trim((string)($dados['email'] ?? ''));
    $senha = (string)($dados['senha'] ?? '');
    $cargo = trim((string)($dados['cargo'] ?? ''));
    $dataAdmissao = trim((string)($dados['data_admissao'] ?? ''));
    $perfil = (string)($dados['tipo_perfil'] ?? 'funcionario');

    if ($nome === '' || $email === '') {
        return [null, 'Informe o nome e o email'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [null, 'Email inválido'];
    }
    if (tamanhoTexto($nome) > 100 || tamanhoTexto($email) > 100 || tamanhoTexto($cargo) > 50) {
        return [null, 'Texto muito longo (nome e email até 100 caracteres, cargo até 50)'];
    }
    if (($senhaObrigatoria || $senha !== '') && strlen($senha) < 6) {
        return [null, 'A senha precisa ter pelo menos 6 caracteres'];
    }
    if (!in_array($perfil, ['funcionario', 'gestor'], true)) {
        return [null, 'Perfil inválido'];
    }
    if (validarData($dataAdmissao) === false) {
        return [null, 'Data de admissão inválida'];
    }

    return [[
        'nome' => $nome,
        'email' => $email,
        'senha' => $senha,
        'cargo' => $cargo !== '' ? $cargo : null,
        'data_admissao' => $dataAdmissao !== '' ? $dataAdmissao : null,
        'perfil' => $perfil,
    ], null];
}

// senha provisória para cadastros importados sem senha (sem letras parecidas, como l, 1, O e 0)
function gerarSenhaAleatoria($tamanho = 8) {
    $caracteres = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $senha = '';
    for ($i = 0; $i < $tamanho; $i++) {
        $senha .= $caracteres[random_int(0, strlen($caracteres) - 1)];
    }
    return $senha;
}


// ---------- criptografia dos comentários e relatórios (RNF05) ----------

// Chave de 256 bits. Vem da variável de ambiente APP_KEY (em base64) ou do
// arquivo database/chave.key, criado automaticamente no primeiro uso.
// Guarde esse arquivo junto com os backups: sem ele os comentários não abrem.
function caminhoChave() {
    return getenv('CHAVE_ARQUIVO') ?: __DIR__ . '/../database/chave.key';
}

function chaveCriptografia() {
    static $chave = null;
    if ($chave !== null) {
        return $chave;
    }

    $daAmbiente = getenv('APP_KEY');
    if ($daAmbiente) {
        $bruta = base64_decode($daAmbiente, true);
        if ($bruta !== false && strlen($bruta) === 32) {
            return $chave = $bruta;
        }
    }

    $arquivo = caminhoChave();
    if (is_file($arquivo)) {
        $bruta = base64_decode(trim((string)file_get_contents($arquivo)), true);
        if ($bruta !== false && strlen($bruta) === 32) {
            return $chave = $bruta;
        }
    }

    $bruta = random_bytes(32);
    if (@file_put_contents($arquivo, base64_encode($bruta) . "\n", LOCK_EX) === false) {
        throw new RuntimeException('Não foi possível criar a chave de criptografia em ' . $arquivo);
    }
    @chmod($arquivo, 0644);
    return $chave = $bruta;
}

function estaCriptografado($valor) {
    return is_string($valor) && str_starts_with($valor, 'enc1:');
}

// AES-256-GCM: "enc1:" + base64(vetor inicial + etiqueta de autenticação + texto cifrado)
function criptografar($texto) {
    if ($texto === null || $texto === '') {
        return $texto;
    }
    $iv = random_bytes(12);
    $etiqueta = '';
    $cifrado = openssl_encrypt((string)$texto, 'aes-256-gcm', chaveCriptografia(), OPENSSL_RAW_DATA, $iv, $etiqueta, '', 16);
    if ($cifrado === false) {
        throw new RuntimeException('Falha ao criptografar');
    }
    return 'enc1:' . base64_encode($iv . $etiqueta . $cifrado);
}

// Textos antigos, gravados antes da criptografia, voltam como estão.
function descriptografar($valor) {
    if (!estaCriptografado($valor)) {
        return $valor;
    }
    $bruto = base64_decode(substr($valor, 5), true);
    if ($bruto === false || strlen($bruto) < 29) {
        return '[conteúdo ilegível]';
    }
    $texto = openssl_decrypt(substr($bruto, 28), 'aes-256-gcm', chaveCriptografia(), OPENSSL_RAW_DATA, substr($bruto, 0, 12), substr($bruto, 12, 16));
    return $texto === false ? '[conteúdo ilegível: a chave de criptografia não confere]' : $texto;
}


// ---------- perguntas (tipos, opções e categorias) ----------

function rotulosOpcoes($pergunta) {
    if ($pergunta['tipo'] === 'sim_nao') {
        return ['Não', 'Sim'];
    }
    if ($pergunta['tipo'] === 'multipla') {
        $opcoes = is_array($pergunta['opcoes'] ?? null) ? $pergunta['opcoes'] : json_decode((string)($pergunta['opcoes'] ?? ''), true);
        return is_array($opcoes) ? array_values($opcoes) : [];
    }
    return [];
}

function rotuloTipo($tipo) {
    return ['nota' => 'Nota de 0 a 10', 'sim_nao' => 'Sim ou não', 'multipla' => 'Múltipla escolha'][$tipo] ?? $tipo;
}

// Aceita a lista antiga (só textos) ou a nova (objetos com texto, tipo, opcoes, categoria).
// Retorna [perguntas normalizadas, null] ou [null, mensagem de erro].
function validarPerguntas($entrada) {
    if (!is_array($entrada)) {
        return [null, 'Lista de perguntas inválida'];
    }
    $perguntas = [];
    foreach ($entrada as $item) {
        if (!is_array($item)) {
            $item = ['texto' => $item];
        }
        $texto = trim((string)($item['texto'] ?? ''));
        if ($texto === '') {
            continue;
        }
        $tipo = (string)($item['tipo'] ?? 'nota');
        $categoria = trim((string)($item['categoria'] ?? ''));
        $numero = count($perguntas) + 1;

        if (!in_array($tipo, TIPOS_PERGUNTA, true)) {
            return [null, "Pergunta $numero: tipo inválido"];
        }
        if (tamanhoTexto($texto) > 255) {
            return [null, "Pergunta $numero: o texto pode ter no máximo 255 caracteres"];
        }
        if (tamanhoTexto($categoria) > 60) {
            return [null, "Pergunta $numero: a categoria pode ter no máximo 60 caracteres"];
        }

        $opcoes = null;
        if ($tipo === 'multipla') {
            $lista = is_array($item['opcoes'] ?? null) ? $item['opcoes'] : [];
            $opcoes = [];
            foreach ($lista as $opcao) {
                $opcao = trim((string)$opcao);
                if ($opcao !== '' && !in_array($opcao, $opcoes, true)) {
                    $opcoes[] = $opcao;
                }
            }
            if (count($opcoes) < 2 || count($opcoes) > MAXIMO_OPCOES) {
                return [null, "Pergunta $numero: informe de 2 a " . MAXIMO_OPCOES . " alternativas diferentes"];
            }
            foreach ($opcoes as $opcao) {
                if (tamanhoTexto($opcao) > 60) {
                    return [null, "Pergunta $numero: cada alternativa pode ter no máximo 60 caracteres"];
                }
            }
        }

        $perguntas[] = ['texto' => $texto, 'tipo' => $tipo, 'opcoes' => $opcoes, 'categoria' => $categoria !== '' ? $categoria : null];
    }

    if (count($perguntas) === 0) {
        return [null, 'Informe ao menos uma pergunta'];
    }
    if (count($perguntas) > 50) {
        return [null, 'Máximo de 50 perguntas por formulário'];
    }
    return [$perguntas, null];
}

function salvarPerguntas($pdo, $formularioId, array $perguntas) {
    $stmt = $pdo->prepare("INSERT INTO perguntas (formulario_id, texto, tipo, opcoes, categoria, ordem) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($perguntas as $i => $p) {
        $stmt->execute([
            $formularioId,
            $p['texto'],
            $p['tipo'],
            $p['opcoes'] !== null ? json_encode(array_values($p['opcoes']), JSON_UNESCAPED_UNICODE) : null,
            $p['categoria'],
            $i + 1,
        ]);
    }
}

function buscarPerguntas($pdo, $formularioId) {
    $stmt = $pdo->prepare("SELECT id, texto, tipo, opcoes, categoria FROM perguntas WHERE formulario_id = ? ORDER BY ordem, id");
    $stmt->execute([$formularioId]);
    $perguntas = [];
    foreach ($stmt->fetchAll() as $p) {
        $p['id'] = (int)$p['id'];
        $p['opcoes'] = $p['tipo'] === 'multipla' ? rotulosOpcoes($p) : null;
        $perguntas[] = $p;
    }
    return $perguntas;
}


// ---------- estatística ----------

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


// ---------- formulários ----------

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

// média geral: só perguntas do tipo nota
function calcularMediaGeral($pdo, $formularioId) {
    $stmt = $pdo->prepare("SELECT AVG(ri.nota) FROM resposta_itens ri JOIN respostas r ON r.id = ri.resposta_id WHERE r.formulario_id = ? AND ri.nota IS NOT NULL");
    $stmt->execute([$formularioId]);
    $media = $stmt->fetchColumn();
    return $media === null ? null : round((float)$media, 1);
}

// classificação de cada envio pela média das suas notas
function calcularDistribuicao($pdo, $formularioId) {
    $stmt = $pdo->prepare("SELECT AVG(ri.nota) FROM respostas r JOIN resposta_itens ri ON ri.resposta_id = r.id WHERE r.formulario_id = ? AND ri.nota IS NOT NULL GROUP BY r.id");
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

// Resultados de cada pergunta conforme o tipo:
//   nota: média, mediana e desvio padrão
//   sim_nao e multipla: quantidade e percentual de cada alternativa
function buscarResultadosPorPergunta($pdo, $formularioId, $periodo = 'completo') {
    $filtroData = '';
    if ($periodo === '7') {
        $filtroData = 'AND r.criada_em >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
    } elseif ($periodo === '30') {
        $filtroData = 'AND r.criada_em >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
    }

    $perguntas = buscarPerguntas($pdo, $formularioId);

    $stmt = $pdo->prepare("SELECT ri.pergunta_id, ri.nota, ri.opcao FROM resposta_itens ri JOIN respostas r ON r.id = ri.resposta_id WHERE r.formulario_id = ? $filtroData");
    $stmt->execute([$formularioId]);
    $porPergunta = [];
    foreach ($stmt->fetchAll() as $item) {
        $porPergunta[(int)$item['pergunta_id']][] = $item;
    }

    $resultado = [];
    foreach ($perguntas as $pergunta) {
        $itens = $porPergunta[$pergunta['id']] ?? [];
        $linha = [
            'id' => $pergunta['id'],
            'texto' => $pergunta['texto'],
            'tipo' => $pergunta['tipo'],
            'categoria' => $pergunta['categoria'],
            'total' => 0,
            'oculto' => false,
            'media' => null,
            'mediana' => null,
            'desvio_padrao' => null,
            'opcoes' => [],
            'percentual_sim' => null,
        ];

        if ($pergunta['tipo'] === 'nota') {
            $notas = [];
            foreach ($itens as $item) {
                if ($item['nota'] !== null) {
                    $notas[] = (int)$item['nota'];
                }
            }
            $stats = calcularEstatisticas($notas);
            $linha['total'] = $stats['total'];
            $linha['oculto'] = dadosOcultos($stats['total']);
            if (!$linha['oculto']) {
                $linha['media'] = $stats['media'];
                $linha['mediana'] = $stats['mediana'];
                $linha['desvio_padrao'] = $stats['desvio_padrao'];
            }
        } else {
            $rotulos = rotulosOpcoes($pergunta);
            $contagem = array_fill(0, count($rotulos), 0);
            foreach ($itens as $item) {
                $opcao = $item['opcao'];
                if ($opcao !== null && isset($contagem[(int)$opcao])) {
                    $contagem[(int)$opcao]++;
                }
            }
            $total = array_sum($contagem);
            $linha['total'] = $total;
            $linha['oculto'] = dadosOcultos($total);
            if (!$linha['oculto']) {
                foreach ($rotulos as $i => $rotulo) {
                    $linha['opcoes'][] = [
                        'indice' => $i,
                        'texto' => $rotulo,
                        'total' => $contagem[$i],
                        'percentual' => $total > 0 ? (int)round($contagem[$i] / $total * 100) : 0,
                    ];
                }
                if ($pergunta['tipo'] === 'sim_nao' && $total > 0) {
                    $linha['percentual_sim'] = (int)round($contagem[1] / $total * 100);
                }
            }
        }
        $resultado[] = $linha;
    }
    return $resultado;
}

// média das perguntas do tipo nota agrupadas por categoria
function calcularMediasPorCategoria($pdo, $formularioId) {
    $stmt = $pdo->prepare("
        SELECT COALESCE(NULLIF(p.categoria, ''), 'Sem categoria') AS categoria,
               AVG(ri.nota) AS media,
               COUNT(DISTINCT p.id) AS perguntas
        FROM perguntas p
        JOIN resposta_itens ri ON ri.pergunta_id = p.id
        WHERE p.formulario_id = ? AND p.tipo = 'nota' AND ri.nota IS NOT NULL
        GROUP BY COALESCE(NULLIF(p.categoria, ''), 'Sem categoria')
        ORDER BY categoria
    ");
    $stmt->execute([$formularioId]);
    $categorias = [];
    foreach ($stmt->fetchAll() as $linha) {
        $categorias[] = [
            'categoria' => $linha['categoria'],
            'media' => round((float)$linha['media'], 1),
            'perguntas' => (int)$linha['perguntas'],
        ];
    }
    return $categorias;
}

// Só a data (sem hora) e em ordem aleatória dentro do mesmo dia: dificulta
// ligar um comentário a quem acabou de aparecer como "já respondeu" (RN07)
function buscarComentarios($pdo, $formularioId) {
    $stmt = $pdo->prepare("SELECT comentario, DATE(criada_em) AS data_envio FROM respostas WHERE formulario_id = ? AND comentario IS NOT NULL AND comentario <> '' ORDER BY DATE(criada_em) DESC, RAND()");
    $stmt->execute([$formularioId]);
    $comentarios = [];
    foreach ($stmt->fetchAll() as $linha) {
        $linha['comentario'] = descriptografar($linha['comentario']);
        $comentarios[] = $linha;
    }
    return $comentarios;
}


// ---------- participação (RN02 / RN03) ----------

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


// ---------- encerramento e relatório automático (RN01 / RN08) ----------

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
        'categorias' => $ocultos ? [] : calcularMediasPorCategoria($pdo, $formularioId),
        'por_pergunta' => buscarResultadosPorPergunta($pdo, $formularioId, 'completo'),
        'comentarios' => $ocultos ? [] : buscarComentarios($pdo, $formularioId),
    ];
}

// o relatório fica guardado criptografado, pois contém os comentários
function gerarRelatorioConsolidado($pdo, $formularioId) {
    $dados = montarDadosConsolidados($pdo, $formularioId);
    if ($dados === null) {
        return;
    }
    $stmt = $pdo->prepare("INSERT INTO relatorios (formulario_id, data_geracao, dados_consolidados) VALUES (?, NOW(), ?) ON DUPLICATE KEY UPDATE data_geracao = NOW(), dados_consolidados = VALUES(dados_consolidados)");
    $stmt->execute([$formularioId, criptografar(json_encode($dados, JSON_UNESCAPED_UNICODE))]);
}

// retorna ['data_geracao' => ..., 'dados' => [...]] ou null
function lerRelatorio($pdo, $formularioId) {
    $stmt = $pdo->prepare("SELECT data_geracao, dados_consolidados FROM relatorios WHERE formulario_id = ?");
    $stmt->execute([$formularioId]);
    $linha = $stmt->fetch();
    if (!$linha) {
        return null;
    }
    $dados = json_decode(descriptografar($linha['dados_consolidados']), true);
    return is_array($dados) ? ['data_geracao' => $linha['data_geracao'], 'dados' => $dados] : null;
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


// ---------- e-mail por SMTP (RF08) ----------

// Configuração pelas variáveis de ambiente do docker-compose.yml.
// Sem SMTP_HOST, o envio de e-mail fica desligado.
function configuracaoSmtp() {
    return [
        'host' => getenv('SMTP_HOST') ?: '',
        'porta' => (int)(getenv('SMTP_PORT') ?: 25),
        'usuario' => getenv('SMTP_USUARIO') ?: '',
        'senha' => getenv('SMTP_SENHA') ?: '',
        'seguranca' => strtolower(getenv('SMTP_SEGURANCA') ?: ''),   // '', 'tls' (STARTTLS) ou 'ssl'
        'remetente' => getenv('SMTP_REMETENTE') ?: 'climatize@escola.local',
        'nome' => getenv('SMTP_NOME') ?: 'Climatize',
    ];
}

function emailAtivo() {
    return configuracaoSmtp()['host'] !== '';
}

function enderecoSistema() {
    return rtrim(getenv('APP_URL') ?: 'http://localhost:8080', '/');
}

function smtpLer($conexao) {
    $resposta = '';
    while (($linha = fgets($conexao, 1024)) !== false) {
        $resposta .= $linha;
        if (strlen($linha) < 4 || $linha[3] === ' ') {
            break;
        }
    }
    return [(int)substr($resposta, 0, 3), $resposta];
}

function smtpComando($conexao, $comando, array $codigosEsperados) {
    fwrite($conexao, $comando . "\r\n");
    [$codigo, $texto] = smtpLer($conexao);
    if (!in_array($codigo, $codigosEsperados, true)) {
        throw new RuntimeException('SMTP recusou "' . strtok($comando, ' ') . '": ' . trim($texto));
    }
    return $texto;
}

function cabecalhoCodificado($texto) {
    return '=?UTF-8?B?' . base64_encode(str_replace(["\r", "\n"], ' ', (string)$texto)) . '?=';
}

// Envia várias mensagens numa única conexão.
// Cada mensagem: ['para' => email, 'nome' => nome, 'assunto' => ..., 'texto' => ...]
// Retorna ['enviados' => n, 'falhas' => n]
function enviarEmails(array $mensagens) {
    $resultado = ['enviados' => 0, 'falhas' => 0];
    if (count($mensagens) === 0) {
        return $resultado;
    }
    $cfg = configuracaoSmtp();
    if ($cfg['host'] === '') {
        $resultado['falhas'] = count($mensagens);
        return $resultado;
    }

    $endereco = ($cfg['seguranca'] === 'ssl' ? 'ssl://' : '') . $cfg['host'];
    $conexao = @fsockopen($endereco, $cfg['porta'], $erroNumero, $erroTexto, 10);
    if (!$conexao) {
        error_log("SMTP: não conectou em {$cfg['host']}:{$cfg['porta']} ($erroTexto)");
        $resultado['falhas'] = count($mensagens);
        return $resultado;
    }
    stream_set_timeout($conexao, 15);

    try {
        [$codigo, $texto] = smtpLer($conexao);
        if ($codigo !== 220) {
            throw new RuntimeException('SMTP não respondeu: ' . trim($texto));
        }
        $dominio = gethostname() ?: 'localhost';
        smtpComando($conexao, "EHLO $dominio", [250]);
        if ($cfg['seguranca'] === 'tls') {
            smtpComando($conexao, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($conexao, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Falha ao iniciar TLS');
            }
            smtpComando($conexao, "EHLO $dominio", [250]);
        }
        if ($cfg['usuario'] !== '') {
            smtpComando($conexao, 'AUTH LOGIN', [334]);
            smtpComando($conexao, base64_encode($cfg['usuario']), [334]);
            smtpComando($conexao, base64_encode($cfg['senha']), [235]);
        }
    } catch (RuntimeException $e) {
        error_log($e->getMessage());
        fclose($conexao);
        $resultado['falhas'] = count($mensagens);
        return $resultado;
    }

    foreach ($mensagens as $m) {
        $para = trim((string)$m['para']);
        if (!filter_var($para, FILTER_VALIDATE_EMAIL)) {
            $resultado['falhas']++;
            continue;
        }
        try {
            smtpComando($conexao, "MAIL FROM:<{$cfg['remetente']}>", [250]);
            smtpComando($conexao, "RCPT TO:<$para>", [250, 251]);
            smtpComando($conexao, 'DATA', [354]);
            $cabecalhos = [
                'Date: ' . date('r'),
                'From: ' . cabecalhoCodificado($cfg['nome']) . " <{$cfg['remetente']}>",
                'To: ' . cabecalhoCodificado($m['nome'] ?? '') . " <$para>",
                'Subject: ' . cabecalhoCodificado($m['assunto']),
                'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . (explode('@', $cfg['remetente'])[1] ?? 'localhost') . '>',
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: base64',
            ];
            // o corpo em base64 não tem linhas começando com ponto, então dispensa "dot-stuffing"
            fwrite($conexao, implode("\r\n", $cabecalhos) . "\r\n\r\n" . rtrim(chunk_split(base64_encode($m['texto']), 76, "\r\n")) . "\r\n.\r\n");
            [$codigo, $texto] = smtpLer($conexao);
            if ($codigo !== 250) {
                throw new RuntimeException('SMTP recusou a mensagem: ' . trim($texto));
            }
            $resultado['enviados']++;
        } catch (RuntimeException $e) {
            error_log($e->getMessage());
            $resultado['falhas']++;
            @fwrite($conexao, "RSET\r\n");
            smtpLer($conexao);
        }
    }

    @fwrite($conexao, "QUIT\r\n");
    fclose($conexao);
    return $resultado;
}


// ---------- notificações de abertura, encerramento e lembrete (RF08) ----------

function formatarDataHoraBr($valor) {
    return $valor ? date('d/m/Y', strtotime($valor)) . ' às ' . date('H:i', strtotime($valor)) : '';
}

// Reserva a notificação antes de enviar: se duas requisições chegarem juntas,
// só a que conseguir gravar a chave envia os e-mails.
function reservarNotificacao($pdo, $formularioId, $tipo, $chave) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO notificacoes (formulario_id, tipo, chave, enviada_em) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$formularioId, $tipo, $chave]);
    return $stmt->rowCount() === 1;
}

function registrarEnvio($pdo, $chave, array $resultado) {
    $stmt = $pdo->prepare("UPDATE notificacoes SET destinatarios = ?, falhas = ? WHERE chave = ?");
    $stmt->execute([$resultado['enviados'], $resultado['falhas'], $chave]);
}

function chaveCiclo($formulario) {
    return (int)$formulario['id'] . '-' . strtotime((string)$formulario['data_abertura']);
}

// funcionários ativos que podem responder a pesquisa agora (não respondida e fora do intervalo de 21 dias)
function funcionariosQuePodemResponder($pdo, $formularioId) {
    $stmt = $pdo->query("SELECT id, nome, email FROM funcionarios WHERE ativo = 1 AND tipo_perfil = 'funcionario' ORDER BY nome");
    $lista = [];
    foreach ($stmt->fetchAll() as $f) {
        if (verificarBloqueioParticipacao($pdo, (int)$f['id'], $formularioId) === null) {
            $lista[] = $f;
        }
    }
    return $lista;
}

function mensagemAbertura($f, $formulario, $lembrete = false) {
    $texto = "Olá, {$f['nome']}!\n\n";
    $texto .= $lembrete
        ? "Lembrete: a pesquisa de clima \"{$formulario['titulo']}\" continua aberta e ainda não recebemos a sua participação.\n\n"
        : "A pesquisa de clima \"{$formulario['titulo']}\" está aberta.\n\n";
    if ($formulario['data_fechamento']) {
        $texto .= 'Ela fica disponível até ' . formatarDataHoraBr($formulario['data_fechamento']) . ".\n\n";
    }
    $texto .= "Suas respostas são anônimas: o sistema registra apenas que você participou, nunca o que você respondeu.\n\n";
    $texto .= 'Para responder, acesse: ' . enderecoSistema() . "\n\nEquipe Climatize";
    return [
        'para' => $f['email'],
        'nome' => $f['nome'],
        'assunto' => ($lembrete ? 'Lembrete: ' : 'Pesquisa de clima aberta: ') . $formulario['titulo'],
        'texto' => $texto,
    ];
}

// Roda no início de cada requisição (e logo após ativar ou encerrar uma pesquisa).
// Só olha pesquisas abertas ou encerradas nos últimos 2 dias, para nunca disparar
// e-mails atrasados de pesquisas antigas.
function processarNotificacoes($pdo) {
    if (!emailAtivo()) {
        return;
    }

    $stmt = $pdo->query("SELECT id, titulo, data_abertura, data_fechamento FROM formularios WHERE status = 'ativo' AND data_abertura IS NOT NULL AND data_abertura <= NOW() AND data_abertura >= DATE_SUB(NOW(), INTERVAL 2 DAY) AND (data_fechamento IS NULL OR data_fechamento > NOW())");
    foreach ($stmt->fetchAll() as $formulario) {
        $chave = 'abertura-' . chaveCiclo($formulario);
        if (!reservarNotificacao($pdo, (int)$formulario['id'], 'abertura', $chave)) {
            continue;
        }
        $mensagens = array_map(fn($f) => mensagemAbertura($f, $formulario), funcionariosQuePodemResponder($pdo, (int)$formulario['id']));
        registrarEnvio($pdo, $chave, enviarEmails($mensagens));
    }

    $stmt = $pdo->query("SELECT id, titulo, data_abertura, data_fechamento FROM formularios WHERE status = 'encerrado' AND data_abertura IS NOT NULL AND data_fechamento >= DATE_SUB(NOW(), INTERVAL 2 DAY)");
    foreach ($stmt->fetchAll() as $formulario) {
        $chave = 'encerramento-' . chaveCiclo($formulario);
        if (!reservarNotificacao($pdo, (int)$formulario['id'], 'encerramento', $chave)) {
            continue;
        }
        $mensagens = [];
        $pessoas = $pdo->query("SELECT nome, email, tipo_perfil FROM funcionarios WHERE ativo = 1 ORDER BY nome")->fetchAll();
        foreach ($pessoas as $p) {
            if ($p['tipo_perfil'] === 'gestor') {
                $texto = "Olá, {$p['nome']}!\n\nA pesquisa \"{$formulario['titulo']}\" foi encerrada em " . formatarDataHoraBr($formulario['data_fechamento'])
                    . ".\n\nO relatório consolidado já foi gerado automaticamente e está disponível no painel do gestor, em Formulários > Ver relatório: "
                    . enderecoSistema() . "\n\nEquipe Climatize";
                $assunto = 'Relatório disponível: ' . $formulario['titulo'];
            } else {
                $texto = "Olá, {$p['nome']}!\n\nA pesquisa \"{$formulario['titulo']}\" foi encerrada em " . formatarDataHoraBr($formulario['data_fechamento'])
                    . ".\n\nObrigado a todos que participaram! Os resultados são analisados apenas de forma agregada e ajudam a construir um ambiente de trabalho melhor.\n\nEquipe Climatize";
                $assunto = 'Pesquisa encerrada: ' . $formulario['titulo'];
            }
            $mensagens[] = ['para' => $p['email'], 'nome' => $p['nome'], 'assunto' => $assunto, 'texto' => $texto];
        }
        registrarEnvio($pdo, $chave, enviarEmails($mensagens));
    }
}

// Lembrete enviado pelo gestor para quem ainda não respondeu
function enviarLembrete($pdo, $formularioId) {
    $stmt = $pdo->prepare("SELECT id, titulo, data_abertura, data_fechamento FROM formularios WHERE id = ? AND status = 'ativo' AND data_abertura <= NOW() AND (data_fechamento IS NULL OR data_fechamento > NOW())");
    $stmt->execute([$formularioId]);
    $formulario = $stmt->fetch();
    if (!$formulario) {
        return [null, 'Só é possível enviar lembrete de uma pesquisa aberta.'];
    }

    $stmt = $pdo->prepare("SELECT 1 FROM notificacoes WHERE formulario_id = ? AND tipo = 'lembrete' AND enviada_em >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
    $stmt->execute([$formularioId]);
    if ($stmt->fetch()) {
        return [null, 'Um lembrete desta pesquisa foi enviado há menos de 10 minutos. Aguarde um pouco antes de enviar outro.'];
    }

    $destinatarios = funcionariosQuePodemResponder($pdo, $formularioId);
    if (count($destinatarios) === 0) {
        return [null, 'Todos os funcionários que podem responder já participaram.'];
    }

    $chave = 'lembrete-' . $formularioId . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
    reservarNotificacao($pdo, $formularioId, 'lembrete', $chave);
    $resultado = enviarEmails(array_map(fn($f) => mensagemAbertura($f, $formulario, true), $destinatarios));
    registrarEnvio($pdo, $chave, $resultado);
    return [$resultado, null];
}


// ---------- comparação entre duas pesquisas ----------

function normalizarTexto($texto) {
    $texto = function_exists('mb_strtolower') ? mb_strtolower(trim($texto)) : strtolower(trim($texto));
    return preg_replace('/\s+/', ' ', $texto);
}

function resumoParaComparacao($pdo, $formularioId) {
    $stmt = $pdo->prepare("SELECT id, titulo, status, data_abertura, data_fechamento, respondentes_esperados FROM formularios WHERE id = ?");
    $stmt->execute([$formularioId]);
    $formulario = $stmt->fetch();
    if (!$formulario) {
        return null;
    }
    $total = contarRespostas($pdo, $formularioId);
    $ocultos = dadosOcultos($total);
    $esperados = $formulario['respondentes_esperados'] ? (int)$formulario['respondentes_esperados'] : contarFuncionariosAtivos($pdo);

    return [
        'formulario' => $formulario,
        'total_respostas' => $total,
        'oculto' => $ocultos,
        'media_geral' => $ocultos ? null : calcularMediaGeral($pdo, $formularioId),
        'taxa_participacao' => $esperados > 0 ? (int)round($total / $esperados * 100) : null,
        'categorias' => $ocultos ? [] : calcularMediasPorCategoria($pdo, $formularioId),
        'perguntas' => buscarResultadosPorPergunta($pdo, $formularioId, 'completo'),
    ];
}

function diferenca($a, $b) {
    return ($a === null || $b === null) ? null : round($a - $b, 1);
}

// $idA = pesquisa principal, $idB = pesquisa usada como referência
function compararFormularios($pdo, $idA, $idB) {
    $a = resumoParaComparacao($pdo, $idA);
    $b = resumoParaComparacao($pdo, $idB);
    if (!$a || !$b) {
        return null;
    }

    // categorias presentes em qualquer uma das duas
    $categorias = [];
    foreach ([['a', $a], ['b', $b]] as [$lado, $dados]) {
        foreach ($dados['categorias'] as $c) {
            $categorias[$c['categoria']]['categoria'] = $c['categoria'];
            $categorias[$c['categoria']]['media_' . $lado] = $c['media'];
        }
    }
    $listaCategorias = [];
    foreach ($categorias as $c) {
        $c += ['media_a' => null, 'media_b' => null];
        $c['diferenca'] = diferenca($c['media_a'], $c['media_b']);
        $listaCategorias[] = $c;
    }
    usort($listaCategorias, fn($x, $y) => strcmp($x['categoria'], $y['categoria']));

    // perguntas com o mesmo texto e o mesmo tipo (nota ou sim/não) nas duas pesquisas
    $indiceB = [];
    foreach ($b['perguntas'] as $p) {
        $indiceB[$p['tipo'] . '|' . normalizarTexto($p['texto'])] = $p;
    }
    $perguntas = [];
    foreach ($a['perguntas'] as $p) {
        if ($p['tipo'] === 'multipla') {
            continue;
        }
        $chave = $p['tipo'] . '|' . normalizarTexto($p['texto']);
        if (!isset($indiceB[$chave])) {
            continue;
        }
        $q = $indiceB[$chave];
        $valorA = $p['tipo'] === 'nota' ? $p['media'] : $p['percentual_sim'];
        $valorB = $q['tipo'] === 'nota' ? $q['media'] : $q['percentual_sim'];
        $perguntas[] = [
            'texto' => $p['texto'],
            'tipo' => $p['tipo'],
            'categoria' => $p['categoria'],
            'valor_a' => $valorA,
            'valor_b' => $valorB,
            'diferenca' => diferenca($valorA, $valorB),
        ];
    }

    $semPar = count($a['perguntas']) - count($perguntas);
    unset($a['perguntas'], $b['perguntas'], $a['categorias'], $b['categorias']);

    return [
        'a' => $a,
        'b' => $b,
        'diferenca_media' => diferenca($a['media_geral'], $b['media_geral']),
        'categorias' => $listaCategorias,
        'perguntas' => $perguntas,
        'perguntas_sem_correspondencia' => $semPar,
        'minimo_anonimato' => MINIMO_RESPOSTAS_ANONIMATO,
    ];
}


// ---------- importação de funcionários por planilha ----------

// "Data de admissão" -> "dataadmissao"
function normalizarCabecalho($texto) {
    $texto = normalizarTexto((string)$texto);
    $texto = strtr($texto, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c']);
    return preg_replace('/[^a-z]/', '', $texto);
}

function campoDoCabecalho($texto) {
    $c = normalizarCabecalho($texto);
    if ($c === 'nome' || $c === 'nomecompleto') return 'nome';
    if (str_contains($c, 'mail')) return 'email';
    if ($c === 'senha' || $c === 'senhainicial') return 'senha';
    if ($c === 'cargo' || $c === 'funcao') return 'cargo';
    if (str_contains($c, 'admissao')) return 'data_admissao';
    if ($c === 'perfil' || $c === 'tipo' || $c === 'tipoperfil' || $c === 'tipodeperfil') return 'tipo_perfil';
    return null;
}

// aceita dd/mm/aaaa, dd-mm-aaaa, aaaa-mm-dd e o número de série de datas do Excel
function normalizarDataImportada($valor) {
    $valor = trim((string)$valor);
    if ($valor === '') {
        return '';
    }
    if (preg_match('/^\d+(\.\d+)?$/', $valor) && (float)$valor > 20000 && (float)$valor < 80000) {
        return gmdate('Y-m-d', (int)round(((float)$valor - 25569) * 86400));
    }
    if (preg_match('#^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$#', $valor, $m)) {
        return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $valor, $m)) {
        return "$m[1]-$m[2]-$m[3]";
    }
    return $valor; // inválida: a validação avisa
}

function normalizarPerfilImportado($valor) {
    $valor = normalizarCabecalho($valor);
    if ($valor === '' || str_starts_with($valor, 'funcion') || $valor === 'colaborador') return 'funcionario';
    if (str_starts_with($valor, 'gestor') || $valor === 'gerente' || $valor === 'coordenador' || $valor === 'coordenadora') return 'gestor';
    return $valor;
}

// $linhas: saída de lerPlanilhaImportacao()
// Retorna ['criados' => n, 'ignorados' => [...], 'senhas_geradas' => [...]] ou lança RuntimeException
function importarCadastros($pdo, array $linhas) {
    // cabeçalho: a primeira linha (entre as 10 primeiras) que tenha nome e email
    $mapa = null;
    $inicio = 0;
    foreach (array_slice($linhas, 0, 10, true) as $i => $linha) {
        $campos = [];
        foreach ($linha as $coluna => $texto) {
            $campo = campoDoCabecalho($texto);
            if ($campo && !in_array($campo, $campos, true)) {
                $campos[$coluna] = $campo;
            }
        }
        if (in_array('nome', $campos, true) && in_array('email', $campos, true)) {
            $mapa = $campos;
            $inicio = $i + 1;
            break;
        }
    }
    if ($mapa === null) {
        throw new RuntimeException('Não encontrei o cabeçalho. A planilha precisa ter as colunas "nome" e "email" (use o modelo).');
    }

    $dadosLinhas = array_slice($linhas, $inicio, null, true);
    if (count($dadosLinhas) > 500) {
        throw new RuntimeException('Máximo de 500 pessoas por importação.');
    }

    $existentes = array_flip(array_map('strtolower', $pdo->query("SELECT email FROM funcionarios")->fetchAll(PDO::FETCH_COLUMN)));
    $resultado = ['criados' => 0, 'ignorados' => [], 'senhas_geradas' => []];
    $novos = [];

    foreach ($dadosLinhas as $i => $linha) {
        $numeroLinha = $i + 1;
        $dados = [];
        foreach ($mapa as $coluna => $campo) {
            $dados[$campo] = trim((string)($linha[$coluna] ?? ''));
        }
        if (implode('', $dados) === '') {
            continue; // linha em branco
        }
        $email = strtolower($dados['email'] ?? '');
        if ($email === EMAIL_EXEMPLO_IMPORTACAO) {
            $resultado['ignorados'][] = ['linha' => $numeroLinha, 'email' => $email, 'motivo' => 'Linha de exemplo do modelo'];
            continue;
        }
        $dados['email'] = $email;
        $dados['data_admissao'] = normalizarDataImportada($dados['data_admissao'] ?? '');
        $dados['tipo_perfil'] = normalizarPerfilImportado($dados['tipo_perfil'] ?? '');
        $senhaGerada = false;
        if (($dados['senha'] ?? '') === '') {
            $dados['senha'] = gerarSenhaAleatoria();
            $senhaGerada = true;
        }

        [$d, $erro] = validarCadastro($dados, true);
        if ($erro) {
            $resultado['ignorados'][] = ['linha' => $numeroLinha, 'email' => $email, 'motivo' => $erro];
            continue;
        }
        if (isset($existentes[$email])) {
            $resultado['ignorados'][] = ['linha' => $numeroLinha, 'email' => $email, 'motivo' => 'Email já cadastrado'];
            continue;
        }
        $existentes[$email] = true;
        $novos[] = $d;
        if ($senhaGerada) {
            $resultado['senhas_geradas'][] = ['nome' => $d['nome'], 'email' => $d['email'], 'senha' => $d['senha']];
        }
    }

    if ($novos) {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO funcionarios (nome, email, senha_hash, cargo, data_admissao, tipo_perfil) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($novos as $d) {
            $stmt->execute([$d['nome'], $d['email'], password_hash($d['senha'], PASSWORD_DEFAULT), $d['cargo'], $d['data_admissao'], $d['perfil']]);
        }
        $pdo->commit();
    }
    $resultado['criados'] = count($novos);
    return $resultado;
}