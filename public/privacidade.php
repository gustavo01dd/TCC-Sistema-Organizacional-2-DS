<?php require_once __DIR__ . '/funcoes.php'; ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0b56c9">
    <title>Política de Privacidade — Pesquisa de Clima Organizacional</title>
    <script>
        (function () {
            try {
                var tema = localStorage.getItem("tema") || (window.matchMedia("(prefers-color-scheme: dark)").matches ? "escuro" : "claro");
                document.documentElement.setAttribute("data-tema", tema);
            } catch (e) {
                document.documentElement.setAttribute("data-tema", "claro");
            }
        })();
    </script>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="pagina">
    <header class="topo">
        <span>Pesquisa de Clima Organizacional</span>
    </header>

    <main class="conteudo">
        <a class="voltar" href="index.php">‹ Voltar para a pesquisa</a>

        <article class="politica">
            <h1>Política de Privacidade</h1>
            <p class="politica-aviso">
                Modelo de política para a Pesquisa de Clima Organizacional. Antes de usar o sistema,
                a diretoria da empresa deve revisar este texto e preencher os campos entre colchetes.
            </p>
            <p class="politica-meta">Última atualização: [dd/mm/aaaa] · Versão do termo de consentimento: <?= VERSAO_TERMO ?></p>

            <h2>1. Quem é o responsável pelos dados</h2>
            <p>A <b>[razão social da empresa]</b>, inscrita no CNPJ [número], é a controladora dos dados tratados neste sistema,
                nos termos da Lei Geral de Proteção de Dados Pessoais (LGPD, Lei nº 13.709/2018).
                Encarregado pelo tratamento de dados: [nome], e-mail [email do encarregado].</p>

            <h2>2. Quais dados são tratados</h2>
            <ul>
                <li><b>Cadastro</b>, feito pela gestão: nome, e-mail, cargo, data de admissão, perfil (funcionário ou gestor) e senha. A senha é guardada apenas como <i>hash</i>, ou seja, ninguém consegue lê-la.</li>
                <li><b>Participação</b>: somente a data em que você respondeu cada pesquisa, para impedir respostas duplicadas e respeitar o intervalo de 21 dias entre participações.</li>
                <li><b>Respostas</b>: notas, alternativas escolhidas e comentário. São gravados <b>sem nenhuma ligação</b> com seu nome, e-mail ou cadastro.</li>
                <li><b>Aceite do termo</b>: a data em que você aceitou este termo.</li>
                <li><b>Acessos dos gestores</b>: data e hora de entradas, saídas, tentativas de login recusadas e trocas de senha no painel de gestão.</li>
                <li><b>Segurança do login</b>: quando alguém erra a senha ou pede "Esqueci minha senha", o sistema guarda por até 1 dia um registro cifrado (<i>hash</i>) do email e do endereço IP, só para bloquear quem tenta adivinhar senhas.</li>
                <li><b>Redefinição de senha</b>: o código do link enviado por email é guardado apenas como <i>hash</i>, vale por <?= MINUTOS_VALIDADE_LINK_SENHA ?> minutos e só pode ser usado uma vez.</li>
            </ul>

            <h2>3. Para que os dados são usados</h2>
            <ul>
                <li>Medir o clima organizacional e orientar ações de melhoria.</li>
                <li>Controlar quem já participou de cada pesquisa.</li>
                <li>Enviar e-mails sobre a abertura, o encerramento e lembretes das pesquisas.</li>
                <li>Enviar o link de "Esqueci minha senha" e avisar quando a sua senha for alterada.</li>
                <li>Manter a segurança do sistema.</li>
            </ul>

            <h2>4. Como o anonimato é garantido</h2>
            <ul>
                <li>O registro de participação e o conteúdo das respostas ficam em tabelas separadas, sem nenhuma coluna que ligue uma resposta a uma pessoa.</li>
                <li>Médias, gráficos, comentários e exportações só aparecem a partir de <?= MINIMO_RESPOSTAS_ANONIMATO ?> respostas.</li>
                <li>Comentários são mostrados apenas com a data, sem horário, e em ordem aleatória.</li>
                <li>Comentários e relatórios são guardados criptografados.</li>
                <li>A gestão vê apenas <b>se</b> você já respondeu a pesquisa atual, nunca <b>o que</b> você respondeu.</li>
            </ul>

            <h2>5. Compartilhamento</h2>
            <p>Os dados não são compartilhados com terceiros. Resultados agregados, que não identificam ninguém,
                podem ser apresentados aos colaboradores da empresa.</p>

            <h2>6. Por quanto tempo os dados são guardados</h2>
            <p>Os dados de cadastro são mantidos enquanto houver vínculo com a empresa [ajuste conforme a política da empresa].
                As respostas anônimas podem ser mantidas para comparação entre ciclos de pesquisa, pois não identificam ninguém (LGPD, art. 12).
                As cópias de segurança diárias são mantidas por 7 dias.</p>

            <h2>7. Seus direitos</h2>
            <p>Conforme o art. 18 da LGPD, você pode solicitar: confirmação e acesso aos seus dados; correção de dados incompletos ou
                desatualizados; eliminação dos seus dados de cadastro; informação sobre compartilhamento; e revogação do consentimento.
                Como as respostas são anônimas, não é possível localizar, alterar ou excluir as respostas de uma pessoa específica.</p>

            <h2>8. Segurança</h2>
            <p>Senhas protegidas com <i>hash</i> e conhecidas só por você (a senha do cadastro é provisória e pode ser trocada a qualquer momento),
                bloqueio temporário depois de várias senhas erradas, aviso por email sempre que a senha muda, comentários e relatórios criptografados,
                acesso aos resultados restrito a gestores autenticados, registro dos acessos dos gestores e cópia de segurança diária.</p>

            <h2>9. Contato</h2>
            <p>Dúvidas ou solicitações: [email do encarregado].</p>
        </article>
    </main>
</div>
</body>
</html>