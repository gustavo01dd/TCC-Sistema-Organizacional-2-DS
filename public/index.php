<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0b56c9">
    <title>Climatize · Pesquisa de Clima Organizacional</title>
    <script>
        // aplica o tema antes de desenhar a página (evita "piscar" claro no modo escuro)
        (function () {
            try {
                var tema = localStorage.getItem("tema");
                if (!tema) {
                    tema = window.matchMedia("(prefers-color-scheme: dark)").matches ? "escuro" : "claro";
                }
                document.documentElement.setAttribute("data-tema", tema);
            } catch (e) {
                document.documentElement.setAttribute("data-tema", "claro");
            }
        })();
    </script>
    <link rel="icon" href="img/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="img/favicon-32.png" type="image/png" sizes="32x32">
    <link rel="apple-touch-icon" href="img/apple-touch-icon.png">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<!-- avisos lidos pelos leitores de tela -->
<div id="avisoLeitor" class="sr-only" aria-live="polite" aria-atomic="true"></div>

<!-- botão de tema (claro / escuro) das telas públicas; no painel ele fica no menu -->
<button id="botaoTema" class="botao-tema" onclick="alternarTema()" title="Mudar para o modo escuro" aria-label="Mudar para o modo escuro">☾</button>

<!-- ===================== TELA INICIAL DA PESQUISA (funcionário, depois do login) ===================== -->
<div id="telaPesquisa" class="pagina escondido">
    <header class="topo">
        <img class="topo-logo so-claro" src="img/logo-climatize-branco.svg" alt="Climatize" width="146" height="34">
        <img class="topo-logo so-escuro" src="img/logo-climatize-escuro.svg" alt="Climatize" width="146" height="34">
        <span class="topo-texto">Pesquisa de Clima Organizacional</span>
    </header>

    <main class="conteudo pesquisa-inicio">
        <div class="acoes-questionario">
            <button class="voltar" onclick="sair()">‹ Sair</button>
            <button class="link-senha" onclick="mostrarAlterarSenha()"><span aria-hidden="true">🔑 </span>Alterar senha</button>
        </div>

        <div class="icone-principal" aria-hidden="true"><img class="so-claro" src="img/logo-simbolo-branco.svg" alt="" width="45" height="56"><img class="so-escuro" src="img/logo-simbolo-escuro.svg" alt="" width="45" height="56"></div>
        <h1 tabindex="-1">Pesquisa de Clima Organizacional</h1>
        <p class="subtitulo"><span id="saudacaoInicio"></span>Sua opinião é fundamental para melhorarmos juntos</p>

        <div id="avisoPesquisa" class="aviso-pesquisa escondido" role="status"></div>

        <div class="info">
            <div class="info-icone azul" aria-hidden="true">♙</div>
            <div>
                <h2 class="info-titulo">Respostas Anônimas</h2>
                <p>Seu login serve só para o sistema saber que você já participou e evitar respostas duplicadas. Suas respostas nunca ficam ligadas ao seu nome: ninguém, nem a gestão, consegue ver o que você respondeu.</p>
            </div>
        </div>

        <div class="info">
            <div class="info-icone cinza" aria-hidden="true">◉</div>
            <div>
                <h2 class="info-titulo">Confidencial</h2>
                <p>Os resultados são analisados apenas de forma agregada e só aparecem para a gestão depois que um número mínimo de pessoas responde. Os comentários ficam guardados criptografados.</p>
            </div>
        </div>

        <div class="info">
            <div class="info-icone verde" aria-hidden="true">✓</div>
            <div>
                <h2 class="info-titulo">Rápida e Simples</h2>
                <p>Poucas perguntas, leva só alguns minutos.</p>
            </div>
        </div>

        <div class="como">
            <h2>Como funciona?</h2>
            <ol>
                <li>Clique em <b>Iniciar Pesquisa</b></li>
                <li>No primeiro acesso, leia e aceite o termo de consentimento</li>
                <li>Responda as perguntas: notas de 0 a 10, sim ou não e alternativas</li>
                <li>Se quiser, deixe um comentário ou sugestão no final e envie</li>
            </ol>
        </div>

        <button class="botao azul-btn" onclick="comecarPesquisa()">Iniciar Pesquisa</button>

        <p class="rodape-frase">Suas respostas nos ajudam a criar um <b>ambiente de trabalho melhor para todos</b></p>

        <p class="rodape-links"><a href="privacidade.php" target="_blank" rel="noopener">Política de privacidade</a></p>
    </main>
</div>

<!-- ===================== LOGIN (primeira tela: funcionários e gestores) ===================== -->
<div id="telaLogin" class="pagina login-gestor">
    <main class="login-card">
        <img class="login-logo so-claro" src="img/logo-climatize.svg" alt="Climatize" width="189" height="44">
        <img class="login-logo so-escuro" src="img/logo-climatize-escuro.svg" alt="Climatize" width="189" height="44">

        <h1 tabindex="-1">Entrar</h1>
        <p class="subtitulo-login">Funcionários vão para a pesquisa e gestores para o painel de gestão</p>

        <div id="avisoLogin" class="aviso-sucesso escondido" role="status"></div>

        <label for="emailLogin">Email</label>
        <input id="emailLogin" type="email" placeholder="seu@email.com" autocomplete="username">

        <label for="senhaLogin">Senha</label>
        <input id="senhaLogin" type="password" placeholder="••••••" autocomplete="current-password" onkeydown="if (event.key === 'Enter') entrar()">

        <button id="botaoEntrar" class="botao azul-btn" onclick="entrar()">Entrar</button>
        <p id="erroLogin" class="erro" role="alert"></p>
        <button class="link-esqueci" onclick="mostrarEsqueciSenha()">Esqueci minha senha</button>

        <div class="credenciais">
            <b>Credenciais de teste</b> (senha <b>123456</b> para todos)
            <br>Gestor: gestorclimatize@gmail.com
            <br>Funcionários: funcionario01.empresa@gmail.com, funcionario02.empresa@gmail.com, funcionario3@empresa.com
        </div>

        <p class="rodape-links"><a href="privacidade.php" target="_blank" rel="noopener">Política de privacidade</a></p>
    </main>
</div>

<!-- ===================== ESQUECI MINHA SENHA ===================== -->
<div id="telaEsqueci" class="pagina login-gestor escondido">
    <main class="login-card">
        <div class="login-icone" aria-hidden="true">✉</div>
        <h1 tabindex="-1">Esqueci minha senha</h1>
        <p class="subtitulo-login">Informe o email cadastrado. Vamos enviar um link para você criar uma nova senha.</p>

        <div id="camposEsqueci">
            <label for="emailEsqueci">Email</label>
            <input id="emailEsqueci" type="email" placeholder="seu@email.com" autocomplete="username" onkeydown="if (event.key === 'Enter') solicitarRedefinicao()">
            <button id="botaoEsqueci" class="botao azul-btn" onclick="solicitarRedefinicao()">Enviar link</button>
        </div>
        <p id="erroEsqueci" class="erro" role="alert"></p>
        <div id="sucessoEsqueci" class="aviso-sucesso escondido" role="status"></div>

        <button class="voltar-login" onclick="mostrarLogin()">← Voltar para o login</button>
    </main>
</div>

<!-- ===================== NOVA SENHA (link recebido por email) ===================== -->
<div id="telaRedefinir" class="pagina login-gestor escondido">
    <main class="login-card">
        <div class="login-icone" aria-hidden="true">🔑</div>
        <h1 tabindex="-1">Criar nova senha</h1>
        <p id="textoRedefinir" class="subtitulo-login">Escolha uma nova senha para a sua conta.</p>

        <div id="camposRedefinir">
            <label for="novaSenhaRedefinir">Nova senha</label>
            <input id="novaSenhaRedefinir" type="password" placeholder="Mínimo de 6 caracteres" autocomplete="new-password">

            <label for="confirmaSenhaRedefinir">Repita a nova senha</label>
            <input id="confirmaSenhaRedefinir" type="password" autocomplete="new-password" onkeydown="if (event.key === 'Enter') redefinirSenha()">

            <label class="mostrar-senha">
                <input type="checkbox" onchange="mostrarSenhas(this, 'telaRedefinir')">
                <span>Mostrar senhas</span>
            </label>

            <button id="botaoRedefinir" class="botao azul-btn" onclick="redefinirSenha()">Salvar nova senha</button>
        </div>
        <p id="erroRedefinir" class="erro" role="alert"></p>
        <button id="botaoNovoLink" class="botao-secundario escondido" onclick="mostrarEsqueciSenha()">Pedir um novo link</button>

        <button class="voltar-login" onclick="mostrarLogin()">← Ir para o login</button>
    </main>
</div>

<!-- ===================== ALTERAR SENHA (usuário logado) ===================== -->
<div id="telaSenha" class="pagina login-gestor escondido">
    <main class="login-card">
        <div class="login-icone" aria-hidden="true">🔑</div>
        <h1 tabindex="-1">Alterar senha</h1>
        <p class="subtitulo-login">Depois da troca, você recebe um email de confirmação.</p>

        <div id="camposSenha">
            <label for="senhaAtual">Senha atual</label>
            <input id="senhaAtual" type="password" autocomplete="current-password">

            <label for="senhaNova">Nova senha</label>
            <input id="senhaNova" type="password" placeholder="Mínimo de 6 caracteres" autocomplete="new-password">

            <label for="senhaNovaConfirma">Repita a nova senha</label>
            <input id="senhaNovaConfirma" type="password" autocomplete="new-password" onkeydown="if (event.key === 'Enter') alterarSenha()">

            <label class="mostrar-senha">
                <input type="checkbox" onchange="mostrarSenhas(this, 'telaSenha')">
                <span>Mostrar senhas</span>
            </label>

            <button id="botaoAlterarSenha" class="botao azul-btn" onclick="alterarSenha()">Salvar nova senha</button>
        </div>
        <p id="erroSenha" class="erro" role="alert"></p>
        <div id="sucessoSenha" class="aviso-sucesso escondido" role="status"></div>

        <button id="botaoVoltarSenha" class="voltar-login" onclick="voltarDaSenha()">← Voltar</button>
    </main>
</div>

<!-- ===================== PRIMEIRO ACESSO: criar a própria senha ===================== -->
<div id="telaSenhaInicial" class="pagina login-gestor escondido">
    <main class="login-card">
        <div class="login-icone" aria-hidden="true">🔑</div>
        <h1 tabindex="-1">Crie a sua senha</h1>
        <p id="textoSenhaInicial" class="subtitulo-login">Por segurança, troque a senha provisória cadastrada pela gestão por uma senha só sua. A gestão não fica sabendo a senha nova.</p>

        <label for="senhaInicialNova">Nova senha</label>
        <input id="senhaInicialNova" type="password" placeholder="Mínimo de 6 caracteres" autocomplete="new-password">

        <label for="senhaInicialConfirma">Repita a nova senha</label>
        <input id="senhaInicialConfirma" type="password" autocomplete="new-password" onkeydown="if (event.key === 'Enter') definirSenhaInicial()">

        <label class="mostrar-senha">
            <input type="checkbox" onchange="mostrarSenhas(this, 'telaSenhaInicial')">
            <span>Mostrar senhas</span>
        </label>

        <button id="botaoSenhaInicial" class="botao azul-btn" onclick="definirSenhaInicial()">Salvar e continuar</button>
        <p id="erroSenhaInicial" class="erro" role="alert"></p>

        <button class="voltar-login" onclick="sair()">← Sair</button>
    </main>
</div>

<!-- ===================== TERMO DE CONSENTIMENTO (RNF09) ===================== -->
<div id="telaTermo" class="pagina login-gestor escondido">
    <main class="login-card termo-card">
        <div class="login-icone" aria-hidden="true">✓</div>
        <h1 tabindex="-1">Antes de começar</h1>
        <p class="subtitulo-login">Para participar das pesquisas de clima, leia e aceite o termo de consentimento.</p>

        <div class="termo-texto" tabindex="0" aria-label="Resumo do termo de consentimento">
            <ul>
                <li>Você entra com email e senha só para o sistema saber que você já participou de cada pesquisa.</li>
                <li>Suas respostas e comentários são gravados <b>sem ligação com seu nome ou email</b>. Nem a gestão consegue ver o que você respondeu.</li>
                <li>A gestão vê apenas <b>se</b> você já respondeu a pesquisa atual e os resultados gerais, que só aparecem a partir de 3 respostas.</li>
                <li>Os comentários ficam guardados criptografados.</li>
                <li>Você pode receber emails avisando sobre a abertura, o encerramento e lembretes das pesquisas.</li>
                <li>Seus dados são tratados conforme a Lei Geral de Proteção de Dados (LGPD, Lei nº 13.709/2018).</li>
            </ul>
            <a href="privacidade.php" target="_blank" rel="noopener">Ler a política de privacidade completa</a>
        </div>

        <label class="checkbox-termo">
            <input id="aceiteTermo" type="checkbox" onchange="document.getElementById('botaoAceitarTermo').disabled = !this.checked">
            <span>Li e concordo com o termo de consentimento e com a política de privacidade.</span>
        </label>

        <button id="botaoAceitarTermo" class="botao azul-btn" onclick="aceitarTermo()" disabled>Concordo e continuar</button>
        <p id="erroTermo" class="erro" role="alert"></p>
        <button class="voltar-login" onclick="sair()">Não concordo, sair</button>
    </main>
</div>

<!-- ===================== QUESTIONÁRIO (funcionário) ===================== -->
<div id="telaQuestionario" class="pagina escondido">
    <header class="topo">
        <img class="topo-logo so-claro" src="img/logo-climatize-branco.svg" alt="Climatize" width="146" height="34">
        <img class="topo-logo so-escuro" src="img/logo-climatize-escuro.svg" alt="Climatize" width="146" height="34">
        <span class="topo-texto">Pesquisa de Clima Organizacional</span>
    </header>

    <main class="conteudo">
        <div class="acoes-questionario">
            <button class="voltar" onclick="sair()">‹ Sair</button>
            <button class="link-senha" onclick="mostrarAlterarSenha()"><span aria-hidden="true">🔑 </span>Alterar senha</button>
        </div>

        <h1 id="saudacaoFuncionario" class="saudacao" tabindex="-1"></h1>
        <div id="formularioTitulo" class="formulario-titulo-ativo"></div>

        <div class="progresso-topo">
            <span id="rotuloProgresso">Progresso</span>
            <span id="numeroPergunta"></span>
        </div>
        <div class="barra" role="progressbar" aria-labelledby="rotuloProgresso" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="barraProgressoContainer">
            <div id="barraProgresso"></div>
        </div>

        <p id="avisoRascunho" class="aviso-rascunho escondido">
            Suas respostas ficam salvas neste navegador até o envio, caso a página seja fechada.
            Ao clicar em Sair, o rascunho é apagado.
        </p>

        <div id="perguntas"></div>

        <div id="blocoEnvio" class="escondido">
            <div class="comentario-box">
                <label for="comentario">Comentário ou sugestão final (opcional)</label>
                <textarea id="comentario" maxlength="2000" placeholder="Escreva aqui sua sugestão, elogio ou crítica..." oninput="salvarRascunho()"></textarea>
            </div>

            <button id="botaoEnviar" class="botao azul-btn" onclick="enviarPesquisa()">Enviar respostas</button>
            <p class="aviso-envio">Depois de enviadas, as respostas não podem ser alteradas.</p>
        </div>
    </main>
</div>

<!-- ===================== AGRADECIMENTO ===================== -->
<div id="telaObrigado" class="pagina login-gestor escondido">
    <main class="login-card obrigado-card">
        <div class="obrigado-icone" aria-hidden="true">✓</div>
        <h1 tabindex="-1">Obrigado por participar!</h1>
        <p id="textoObrigado" class="subtitulo-login"></p>
        <div class="obrigado-info">
            <p>Suas respostas foram gravadas de forma <b>anônima</b>: o sistema registrou apenas que você participou, nunca o que você respondeu.</p>
            <p>Os resultados são analisados pela gestão de forma agregada e ajudam a construir um ambiente de trabalho melhor.</p>
        </div>
        <p class="obrigado-saida">Por segurança, você já saiu do sistema.</p>
        <button class="botao azul-btn" onclick="mostrarLogin()">Voltar para o login</button>
    </main>
</div>

<!-- ===================== PAINEL DO GESTOR ===================== -->
<div id="telaDashboard" class="dashboard escondido">
    <nav class="menu" aria-label="Menu do painel">
        <div class="logo"><img class="so-claro" src="img/logo-climatize-branco.svg" alt="Climatize" width="163" height="38"><img class="so-escuro" src="img/logo-climatize-escuro.svg" alt="Climatize" width="163" height="38"><span>Pesquisa de Clima Organizacional</span></div>
        <div class="gestor-label">Painel do Gestor</div>
        <div id="nomeGestor" class="nome-gestor"></div>

        <button class="menu-item ativo" data-view="dashboard" onclick="mostrarView('dashboard', this)" aria-current="page"><span aria-hidden="true">▣ &nbsp;</span>Dashboard</button>
        <button class="menu-item" data-view="resultados" onclick="mostrarView('resultados', this)"><span aria-hidden="true">▥ &nbsp;</span>Resultados</button>
        <button class="menu-item" data-view="comparar" onclick="mostrarView('comparar', this)"><span aria-hidden="true">⇄ &nbsp;</span>Comparar</button>
        <button class="menu-item" data-view="formularios" onclick="mostrarView('formularios', this)"><span aria-hidden="true">▤ &nbsp;</span>Formulários</button>
        <button class="menu-item" data-view="comentarios" onclick="mostrarView('comentarios', this)"><span aria-hidden="true">▱ &nbsp;</span>Comentários</button>
        <button class="menu-item" data-view="funcionarios" onclick="mostrarView('funcionarios', this)"><span aria-hidden="true">◈ &nbsp;</span>Funcionários</button>
        <button class="menu-item" data-view="relatorios" onclick="mostrarView('relatorios', this)"><span aria-hidden="true">▦ &nbsp;</span>Relatórios</button>

        <button class="menu-tema menu-senha" onclick="mostrarAlterarSenha()"><span aria-hidden="true">🔑 &nbsp;</span>Alterar senha</button>
        <button id="botaoTemaMenu" class="menu-tema" onclick="alternarTema()">☾ &nbsp; Modo escuro</button>
        <button class="menu-sair" onclick="sair()"><span aria-hidden="true">⎋ &nbsp;</span>Sair</button>
    </nav>

    <main class="dashboard-conteudo">

        <!-- ========== VIEW: DASHBOARD ========== -->
        <section id="viewDashboard" aria-labelledby="dashboardTitulo">
            <div class="dashboard-topo">
                <div>
                    <h1 id="dashboardTitulo" tabindex="-1">Dashboard</h1>
                    <p>Visão geral dos resultados da pesquisa de clima organizacional</p>
                </div>
            </div>

            <div id="avisoAnonimatoDashboard" class="aviso-anonimato escondido" role="status"></div>

            <div class="cards">
                <div class="card card-azul">
                    <span>Média Geral</span>
                    <strong id="statMediaGeral">—</strong>
                    <p>de 10.0 pontos</p>
                </div>
                <div class="card">
                    <span>Total de Respostas</span>
                    <strong id="statTotalRespostas">—</strong>
                    <p id="statRespostasDesde">&nbsp;</p>
                </div>
                <div class="card">
                    <span>Taxa de Participação</span>
                    <strong id="statTaxaParticipacao">—</strong>
                    <p id="statTaxaDetalhe">&nbsp;</p>
                </div>
                <div class="card">
                    <span>Última Atualização</span>
                    <strong class="data" id="statUltimaAtualizacao">—</strong>
                    <p id="statUltimaAtualizacaoData">&nbsp;</p>
                </div>
            </div>

            <div class="duas-colunas">
                <div class="painel">
                    <h2 class="titulo-critico"><span aria-hidden="true">⚠ </span>Pontos Críticos</h2>
                    <div id="pontosCriticos"></div>
                </div>
                <div class="painel">
                    <h2 class="titulo-forte"><span aria-hidden="true">↗ </span>Pontos Fortes</h2>
                    <div id="pontosFortes"></div>
                </div>
            </div>

            <div class="painel painel-categorias">
                <h2>Média por Categoria</h2>
                <p>Perguntas de nota agrupadas por tema (0 a 10)</p>
                <div id="graficoCategorias"></div>
            </div>

            <div class="graficos">
                <div class="painel">
                    <h2>Evolução Temporal</h2>
                    <p>Respostas recebidas por dia</p>
                    <div id="graficoEvolucao" class="grafico-linha" role="img" aria-label="Gráfico de respostas recebidas por dia"></div>
                    <div id="legendaEvolucao" class="meses" aria-hidden="true"></div>
                    <p id="textoTendencia" class="tendencia"></p>
                </div>
                <div class="painel">
                    <h2>Distribuição de Satisfação</h2>
                    <p>Classificação geral das respostas</p>
                    <div class="pizza-area">
                        <div id="graficoPizza" class="pizza" role="img" aria-label="Distribuição de satisfação"></div>
                        <div class="legenda">
                            <p><span class="verde-bola" aria-hidden="true"></span> Satisfeito (7-10)</p>
                            <p><span class="amarelo-bola" aria-hidden="true"></span> Neutro (4-6)</p>
                            <p><span class="vermelho-bola" aria-hidden="true"></span> Insatisfeito (0-3)</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ========== VIEW: RESULTADOS ========== -->
        <section id="viewResultados" class="escondido" aria-labelledby="tituloResultados">
            <div class="dashboard-topo">
                <div>
                    <h1 id="tituloResultados" tabindex="-1">Resultados</h1>
                    <p>Análise detalhada de cada pergunta da pesquisa</p>
                    <p id="resultadosFormulario" class="subtitulo-resultados"></p>
                </div>
                <div class="filtros-resultados">
                    <label class="sr-only" for="filtroCategoria">Categoria</label>
                    <select id="filtroCategoria" class="filtro-periodo" onchange="renderizarResultadosFiltrados()">
                        <option value="">Todas as categorias</option>
                    </select>
                    <label class="sr-only" for="filtroPeriodo">Período</label>
                    <select id="filtroPeriodo" class="filtro-periodo" onchange="carregarResultados()">
                        <option value="7">Últimos 7 dias</option>
                        <option value="30">Últimos 30 dias</option>
                        <option value="completo" selected>Período completo</option>
                    </select>
                </div>
            </div>

            <div id="listaResultados"></div>
        </section>

        <!-- ========== VIEW: COMPARAR ========== -->
        <section id="viewComparar" class="escondido" aria-labelledby="tituloComparar">
            <div class="dashboard-topo">
                <div>
                    <h1 id="tituloComparar" tabindex="-1">Comparar pesquisas</h1>
                    <p>Veja como as médias mudaram de um ciclo de pesquisa para outro</p>
                </div>
            </div>

            <div class="painel">
                <div class="comparar-seletores">
                    <div>
                        <label for="compararA">Pesquisa principal</label>
                        <select id="compararA"></select>
                    </div>
                    <div>
                        <label for="compararB">Comparar com</label>
                        <select id="compararB"></select>
                    </div>
                    <button class="botao azul-btn" onclick="carregarComparacao()">Comparar</button>
                </div>
                <p class="dica-campo">As perguntas são comparadas pelo texto; por isso, duplicar o formulário do ciclo anterior mantém a comparação completa.</p>
                <p id="erroComparar" class="erro" role="alert"></p>
            </div>

            <div id="resultadoComparacao"></div>
        </section>

        <!-- ========== VIEW: FORMULÁRIOS ========== -->
        <section id="viewFormularios" class="escondido" aria-labelledby="tituloFormularios">
            <div class="dashboard-topo">
                <div>
                    <h1 id="tituloFormularios" tabindex="-1">Formulários</h1>
                    <p>Crie novos formulários e escolha qual fica ativo para os funcionários</p>
                </div>
            </div>

            <div id="painelEditorFormulario" class="painel">
                <h2 id="tituloEditorFormulario">Novo formulário</h2>

                <label for="novoFormTitulo">Título</label>
                <input id="novoFormTitulo" type="text" maxlength="150" placeholder="Ex.: Pesquisa de Clima 2º Semestre">

                <div class="campos-lado">
                    <div>
                        <label for="novoFormEsperados">Nº de respondentes esperados (opcional)</label>
                        <input id="novoFormEsperados" type="number" min="1" placeholder="Vazio = total de funcionários">
                    </div>
                    <div>
                        <label for="novoFormInicio">Início (opcional)</label>
                        <input id="novoFormInicio" type="datetime-local">
                    </div>
                    <div>
                        <label for="novoFormFim">Término (opcional)</label>
                        <input id="novoFormFim" type="datetime-local">
                    </div>
                </div>
                <p class="dica-campo">Sem data de término, a pesquisa fica aberta por 7 dias depois de ativada.</p>

                <h3 class="subtitulo-editor">Perguntas</h3>
                <div id="editorPerguntas"></div>
                <datalist id="listaCategorias"></datalist>
                <button class="botao-pequeno" onclick="adicionarPerguntaEditor()">+ Adicionar pergunta</button>

                <div class="botoes-form">
                    <button id="botaoSalvarFormulario" class="botao azul-btn" onclick="salvarFormulario()">Criar formulário</button>
                    <button id="botaoCancelarEdicaoForm" class="botao-pequeno escondido" onclick="cancelarEdicaoFormulario()">Cancelar edição</button>
                </div>
                <p id="erroNovoForm" class="erro" role="alert"></p>
            </div>

            <div class="painel">
                <h2>Formulários existentes</h2>
                <p id="avisoEmail" class="info-lista"></p>
                <div id="listaFormularios"></div>
            </div>
        </section>

        <!-- ========== VIEW: COMENTÁRIOS ========== -->
        <section id="viewComentarios" class="escondido" aria-labelledby="tituloComentarios">
            <div class="dashboard-topo">
                <div>
                    <h1 id="tituloComentarios" tabindex="-1">Comentários</h1>
                    <p>Sugestões e comentários enviados de forma anônima</p>
                    <p id="comentariosFormulario" class="subtitulo-resultados"></p>
                </div>
            </div>

            <div class="painel">
                <div id="listaComentarios"></div>
            </div>
        </section>

        <!-- ========== VIEW: FUNCIONÁRIOS ========== -->
        <section id="viewFuncionarios" class="escondido" aria-labelledby="tituloFuncionarios">
            <div class="dashboard-topo">
                <div>
                    <h1 id="tituloFuncionarios" tabindex="-1">Funcionários</h1>
                    <p>Cadastre os logins de quem responde a pesquisa (e de outros gestores)</p>
                </div>
            </div>

            <div id="formFuncionario" class="painel">
                <h2 id="tituloFormFunc">Novo cadastro</h2>

                <label for="novoFuncNome">Nome</label>
                <input id="novoFuncNome" type="text" maxlength="100" placeholder="Nome completo">

                <label for="novoFuncEmail">Email (usado no login)</label>
                <input id="novoFuncEmail" type="email" maxlength="100" placeholder="nome@empresa.com">

                <label for="novoFuncSenha">Senha provisória</label>
                <input id="novoFuncSenha" type="password" placeholder="Mínimo de 6 caracteres" autocomplete="new-password">
                <p class="dica-campo">Serve só para o primeiro acesso: ao entrar, a pessoa é obrigada a criar a própria senha, e a gestão não fica sabendo a senha nova.</p>

                <label for="novoFuncCargo">Cargo (opcional)</label>
                <input id="novoFuncCargo" type="text" maxlength="50" placeholder="Ex.: Analista">

                <label for="novoFuncAdmissao">Data de admissão (opcional)</label>
                <input id="novoFuncAdmissao" type="date">

                <label for="novoFuncPerfil">Perfil</label>
                <select id="novoFuncPerfil">
                    <option value="funcionario">Funcionário (responde a pesquisa)</option>
                    <option value="gestor">Gestor (acessa este painel)</option>
                </select>

                <div class="botoes-form">
                    <button id="botaoSalvarFunc" class="botao azul-btn" onclick="salvarFuncionario()">Cadastrar</button>
                    <button id="botaoCancelarEdicaoFunc" class="botao-pequeno escondido" onclick="cancelarEdicaoFuncionario()">Cancelar edição</button>
                </div>
                <p id="erroNovoFunc" class="erro" role="alert"></p>
            </div>

            <div class="painel">
                <h2>Importar planilha</h2>
                <p>Cadastre várias pessoas de uma vez com uma planilha do Excel (.xlsx) ou um arquivo .csv com as colunas nome, email, senha, cargo, data_admissao e perfil.</p>
                <div class="importar-linha">
                    <button class="botao-pequeno" onclick="baixarModeloImportacao()">⤓ Baixar planilha-modelo</button>
                    <label class="sr-only" for="arquivoImportacao">Planilha para importar</label>
                    <input id="arquivoImportacao" type="file" accept=".xlsx,.csv">
                    <button id="botaoImportar" class="botao azul-btn botao-medio" onclick="importarPlanilha()">Importar</button>
                </div>
                <p id="erroImportacao" class="erro" role="alert"></p>
                <div id="resultadoImportacao" aria-live="polite"></div>
            </div>

            <div class="painel">
                <h2>Cadastrados</h2>
                <p id="infoPesquisaAtual" class="info-lista"></p>
                <div id="listaFuncionarios"></div>
            </div>
        </section>

        <!-- ========== VIEW: RELATÓRIOS ========== -->
        <section id="viewRelatorios" class="escondido" aria-labelledby="tituloRelatorios">
            <div class="dashboard-topo">
                <div>
                    <h1 id="tituloRelatorios" tabindex="-1">Relatórios e Exportações</h1>
                    <p>Gere relatórios e exporte dados para análise externa</p>
                </div>
            </div>

            <div class="grade-exportacao">
                <div class="card-exportacao">
                    <div class="icone-exportacao pdf" aria-hidden="true">▤</div>
                    <h2>Relatório Completo (PDF)</h2>
                    <p>Resumo da pesquisa, médias por categoria, resultados de cada pergunta e comentários, pronto para imprimir ou salvar em PDF.</p>
                    <button class="botao-exportar pdf" onclick="exportarPdf()">⤓ Exportar PDF</button>
                </div>

                <div class="card-exportacao">
                    <div class="icone-exportacao excel" aria-hidden="true">▦</div>
                    <h2>Dados Brutos (Excel)</h2>
                    <p>Planilha formatada com filtros e todas as respostas anônimas, mais a aba Resumo com médias e percentuais calculados por fórmulas.</p>
                    <button class="botao-exportar excel" onclick="exportarExcel()">⤓ Exportar Excel</button>
                </div>

                <div class="card-exportacao">
                    <div class="icone-exportacao txt" aria-hidden="true">▱</div>
                    <h2>Comentários (TXT)</h2>
                    <p>Arquivo de texto com todos os comentários e sugestões recebidos.</p>
                    <button class="botao-exportar txt" onclick="exportarComentariosArquivo()">⤓ Exportar TXT</button>
                </div>

                <div class="card-exportacao">
                    <div class="icone-exportacao png" aria-hidden="true">▥</div>
                    <h2>Gráficos (PNG)</h2>
                    <p>Imagem com os gráficos de evolução e de distribuição de satisfação.</p>
                    <button class="botao-exportar png" onclick="exportarGraficoPng()">⤓ Exportar PNG</button>
                </div>
            </div>

            <div class="dica-analise">
                <b>💡 Dica de análise</b>
                Para uma análise mais aprofundada, exporte os dados brutos para o Excel: a aba Resumo já traz as médias por pergunta e por categoria, e a aba Respostas permite usar filtros e tabelas dinâmicas para identificar padrões ao longo do período.
            </div>

            <div class="painel">
                <h2>Registro de acessos dos gestores</h2>
                <div id="listaLogs"></div>
            </div>
        </section>

    </main>
</div>

<script src="script.js"></script>
</body>
</html>