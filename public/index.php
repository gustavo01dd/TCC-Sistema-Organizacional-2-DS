<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesquisa de Clima Organizacional</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div id="telaPesquisa" class="pagina">
    <header class="topo">
        <span>Pesquisa de Clima Organizacional</span>
        <span>⌄</span>
    </header>

    <main class="conteudo pesquisa-inicio">
        <div class="icone-principal">♢</div>
        <h1>Pesquisa de Clima Organizacional</h1>
        <p class="subtitulo">Sua opinião é fundamental para melhorarmos juntos</p>

        <div class="info">
            <div class="info-icone azul">♙</div>
            <div>
                <h3>100% Anônima</h3>
                <p>Esta pesquisa é totalmente anônima. Não coletamos nenhuma informação pessoal que possa identificá-lo.</p>
            </div>
        </div>

        <div class="info">
            <div class="info-icone cinza">◉</div>
            <div>
                <h3>Confidencial</h3>
                <p>Suas respostas são confidenciais e serão analisadas apenas de forma agregada pela equipe gestora.</p>
            </div>
        </div>

        <div class="info">
            <div class="info-icone verde">✓</div>
            <div>
                <h3>Rápida e Simples</h3>
                <p>Poucas perguntas. Leva só alguns minutos.</p>
            </div>
        </div>

        <div class="como">
            <h2>Como funciona?</h2>
            <ol>
                <li>Você avaliará os aspectos do ambiente de trabalho</li>
                <li>Arraste o controle para escolher sua nota de 0 a 10</li>
                <li>Opcionalmente, deixe um comentário ao final</li>
            </ol>
        </div>

        <button class="botao azul-btn" onclick="mostrarPesquisa()">Iniciar Pesquisa</button>

        <p class="rodape-frase">Suas respostas nos ajudam a criar um <b>ambiente de trabalho melhor para todos</b></p>

        <button class="link-gestor" onclick="mostrarGestor()">Acesso para gestores →</button>
    </main>
</div>

<div id="telaQuestionario" class="pagina escondido">
    <header class="topo">
        <span>Pesquisa de Clima Organizacional</span>
        <span>⌄</span>
    </header>

    <main class="conteudo">
        <button class="voltar" onclick="mostrarInicio()">‹ Voltar</button>

        <div id="formularioTitulo" class="formulario-titulo-ativo"></div>

        <div class="progresso-topo">
            <div>
                <span>Progresso da pesquisa</span>
                <b id="numeroPergunta">1 de 1</b>
            </div>
            <div class="barra">
                <div id="barraProgresso"></div>
            </div>
        </div>

        <div id="perguntas"><p>Carregando pesquisa...</p></div>

        <div class="comentario-box">
            <label>Comentário final (opcional)</label>
            <textarea id="comentario" placeholder="Gostaria de deixar algum comentário?"></textarea>
        </div>

        <button id="botaoEnviar" class="botao azul-btn" onclick="enviarPesquisa()">Enviar pesquisa</button>
    </main>
</div>

<div id="telaGestor" class="pagina login-gestor escondido">
    <div class="login-card">
        <div class="icone-login">♢</div>
        <h1>Acesso Gestor</h1>
        <p>Dashboard de Clima Organizacional</p>

        <label>Email</label>
        <input id="emailGestor" type="email" placeholder="seu.email@escola.com">

        <label>Senha</label>
        <input id="senhaGestor" type="password" placeholder="••••••••">

        <button class="botao azul-btn" onclick="entrarGestor()">Entrar</button>

        <div class="credenciais">
            <b>Credenciais de teste:</b><br>
            Email: gestor@escola.com<br>
            Senha: 123456
        </div>

        <button class="voltar-login" onclick="mostrarInicio()">← Voltar para a pesquisa</button>
        <p id="erroLogin" class="erro"></p>
    </div>
</div>

<div id="telaDashboard" class="dashboard escondido">
    <aside class="menu">
        <div class="logo">Pesquisa de Clima<br><span>Organizacional</span></div>
        <div class="gestor-label">Painel do Gestor</div>

        <button class="menu-item ativo" onclick="mostrarView('dashboard', this)">▣ &nbsp; Dashboard</button>
        <button class="menu-item" onclick="mostrarView('formularios', this)">▤ &nbsp; Formulários</button>
        <button class="menu-item" onclick="mostrarView('comentarios', this)">▱ &nbsp; Comentários</button>
        <button class="menu-item" onclick="mostrarAviso(this)">▥ &nbsp; Relatórios</button>
    </aside>

    <section class="dashboard-conteudo">

        <!-- ===================== VIEW: DASHBOARD ===================== -->
        <div id="viewDashboard">
            <div class="dashboard-topo">
                <h1 id="dashboardTitulo">Dashboard de Clima Organizacional</h1>
            </div>

            <div class="cards">
                <div class="card card-azul">
                    <small>Média Geral</small>
                    <strong id="statMediaGeral">—</strong>
                    <span>de 10 pontos</span>
                </div>

                <div class="card">
                    <small>Total de Respostas</small>
                    <strong id="statTotalRespostas">—</strong>
                    <span id="statRespostasDesde">&nbsp;</span>
                </div>

                <div class="card">
                    <small>Taxa de Participação</small>
                    <strong id="statTaxaParticipacao">—</strong>
                    <span id="statTaxaDetalhe">&nbsp;</span>
                </div>

                <div class="card">
                    <small>Última Atualização</small>
                    <strong class="data" id="statUltimaAtualizacao">—</strong>
                    <span id="statUltimaAtualizacaoData">&nbsp;</span>
                </div>
            </div>

            <div class="duas-colunas">
                <div class="painel">
                    <h3 class="titulo-vermelho">⚠ Pontos Críticos</h3>
                    <div id="pontosCriticos"><p class="aviso-vazio">Ainda sem dados suficientes</p></div>
                </div>

                <div class="painel">
                    <h3 class="titulo-verde">♧ Pontos Fortes</h3>
                    <div id="pontosFortes"><p class="aviso-vazio">Ainda sem dados suficientes</p></div>
                </div>
            </div>

            <div class="graficos">
                <div class="painel">
                    <h3>Evolução Temporal</h3>
                    <div class="grafico-linha">
                        <div class="linha-svg" id="graficoEvolucao"></div>
                        <div class="meses" id="legendaEvolucao"></div>
                        <p class="tendencia" id="textoTendencia"></p>
                    </div>
                </div>

                <div class="painel">
                    <h3>Distribuição de Respostas</h3>
                    <div class="pizza-area">
                        <div class="pizza" id="graficoPizza"></div>
                        <div class="legenda">
                            <span><i class="vermelho"></i>0-3 (Insatisfeito)</span>
                            <span><i class="amarelo"></i>4-6 (Neutro)</span>
                            <span><i class="verde"></i>7-10 (Satisfeito)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===================== VIEW: FORMULÁRIOS ===================== -->
        <div id="viewFormularios" class="escondido">
            <div class="dashboard-topo">
                <h1>Formulários</h1>
            </div>

            <div class="painel" style="margin-bottom:15px;">
                <h3>Criar novo formulário</h3>
                <label>Título</label>
                <input id="novoFormTitulo" type="text" placeholder="Ex: Pesquisa de Clima — 2º Semestre 2026">

                <label>Nº de funcionários esperados (opcional)</label>
                <input id="novoFormEsperados" type="number" min="1" placeholder="Ex: 151">

                <label>Data de início (opcional)</label>
                <input id="novoFormInicio" type="datetime-local">
                <label>Data de término (opcional)</label>
                <input id="novoFormFim" type="datetime-local">

                <label>Perguntas (uma por linha)</label>
                <textarea id="novoFormPerguntas" placeholder="Digite uma pergunta por linha..."></textarea>

                <button class="botao azul-btn" onclick="criarFormulario()">Criar formulário</button>
                <p id="erroNovoForm" class="erro"></p>
            </div>

            <div class="painel">
                <h3>Formulários existentes</h3>
                <div id="listaFormularios"><p class="aviso-vazio">Carregando...</p></div>
            </div>
        </div>

        <!-- ===================== VIEW: COMENTÁRIOS ===================== -->
        <div id="viewComentarios" class="escondido">
            <div class="dashboard-topo">
                <h1>Comentários</h1>
            </div>

            <div class="painel">
                <div id="listaComentarios"><p class="aviso-vazio">Carregando...</p></div>
            </div>
        </div>

        <button class="sair-dashboard" onclick="sairDashboard()">Sair</button>
    </section>
</div>

<script src="script.js"></script>
</body>
</html>