// ============================================================
// Pesquisa de Clima Organizacional - front-end (JavaScript puro)
// Conversa com o back-end em api.php usando fetch()
// ============================================================

// ===================== Estado =====================

var perguntasAtuais = [];           // perguntas da pesquisa aberta (tela do funcionário)
var formularioAtualId = null;       // pesquisa que o funcionário está respondendo
var notas = {};                     // notas escolhidas, por id da pergunta

var formularioSelecionadoId = null; // formulário que o gestor está analisando
var ultimoDashboard = null;         // últimos dados do dashboard (usados no PNG)
var ultimaListaFuncionarios = [];   // última lista da tela Funcionários
var meuId = null;                   // id do gestor logado
var funcionarioEditandoId = null;   // null = cadastrando; número = editando esse id

var TELAS = ["telaPesquisa", "telaLogin", "telaQuestionario", "telaDashboard"];
var VIEWS = ["dashboard", "resultados", "formularios", "comentarios", "funcionarios", "relatorios"];

document.addEventListener("DOMContentLoaded", iniciar);

function iniciar() {
    aplicarTema(temaAtual());
    carregarAvisoPesquisa();

    // se a pessoa nunca escolheu um tema, acompanha o tema do sistema
    try {
        window.matchMedia("(prefers-color-scheme: dark)").addEventListener("change", function (evento) {
            if (!localStorage.getItem("tema")) {
                aplicarTema(evento.matches ? "escuro" : "claro");
            }
        });
    } catch (e) {
        // navegador antigo: fica com o tema atual
    }
}


// ===================== Tema claro / escuro =====================

function temaAtual() {
    return document.documentElement.getAttribute("data-tema") === "escuro" ? "escuro" : "claro";
}

function aplicarTema(tema) {
    var escuro = tema === "escuro";
    document.documentElement.setAttribute("data-tema", escuro ? "escuro" : "claro");

    var dica = escuro ? "Mudar para o modo claro" : "Mudar para o modo escuro";
    var botao = document.getElementById("botaoTema");
    botao.innerText = escuro ? "☀" : "☾";
    botao.title = dica;
    botao.setAttribute("aria-label", dica);

    document.getElementById("botaoTemaMenu").innerText = escuro ? "☀ \u00A0 Modo claro" : "☾ \u00A0 Modo escuro";
    document.querySelector('meta[name="theme-color"]').setAttribute("content", escuro ? "#0a1733" : "#0b56c9");
}

function alternarTema() {
    var novoTema = temaAtual() === "escuro" ? "claro" : "escuro";
    aplicarTema(novoTema);
    try {
        localStorage.setItem("tema", novoTema);
    } catch (e) {
        // sem permissão para salvar: o tema vale só até fechar a página
    }
}


// ===================== Comunicação com a API =====================

// GET quando não há corpo; POST com JSON quando há
async function chamarApi(acao, corpo, parametros) {
    var url = "api.php?action=" + acao + (parametros || "");
    var opcoes = {};
    if (corpo !== undefined) {
        opcoes = {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(corpo)
        };
    }
    var resposta = await fetch(url, opcoes);
    var dados = {};
    try {
        dados = await resposta.json();
    } catch (e) {
        dados = {};
    }
    return { ok: resposta.ok, status: resposta.status, dados: dados };
}

function sessaoExpirada(retorno) {
    if (retorno.status === 401) {
        alert("Sua sessão expirou. Entre novamente.");
        mostrarLogin();
        return true;
    }
    return false;
}

function parametroFormulario() {
    return formularioSelecionadoId ? "&formulario_id=" + formularioSelecionadoId : "";
}


// ===================== Tela inicial: aviso da pesquisa (RF08) =====================

async function carregarAvisoPesquisa() {
    var aviso = document.getElementById("avisoPesquisa");
    try {
        var r = await chamarApi("status_pesquisa");
        var d = r.dados;
        if (d.situacao === "aberta") {
            aviso.className = "aviso-pesquisa aberta";
            aviso.innerText = "📢 Pesquisa aberta: " + d.titulo +
                (d.data_fechamento ? ". Responda até " + formatarDataHora(d.data_fechamento) + "." : ".");
        } else if (d.situacao === "encerrada") {
            aviso.className = "aviso-pesquisa encerrada";
            aviso.innerText = "🔔 A pesquisa \u201C" + d.titulo + "\u201D foi encerrada em " +
                formatarData(d.data_fechamento) + ". Obrigado a todos que participaram!";
        } else {
            aviso.className = "aviso-pesquisa escondido";
        }
    } catch (e) {
        aviso.className = "aviso-pesquisa escondido";
    }
}


// ===================== Navegação entre telas =====================

function mostrarTela(id) {
    TELAS.forEach(function (tela) {
        document.getElementById(tela).classList.add("escondido");
    });
    document.getElementById(id).classList.remove("escondido");

    // no painel do gestor o botão de tema fica dentro do menu
    document.getElementById("botaoTema").classList.toggle("escondido", id === "telaDashboard");
    window.scrollTo(0, 0);
}

function mostrarInicio() {
    mostrarTela("telaPesquisa");
    carregarAvisoPesquisa();
}

function mostrarLogin() {
    mostrarTela("telaLogin");
    document.getElementById("erroLogin").innerText = "";
    document.getElementById("emailLogin").focus();
}

// Login único: a API responde o perfil e ele decide a próxima tela
async function entrar() {
    var email = document.getElementById("emailLogin").value.trim();
    var senha = document.getElementById("senhaLogin").value;
    var erro = document.getElementById("erroLogin");
    var botao = document.getElementById("botaoEntrar");

    erro.innerText = "";
    if (!email || !senha) {
        erro.innerText = "Informe o email e a senha.";
        return;
    }

    botao.disabled = true;
    try {
        var r = await chamarApi("login", { email: email, senha: senha });
        if (!r.ok || !r.dados.sucesso) {
            erro.innerText = r.dados.erro || "Email ou senha incorretos.";
            return;
        }
        document.getElementById("senhaLogin").value = "";
        if (r.dados.perfil === "gestor") {
            abrirPainelGestor(r.dados.nome);
        } else {
            mostrarPesquisa(r.dados.nome);
        }
    } catch (e) {
        erro.innerText = "Erro de conexão. Tente novamente.";
    } finally {
        botao.disabled = false;
    }
}

async function sair() {
    try {
        await chamarApi("logout");
    } catch (e) {
        // volta para o início mesmo sem conexão
    }
    formularioSelecionadoId = null;
    ultimoDashboard = null;
    meuId = null;
    cancelarEdicaoFuncionario();
    mostrarInicio();
}


// ===================== Questionário (funcionário) =====================

async function mostrarPesquisa(nome) {
    mostrarTela("telaQuestionario");
    document.getElementById("saudacaoFuncionario").innerText = nome ? "Olá, " + nome + "!" : "";
    document.getElementById("formularioTitulo").innerText = "";
    document.getElementById("blocoEnvio").classList.add("escondido");
    document.getElementById("comentario").value = "";

    var area = document.getElementById("perguntas");
    area.innerHTML = '<div class="mensagem-pesquisa">Carregando pesquisa...</div>';

    perguntasAtuais = [];
    formularioAtualId = null;
    notas = {};
    atualizarProgresso();

    try {
        var r = await chamarApi("formulario_ativo");
        if (sessaoExpirada(r)) return;

        if (!r.ok) {
            area.innerHTML = '<div class="mensagem-pesquisa">' +
                escaparHtml(r.dados.erro || "Nenhuma pesquisa disponível no momento.") + '</div>';
            return;
        }

        formularioAtualId = Number(r.dados.id);
        perguntasAtuais = r.dados.perguntas || [];
        document.getElementById("formularioTitulo").innerText = r.dados.titulo || "";
        criarPerguntas();

        if (perguntasAtuais.length > 0) {
            document.getElementById("blocoEnvio").classList.remove("escondido");
        }
    } catch (e) {
        area.innerHTML = '<div class="mensagem-pesquisa">Não foi possível carregar a pesquisa. Verifique sua conexão e tente novamente.</div>';
    }
}

function criarPerguntas() {
    var area = document.getElementById("perguntas");

    if (perguntasAtuais.length === 0) {
        area.innerHTML = '<div class="mensagem-pesquisa">Esta pesquisa ainda não tem perguntas cadastradas.</div>';
        atualizarProgresso();
        return;
    }

    // régua 0-10; no celular os números do meio (num-meio) somem para não apertar
    var regua = '<div class="numeros">' +
        '<span>0<br><small>Insatisfeito</small></span>' +
        '<span class="num-meio">1</span><span class="num-meio">2</span>' +
        '<span class="num-meio">3</span><span class="num-meio">4</span>' +
        '<span>5<br><small>Neutro</small></span>' +
        '<span class="num-meio">6</span><span class="num-meio">7</span>' +
        '<span class="num-meio">8</span><span class="num-meio">9</span>' +
        '<span>10<br><small>Muito satisfeito</small></span>' +
        '</div>';

    area.innerHTML = perguntasAtuais.map(function (pergunta, i) {
        var id = Number(pergunta.id);
        var nota = notas[id] != null ? notas[id] : 6;

        return '<div class="pergunta-card">' +
            '<p class="pergunta-numero">Pergunta ' + (i + 1) + ' de ' + perguntasAtuais.length + '</p>' +
            '<h2 class="pergunta-titulo">' + escaparHtml(pergunta.texto) + '</h2>' +
            '<input type="range" min="0" max="10" value="' + nota + '" class="range" ' +
            'style="' + corPreenchimento(nota) + '" ' +
            'oninput="mudarNota(' + id + ', this.value, this)">' +
            regua +
            '<div class="nota">' +
            '<div class="nota-grande" id="nota-' + id + '">' + nota + '</div>' +
            '<div class="satisfacao" id="texto-' + id + '">' + textoNota(nota) + '</div>' +
            '</div>' +
            '</div>';
    }).join("");

    atualizarProgresso();
}

function corPreenchimento(valor) {
    var porcentagem = (Number(valor) / 10) * 100;
    return "background: linear-gradient(to right, var(--primaria) 0%, var(--primaria) " + porcentagem +
        "%, var(--trilho) " + porcentagem + "%, var(--trilho) 100%);";
}

function mudarNota(perguntaId, valor, elemento) {
    var nota = Number(valor);
    notas[perguntaId] = nota;

    document.getElementById("nota-" + perguntaId).innerText = nota;
    document.getElementById("texto-" + perguntaId).innerText = textoNota(nota);
    elemento.style.cssText = corPreenchimento(nota);

    atualizarProgresso();
}

// mesmas faixas da legenda do dashboard
function textoNota(nota) {
    if (nota <= 3) return "😞 Insatisfeito";
    if (nota <= 6) return "😐 Neutro";
    if (nota <= 8) return "🙂 Satisfeito";
    return "😄 Muito satisfeito";
}

function atualizarProgresso() {
    var total = perguntasAtuais.length;
    var texto = document.getElementById("numeroPergunta");
    var barra = document.getElementById("barraProgresso");

    if (total === 0) {
        texto.innerText = "";
        barra.style.width = "0%";
        return;
    }

    var respondidas = perguntasAtuais.filter(function (p) {
        return notas[Number(p.id)] != null;
    }).length;

    texto.innerText = respondidas + " de " + total + " respondidas";
    barra.style.width = ((respondidas / total) * 100) + "%";
}

async function enviarPesquisa() {
    if (!formularioAtualId || perguntasAtuais.length === 0) {
        alert("Não há pesquisa carregada para enviar.");
        return;
    }

    // RN04: depois de enviada não dá para alterar, então confirma antes
    var naoMexidas = perguntasAtuais.filter(function (p) {
        return notas[Number(p.id)] == null;
    }).length;

    var aviso = "Depois de enviadas, as respostas não podem ser alteradas.";
    if (naoMexidas > 0) {
        aviso = "Você não mexeu em " + naoMexidas +
            (naoMexidas === 1 ? " pergunta, que será enviada" : " perguntas, que serão enviadas") +
            " com a nota 6.\n\n" + aviso;
    }
    if (!confirm(aviso + "\n\nDeseja enviar agora?")) return;

    var itens = perguntasAtuais.map(function (p) {
        var id = Number(p.id);
        return { pergunta_id: id, nota: notas[id] != null ? notas[id] : 6 };
    });

    var soma = itens.reduce(function (acc, item) { return acc + item.nota; }, 0);
    var media = (soma / itens.length).toFixed(1);

    var botao = document.getElementById("botaoEnviar");
    botao.disabled = true;
    botao.innerText = "Enviando...";

    try {
        var r = await chamarApi("enviar_resposta", {
            formulario_id: formularioAtualId,
            respostas: itens,
            comentario: document.getElementById("comentario").value
        });
        if (sessaoExpirada(r)) return;

        if (r.ok && r.dados.sucesso) {
            alert("Pesquisa enviada com sucesso! Obrigado pela participação.\nSua média foi: " + media);
            await sair();
        } else {
            alert(r.dados.erro || "Não foi possível enviar sua resposta. Tente novamente.");
        }
    } catch (e) {
        alert("Erro de conexão. Verifique sua internet e tente novamente.");
    } finally {
        botao.disabled = false;
        botao.innerText = "Enviar respostas";
    }
}


// ===================== Painel do gestor: navegação =====================

function abrirPainelGestor(nome) {
    mostrarTela("telaDashboard");
    document.getElementById("nomeGestor").innerText = nome ? "Olá, " + nome : "";
    formularioSelecionadoId = null;
    ultimoDashboard = null;
    mostrarView("dashboard", document.querySelector(".menu-item"));
}

function mostrarView(nome, botao) {
    document.querySelectorAll(".menu-item").forEach(function (b) {
        b.classList.remove("ativo");
    });
    if (botao) botao.classList.add("ativo");

    VIEWS.forEach(function (view) {
        document.getElementById("view" + capitalizar(view)).classList.add("escondido");
    });
    document.getElementById("view" + capitalizar(nome)).classList.remove("escondido");
    window.scrollTo(0, 0);

    if (nome === "dashboard") carregarDashboard();
    if (nome === "resultados") carregarResultados();
    if (nome === "formularios") carregarListaFormularios();
    if (nome === "comentarios") carregarComentarios();
    if (nome === "funcionarios") carregarListaFuncionarios();
    if (nome === "relatorios") carregarLogsAcesso();
}

function capitalizar(texto) {
    return texto.charAt(0).toUpperCase() + texto.slice(1);
}

function rotuloStatus(status) {
    if (status === "ativo") return "Ativo";
    if (status === "encerrado") return "Encerrado";
    return "Rascunho";
}


// ===================== Dashboard =====================

async function carregarDashboard() {
    try {
        var r = await chamarApi("dashboard", undefined, parametroFormulario());
        if (sessaoExpirada(r)) return;

        if (!r.ok || r.dados.erro) {
            limparDashboard(r.dados.erro || "Nenhum formulário cadastrado ainda");
            return;
        }

        var d = r.dados;
        formularioSelecionadoId = Number(d.formulario.id);
        ultimoDashboard = d;

        document.getElementById("dashboardTitulo").innerText =
            "Dashboard — " + d.formulario.titulo + " (" + rotuloStatus(d.formulario.status) + ")";

        document.getElementById("statMediaGeral").innerText =
            d.media_geral !== null ? Number(d.media_geral).toFixed(1) : "—";

        document.getElementById("statTotalRespostas").innerText = d.total_respostas;
        document.getElementById("statRespostasDesde").innerText =
            d.formulario.data_abertura ? "desde " + formatarData(d.formulario.data_abertura) : "\u00A0";

        if (d.taxa_participacao !== null) {
            document.getElementById("statTaxaParticipacao").innerText = d.taxa_participacao + "%";
            document.getElementById("statTaxaDetalhe").innerText = d.total_respostas + " de " + d.respondentes_esperados +
                (d.formulario.respondentes_esperados ? " esperadas" : " funcionários");
        } else {
            document.getElementById("statTaxaParticipacao").innerText = "—";
            document.getElementById("statTaxaDetalhe").innerText = "nenhum funcionário cadastrado";
        }

        var agora = new Date();
        document.getElementById("statUltimaAtualizacao").innerText = "Hoje";
        document.getElementById("statUltimaAtualizacaoData").innerText =
            agora.toLocaleDateString("pt-BR") + ", " +
            agora.toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" });

        var aviso = document.getElementById("avisoAnonimatoDashboard");
        if (d.dados_ocultos) {
            aviso.innerText = "🔒 Este formulário tem " + d.total_respostas + " resposta(s). Para proteger o anonimato, " +
                "médias, gráficos e comentários só aparecem a partir de " + d.minimo_anonimato + " respostas.";
            aviso.classList.remove("escondido");
        } else {
            aviso.classList.add("escondido");
        }

        var comMedia = (d.por_pergunta || []).filter(function (p) { return p.media !== null; });
        renderizarPontos("pontosCriticos", comMedia.slice(0, 2), "↘");
        renderizarPontos("pontosFortes", comMedia.slice(-2).reverse(), "↗");
        renderizarDistribuicao(d.distribuicao);
        renderizarEvolucao(d.evolucao || []);
    } catch (e) {
        console.error("Erro ao carregar dashboard:", e);
        limparDashboard("Erro ao carregar o dashboard");
    }
}

function limparDashboard(mensagem) {
    ultimoDashboard = null;
    document.getElementById("dashboardTitulo").innerText = mensagem;
    ["statMediaGeral", "statTotalRespostas", "statTaxaParticipacao", "statUltimaAtualizacao"].forEach(function (id) {
        document.getElementById(id).innerText = "—";
    });
    ["statRespostasDesde", "statTaxaDetalhe", "statUltimaAtualizacaoData"].forEach(function (id) {
        document.getElementById(id).innerText = "\u00A0";
    });
    document.getElementById("avisoAnonimatoDashboard").classList.add("escondido");
    renderizarPontos("pontosCriticos", [], "");
    renderizarPontos("pontosFortes", [], "");
    renderizarDistribuicao({ insatisfeito: 0, neutro: 0, satisfeito: 0 });
    renderizarEvolucao([]);
}

function renderizarPontos(containerId, lista, seta) {
    var container = document.getElementById(containerId);
    if (lista.length === 0) {
        container.innerHTML = '<p class="aviso-vazio">Ainda sem dados suficientes.</p>';
        return;
    }
    container.innerHTML = lista.map(function (p) {
        return '<div class="linha-ponto"><span>' + escaparHtml(p.texto) + '</span><b>' +
            Number(p.media).toFixed(1) + ' ' + seta + '</b></div>';
    }).join("");
}

function renderizarDistribuicao(dist) {
    var insatisfeito = Number(dist.insatisfeito) || 0;
    var neutro = Number(dist.neutro) || 0;
    var satisfeito = Number(dist.satisfeito) || 0;
    var total = insatisfeito + neutro + satisfeito;
    var pizza = document.getElementById("graficoPizza");

    if (total === 0) {
        pizza.style.background = "var(--trilho)";
        return;
    }

    var pSatisfeito = (satisfeito / total) * 100;
    var pNeutro = (neutro / total) * 100;

    pizza.style.background = "conic-gradient(" +
        "#15b573 0% " + pSatisfeito + "%, " +
        "#f4b400 " + pSatisfeito + "% " + (pSatisfeito + pNeutro) + "%, " +
        "#ef4444 " + (pSatisfeito + pNeutro) + "% 100%)";
}

function renderizarEvolucao(evolucao) {
    var grafico = document.getElementById("graficoEvolucao");
    var legenda = document.getElementById("legendaEvolucao");
    var tendencia = document.getElementById("textoTendencia");

    if (evolucao.length === 0) {
        grafico.innerHTML = '<p class="aviso-vazio">Sem respostas ainda.</p>';
        legenda.innerHTML = "";
        tendencia.innerText = "";
        return;
    }

    var largura = 400;
    var altura = 130;
    var maximo = Math.max.apply(null, evolucao.map(function (e) { return Number(e.total); }));
    if (maximo === 0) maximo = 1;

    var pontos = evolucao.map(function (e, i) {
        var x = evolucao.length === 1 ? largura / 2 : (i / (evolucao.length - 1)) * largura;
        var y = altura - (Number(e.total) / maximo) * (altura - 20) - 10;
        return x.toFixed(1) + "," + y.toFixed(1);
    });

    var circulos = pontos.map(function (p) {
        var xy = p.split(",");
        return '<circle cx="' + xy[0] + '" cy="' + xy[1] + '" r="4" fill="#075fd3"/>';
    }).join("");

    grafico.innerHTML =
        '<svg viewBox="0 0 ' + largura + ' ' + altura + '" preserveAspectRatio="none" style="width:100%;height:100%">' +
        '<polyline points="' + pontos.join(" ") + '" fill="none" stroke="#075fd3" stroke-width="3"/>' +
        circulos +
        '</svg>';

    legenda.innerHTML = evolucao.map(function (e) {
        return "<span>" + formatarData(e.dia).slice(0, 5) + "</span>";
    }).join("");

    var totalGeral = evolucao.reduce(function (acc, e) { return acc + Number(e.total); }, 0);
    tendencia.innerText = totalGeral + " resposta(s) em " + evolucao.length + " dia(s) com envios";
}


// ===================== Resultados =====================

async function carregarResultados() {
    var container = document.getElementById("listaResultados");
    var titulo = document.getElementById("resultadosFormulario");
    var periodo = document.getElementById("filtroPeriodo").value;

    container.innerHTML = '<p class="aviso-vazio">Carregando...</p>';

    try {
        var r = await chamarApi("resultados", undefined, "&periodo=" + periodo + parametroFormulario());
        if (sessaoExpirada(r)) return;

        if (!r.ok || r.dados.erro) {
            titulo.innerText = "";
            container.innerHTML = '<div class="painel"><p class="aviso-vazio">' +
                escaparHtml(r.dados.erro || "Não foi possível carregar os resultados.") + '</p></div>';
            return;
        }

        formularioSelecionadoId = Number(r.dados.formulario.id);
        titulo.innerText = "Formulário: " + r.dados.formulario.titulo;
        renderizarResultados(r.dados.perguntas || [], r.dados.minimo_anonimato);
    } catch (e) {
        container.innerHTML = '<div class="painel"><p class="aviso-vazio">Erro de conexão ao carregar os resultados.</p></div>';
    }
}

function renderizarResultados(perguntas, minimo) {
    var container = document.getElementById("listaResultados");

    if (perguntas.length === 0) {
        container.innerHTML = '<div class="painel"><p class="aviso-vazio">Este formulário não tem perguntas.</p></div>';
        return;
    }

    container.innerHTML = perguntas.map(function (p, i) {
        var total = Number(p.total);
        var temMedia = p.media !== null;
        var media = temMedia ? Number(p.media) : 0;
        var classeMedia = !temMedia ? "" : media >= 7 ? "media-boa" : media >= 4 ? "media-neutra" : "media-ruim";

        var corpo;
        if (total === 0) {
            corpo = '<div class="resultado-oculto">Nenhuma resposta neste período.</div>';
        } else if (p.oculto) {
            corpo = '<div class="resultado-oculto">🔒 ' + total + ' resposta(s) neste período. Para proteger o anonimato, ' +
                'os números só aparecem a partir de ' + minimo + ' respostas.</div>';
        } else {
            corpo =
                '<div class="stats-resultado">' +
                '<div><span>Respostas</span><b>' + total + '</b></div>' +
                '<div><span>Desvio Padrão</span><b>' + Number(p.desvio_padrao).toFixed(1) + '</b></div>' +
                '<div><span>Mediana</span><b>' + Number(p.mediana).toFixed(1) + '</b></div>' +
                '</div>' +
                '<div class="barra-resultado-topo"><span>Satisfação média</span><span>' + Math.round(media * 10) + '%</span></div>' +
                '<div class="barra-resultado"><div class="barra-resultado-preenchida ' + classeMedia +
                '" style="width:' + (media * 10) + '%"></div></div>';
        }

        return '<div class="card-resultado">' +
            '<div class="resultado-topo">' +
            '<div class="numero-resultado">' + (i + 1) + '</div>' +
            '<div class="texto-resultado">' + escaparHtml(p.texto) + '</div>' +
            '<div class="badge-media ' + classeMedia + '">Média: ' + (temMedia ? media.toFixed(1) : "—") + '</div>' +
            '</div>' +
            corpo +
            '</div>';
    }).join("");
}


// ===================== Formulários =====================

async function carregarListaFormularios() {
    var container = document.getElementById("listaFormularios");
    try {
        var r = await chamarApi("formularios");
        if (sessaoExpirada(r)) return;
        if (!r.ok) {
            container.innerHTML = '<p class="aviso-vazio">' + escaparHtml(r.dados.erro || "Erro ao carregar formulários.") + '</p>';
            return;
        }
        renderizarListaFormularios(Array.isArray(r.dados) ? r.dados : []);
    } catch (e) {
        container.innerHTML = '<p class="aviso-vazio">Erro de conexão ao carregar formulários.</p>';
    }
}

function renderizarListaFormularios(lista) {
    var container = document.getElementById("listaFormularios");

    if (lista.length === 0) {
        container.innerHTML = '<p class="aviso-vazio">Nenhum formulário cadastrado ainda.</p>';
        return;
    }

    container.innerHTML = lista.map(function (f) {
        var id = Number(f.id);
        var total = Number(f.total_respostas);

        var acoes = "";
        if (f.status !== "ativo") {
            acoes += '<button class="botao-pequeno" onclick="mudarStatusFormulario(' + id + ', \'ativo\')">Ativar</button>';
        } else {
            acoes += '<button class="botao-pequeno botao-encerrar" onclick="mudarStatusFormulario(' + id + ', \'encerrado\')">Encerrar</button>';
        }
        acoes += '<button class="botao-pequeno" onclick="verDashboardFormulario(' + id + ')">Ver dashboard</button>';
        if (f.relatorio_gerado_em) {
            acoes += '<button class="botao-pequeno" onclick="verRelatorioFormulario(' + id + ')">Ver relatório</button>';
        }

        var detalhe = total + (total === 1 ? " resposta" : " respostas");
        if (f.respondentes_esperados) detalhe += " de " + f.respondentes_esperados + " esperadas";

        var periodo = "";
        if (f.data_abertura || f.data_fechamento) {
            periodo = "<br><small>Período: " +
                (f.data_abertura ? formatarDataHora(f.data_abertura) : "sem início definido") + " até " +
                (f.data_fechamento ? formatarDataHora(f.data_fechamento) : "7 dias após ativar") + "</small>";
        }

        var relatorio = "";
        if (f.relatorio_gerado_em) {
            relatorio = '<small class="linha-relatorio">📄 Relatório consolidado gerado automaticamente em ' +
                formatarDataHora(f.relatorio_gerado_em) + '</small>';
        }

        return '<div class="item-formulario">' +
            '<div>' +
            '<b>' + escaparHtml(f.titulo) + '</b>' +
            '<span class="status-' + f.status + '">' + rotuloStatus(f.status) + '</span>' +
            '<br><small>' + detalhe + '</small>' +
            periodo +
            relatorio +
            '</div>' +
            '<div class="acoes-formulario">' + acoes + '</div>' +
            '</div>';
    }).join("");
}

async function mudarStatusFormulario(id, novoStatus) {
    var mensagem = novoStatus === "ativo"
        ? "Ativar este formulário? Se outro estiver ativo, ele será encerrado. Sem data de término, a pesquisa fica aberta por 7 dias."
        : "Encerrar este formulário agora? Os funcionários não poderão mais responder e o relatório consolidado será gerado automaticamente.";
    if (!confirm(mensagem)) return;

    try {
        var r = await chamarApi("alterar_status_formulario", { formulario_id: id, status: novoStatus });
        if (sessaoExpirada(r)) return;
        if (!r.ok) alert(r.dados.erro || "Não foi possível alterar o status.");
    } catch (e) {
        alert("Erro de conexão.");
    }
    carregarListaFormularios();
}

function verDashboardFormulario(id) {
    formularioSelecionadoId = id;
    mostrarView("dashboard", document.querySelector(".menu-item"));
}

function verRelatorioFormulario(id) {
    window.open("relatorio_impressao.php?formulario_id=" + id, "_blank");
}

async function criarFormulario() {
    var titulo = document.getElementById("novoFormTitulo").value.trim();
    var esperados = document.getElementById("novoFormEsperados").value;
    var inicio = document.getElementById("novoFormInicio").value;
    var fim = document.getElementById("novoFormFim").value;
    var textoPerguntas = document.getElementById("novoFormPerguntas").value;
    var erro = document.getElementById("erroNovoForm");

    var perguntas = textoPerguntas.split("\n")
        .map(function (linha) { return linha.trim(); })
        .filter(function (linha) { return linha !== ""; });

    erro.innerText = "";

    if (!titulo || perguntas.length === 0) {
        erro.innerText = "Informe um título e ao menos uma pergunta.";
        return;
    }
    if (inicio && fim && new Date(fim) <= new Date(inicio)) {
        erro.innerText = "A data de término precisa ser depois da data de início.";
        return;
    }
    if (fim && new Date(fim) <= new Date()) {
        erro.innerText = "A data de término já passou.";
        return;
    }

    try {
        var r = await chamarApi("criar_formulario", {
            titulo: titulo,
            respondentes_esperados: esperados ? Number(esperados) : null,
            data_abertura: paraDatetimeMysql(inicio),
            data_fechamento: paraDatetimeMysql(fim),
            perguntas: perguntas
        });
        if (sessaoExpirada(r)) return;

        if (!r.ok) {
            erro.innerText = r.dados.erro || "Erro ao criar formulário.";
            return;
        }

        ["novoFormTitulo", "novoFormEsperados", "novoFormInicio", "novoFormFim", "novoFormPerguntas"].forEach(function (id) {
            document.getElementById(id).value = "";
        });
        carregarListaFormularios();
        alert("Formulário criado como rascunho. Clique em \"Ativar\" para liberar para os funcionários.");
    } catch (e) {
        erro.innerText = "Erro de conexão.";
    }
}


// ===================== Comentários =====================

async function carregarComentarios() {
    var container = document.getElementById("listaComentarios");
    var titulo = document.getElementById("comentariosFormulario");

    container.innerHTML = '<p class="aviso-vazio">Carregando...</p>';

    try {
        var r = await chamarApi("comentarios", undefined, parametroFormulario());
        if (sessaoExpirada(r)) return;

        if (!r.ok || r.dados.erro) {
            titulo.innerText = "";
            container.innerHTML = '<p class="aviso-vazio">' +
                escaparHtml(r.dados.erro || "Não foi possível carregar os comentários.") + '</p>';
            return;
        }

        var d = r.dados;
        formularioSelecionadoId = Number(d.formulario.id);
        titulo.innerText = "Formulário: " + d.formulario.titulo;

        if (d.oculto) {
            container.innerHTML = '<div class="aviso-anonimato">🔒 Este formulário tem ' + d.total_respostas +
                ' resposta(s). Para proteger o anonimato, os comentários só aparecem a partir de ' +
                d.minimo_anonimato + ' respostas.</div>';
            return;
        }

        if (d.comentarios.length === 0) {
            container.innerHTML = '<p class="aviso-vazio">Nenhum comentário enviado ainda.</p>';
            return;
        }

        container.innerHTML = d.comentarios.map(function (c) {
            return '<div class="comentario-item">' +
                '<small>' + formatarData(c.data_envio) + '</small>' +
                '<p>' + escaparHtml(c.comentario) + '</p>' +
                '</div>';
        }).join("");
    } catch (e) {
        container.innerHTML = '<p class="aviso-vazio">Erro de conexão ao carregar os comentários.</p>';
    }
}


// ===================== Funcionários (cadastro - RF01) =====================

async function carregarListaFuncionarios() {
    var container = document.getElementById("listaFuncionarios");
    var info = document.getElementById("infoPesquisaAtual");

    try {
        var r = await chamarApi("funcionarios");
        if (sessaoExpirada(r)) return;

        if (!r.ok) {
            container.innerHTML = '<p class="aviso-vazio">' + escaparHtml(r.dados.erro || "Erro ao carregar os cadastros.") + '</p>';
            return;
        }

        ultimaListaFuncionarios = r.dados.funcionarios || [];
        meuId = Number(r.dados.meu_id);

        info.innerText = r.dados.formulario_atual
            ? "Situação de resposta em relação à pesquisa ativa: \u201C" + r.dados.formulario_atual.titulo + "\u201D"
            : "Nenhuma pesquisa ativa no momento.";

        renderizarListaFuncionarios(ultimaListaFuncionarios, !!r.dados.formulario_atual);
    } catch (e) {
        container.innerHTML = '<p class="aviso-vazio">Erro de conexão ao carregar os cadastros.</p>';
    }
}

function renderizarListaFuncionarios(lista, temPesquisaAtiva) {
    var container = document.getElementById("listaFuncionarios");

    if (lista.length === 0) {
        container.innerHTML = '<p class="aviso-vazio">Nenhum cadastro ainda.</p>';
        return;
    }

    container.innerHTML = lista.map(function (f) {
        var id = Number(f.id);
        var ativo = Number(f.ativo) === 1;
        var gestor = f.tipo_perfil === "gestor";
        var souEu = id === meuId;

        var selos = '<span class="' + (gestor ? "perfil-gestor" : "perfil-funcionario") + '">' +
            (gestor ? "Gestor" : "Funcionário") + '</span>' +
            '<span class="' + (ativo ? "status-ativo" : "status-encerrado") + '">' +
            (ativo ? "Ativo" : "Inativo") + '</span>';

        // o gestor vê apenas SE a pessoa já respondeu, nunca O QUE respondeu
        if (!gestor && temPesquisaAtiva) {
            var respondeu = Number(f.respondeu_atual) === 1;
            selos += '<span class="' + (respondeu ? "status-ativo" : "status-rascunho") + '">' +
                (respondeu ? "✓ Já respondeu" : "Ainda não respondeu") + '</span>';
        }

        var detalhes = [escaparHtml(f.email)];
        if (f.cargo) detalhes.push(escaparHtml(f.cargo));
        if (f.data_admissao) detalhes.push("admissão em " + formatarData(f.data_admissao));

        var acoes = '<button class="botao-pequeno" onclick="iniciarEdicaoFuncionario(' + id + ')">Editar</button>';
        if (souEu) {
            acoes += '<span class="voce">(você)</span>';
        } else {
            acoes += '<button class="botao-pequeno" onclick="alternarStatusFuncionario(' + id + ', ' + (ativo ? 0 : 1) + ')">' +
                (ativo ? "Desativar" : "Reativar") + '</button>';
            acoes += '<button class="botao-pequeno botao-encerrar" onclick="excluirFuncionario(' + id + ')">Excluir</button>';
        }

        return '<div class="item-formulario">' +
            '<div><b>' + escaparHtml(f.nome) + '</b>' + selos +
            '<br><small>' + detalhes.join(" · ") + '</small></div>' +
            '<div class="acoes-formulario">' + acoes + '</div>' +
            '</div>';
    }).join("");
}

function iniciarEdicaoFuncionario(id) {
    var f = ultimaListaFuncionarios.find(function (x) { return Number(x.id) === Number(id); });
    if (!f) return;

    funcionarioEditandoId = Number(f.id);
    document.getElementById("novoFuncNome").value = f.nome;
    document.getElementById("novoFuncEmail").value = f.email;
    document.getElementById("novoFuncSenha").value = "";
    document.getElementById("novoFuncSenha").placeholder = "Deixe em branco para manter a senha atual";
    document.getElementById("novoFuncCargo").value = f.cargo || "";
    document.getElementById("novoFuncAdmissao").value = f.data_admissao || "";
    document.getElementById("novoFuncPerfil").value = f.tipo_perfil;

    document.getElementById("tituloFormFunc").innerText = "Editando: " + f.nome;
    document.getElementById("botaoSalvarFunc").innerText = "Salvar alterações";
    document.getElementById("botaoCancelarEdicaoFunc").classList.remove("escondido");
    document.getElementById("erroNovoFunc").innerText = "";
    document.getElementById("formFuncionario").scrollIntoView({ behavior: "smooth", block: "start" });
}

function cancelarEdicaoFuncionario() {
    funcionarioEditandoId = null;
    ["novoFuncNome", "novoFuncEmail", "novoFuncSenha", "novoFuncCargo", "novoFuncAdmissao"].forEach(function (id) {
        document.getElementById(id).value = "";
    });
    document.getElementById("novoFuncPerfil").value = "funcionario";
    document.getElementById("novoFuncSenha").placeholder = "Mínimo de 6 caracteres";
    document.getElementById("tituloFormFunc").innerText = "Novo cadastro";
    document.getElementById("botaoSalvarFunc").innerText = "Cadastrar";
    document.getElementById("botaoCancelarEdicaoFunc").classList.add("escondido");
    document.getElementById("erroNovoFunc").innerText = "";
}

async function salvarFuncionario() {
    var nome = document.getElementById("novoFuncNome").value.trim();
    var email = document.getElementById("novoFuncEmail").value.trim();
    var senha = document.getElementById("novoFuncSenha").value;
    var cargo = document.getElementById("novoFuncCargo").value.trim();
    var admissao = document.getElementById("novoFuncAdmissao").value;
    var perfil = document.getElementById("novoFuncPerfil").value;
    var erro = document.getElementById("erroNovoFunc");
    var editando = funcionarioEditandoId !== null;

    erro.innerText = "";

    if (!nome || !email) {
        erro.innerText = "Informe o nome e o email.";
        return;
    }
    if (!editando && senha.length < 6) {
        erro.innerText = "A senha precisa ter pelo menos 6 caracteres.";
        return;
    }
    if (editando && senha !== "" && senha.length < 6) {
        erro.innerText = "A nova senha precisa ter pelo menos 6 caracteres.";
        return;
    }

    var corpo = {
        nome: nome,
        email: email,
        senha: senha,
        cargo: cargo,
        data_admissao: admissao,
        tipo_perfil: perfil
    };
    if (editando) corpo.funcionario_id = funcionarioEditandoId;

    try {
        var r = await chamarApi(editando ? "editar_funcionario" : "criar_funcionario", corpo);
        if (sessaoExpirada(r)) return;

        if (!r.ok) {
            erro.innerText = r.dados.erro || "Não foi possível salvar.";
            return;
        }

        alert(editando ? "Cadastro atualizado!" : "Cadastro criado! A pessoa já pode entrar com esse email e senha.");
        cancelarEdicaoFuncionario();
        carregarListaFuncionarios();
    } catch (e) {
        erro.innerText = "Erro de conexão.";
    }
}

async function alternarStatusFuncionario(id, novoAtivo) {
    var mensagem = novoAtivo
        ? "Reativar este cadastro? A pessoa volta a conseguir entrar."
        : "Desativar este cadastro? A pessoa não conseguirá mais entrar (as respostas já enviadas continuam anônimas e guardadas).";
    if (!confirm(mensagem)) return;

    try {
        var r = await chamarApi("alterar_status_funcionario", { funcionario_id: id, ativo: novoAtivo });
        if (sessaoExpirada(r)) return;
        if (!r.ok) alert(r.dados.erro || "Não foi possível alterar o status.");
    } catch (e) {
        alert("Erro de conexão.");
    }
    carregarListaFuncionarios();
}

async function excluirFuncionario(id) {
    var f = ultimaListaFuncionarios.find(function (x) { return Number(x.id) === Number(id); });
    var nome = f ? f.nome : "este cadastro";
    if (!confirm("Excluir o cadastro de " + nome + "? Esta ação não pode ser desfeita.\nAs respostas já enviadas continuam guardadas, de forma anônima.")) return;

    try {
        var r = await chamarApi("excluir_funcionario", { funcionario_id: id });
        if (sessaoExpirada(r)) return;
        if (!r.ok) {
            alert(r.dados.erro || "Não foi possível excluir.");
        } else if (funcionarioEditandoId === Number(id)) {
            cancelarEdicaoFuncionario();
        }
    } catch (e) {
        alert("Erro de conexão.");
    }
    carregarListaFuncionarios();
}


// ===================== Relatórios e exportações =====================

function exigirFormularioSelecionado() {
    if (!formularioSelecionadoId) {
        alert("Nenhum formulário selecionado. Abra o Dashboard primeiro.");
        return false;
    }
    return true;
}

// baixa pelo fetch para conseguir mostrar a mensagem de erro sem sair do painel
async function baixarArquivo(acao, nomeArquivo) {
    try {
        var resposta = await fetch("api.php?action=" + acao + parametroFormulario());
        if (!resposta.ok) {
            var dados = {};
            try {
                dados = await resposta.json();
            } catch (e) {
                dados = {};
            }
            if (resposta.status === 401) {
                alert("Sua sessão expirou. Entre novamente.");
                mostrarLogin();
                return;
            }
            alert(dados.erro || "Não foi possível gerar o arquivo.");
            return;
        }

        var blob = await resposta.blob();
        var link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = nomeArquivo;
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(function () { URL.revokeObjectURL(link.href); }, 2000);
    } catch (e) {
        alert("Erro de conexão ao gerar o arquivo.");
    }
}

function exportarPdf() {
    if (!exigirFormularioSelecionado()) return;
    window.open("relatorio_impressao.php?formulario_id=" + formularioSelecionadoId, "_blank");
}

function exportarCsv() {
    if (!exigirFormularioSelecionado()) return;
    baixarArquivo("exportar_csv", "respostas_pesquisa_" + formularioSelecionadoId + ".csv");
}

function exportarComentariosArquivo() {
    if (!exigirFormularioSelecionado()) return;
    baixarArquivo("exportar_comentarios", "comentarios_pesquisa_" + formularioSelecionadoId + ".txt");
}

function exportarGraficoPng() {
    if (!ultimoDashboard) {
        alert("Abra o Dashboard antes de exportar os gráficos.");
        return;
    }
    if (ultimoDashboard.dados_ocultos || Number(ultimoDashboard.total_respostas) === 0) {
        alert("Ainda não há respostas suficientes para gerar os gráficos (mínimo de " +
            ultimoDashboard.minimo_anonimato + ", para proteger o anonimato).");
        return;
    }

    var canvas = document.createElement("canvas");
    canvas.width = 1000;
    canvas.height = 480;
    var ctx = canvas.getContext("2d");

    ctx.fillStyle = "#ffffff";
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    ctx.fillStyle = "#111827";
    ctx.font = "bold 22px Arial";
    ctx.fillText(ultimoDashboard.formulario.titulo, 40, 45);
    ctx.font = "15px Arial";
    ctx.fillStyle = "#64748b";
    ctx.fillText("Média geral: " + Number(ultimoDashboard.media_geral).toFixed(1) +
        "   |   Respostas: " + ultimoDashboard.total_respostas, 40, 72);

    // pizza de distribuição
    var dist = ultimoDashboard.distribuicao;
    var fatias = [
        { valor: Number(dist.satisfeito), cor: "#15b573", rotulo: "Satisfeito (7-10)" },
        { valor: Number(dist.neutro), cor: "#f4b400", rotulo: "Neutro (4-6)" },
        { valor: Number(dist.insatisfeito), cor: "#ef4444", rotulo: "Insatisfeito (0-3)" }
    ];
    var totalDist = fatias.reduce(function (acc, f) { return acc + f.valor; }, 0);

    ctx.fillStyle = "#111827";
    ctx.font = "bold 18px Arial";
    ctx.fillText("Distribuição de Satisfação", 600, 120);

    var cx = 700, cy = 280, raio = 110, angulo = -Math.PI / 2;
    fatias.forEach(function (f, i) {
        if (totalDist > 0 && f.valor > 0) {
            var fim = angulo + (f.valor / totalDist) * Math.PI * 2;
            ctx.beginPath();
            ctx.moveTo(cx, cy);
            ctx.arc(cx, cy, raio, angulo, fim);
            ctx.closePath();
            ctx.fillStyle = f.cor;
            ctx.fill();
            angulo = fim;
        }
        ctx.fillStyle = f.cor;
        ctx.fillRect(840, 220 + i * 30, 14, 14);
        ctx.fillStyle = "#111827";
        ctx.font = "14px Arial";
        ctx.fillText(f.rotulo + ": " + f.valor, 862, 232 + i * 30);
    });

    // gráfico de evolução (desenhado a partir do SVG da tela)
    ctx.fillStyle = "#111827";
    ctx.font = "bold 18px Arial";
    ctx.fillText("Evolução Temporal", 40, 120);

    var svg = document.querySelector("#graficoEvolucao svg");
    var finalizar = function () {
        canvas.toBlob(function (blob) {
            var link = document.createElement("a");
            link.href = URL.createObjectURL(blob);
            link.download = "graficos_pesquisa_" + formularioSelecionadoId + ".png";
            document.body.appendChild(link);
            link.click();
            link.remove();
        });
    };

    if (!svg) {
        finalizar();
        return;
    }

    var imagem = new Image();
    imagem.onload = function () {
        ctx.drawImage(imagem, 40, 140, 500, 280);
        finalizar();
    };
    imagem.onerror = finalizar;
    imagem.src = "data:image/svg+xml;charset=utf-8," + encodeURIComponent(new XMLSerializer().serializeToString(svg));
}

// RF12: log de acesso dos gestores
async function carregarLogsAcesso() {
    var container = document.getElementById("listaLogs");
    try {
        var r = await chamarApi("logs_acesso");
        if (sessaoExpirada(r)) return;

        var logs = Array.isArray(r.dados) ? r.dados : [];
        if (!r.ok || logs.length === 0) {
            container.innerHTML = '<p class="aviso-vazio">' + (r.ok ? "Nenhum acesso registrado ainda." : escaparHtml(r.dados.erro || "Erro ao carregar os acessos.")) + '</p>';
            return;
        }

        var rotulos = {
            login: ["acao-login", "Entrou no painel"],
            logout: ["acao-logout", "Saiu do painel"],
            falha_login: ["acao-falha", "Tentativa de login recusada"]
        };

        container.innerHTML = logs.map(function (log) {
            var rotulo = rotulos[log.acao] || ["acao-logout", log.acao];
            return '<div class="linha-log">' +
                '<span><span class="' + rotulo[0] + '">' + rotulo[1] + '</span> · ' + escaparHtml(log.email) + '</span>' +
                '<small>' + formatarDataHora(log.data_hora) + '</small>' +
                '</div>';
        }).join("");
    } catch (e) {
        container.innerHTML = '<p class="aviso-vazio">Erro de conexão ao carregar os acessos.</p>';
    }
}


// ===================== Utilidades =====================

// "2026-09-16 19:45:00" ou "2026-09-16" -> Date no horário local
// (datas sem hora são lidas como meia-noite local, senão o navegador
// mostraria o dia anterior)
function paraData(valor) {
    if (!valor) return null;
    var texto = String(valor).replace(" ", "T");
    if (texto.length === 10) texto += "T00:00:00";
    var data = new Date(texto);
    return isNaN(data.getTime()) ? null : data;
}

function formatarData(valor) {
    var data = paraData(valor);
    return data ? data.toLocaleDateString("pt-BR") : (valor || "");
}

function formatarDataHora(valor) {
    var data = paraData(valor);
    if (!data) return valor || "";
    return data.toLocaleDateString("pt-BR") + " às " +
        data.toLocaleTimeString("pt-BR", { hour: "2-digit", minute: "2-digit" });
}

// "2026-09-16T19:45" (campo datetime-local) -> "2026-09-16 19:45:00"
function paraDatetimeMysql(valor) {
    if (!valor) return null;
    return valor.replace("T", " ") + (valor.length === 16 ? ":00" : "");
}

function escaparHtml(texto) {
    var div = document.createElement("div");
    div.innerText = texto == null ? "" : String(texto);
    return div.innerHTML;
}