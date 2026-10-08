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

function exigirFuncionario($pdo, $exigirTermo = true) {
    $usuario = usuarioLogado($pdo);
    if (!$usuario) {
        responderJson(['erro' => 'Faça login para acessar a pesquisa'], 401);
    }
    if ($usuario['tipo_perfil'] !== 'funcionario') {
        responderJson(['erro' => 'A pesquisa é respondida pelos funcionários. Gestores acompanham os resultados pelo painel.'], 403);
    }
    // RNF09: sem o aceite do termo de consentimento, não responde
    if ($exigirTermo && !termoAceito($usuario)) {
        responderJson(['erro' => 'É preciso aceitar o termo de consentimento antes de responder.', 'precisa_termo' => true], 403);
    }
    return $usuario;
}

function validarDadosFuncionario($dados, $senhaObrigatoria) {
    [$d, $erro] = validarCadastro($dados, $senhaObrigatoria);
    if ($erro) {
        responderJson(['erro' => $erro], 400);
    }
    return $d;
}

function buscarFormularioOu404($pdo, $formularioId, $campos = 'id, titulo, status') {
    $stmt = $pdo->prepare("SELECT $campos FROM formularios WHERE id = ?");
    $stmt->execute([(int)$formularioId]);
    $formulario = $stmt->fetch();
    if (!$formulario) {
        responderJson(['erro' => 'Formulário não encontrado'], 404);
    }
    return $formulario;
}

function formularioAnalisado($pdo) {
    $formularioId = resolverFormularioId($pdo, $_GET['formulario_id'] ?? null);
    if (!$formularioId) {
        responderJson(['erro' => 'Nenhum formulário cadastrado ainda'], 404);
    }
    return $formularioId;
}

// título, datas e respondentes esperados de criar_formulario e editar_formulario
function validarDadosFormulario($dados) {
    $titulo = trim((string)($dados['titulo'] ?? ''));
    $esperados = !empty($dados['respondentes_esperados']) ? (int)$dados['respondentes_esperados'] : null;
    $abertura = validarDataHora($dados['data_abertura'] ?? null);
    $fechamento = validarDataHora($dados['data_fechamento'] ?? null);

    if ($titulo === '') {
        responderJson(['erro' => 'Informe um título e ao menos uma pergunta'], 400);
    }
    if (tamanhoTexto($titulo) > 150) {
        responderJson(['erro' => 'O título pode ter no máximo 150 caracteres'], 400);
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

    [$perguntas, $erro] = validarPerguntas($dados['perguntas'] ?? []);
    if ($erro) {
        responderJson(['erro' => $erro], 400);
    }
    return [$titulo, $esperados, $abertura, $fechamento, $perguntas];
}


// ---------------- conexão ----------------

try {
    $pdo = conectarBanco();
} catch (PDOException $e) {
    responderJson(['erro' => 'Falha na conexão com o banco de dados'], 500);
}

try {
    processarEncerramentos($pdo);
    processarNotificacoes($pdo);
} catch (Throwable $e) {
    error_log('Erro ao processar encerramentos/notificações: ' . $e->getMessage());
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

            // limite de tentativas: 5 senhas erradas no mesmo email bloqueiam o login dele por 15 minutos
            $chaveEmail = chaveTentativa('login', $email);
            $chaveIp = chaveTentativa('ip', ipCliente());
            $bloqueio = minutosDeBloqueio($pdo, $chaveEmail, MAX_TENTATIVAS_LOGIN, MINUTOS_BLOQUEIO_LOGIN)
                ?? minutosDeBloqueio($pdo, $chaveIp, MAX_TENTATIVAS_POR_IP, MINUTOS_BLOQUEIO_LOGIN);
            if ($bloqueio !== null) {
                responderJson([
                    'erro' => 'Muitas tentativas com senha errada. Por segurança, o login foi bloqueado por alguns minutos. '
                        . 'Tente de novo em ' . $bloqueio . ($bloqueio === 1 ? ' minuto' : ' minutos') . ' ou use "Esqueci minha senha".',
                    'bloqueado' => true,
                    'minutos' => $bloqueio,
                ], 429);
            }

            $stmt = $pdo->prepare("SELECT id, nome, email, senha_hash, tipo_perfil, ativo, termo_versao, senha_alterada_em FROM funcionarios WHERE email = ?");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();

            if ($usuario && $usuario['ativo'] && password_verify($senha, $usuario['senha_hash'])) {
                limparTentativas($pdo, $chaveEmail);
                session_regenerate_id(true);
                $_SESSION = [];
                marcarSessao($usuario['id'], $usuario['senha_alterada_em']);
                if ($usuario['tipo_perfil'] === 'gestor') {
                    registrarLog($pdo, (int)$usuario['id'], $usuario['email'], 'login');
                }
                responderJson([
                    'sucesso' => true,
                    'id' => (int)$usuario['id'],
                    'nome' => $usuario['nome'],
                    'perfil' => $usuario['tipo_perfil'],
                    'precisa_termo' => !termoAceito($usuario),
                ]);
            }

            if ($usuario && $usuario['tipo_perfil'] === 'gestor') {
                registrarLog($pdo, (int)$usuario['id'], $usuario['email'], 'falha_login');
            }
            registrarTentativa($pdo, $chaveEmail);
            registrarTentativa($pdo, $chaveIp);
            $restantes = MAX_TENTATIVAS_LOGIN - contarTentativas($pdo, $chaveEmail, MINUTOS_BLOQUEIO_LOGIN);
            $erro = 'Email ou senha incorretos.';
            if ($restantes <= 0) {
                $erro .= ' O login deste email foi bloqueado por ' . MINUTOS_BLOQUEIO_LOGIN . ' minutos. Se esqueceu a senha, use "Esqueci minha senha".';
            } elseif ($restantes <= 2) {
                $erro .= ' Você tem mais ' . $restantes . ($restantes === 1 ? ' tentativa' : ' tentativas') . ' antes de o login ser bloqueado por ' . MINUTOS_BLOQUEIO_LOGIN . ' minutos.';
            }
            responderJson(['erro' => $erro], 401);
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
        // SENHAS: alterar (logado) e "Esqueci minha senha" (por email)
        // =======================================================

        // qualquer usuário logado troca a própria senha informando a atual
        case 'alterar_senha':
            exigirMetodo('POST');
            $usuario = usuarioLogado($pdo);
            if (!$usuario) {
                responderJson(['erro' => 'Faça login para continuar'], 401);
            }
            $dados = corpoJson();
            $atual = (string)($dados['senha_atual'] ?? '');
            $nova = (string)($dados['nova_senha'] ?? '');

            $chaveSenha = chaveTentativa('alterar_senha', $usuario['id']);
            $bloqueio = minutosDeBloqueio($pdo, $chaveSenha, MAX_TENTATIVAS_LOGIN, MINUTOS_BLOQUEIO_LOGIN);
            if ($bloqueio !== null) {
                responderJson(['erro' => 'Muitas tentativas com a senha atual errada. Tente de novo em ' . $bloqueio . ($bloqueio === 1 ? ' minuto.' : ' minutos.')], 429);
            }

            $stmt = $pdo->prepare("SELECT senha_hash FROM funcionarios WHERE id = ?");
            $stmt->execute([$usuario['id']]);
            $hashAtual = (string)$stmt->fetchColumn();
            if (!password_verify($atual, $hashAtual)) {
                registrarTentativa($pdo, $chaveSenha);
                responderJson(['erro' => 'A senha atual está incorreta.'], 400);
            }
            $erroSenha = validarSenhaNova($nova);
            if ($erroSenha) {
                responderJson(['erro' => $erroSenha . '.'], 400);
            }
            if (password_verify($nova, $hashAtual)) {
                responderJson(['erro' => 'A nova senha precisa ser diferente da atual.'], 400);
            }

            $marca = definirSenha($pdo, (int)$usuario['id'], $nova);
            limparTentativas($pdo, $chaveSenha);
            limparTentativas($pdo, chaveTentativa('login', $usuario['email']));
            // esta sessão continua valendo; as outras (com a senha antiga) caem
            session_regenerate_id(true);
            marcarSessao($usuario['id'], $marca);
            if ($usuario['tipo_perfil'] === 'gestor') {
                registrarLog($pdo, (int)$usuario['id'], $usuario['email'], 'senha_alterada');
            }
            responderJson(['sucesso' => true, 'email_enviado' => avisarSenhaAlterada($usuario, 'usuario')]);
            break;

        // Esqueci minha senha: manda um link por email. A resposta é sempre a mesma,
        // exista ou não o email, para ninguém descobrir quem tem cadastro.
        case 'solicitar_redefinicao':
            exigirMetodo('POST');
            if (!emailAtivo()) {
                responderJson(['erro' => 'O envio de emails não está configurado no servidor. Peça ao gestor para cadastrar uma nova senha para você.'], 503);
            }
            $email = trim((string)(corpoJson()['email'] ?? ''));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                responderJson(['erro' => 'Informe um email válido.'], 400);
            }
            $chaveIp = chaveTentativa('redefinicao_ip', ipCliente());
            if (minutosDeBloqueio($pdo, $chaveIp, MAX_TENTATIVAS_POR_IP, 60) !== null) {
                responderJson(['erro' => 'Muitos pedidos de redefinição. Tente de novo mais tarde.'], 429);
            }
            registrarTentativa($pdo, $chaveIp);

            $stmt = $pdo->prepare("SELECT id, nome, email FROM funcionarios WHERE email = ? AND ativo = 1");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch();
            if ($usuario) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM redefinicoes_senha WHERE funcionario_id = ? AND criado_em >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
                $stmt->execute([$usuario['id']]);
                if ((int)$stmt->fetchColumn() < MAX_PEDIDOS_REDEFINICAO_HORA) {
                    $link = criarLinkRedefinicao($pdo, (int)$usuario['id']);
                    enviarEmails([mensagemLinkRedefinicao($usuario, $link)]);
                }
            }
            responderJson([
                'sucesso' => true,
                'mensagem' => 'Se este email estiver cadastrado, você vai receber em alguns minutos um link para criar uma nova senha. '
                    . 'O link vale por ' . MINUTOS_VALIDADE_LINK_SENHA . ' minutos. Confira também a caixa de spam.',
            ]);
            break;

        // a tela do link confere se ele ainda vale antes de pedir a nova senha
        case 'verificar_redefinicao':
            exigirMetodo('POST');
            responderJson(['valido' => buscarRedefinicaoValida($pdo, corpoJson()['token'] ?? '') !== null]);
            break;

        case 'redefinir_senha':
            exigirMetodo('POST');
            $dados = corpoJson();
            $redefinicao = buscarRedefinicaoValida($pdo, $dados['token'] ?? '');
            if (!$redefinicao) {
                responderJson(['erro' => 'Este link é inválido, já foi usado ou venceu. Peça um novo em "Esqueci minha senha".'], 400);
            }
            $nova = (string)($dados['nova_senha'] ?? '');
            $erroSenha = validarSenhaNova($nova);
            if ($erroSenha) {
                responderJson(['erro' => $erroSenha . '.'], 400);
            }

            definirSenha($pdo, (int)$redefinicao['funcionario_id'], $nova);
            // quem provou ter acesso ao email sai do bloqueio de tentativas
            limparTentativas($pdo, chaveTentativa('login', $redefinicao['email']));
            if ($redefinicao['tipo_perfil'] === 'gestor') {
                registrarLog($pdo, (int)$redefinicao['funcionario_id'], $redefinicao['email'], 'senha_redefinida');
            }
            avisarSenhaAlterada($redefinicao, 'link');
            responderJson(['sucesso' => true, 'email' => $redefinicao['email']]);
            break;

        // =======================================================
        // FUNCIONÁRIO: termo de consentimento e pesquisa
        // =======================================================

        // RNF09: aceite do termo (a data fica registrada como comprovante)
        case 'aceitar_termo':
            exigirMetodo('POST');
            $usuario = exigirFuncionario($pdo, false);
            $stmt = $pdo->prepare("UPDATE funcionarios SET termo_versao = ?, termo_aceito_em = NOW() WHERE id = ?");
            $stmt->execute([VERSAO_TERMO, $usuario['id']]);
            responderJson(['sucesso' => true]);
            break;

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

            $formulario['id'] = (int)$formulario['id'];
            $formulario['perguntas'] = buscarPerguntas($pdo, $formulario['id']);
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

            // cada pergunta do formulário precisa de uma resposta válida para o seu tipo
            $perguntas = [];
            foreach (buscarPerguntas($pdo, $formularioId) as $p) {
                $perguntas[$p['id']] = $p;
            }
            if (!is_array($itens)) {
                responderJson(['erro' => 'Resposta inválida'], 400);
            }
            $respostasValidas = [];
            foreach ($itens as $item) {
                $perguntaId = (int)($item['pergunta_id'] ?? 0);
                if (!isset($perguntas[$perguntaId])) {
                    responderJson(['erro' => 'Resposta inválida'], 400);
                }
                $pergunta = $perguntas[$perguntaId];
                if ($pergunta['tipo'] === 'nota') {
                    $nota = $item['nota'] ?? null;
                    if (!is_numeric($nota) || (int)$nota < 0 || (int)$nota > 10) {
                        responderJson(['erro' => 'Resposta inválida'], 400);
                    }
                    $respostasValidas[$perguntaId] = ['nota' => (int)$nota, 'opcao' => null];
                } else {
                    $opcao = $item['opcao'] ?? null;
                    $limite = $pergunta['tipo'] === 'sim_nao' ? 2 : count($pergunta['opcoes']);
                    if (!is_numeric($opcao) || (int)$opcao < 0 || (int)$opcao >= $limite) {
                        responderJson(['erro' => 'Resposta inválida'], 400);
                    }
                    $respostasValidas[$perguntaId] = ['nota' => null, 'opcao' => (int)$opcao];
                }
            }
            if (count($respostasValidas) !== count($perguntas)) {
                responderJson(['erro' => 'Responda todas as perguntas antes de enviar.'], 400);
            }

            try {
                $pdo->beginTransaction();

                // 1) carimbo de participação. A chave primária (funcionário + formulário)
                //    impede resposta dupla até com dois envios ao mesmo tempo (RN02).
                $stmt = $pdo->prepare("INSERT INTO controle_acesso (funcionario_id, formulario_id, respondeu, data_resposta) VALUES (?, ?, 1, CURDATE())");
                $stmt->execute([(int)$usuario['id'], $formularioId]);

                // 2) conteúdo anônimo: nada aqui aponta para o funcionário (RN05);
                //    o comentário é gravado criptografado (RNF05)
                $stmt = $pdo->prepare("INSERT INTO respostas (formulario_id, comentario) VALUES (?, ?)");
                $stmt->execute([$formularioId, $comentario !== '' ? criptografar($comentario) : null]);
                $respostaId = $pdo->lastInsertId();

                $stmtItem = $pdo->prepare("INSERT INTO resposta_itens (resposta_id, pergunta_id, nota, opcao) VALUES (?, ?, ?, ?)");
                foreach ($respostasValidas as $perguntaId => $r) {
                    $stmtItem->execute([$respostaId, $perguntaId, $r['nota'], $r['opcao']]);
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

            responderJson(['sucesso' => true, 'data' => date('Y-m-d')]);
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
                       f.termo_aceito_em, (f.termo_versao >= ?) AS termo_em_dia,
                       (ca.funcionario_id IS NOT NULL) AS respondeu_atual
                FROM funcionarios f
                LEFT JOIN controle_acesso ca ON ca.funcionario_id = f.id AND ca.formulario_id = ?
                ORDER BY f.tipo_perfil DESC, f.nome
            ");
            $stmt->execute([VERSAO_TERMO, $formularioAtual ? $formularioAtual['id'] : 0]);

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

            $stmt = $pdo->prepare("UPDATE funcionarios SET nome = ?, email = ?, cargo = ?, data_admissao = ?, tipo_perfil = ? WHERE id = ?");
            $stmt->execute([$d['nome'], $d['email'], $d['cargo'], $d['data_admissao'], $d['perfil'], $funcionarioId]);

            // nova senha definida pelo gestor: derruba as sessões antigas da pessoa e avisa por email
            $emailEnviado = false;
            if ($d['senha'] !== '') {
                $marca = definirSenha($pdo, $funcionarioId, $d['senha']);
                limparTentativas($pdo, chaveTentativa('login', $d['email']));
                if ($funcionarioId === (int)$gestor['id']) {
                    marcarSessao($funcionarioId, $marca);
                    $emailEnviado = avisarSenhaAlterada(['nome' => $d['nome'], 'email' => $d['email']], 'usuario');
                } else {
                    $emailEnviado = avisarSenhaAlterada(['nome' => $d['nome'], 'email' => $d['email']], 'gestor');
                }
            }

            responderJson(['sucesso' => true, 'email_senha_enviado' => $emailEnviado]);
            break;

        // O gestor manda para a pessoa (funcionário ou gestor) um link de "criar nova senha".
        // A gestão não vê o link nem a senha nova; a senha atual vale até a pessoa usar o link.
        case 'enviar_link_senha':
            $gestor = exigirGestor($pdo);
            exigirMetodo('POST');
            if (!emailAtivo()) {
                responderJson(['erro' => 'O envio de emails não está configurado no servidor (SMTP). Use "Editar" para cadastrar uma senha provisória.'], 503);
            }
            $funcionarioId = (int)(corpoJson()['funcionario_id'] ?? 0);
            $stmt = $pdo->prepare("SELECT id, nome, email, ativo FROM funcionarios WHERE id = ?");
            $stmt->execute([$funcionarioId]);
            $pessoa = $stmt->fetch();
            if (!$pessoa) {
                responderJson(['erro' => 'Cadastro não encontrado'], 404);
            }
            if (!(int)$pessoa['ativo']) {
                responderJson(['erro' => 'Este cadastro está desativado. Reative-o antes de enviar o link.'], 400);
            }
            // mesmo limite do "Esqueci minha senha": no máximo alguns links por hora para a mesma pessoa
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM redefinicoes_senha WHERE funcionario_id = ? AND criado_em >= DATE_SUB(NOW(), INTERVAL 1 HOUR)");
            $stmt->execute([$funcionarioId]);
            if ((int)$stmt->fetchColumn() >= MAX_PEDIDOS_REDEFINICAO_HORA) {
                responderJson(['erro' => 'Já foram enviados ' . MAX_PEDIDOS_REDEFINICAO_HORA . ' links para esta pessoa na última hora. Aguarde um pouco antes de enviar outro.'], 429);
            }
            $link = criarLinkRedefinicao($pdo, $funcionarioId);
            $envio = enviarEmails([mensagemLinkRedefinicao($pessoa, $link, true)]);
            if ($envio['enviados'] !== 1) {
                responderJson(['erro' => 'Não foi possível enviar o email. Confira a configuração de email do servidor (docker compose logs php).'], 502);
            }
            registrarLog($pdo, (int)$gestor['id'], $gestor['email'], 'link_senha_enviado');
            responderJson([
                'sucesso' => true,
                'mensagem' => 'Link enviado para ' . $pessoa['email'] . '. Ele vale por ' . MINUTOS_VALIDADE_LINK_SENHA
                    . ' minutos. A senha atual continua valendo até a pessoa criar a nova.',
            ]);
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

        // importação por planilha (.xlsx ou .csv)
        case 'importar_funcionarios':
            exigirGestor($pdo);
            exigirMetodo('POST');
            $arquivo = $_FILES['arquivo'] ?? null;
            if (!$arquivo || ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                responderJson(['erro' => 'Escolha uma planilha para importar.'], 400);
            }
            if ($arquivo['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($arquivo['tmp_name'])) {
                responderJson(['erro' => 'Não foi possível receber o arquivo. Tente novamente.'], 400);
            }
            if ($arquivo['size'] > 2 * 1024 * 1024) {
                responderJson(['erro' => 'A planilha pode ter no máximo 2 MB.'], 400);
            }
            try {
                $linhas = lerPlanilhaImportacao($arquivo['tmp_name'], $arquivo['name']);
                $resultado = importarCadastros($pdo, $linhas);
            } catch (RuntimeException $e) {
                responderJson(['erro' => $e->getMessage()], 400);
            }
            responderJson(['sucesso' => true] + $resultado);
            break;

        case 'modelo_importacao':
            exigirGestor($pdo);
            $arquivo = gerarModeloImportacao();
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="modelo_importacao_funcionarios.xlsx"');
            header('Content-Length: ' . strlen($arquivo));
            echo $arquivo;
            exit;

        // =======================================================
        // GESTOR: formulários (RF02)
        // =======================================================

        case 'formularios':
            exigirGestor($pdo);
            $stmt = $pdo->query("
                SELECT f.id, f.titulo, f.status, f.respondentes_esperados, f.data_abertura, f.data_fechamento,
                       (SELECT COUNT(*) FROM respostas r WHERE r.formulario_id = f.id) AS total_respostas,
                       (SELECT COUNT(*) FROM perguntas p WHERE p.formulario_id = f.id) AS total_perguntas,
                       rel.data_geracao AS relatorio_gerado_em,
                       (SELECT MAX(enviada_em) FROM notificacoes n WHERE n.formulario_id = f.id AND n.tipo = 'abertura' AND n.destinatarios > 0) AS email_abertura_em,
                       (SELECT MAX(enviada_em) FROM notificacoes n WHERE n.formulario_id = f.id AND n.tipo = 'encerramento' AND n.destinatarios > 0) AS email_encerramento_em,
                       (SELECT MAX(enviada_em) FROM notificacoes n WHERE n.formulario_id = f.id AND n.tipo = 'lembrete') AS lembrete_em,
                       (f.status = 'ativo' AND (f.data_abertura IS NULL OR f.data_abertura <= NOW()) AND (f.data_fechamento IS NULL OR f.data_fechamento >= NOW())) AS aberta_agora
                FROM formularios f
                LEFT JOIN relatorios rel ON rel.formulario_id = f.id
                ORDER BY f.criado_em DESC, f.id DESC
            ");
            responderJson(['email_ativo' => emailAtivo(), 'formularios' => $stmt->fetchAll()]);
            break;

        // dados completos de um formulário, para edição
        case 'detalhes_formulario':
            exigirGestor($pdo);
            $formulario = buscarFormularioOu404($pdo, $_GET['formulario_id'] ?? 0, 'id, titulo, status, respondentes_esperados, data_abertura, data_fechamento');
            $formulario['total_respostas'] = contarRespostas($pdo, (int)$formulario['id']);
            $formulario['perguntas'] = buscarPerguntas($pdo, (int)$formulario['id']);
            responderJson($formulario);
            break;

        case 'criar_formulario':
            exigirGestor($pdo);
            exigirMetodo('POST');
            [$titulo, $esperados, $abertura, $fechamento, $perguntas] = validarDadosFormulario(corpoJson());

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO formularios (titulo, respondentes_esperados, status, data_abertura, data_fechamento) VALUES (?, ?, 'rascunho', ?, ?)");
            $stmt->execute([$titulo, $esperados, $abertura, $fechamento]);
            $formularioId = (int)$pdo->lastInsertId();
            salvarPerguntas($pdo, $formularioId, $perguntas);
            $pdo->commit();

            responderJson(['sucesso' => true, 'formulario_id' => $formularioId]);
            break;

        // só rascunhos sem respostas podem ser editados (as respostas dependem das perguntas)
        case 'editar_formulario':
            exigirGestor($pdo);
            exigirMetodo('POST');
            $dados = corpoJson();
            $formulario = buscarFormularioOu404($pdo, $dados['formulario_id'] ?? 0);
            if ($formulario['status'] !== 'rascunho' || contarRespostas($pdo, (int)$formulario['id']) > 0) {
                responderJson(['erro' => 'Só é possível editar formulários em rascunho que ainda não receberam respostas.'], 409);
            }
            [$titulo, $esperados, $abertura, $fechamento, $perguntas] = validarDadosFormulario($dados);

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE formularios SET titulo = ?, respondentes_esperados = ?, data_abertura = ?, data_fechamento = ? WHERE id = ?");
            $stmt->execute([$titulo, $esperados, $abertura, $fechamento, $formulario['id']]);
            $stmt = $pdo->prepare("DELETE FROM perguntas WHERE formulario_id = ?");
            $stmt->execute([$formulario['id']]);
            salvarPerguntas($pdo, (int)$formulario['id'], $perguntas);
            $pdo->commit();

            responderJson(['sucesso' => true]);
            break;

        // cópia em rascunho, com as mesmas perguntas (útil para o próximo ciclo)
        case 'duplicar_formulario':
            exigirGestor($pdo);
            exigirMetodo('POST');
            $dados = corpoJson();
            $formulario = buscarFormularioOu404($pdo, $dados['formulario_id'] ?? 0, 'id, titulo, respondentes_esperados');
            $titulo = 'Cópia de ' . $formulario['titulo'];
            if (tamanhoTexto($titulo) > 150) {
                $titulo = mb_substr($titulo, 0, 150);
            }
            $perguntas = buscarPerguntas($pdo, (int)$formulario['id']);

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO formularios (titulo, respondentes_esperados, status) VALUES (?, ?, 'rascunho')");
            $stmt->execute([$titulo, $formulario['respondentes_esperados']]);
            $novoId = (int)$pdo->lastInsertId();
            salvarPerguntas($pdo, $novoId, $perguntas);
            $pdo->commit();

            responderJson(['sucesso' => true, 'formulario_id' => $novoId]);
            break;

        case 'excluir_formulario':
            exigirGestor($pdo);
            exigirMetodo('POST');
            $dados = corpoJson();
            $formulario = buscarFormularioOu404($pdo, $dados['formulario_id'] ?? 0);
            if ($formulario['status'] !== 'rascunho' || contarRespostas($pdo, (int)$formulario['id']) > 0) {
                responderJson(['erro' => 'Só é possível excluir formulários em rascunho que ainda não receberam respostas.'], 409);
            }
            $stmt = $pdo->prepare("DELETE FROM formularios WHERE id = ?");
            $stmt->execute([$formulario['id']]);
            responderJson(['sucesso' => true]);
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

            $formulario = buscarFormularioOu404($pdo, $formularioId, 'id, data_abertura, data_fechamento');
            if ($novoStatus === 'ativo' && count(buscarPerguntas($pdo, $formularioId)) === 0) {
                responderJson(['erro' => 'O formulário não tem perguntas.'], 400);
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

            // RF08: e-mails de abertura/encerramento saem na hora
            try {
                processarNotificacoes($pdo);
            } catch (Throwable $e) {
                error_log('Erro ao enviar notificações: ' . $e->getMessage());
            }
            responderJson(['sucesso' => true]);
            break;

        // RF08: lembrete por e-mail para quem ainda não respondeu
        case 'enviar_lembrete':
            $gestor = exigirGestor($pdo);
            exigirMetodo('POST');
            if (!emailAtivo()) {
                responderJson(['erro' => 'O envio de e-mails não está configurado no servidor (SMTP).'], 400);
            }
            $dados = corpoJson();
            [$resultado, $erro] = enviarLembrete($pdo, (int)($dados['formulario_id'] ?? 0), $gestor);
            if ($erro) {
                responderJson(['erro' => $erro], 409);
            }
            responderJson(['sucesso' => true] + $resultado);
            break;

        // =======================================================
        // GESTOR: indicadores (RF05, RF07)
        // =======================================================

        case 'dashboard':
            exigirGestor($pdo);
            $formularioId = formularioAnalisado($pdo);
            $formulario = buscarFormularioOu404($pdo, $formularioId, 'id, titulo, status, respondentes_esperados, data_abertura, data_fechamento');

            $total = contarRespostas($pdo, $formularioId);
            $ocultos = dadosOcultos($total);

            // do pior para o melhor; perguntas sem média (ou que não são de nota) vão para o fim
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
                'categorias' => $ocultos ? [] : calcularMediasPorCategoria($pdo, $formularioId),
                'distribuicao' => $ocultos ? ['insatisfeito' => 0, 'neutro' => 0, 'satisfeito' => 0] : calcularDistribuicao($pdo, $formularioId),
                'evolucao' => $evolucao,
            ]);
            break;

        case 'resultados':
            exigirGestor($pdo);
            $formularioId = formularioAnalisado($pdo);
            $periodo = in_array($_GET['periodo'] ?? '', ['7', '30', 'completo'], true) ? $_GET['periodo'] : 'completo';
            $formulario = buscarFormularioOu404($pdo, $formularioId, 'id, titulo');

            responderJson([
                'formulario' => $formulario,
                'periodo' => $periodo,
                'minimo_anonimato' => MINIMO_RESPOSTAS_ANONIMATO,
                'perguntas' => buscarResultadosPorPergunta($pdo, $formularioId, $periodo),
            ]);
            break;

        // comparação entre duas pesquisas (a = principal, b = referência)
        case 'comparar':
            exigirGestor($pdo);
            $a = (int)($_GET['a'] ?? 0);
            $b = (int)($_GET['b'] ?? 0);
            if (!$a || !$b || $a === $b) {
                responderJson(['erro' => 'Escolha duas pesquisas diferentes para comparar.'], 400);
            }
            $comparacao = compararFormularios($pdo, $a, $b);
            if (!$comparacao) {
                responderJson(['erro' => 'Formulário não encontrado'], 404);
            }
            responderJson($comparacao);
            break;

        case 'comentarios':
            exigirGestor($pdo);
            $formularioId = formularioAnalisado($pdo);
            $formulario = buscarFormularioOu404($pdo, $formularioId, 'id, titulo');

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

        // Planilha do Excel (.xlsx) formatada, com a aba Resumo em fórmulas
        case 'exportar_excel':
            exigirGestor($pdo);
            $formularioId = formularioAnalisado($pdo);

            $total = contarRespostas($pdo, $formularioId);
            if (dadosOcultos($total)) {
                responderJson(['erro' => "Exportação indisponível: este formulário tem $total resposta(s). Para proteger o anonimato, é preciso ter pelo menos " . MINIMO_RESPOSTAS_ANONIMATO . '.'], 403);
            }

            $formulario = buscarFormularioOu404($pdo, $formularioId, 'titulo, data_abertura, data_fechamento');
            $perguntas = buscarPerguntas($pdo, $formularioId);

            // sem id e sem hora, em ordem aleatória dentro do dia (RN07)
            $stmt = $pdo->prepare("SELECT id, comentario, DATE(criada_em) AS data_envio FROM respostas WHERE formulario_id = ? ORDER BY DATE(criada_em), RAND()");
            $stmt->execute([$formularioId]);
            $respostas = $stmt->fetchAll();

            $stmtItens = $pdo->prepare("SELECT pergunta_id, nota, opcao FROM resposta_itens WHERE resposta_id = ?");
            $linhas = [];
            foreach ($respostas as $resposta) {
                $stmtItens->execute([$resposta['id']]);
                $itens = [];
                foreach ($stmtItens->fetchAll() as $item) {
                    $itens[(int)$item['pergunta_id']] = $item;
                }
                $valores = [];
                foreach ($perguntas as $p) {
                    $item = $itens[$p['id']] ?? null;
                    if (!$item) {
                        $valores[] = null;
                    } elseif ($p['tipo'] === 'nota') {
                        $valores[] = $item['nota'] === null ? null : (int)$item['nota'];
                    } else {
                        $valores[] = $item['opcao'] === null ? null : (rotulosOpcoes($p)[(int)$item['opcao']] ?? null);
                    }
                }
                $linhas[] = [
                    'data' => $resposta['data_envio'],
                    'valores' => $valores,
                    'comentario' => (string)descriptografar($resposta['comentario'] ?? ''),
                ];
            }

            $subtitulo = 'Gerado em ' . date('d/m/Y') . ' às ' . date('H:i') . '  ·  ' . count($linhas) . ' resposta(s), todas anônimas';
            if ($formulario['data_abertura']) {
                $subtitulo .= '  ·  Período: ' . date('d/m/Y', strtotime($formulario['data_abertura'])) . ' a '
                    . ($formulario['data_fechamento'] ? date('d/m/Y', strtotime($formulario['data_fechamento'])) : 'em aberto');
            }

            $arquivo = gerarPlanilhaRespostas($formulario['titulo'], $subtitulo, $perguntas, $linhas);

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="respostas_pesquisa_' . $formularioId . '.xlsx"');
            header('Content-Length: ' . strlen($arquivo));
            echo $arquivo;
            exit;

        case 'exportar_comentarios':
            exigirGestor($pdo);
            $formularioId = formularioAnalisado($pdo);

            $total = contarRespostas($pdo, $formularioId);
            if (dadosOcultos($total)) {
                responderJson(['erro' => "Exportação indisponível: este formulário tem $total resposta(s). Para proteger o anonimato, é preciso ter pelo menos " . MINIMO_RESPOSTAS_ANONIMATO . '.'], 403);
            }

            $titulo = (string)buscarFormularioOu404($pdo, $formularioId, 'titulo')['titulo'];
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
    if (in_array($e->getCode(), ['42S02', '42S22'], true)) {
        responderJson(['erro' => 'O banco de dados está desatualizado. Rode o seed: docker compose exec php php /var/www/database/seed.php'], 500);
    }
    responderJson(['erro' => 'Erro interno no banco de dados. Tente novamente.'], 500);
}