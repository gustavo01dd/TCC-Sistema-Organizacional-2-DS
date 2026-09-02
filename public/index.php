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
                <p>São apenas 10 perguntas. O tempo estimado é de 3 a 5 minutos.</p>
            </div>
        </div>

        <div class="como">
            <h2>Como funciona?</h2>
            <ol>
                <li>Você avaliará 10 aspectos do ambiente de trabalho</li>
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

        <div class="progresso-topo">
            <div>
                <span>Progresso da pesquisa</span>
                <b id="numeroPergunta">1 de 10</b>
            </div>
            <div class="barra">
                <div id="barraProgresso"></div>
            </div>
        </div>

        <div id="perguntas"></div>

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

        <button class="menu-item ativo" onclick="menuDashboard(this)">▣ &nbsp; Dashboard</button>
        <button class="menu-item" onclick="mostrarAviso(this)">▥ &nbsp; Resultados</button>
        <button class="menu-item" onclick="mostrarAviso(this)">▱ &nbsp; Comentários</button>
        <button class="menu-item" onclick="mostrarAviso(this)">▤ &nbsp; Relatórios</button>
    </aside>

    <section class="dashboard-conteudo">
        <div class="dashboard-topo">
            <h1>Dashboard de Clima Organizacional</h1>
            <button class="periodo">Últimos 30 dias ⌄</button>
        </div>

        <div class="cards">
            <div class="card card-azul">
                <small>Média Geral</small>
                <strong id="mediaGeral">0.0</strong>
                <span>de 10 pontos</span>
                <div class="mini-grafico">▂▃▅▆▇</div>
            </div>

            <div class="card">
                <small>Total de Respostas</small>
                <strong id="totalRespostas">0</strong>
                <span>respostas registradas</span>
            </div>

            <div class="card">
                <small>Taxa de Participação</small>
                <strong id="taxaParticipacao">0%</strong>
                <span>baseado nas respostas</span>
            </div>

            <div class="card">
                <small>Última Atualização</small>
                <strong class="data" id="ultimaAtualizacao">—</strong>
                <span id="dataAtualizacao">Nenhuma resposta ainda</span>
            </div>
        </div>

        <div class="duas-colunas">
            <div class="painel">
                <h3 class="titulo-vermelho">⚠ Pontos Críticos</h3>
                <div class="linha-ponto">
                    <span id="critico1Nome">Comunicação interna</span><b id="critico1Valor">—</b>
                </div>
                <div class="linha-ponto">
                    <span id="critico2Nome">Reconhecimento</span><b id="critico2Valor">—</b>
                </div>
            </div>

            <div class="painel">
                <h3 class="titulo-verde">♧ Pontos Fortes</h3>
                <div class="linha-ponto">
                    <span id="forte1Nome">Ambiente respeitoso e colaborativo</span><b id="forte1Valor">—</b>
                </div>
                <div class="linha-ponto">
                    <span id="forte2Nome">Recomendaria a escola</span><b id="forte2Valor">—</b>
                </div>
            </div>
        </div>

        <div class="graficos">
            <div class="painel">
                <h3>Evolução Temporal</h3>
                <div class="grafico-linha">
                    <div class="linha-svg">
                        <svg viewBox="0 0 500 180" preserveAspectRatio="none">
                            <line x1="45" y1="150" x2="480" y2="150" stroke="#ddd"/>
                            <line x1="45" y1="20" x2="45" y2="150" stroke="#ddd"/>
                            <polyline points="45,90 150,80 255,70 360,62 475,55"
                                fill="none" stroke="#1261d6" stroke-width="4"/>
                            <circle cx="45" cy="90" r="5" fill="#1261d6"/>
                            <circle cx="150" cy="80" r="5" fill="#1261d6"/>
                            <circle cx="255" cy="70" r="5" fill="#1261d6"/>
                            <circle cx="360" cy="62" r="5" fill="#1261d6"/>
                            <circle cx="475" cy="55" r="5" fill="#1261d6"/>
                        </svg>
                    </div>
                    <div class="meses"><span>Jan</span><span>Fev</span><span>Mar</span><span>Abr</span><span>Mai</span></div>
                    <p class="tendencia">↑ Tendência positiva nos últimos 5 meses</p>
                </div>
            </div>

            <div class="painel">
                <h3>Distribuição de Respostas</h3>
                <div class="pizza-area">
                    <div class="pizza"></div>
                    <div class="legenda">
                        <span><i class="vermelho"></i>0-3 (Insatisfeito)</span>
                        <span><i class="amarelo"></i>4-6 (Neutro)</span>
                        <span><i class="verde"></i>7-10 (Satisfeito)</span>
                    </div>
                </div>
            </div>
        </div>

        <button class="sair-dashboard" onclick="sairDashboard()">Sair</button>
    </section>
</div>

<script src="script.js"></script>
</body>
</html>
