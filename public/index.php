<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0b56c9">
    <title>Pesquisa de Clima Organizacional</title>
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
    <link rel="stylesheet" href="style.css">
</head>
<body>

<!-- botão de tema (claro / escuro) das telas públicas; no painel ele fica no menu -->
<button id="botaoTema" class="botao-tema" onclick="alternarTema()" title="Mudar para o modo escuro" aria-label="Mudar para o modo escuro">☾</button>

<!-- ===================== TELA INICIAL ===================== -->
<div id="telaPesquisa" class="pagina">
    <header class="topo">
        <span>Pesquisa de Clima Organizacional</span>
        <span>⌄</span>
    </header>

    <main class="conteudo pesquisa-inicio">
        <div class="icone-principal">♢</div>
        <h1>Pesquisa de Clima Organizacional</h1>
        <p class="subtitulo">Sua opinião é fundamental para melhorarmos juntos</p>

        <div id="avisoPesquisa" class="aviso-pesquisa escondido"></div>

        <div class="info">
            <div class="info-icone azul">♙</div>
            <div>
                <h3>Respostas Anônimas</h3>
                <p>Você entra com email e senha só para o sistema saber que você já participou e evitar respostas duplicadas. Suas respostas nunca ficam ligadas ao seu nome: ninguém, nem a gestão, consegue ver o que você respondeu.</p>
            </div>
        </div>

        <div class="info">
            <div class="info-icone cinza">◉</div>
            <div>
                <h3>Confidencial</h3>
                <p>Os resultados são analisados apenas de forma agregada e só aparecem para a gestão depois que um número mínimo de pessoas responde.</p>
            </div>
        </div>

        <div class="info">
            <div class="info-icone verde">✓</div>
            <div>
                <h3>Rápida e Simples</h3>
                <p>Poucas perguntas, leva só alguns minutos.</p>
            </div>
        </div>

        <div class="como">
            <h2>Como funciona?</h2>
            <ol>
                <li>Entre com o email e a senha cadastrados pela gestão</li>
                <li>Arraste o controle para escolher sua nota de 0 a 10 em cada pergunta</li>
                <li>Se quiser, deixe um comentário ou sugestão no final e envie</li>
            </ol>
        </div>

        <button class="botao azul-btn" onclick="mostrarLogin()">Iniciar Pesquisa</button>

        <p class="rodape-frase">Suas respostas nos ajudam a criar um <b>ambiente de trabalho melhor para todos</b></p>

        <button class="link-gestor" onclick="mostrarLogin()">Acesso para gestores →</button>
    </main>
</div>

<!-- ===================== LOGIN (funcionários e gestores) ===================== -->
<div id="telaLogin" class="pagina login-gestor escondido">
    <div class="login-card">
        <div class="login-icone">▣</div>

        <h1>Entrar</h1>
        <p class="subtitulo-login">Funcionários vão para a pesquisa e gestores para o painel de gestão</p>

        <label for="emailLogin">Email</label>
        <input id="emailLogin" type="email" placeholder="seu@email.com" autocomplete="username">

        <label for="senhaLogin">Senha</label>
        <input id="senhaLogin" type="password" placeholder="••••••" autocomplete="current-password" onkeydown="if (event.key === 'Enter') entrar()">

        <button id="botaoEntrar" class="botao azul-btn" onclick="entrar()">Entrar</button>
        <p id="erroLogin" class="erro"></p>

        <div class="credenciais">
            <b>Credenciais de teste</b> (senha <b>123456</b> para todos)
            <br>Gestor: gestor@escola.com
            <br>Funcionários: funcionario1@escola.com, funcionario2@escola.com, funcionario3@escola.com
        </div>

        <button class="voltar-login" onclick="mostrarInicio()">← Voltar para o início</button>
    </div>
</div>

<!-- ===================== QUESTIONÁRIO (funcionário) ===================== -->
<div id="telaQuestionario" class="pagina escondido">
    <header class="topo">
        <span>Pesquisa de Clima Organizacional</span>
        <span>⌄</span>
    </header>

    <main class="conteudo">
        <button class="voltar" onclick="sair()">‹ Sair</button>

        <div id="saudacaoFuncionario" class="saudacao"></div>
        <div id="formularioTitulo" class="formulario-titulo-ativo"></div>

        <div class="progresso-topo">
            <span>Progresso</span>
            <span id="numeroPergunta"></span>
        </div>
        <div class="barra">
            <div id="barraProgresso"></div>
        </div>

        <div id="perguntas"></div>

        <div id="blocoEnvio" class="escondido">
            <div class="comentario-box">
                <label for="comentario">Comentário ou sugestão final (opcional)</label>
                <textarea id="comentario" maxlength="2000" placeholder="Escreva aqui sua sugestão, elogio ou crítica..."></textarea>
            </div>

            <button id="botaoEnviar" class="botao azul-btn" onclick="enviarPesquisa()">Enviar respostas</button>
            <p class="aviso-envio">Depois de enviadas, as respostas não podem ser alteradas.</p>
        </div>
    </main>
</div>

<!-- ===================== PAINEL DO GESTOR ===================== -->
<div id="telaDashboard" class="dashboard escondido">
    <aside class="menu">
        <div class="logo">Pesquisa de Clima<br><span>Organizacional</span></div>
        <div class="gestor-label">Painel do Gestor</div>
        <div id="nomeGestor" class="nome-gestor"></div>

        <button class="menu-item ativo" onclick="mostrarView('dashboard', this)">▣ &nbsp; Dashboard</button>
        <button class="menu-item" onclick="mostrarView('resultados', this)">▥ &nbsp; Resultados</button>
        <button class="menu-item" onclick="mostrarView('formularios', this)">▤ &nbsp; Formulários</button>
        <button class="menu-item" onclick="mostrarView('comentarios', this)">▱ &nbsp; Comentários</button>
        <button class="menu-item" onclick="mostrarView('funcionarios', this)">◈ &nbsp; Funcionários</button>
        <button class="menu-item" onclick="mostrarView('relatorios', this)">▦ &nbsp; Relatórios</button>

        <button id="botaoTemaMenu" class="menu-tema" onclick="alternarTema()">☾ &nbsp; Modo escuro</button>
        <button class="menu-sair" onclick="sair()">⎋ &nbsp; Sair</button>
    </aside>

    <main class="dashboard-conteudo">

        <!-- ========== VIEW: DASHBOARD ========== -->
        <section id="viewDashboard">
            <div class="dashboard-topo">
                <div>
                    <h1 id="dashboardTitulo">Dashboard</h1>
                    <p>Visão geral dos resultados da pesquisa de clima organizacional</p>
                </div>
            </div>

            <div id="avisoAnonimatoDashboard" class="aviso-anonimato escondido"></div>

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
                    <h2 class="titulo-critico">⚠ Pontos Críticos</h2>
                    <div id="pontosCriticos"></div>
                </div>
                <div class="painel">
                    <h2 class="titulo-forte">↗ Pontos Fortes</h2>
                    <div id="pontosFortes"></div>
                </div>
            </div>

            <div class="graficos">
                <div class="painel">
                    <h2>Evolução Temporal</h2>
                    <p>Respostas recebidas por dia</p>
                    <div id="graficoEvolucao" class="grafico-linha"></div>
                    <div id="legendaEvolucao" class="meses"></div>
                    <p id="textoTendencia" class="tendencia"></p>
                </div>
                <div class="painel">
                    <h2>Distribuição de Satisfação</h2>
                    <p>Classificação geral das respostas</p>
                    <div class="pizza-area">
                        <div id="graficoPizza" class="pizza"></div>
                        <div class="legenda">
                            <p><span class="verde-bola"></span> Satisfeito (7-10)</p>
                            <p><span class="amarelo-bola"></span> Neutro (4-6)</p>
                            <p><span class="vermelho-bola"></span> Insatisfeito (0-3)</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ========== VIEW: RESULTADOS ========== -->
        <section id="viewResultados" class="escondido">
            <div class="dashboard-topo">
                <div>
                    <h1>Resultados</h1>
                    <p>Análise detalhada de cada pergunta da pesquisa</p>
                    <p id="resultadosFormulario" class="subtitulo-resultados"></p>
                </div>
                <select id="filtroPeriodo" class="filtro-periodo" onchange="carregarResultados()">
                    <option value="7">Últimos 7 dias</option>
                    <option value="30">Últimos 30 dias</option>
                    <option value="completo" selected>Período completo</option>
                </select>
            </div>

            <div id="listaResultados"></div>
        </section>

        <!-- ========== VIEW: FORMULÁRIOS ========== -->
        <section id="viewFormularios" class="escondido">
            <div class="dashboard-topo">
                <div>
                    <h1>Formulários</h1>
                    <p>Crie novos formulários e escolha qual fica ativo para os funcionários</p>
                </div>
            </div>

            <div class="painel">
                <h2>Novo formulário</h2>

                <label for="novoFormTitulo">Título</label>
                <input id="novoFormTitulo" type="text" maxlength="150" placeholder="Ex.: Pesquisa de Clima 2º Semestre">

                <label for="novoFormEsperados">Nº de respondentes esperados (opcional)</label>
                <input id="novoFormEsperados" type="number" min="1" placeholder="Se ficar vazio, usa o total de funcionários cadastrados">

                <label for="novoFormInicio">Data e hora de início (opcional)</label>
                <input id="novoFormInicio" type="datetime-local">

                <label for="novoFormFim">Data e hora de término (opcional)</label>
                <input id="novoFormFim" type="datetime-local">
                <p class="dica-campo">Sem data de término, a pesquisa fica aberta por 7 dias depois de ativada.</p>

                <label for="novoFormPerguntas">Perguntas (uma por linha)</label>
                <textarea id="novoFormPerguntas" placeholder="Como você avalia o ambiente de trabalho?&#10;Você se sente valorizado pela gestão?"></textarea>

                <button class="botao azul-btn" onclick="criarFormulario()">Criar formulário</button>
                <p id="erroNovoForm" class="erro"></p>
            </div>

            <div class="painel">
                <h2>Formulários existentes</h2>
                <div id="listaFormularios"></div>
            </div>
        </section>

        <!-- ========== VIEW: COMENTÁRIOS ========== -->
        <section id="viewComentarios" class="escondido">
            <div class="dashboard-topo">
                <div>
                    <h1>Comentários</h1>
                    <p>Sugestões e comentários enviados de forma anônima</p>
                    <p id="comentariosFormulario" class="subtitulo-resultados"></p>
                </div>
            </div>

            <div class="painel">
                <div id="listaComentarios"></div>
            </div>
        </section>

        <!-- ========== VIEW: FUNCIONÁRIOS ========== -->
        <section id="viewFuncionarios" class="escondido">
            <div class="dashboard-topo">
                <div>
                    <h1>Funcionários</h1>
                    <p>Cadastre os logins de quem responde a pesquisa (e de outros gestores)</p>
                </div>
            </div>

            <div id="formFuncionario" class="painel">
                <h2 id="tituloFormFunc">Novo cadastro</h2>

                <label for="novoFuncNome">Nome</label>
                <input id="novoFuncNome" type="text" maxlength="100" placeholder="Nome completo">

                <label for="novoFuncEmail">Email (usado no login)</label>
                <input id="novoFuncEmail" type="email" maxlength="100" placeholder="nome@escola.com">

                <label for="novoFuncSenha">Senha</label>
                <input id="novoFuncSenha" type="password" placeholder="Mínimo de 6 caracteres" autocomplete="new-password">

                <label for="novoFuncCargo">Cargo (opcional)</label>
                <input id="novoFuncCargo" type="text" maxlength="50" placeholder="Ex.: Professor">

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
                <p id="erroNovoFunc" class="erro"></p>
            </div>

            <div class="painel">
                <h2>Cadastrados</h2>
                <p id="infoPesquisaAtual" class="info-lista"></p>
                <div id="listaFuncionarios"></div>
            </div>
        </section>

        <!-- ========== VIEW: RELATÓRIOS ========== -->
        <section id="viewRelatorios" class="escondido">
            <div class="dashboard-topo">
                <div>
                    <h1>Relatórios e Exportações</h1>
                    <p>Gere relatórios e exporte dados para análise externa</p>
                </div>
            </div>

            <div class="grade-exportacao">
                <div class="card-exportacao">
                    <div class="icone-exportacao pdf">▤</div>
                    <h2>Relatório Completo (PDF)</h2>
                    <p>Resumo da pesquisa, estatísticas de cada pergunta e comentários, pronto para imprimir ou salvar em PDF.</p>
                    <button class="botao-exportar pdf" onclick="exportarPdf()">⤓ Exportar PDF</button>
                </div>

                <div class="card-exportacao">
                    <div class="icone-exportacao excel">▦</div>
                    <h2>Dados Brutos (Excel)</h2>
                    <p>Planilha do Excel já formatada, com filtros e todas as respostas anônimas, para análise personalizada.</p>
                    <button class="botao-exportar excel" onclick="exportarExcel()">⤓ Exportar Excel</button>
                </div>

                <div class="card-exportacao">
                    <div class="icone-exportacao txt">▱</div>
                    <h2>Comentários (TXT)</h2>
                    <p>Arquivo de texto com todos os comentários e sugestões recebidos.</p>
                    <button class="botao-exportar txt" onclick="exportarComentariosArquivo()">⤓ Exportar TXT</button>
                </div>

                <div class="card-exportacao">
                    <div class="icone-exportacao png">▥</div>
                    <h2>Gráficos (PNG)</h2>
                    <p>Imagem com os gráficos de evolução e de distribuição de satisfação.</p>
                    <button class="botao-exportar png" onclick="exportarGraficoPng()">⤓ Exportar PNG</button>
                </div>
            </div>

            <div class="dica-analise">
                <b>💡 Dica de análise</b>
                Para uma análise mais aprofundada, exporte os dados brutos para o Excel e use tabelas dinâmicas para cruzar as notas das perguntas e identificar padrões ao longo do período.
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