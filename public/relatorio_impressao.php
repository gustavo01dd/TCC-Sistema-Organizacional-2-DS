<?php
// ============================================================
// Relatório para impressão / "Salvar como PDF" (RF10)
// Pesquisa encerrada: mostra o relatório consolidado gerado
// automaticamente no encerramento (RN08).
// Pesquisa em andamento: mostra um relatório parcial.
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/funcoes.php';

iniciarSessao();

function e($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function dataBr($valor, $comHora = false) {
    if (!$valor) {
        return '—';
    }
    $tempo = strtotime($valor);
    return $tempo ? date($comHora ? 'd/m/Y H:i' : 'd/m/Y', $tempo) : e($valor);
}

function numero($valor) {
    return $valor === null ? '—' : number_format((float)$valor, 1, ',', '');
}

try {
    $pdo = conectarBanco();
} catch (PDOException $ex) {
    http_response_code(500);
    exit('Falha na conexão com o banco de dados.');
}

// RN06 / RNF04: só gestor autenticado
$usuario = usuarioLogado($pdo);
if (!$usuario || $usuario['tipo_perfil'] !== 'gestor') {
    http_response_code(401);
    exit('Acesso restrito aos gestores. Faça login no painel e tente novamente.');
}

try {
    processarEncerramentos($pdo);
} catch (PDOException $ex) {
    error_log('Erro ao processar encerramentos: ' . $ex->getMessage());
}

$formularioId = resolverFormularioId($pdo, $_GET['formulario_id'] ?? null);
if (!$formularioId) {
    exit('Nenhum formulário cadastrado ainda.');
}

$stmt = $pdo->prepare("SELECT status FROM formularios WHERE id = ?");
$stmt->execute([$formularioId]);
$status = $stmt->fetchColumn();
if ($status === false) {
    http_response_code(404);
    exit('Formulário não encontrado.');
}

// o relatório consolidado fica guardado criptografado (RNF05)
$relatorio = lerRelatorio($pdo, $formularioId);

$consolidado = false;
if ($status === 'encerrado' && $relatorio) {
    $dados = $relatorio['dados'];
    $consolidado = true;
}
if (!$consolidado) {
    $dados = montarDadosConsolidados($pdo, $formularioId);
}
$dados += ['categorias' => []];

// resultado de uma pergunta em texto, conforme o tipo
function resultadoPergunta($p) {
    $tipo = $p['tipo'] ?? 'nota';
    if ($tipo === 'nota') {
        return 'Média ' . numero($p['media']) . ' · mediana ' . numero($p['mediana']) . ' · desvio ' . numero($p['desvio_padrao']);
    }
    if (empty($p['opcoes'])) {
        return '—';
    }
    $partes = [];
    foreach ($p['opcoes'] as $o) {
        $partes[] = e($o['texto']) . ': ' . (int)$o['percentual'] . '% (' . (int)$o['total'] . ')';
    }
    return implode(' · ', $partes);
}

$formulario = $dados['formulario'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Relatório — <?= e($formulario['titulo']) ?> · Climatize</title>
<link rel="icon" href="img/favicon.svg" type="image/svg+xml">
<link rel="icon" href="img/favicon-32.png" type="image/png" sizes="32x32">
<style>
    * { box-sizing: border-box; }
    body { font-family: Arial, Helvetica, sans-serif; color: #111827; margin: 0; background: #f7f9fc; }
    .folha { max-width: 900px; margin: 30px auto; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,.06); }
    h1 { font-size: 26px; margin: 0 0 6px; }
    h2 { font-size: 19px; margin: 32px 0 12px; }
    .sub { color: #64748b; margin: 0 0 4px; }
    .selo { display: inline-block; margin-top: 12px; padding: 8px 12px; border-radius: 8px; font-size: 14px; }
    .selo.consolidado { background: #e3f6ec; color: #15945d; }
    .selo.parcial { background: #fdf3d9; color: #7a5b00; }
    .resumo { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-top: 25px; }
    .caixa { border: 1px solid #e4e8ee; border-radius: 10px; padding: 15px; }
    .caixa span { color: #64748b; font-size: 14px; }
    .caixa b { display: block; font-size: 28px; margin-top: 6px; }
    .categoria { display: inline-block; font-size: 11px; color: #075fd3; background: #e9f0ff; border-radius: 10px; padding: 1px 8px; margin-top: 3px; }
    .tabela-container { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; font-size: 14px; }
    th, td { border-bottom: 1px solid #e4e8ee; padding: 10px 8px; text-align: left; }
    th { background: #f7f9fc; }
    td.num, th.num { text-align: center; white-space: nowrap; }
    .comentario { border-left: 3px solid #075fd3; padding: 8px 14px; margin-bottom: 12px; background: #f7f9fc; }
    .comentario small { color: #64748b; display: block; margin-bottom: 4px; }
    .aviso { background: #fdf3d9; color: #7a5b00; border-radius: 10px; padding: 15px; margin-top: 20px; }
    .marca { display: block; height: 40px; width: auto; margin-bottom: 22px; }
    .rodape { margin-top: 35px; color: #64748b; font-size: 12px; text-align: center; }
    .botao-imprimir { display: block; margin: 20px auto 0; padding: 12px 22px; background: #075fd3; color: white; border: 0; border-radius: 8px; font-size: 16px; cursor: pointer; }
    @media (max-width: 650px) {
        .folha { margin: 0; padding: 22px; border-radius: 0; }
        .resumo { grid-template-columns: 1fr; }
    }
    @media print {
        body { background: white; }
        .folha { box-shadow: none; margin: 0; max-width: none; padding: 0; }
        .botao-imprimir { display: none; }
        .comentario, tr { break-inside: avoid; }
    }
</style>
</head>
<body>
<div class="folha">
    <img class="marca" src="img/logo-climatize.svg" alt="Climatize" width="172" height="40">
    <h1>Relatório de Clima Organizacional</h1>
    <p class="sub"><b><?= e($formulario['titulo']) ?></b></p>
    <p class="sub">Período: <?= dataBr($formulario['data_abertura'], true) ?> até <?= dataBr($formulario['data_fechamento'], true) ?></p>

    <?php if ($consolidado): ?>
        <div class="selo consolidado">✓ Relatório consolidado gerado automaticamente no encerramento, em <?= dataBr($relatorio['data_geracao'], true) ?></div>
    <?php else: ?>
        <div class="selo parcial">Relatório parcial: pesquisa ainda não encerrada (gerado em <?= date('d/m/Y H:i') ?>)</div>
    <?php endif; ?>

    <div class="resumo">
        <div class="caixa"><span>Total de respostas</span><b><?= (int)$dados['total_respostas'] ?></b></div>
        <div class="caixa"><span>Média geral (0 a 10)</span><b><?= numero($dados['media_geral']) ?></b></div>
        <div class="caixa"><span>Perguntas</span><b><?= count($dados['por_pergunta']) ?></b></div>
    </div>

    <?php if ($dados['dados_ocultos']): ?>
        <div class="aviso">
            🔒 Esta pesquisa tem <?= (int)$dados['total_respostas'] ?> resposta(s). Para proteger o anonimato dos participantes,
            as médias e os comentários só aparecem a partir de <?= (int)$dados['minimo_anonimato'] ?> respostas.
        </div>
    <?php elseif ((int)$dados['total_respostas'] === 0): ?>
        <div class="aviso">Ainda não há respostas para esta pesquisa.</div>
    <?php else: ?>
        <?php if (count($dados['categorias']) > 0): ?>
            <h2>Média por categoria</h2>
            <div class="tabela-container">
                <table>
                    <tr><th>Categoria</th><th class="num">Perguntas</th><th class="num">Média (0 a 10)</th></tr>
                    <?php foreach ($dados['categorias'] as $c): ?>
                        <tr>
                            <td><?= e($c['categoria']) ?></td>
                            <td class="num"><?= (int)$c['perguntas'] ?></td>
                            <td class="num"><?= numero($c['media']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endif; ?>

        <h2>Resultados por pergunta</h2>
        <div class="tabela-container">
            <table>
                <tr>
                    <th>#</th>
                    <th>Pergunta</th>
                    <th class="num">Respostas</th>
                    <th>Resultado</th>
                </tr>
                <?php foreach ($dados['por_pergunta'] as $i => $p): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= e($p['texto']) ?><?php if (!empty($p['categoria'])): ?><br><span class="categoria"><?= e($p['categoria']) ?></span><?php endif; ?></td>
                        <td class="num"><?= (int)$p['total'] ?></td>
                        <td><?= resultadoPergunta($p) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <h2>Comentários e sugestões (<?= count($dados['comentarios']) ?>)</h2>
        <?php if (count($dados['comentarios']) === 0): ?>
            <p class="sub">Nenhum comentário registrado.</p>
        <?php endif; ?>
        <?php foreach ($dados['comentarios'] as $c): ?>
            <div class="comentario">
                <small><?= dataBr($c['data_envio']) ?></small>
                <?= nl2br(e($c['comentario'])) ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <p class="rodape">Todas as respostas são anônimas. Nenhuma informação deste relatório identifica participantes.</p>
    <button class="botao-imprimir" onclick="window.print()">Imprimir / Salvar como PDF</button>
</div>
</body>
</html>