<?php
// ============================================================
// Tarefas agendadas. O container "agendador" roda este arquivo a cada 5 minutos:
//   - encerra as pesquisas cujo prazo acabou e gera o relatório consolidado (RN01 / RN08)
//   - envia os avisos por e-mail: abertura, encerramento e o lembrete automático
//     dos últimos dias para quem ainda não respondeu (RF08)
// O site também faz isso a cada acesso; com o agendador, acontece mesmo sem ninguém usando.
// Cada aviso sai uma vez só (tabela notificacoes), então rodar de novo não duplica nada.
//
// Rodar na mão: docker compose exec php php /var/www/database/tarefas.php
// ============================================================

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../public/config.php';
require_once __DIR__ . '/../public/funcoes.php';

try {
    $pdo = conectarBanco();
} catch (PDOException $e) {
    // banco ainda subindo: tenta de novo na próxima rodada
    fwrite(STDERR, date('d/m/Y H:i') . " agendador: banco indisponível\n");
    exit(1);
}

try {
    $ultimoId = (int)$pdo->query("SELECT COALESCE(MAX(id), 0) FROM notificacoes")->fetchColumn();
    processarEncerramentos($pdo);
    processarNotificacoes($pdo);
    // registra no log do container (docker compose logs agendador) só o que foi enviado agora
    $stmt = $pdo->prepare("SELECT tipo, destinatarios, falhas FROM notificacoes WHERE id > ? ORDER BY id");
    $stmt->execute([$ultimoId]);
    foreach ($stmt->fetchAll() as $n) {
        echo date('d/m/Y H:i') . " agendador: aviso '{$n['tipo']}' enviado para {$n['destinatarios']} pessoa(s)"
            . ($n['falhas'] ? ", {$n['falhas']} falha(s)" : '') . "\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, date('d/m/Y H:i') . ' agendador: ' . $e->getMessage() . "\n");
    exit(1);
}
